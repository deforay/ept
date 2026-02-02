<?php
require_once __DIR__ . '/../cli-bootstrap.php';

use PhpOffice\PhpWord\TemplateProcessor;

$cliOptions = getopt("s:c:t:b:");
$shipmentsToGenerate = $cliOptions['s'] ?? null;
$certificateName = !empty($cliOptions['c']) ? $cliOptions['c'] : date('Y');
$templateMode = strtolower($cliOptions['t'] ?? 'pdf');   // 'pdf' | 'docx'
$batchId = !empty($cliOptions['b']) ? (int) $cliOptions['b'] : null;

// Always initialize batch model - auto-create batch if not provided
$certificateBatchesModel = new Application_Model_DbTable_CertificateBatches();

if ($batchId) {
	// Existing batch - just update status
	$certificateBatchesModel->updateStatus($batchId, 'generating');
} else {
	// No batch provided - auto-create one for tracking
	// This ensures CLI runs are also tracked in the dashboard
	if (is_array($shipmentsToGenerate)) {
		$shipmentIdsForBatch = implode(",", $shipmentsToGenerate);
	} else {
		$shipmentIdsForBatch = $shipmentsToGenerate ?? '';
	}

	$batchId = $certificateBatchesModel->createBatch([
		'batch_name' => $certificateName,
		'shipment_ids' => $shipmentIdsForBatch,
		'created_by' => 0, // CLI execution (no admin user)
		'status' => 'generating'
	]);

	echo "Auto-created batch #{$batchId} for tracking\n";
}

if (is_array($shipmentsToGenerate))
	$shipmentsToGenerate = implode(",", $shipmentsToGenerate);
if (empty($shipmentsToGenerate)) {
	error_log("Please specify the shipment ids with -s");
	$certificateBatchesModel->updateStatus($batchId, 'failed', [
		'error_message' => 'No shipment IDs specified'
	]);
	exit(1);
}

/* ---------- Common helpers ---------- */

function sendNotification($emailConfig, $shipmentsList, ?string $downloadUrl = null)
{
	if (!empty($emailConfig) && !empty($shipmentsList) && $emailConfig->status == "yes" && !empty($emailConfig->mails)) {
		$common = new Application_Service_Common();
		$emailSubject = "ePT | Certificates Generated";
		$emailContent = "Certificates for Shipment " . implode(", ", $shipmentsList) . " have been generated.";

		if ($downloadUrl) {
			$emailContent .= "<br><br>Download link: <a href=\"$downloadUrl\">$downloadUrl</a>";
		}

		$emailContent .= "<br><br><br><small>This is a system generated email</small>";

		$common->insertTempMail($emailConfig->mails, null, null, $emailSubject, $emailContent);
	}
}



function findPdftk(): ?string
{
	foreach (['/usr/bin/pdftk', '/usr/bin/pdftk-java', '/usr/local/bin/pdftk'] as $p) {
		if (is_executable($p))
			return $p;
	}
	$which = trim(shell_exec('command -v pdftk 2>/dev/null') ?? '');
	return $which !== '' ? $which : null;
}

/**
 * Find a usable LibreOffice/soffice binary on Linux or macOS.
 * - Respects SOFFICE_BIN env override.
 * - Checks common Linux, macOS bundle, Homebrew, MacPorts, and PATH locations.
 * - Verifies the binary by executing `--version` headlessly.
 */
function findLibreOffice(): ?string
{
	// 1) Explicit override
	$override = getenv('SOFFICE_BIN');
	if ($override && is_executable($override) && _verifySoffice($override)) {
		return $override;
	}

	// 2) OS-specific candidates
	$candidates = [];

	if (PHP_OS_FAMILY === 'Darwin') { // macOS
		// App bundle
		$candidates[] = '/Applications/LibreOffice.app/Contents/MacOS/soffice';
		// Homebrew (symlink if user created one)
		$candidates[] = '/usr/local/bin/soffice';
		$candidates[] = '/opt/homebrew/bin/soffice';
		// MacPorts
		$candidates[] = '/opt/local/bin/soffice';
	} else { // Linux/other
		// Standard packages
		$candidates[] = '/usr/bin/soffice';
		$candidates[] = '/usr/local/bin/soffice';
		// Some distros expose `libreoffice` wrapper instead of `soffice`
		$candidates[] = '/usr/bin/libreoffice';
		$candidates[] = '/usr/local/bin/libreoffice';
		// Snap wrapper (works with same CLI flags)
		$candidates[] = '/snap/bin/libreoffice';
	}

	// 3) PATH lookups (both names)
	foreach (['soffice', 'libreoffice'] as $name) {
		$path = trim(shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null') ?? '');
		if ($path !== '')
			$candidates[] = $path;
	}

	// 4) Return first verified candidate
	foreach ($candidates as $bin) {
		if ($bin && is_executable($bin) && _verifySoffice($bin)) {
			return $bin;
		}
	}

	return null;
}

/**
 * Minimal sanity check that the binary runs and is LibreOffice.
 */
function _verifySoffice(string $bin): bool
{
	$cmd = escapeshellcmd($bin) . ' --headless --version 2>&1';
	exec($cmd, $out, $code);
	if ($code !== 0)
		return false;
	$joined = strtolower(implode("\n", $out));
	// Typical outputs include "LibreOffice" or "soffice ..."
	return (stripos($joined, 'libreoffice') !== false || stripos($joined, 'soffice') !== false);
}


/* ---------- PDF (AcroForm) path  ---------- */

function createFDF(array $data): string
{
	// UTF-16BE + BOM for reliable Unicode
	$fdf = "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n";
	foreach ($data as $key => $value) {
		$v = str_replace(["\r\n", "\r", "\n"], "\r", (string) ($value ?? ''));
		$k16 = "\xFE\xFF" . mb_convert_encoding((string) $key, 'UTF-16BE', 'UTF-8');
		$v16 = "\xFE\xFF" . mb_convert_encoding($v, 'UTF-16BE', 'UTF-8');
		$k16 = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $k16);
		$v16 = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $v16);
		$fdf .= "<< /T ({$k16}) /V ({$v16}) >>\n";
	}
	$fdf .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
	$tmp = tempnam(TEMP_UPLOAD_PATH, 'fdf');
	file_put_contents($tmp, $fdf);
	return $tmp;
}

function fillPdfTemplate(string $templateFile, array $fields, string $outputPdf): bool
{
	if (!file_exists($templateFile)) {
		error_log("Template missing: $templateFile");
		return false;
	}
	$pdftk = findPdftk();
	if (!$pdftk) {
		error_log("pdftk not found");
		return false;
	}

	@mkdir(dirname($outputPdf), 0777, true);
	$fdf = createFDF($fields);
	$cmd = escapeshellcmd($pdftk) . ' ' . escapeshellarg($templateFile) . ' fill_form ' .
		escapeshellarg($fdf) . ' output ' . escapeshellarg($outputPdf) . ' flatten';
	exec($cmd . ' 2>&1', $out, $code);
	unlink($fdf);

	if ($code !== 0 || !is_file($outputPdf) || filesize($outputPdf) === 0) {
		error_log("pdftk failed (code=$code): " . implode("\n", $out));
		return false;
	}
	return true;
}

/* ---------- DOCX path (search & replace) ---------- */

function renderDocx(string $docxTemplate, array $fields, string $outDocx): bool
{
	if (!file_exists($docxTemplate)) {
		error_log("Template missing: $docxTemplate");
		return false;
	}

	$t = new TemplateProcessor($docxTemplate);

	// Helper: sanitize for Word XML + convert newlines to <w:br/>
	$wordSanitize = function (string $s): string {
		return str_replace(["\r\n", "\r"], "\n", $s);
	};

	foreach ($fields as $k => $v) {
		$val = $wordSanitize((string) ($v ?? ''));
		// Support ${participant_name} written in various cases in the DOCX:
		$names = [$k, strtolower($k), strtoupper($k)];
		foreach ($names as $name) {
			// Important: pass the name WITHOUT ${}
			$t->setValue($name, $val);
		}
	}

	@mkdir(dirname($outDocx), 0777, true);
	$t->saveAs($outDocx);
	return is_file($outDocx) && filesize($outDocx) > 0;
}


/**
 * Validate that a DOCX template contains expected placeholder fields.
 * Returns array of missing field names.
 */
function validateDocxTemplate(string $docxPath, array $expectedFields): array
{
	if (!file_exists($docxPath)) {
		return $expectedFields; // All missing if file doesn't exist
	}

	$missing = [];
	$content = @file_get_contents('zip://' . $docxPath . '#word/document.xml');
	if ($content === false) {
		return $expectedFields;
	}

	foreach ($expectedFields as $field) {
		// PhpWord uses ${field} syntax, but Word may split tags
		// Check for the field name in various forms
		if (stripos($content, $field) === false) {
			$missing[] = $field;
		}
	}

	return $missing;
}

// Returns path to PDF or null. Includes retry logic for flaky LibreOffice.
function docxToPdf(string $inDocx, string $outPdf, int $maxRetries = 2): ?string
{
	$soffice = findLibreOffice();
	if (!$soffice)
		return null;

	$dir = dirname($outPdf);
	@mkdir($dir, 0777, true);

	$out = [];
	$code = 1;

	for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
		$tempProfile = '/tmp/lo_profile_' . bin2hex(random_bytes(8));
		@mkdir($tempProfile, 0700, true);

		// Use writer_pdf_Export filter for better DOCX handling
		$cmd = escapeshellcmd($soffice) .
			' --headless' .
			' --invisible' .
			' --nodefault' .
			' --nolockcheck' .
			' --nologo' .
			' --norestore' .
			' --convert-to pdf:writer_pdf_Export' .
			' -env:UserInstallation=file://' . escapeshellarg($tempProfile) .
			' --outdir ' . escapeshellarg($dir) .
			' ' . escapeshellarg($inDocx) .
			' 2>&1';

		$out = [];
		exec($cmd, $out, $code);
		shell_exec('rm -rf ' . escapeshellarg($tempProfile) . ' 2>/dev/null');

		$expectedPdf = $dir . '/' . basename(preg_replace('/\.docx$/i', '.pdf', $inDocx));

		if ($code === 0 && is_file($expectedPdf) && filesize($expectedPdf) > 1000) {
			if ($expectedPdf !== $outPdf) {
				rename($expectedPdf, $outPdf);
			}
			return $outPdf;
		}

		if ($attempt < $maxRetries) {
			error_log("LibreOffice attempt $attempt failed, retrying...");
			sleep(1); // Brief pause before retry
		}
	}

	error_log("LibreOffice conversion failed after $maxRetries attempts (code=$code): " . implode("\n", $out));
	return null;
}

/* ---------- Setup ---------- */
$generalModel = new Pt_Commons_General();
$certificatePaths = [];
$folderPath = TEMP_UPLOAD_PATH . "/certificates/$certificateName";
Pt_Commons_General::rmdirRecursive($folderPath);
$certificatePaths[] = $excellenceCertPath = "$folderPath/excellence";
$certificatePaths[] = $participationCertPath = "$folderPath/participation";
foreach ($certificatePaths as $p)
	if (!is_dir($p))
		@mkdir($p, 0777, true);

/* ---------- DB + business logic (unchanged) ---------- */
$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$participantsDb = new Application_Model_DbTable_Participants();
$dataManagerDb = new Application_Model_DbTable_DataManagers();
$schemeService = new Application_Service_Schemes();
$vlModel = new Application_Model_Vl();
$vlAssayArray = $vlModel->getVlAssay();
$eidAssayArray = $schemeService->getEidExtractionAssay();

$certificates = Application_Service_Common::getConfig('certificates');

/* ---------- Template resolver that supports both modes ---------- */

function generateCertificate(string $shipmentType, string $certificateType, array $fields, string $outputFileBase, string $mode): void
{
	// Map both PDF and DOCX templates;
	$templates = [
		'excellence' => [
			'dts' => [
				'pdf' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/dts-e.pdf",
				'docx' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/dts-e.docx",
			],
			'eid' => [
				'pdf' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/eid-e.pdf",
				'docx' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/eid-e.docx",
			],
			'vl' => [
				'pdf' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/vl-e.pdf",
				'docx' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/vl-e.docx",
			],
		],
		'participation' => [
			'dts' => [
				'pdf' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/dts-p.pdf",
				'docx' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/dts-p.docx",
			],
			'eid' => [
				'pdf' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/eid-p.pdf",
				'docx' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/eid-p.docx",
			],
			'vl' => [
				'pdf' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/vl-p.pdf",
				'docx' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/vl-p.docx",
			],
		],
	];

	$t = $templates[$certificateType][$shipmentType] ?? null;
	if (!$t) {
		echo "No template found for $certificateType - $shipmentType. Skipping.\n";
		return;
		//throw new Exception("$certificateType template map missing for $shipmentType");
	}

	if ($mode === 'pdf') {
		$tpl = $t['pdf'] ?? '';
		if (!is_file($tpl))
			throw new Exception("PDF template not found: $tpl");
		$ok = fillPdfTemplate($tpl, $fields, $outputFileBase . ".pdf");
		if (!$ok)
			throw new Exception("PDF fill failed for $tpl");
		return;
	}

	if ($mode === 'docx') {
		$tpl = $t['docx'] ?? '';
		if (!is_file($tpl)) {
			echo "DOCX template not found: $tpl. Skipping.\n";
			return;
			//throw new Exception("DOCX template not found: $tpl");
		}

		$outDocx = "$outputFileBase.docx";
		if (!renderDocx($tpl, $fields, $outDocx))
			throw new Exception("DOCX render failed");

		// No PDF conversion - let users handle this locally
		//error_log("DOCX certificate generated: $outDocx");
		return;
	}

	throw new Exception("Unknown template mode: $mode");
}
function createZipAndGetDownloadUrl(string $folderPath, string $certificateName, string $urlToTempFolder, string $mode = 'pdf'): ?string
{
	$zipFileName = "certificates-{$certificateName}-" . date('Y-m-d-H-i-s') . ".zip";
	$zipPath = $folderPath . DIRECTORY_SEPARATOR . $zipFileName;


	$zip = new ZipArchive();
	if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
		error_log("Cannot create ZIP file: $zipPath");
		return null;
	}

	// Determine file extension based on mode
	$fileExtension = ($mode === 'docx') ? '*.docx' : '*.pdf';

	// Add all certificate files from both excellence and participation folders
	$folders = ['excellence', 'participation'];
	$fileCount = 0;

	foreach ($folders as $folder) {
		$fullFolderPath = $folderPath . DIRECTORY_SEPARATOR . $folder;
		if (is_dir($fullFolderPath)) {
			$files = glob($fullFolderPath . DIRECTORY_SEPARATOR . $fileExtension);
			foreach ($files as $file) {
				$relativeName = $folder . '/' . basename($file);
				$zip->addFile($file, $relativeName);
				$fileCount++;
			}
		}
	}

	// For PDF mode, just add a simple README and close
	if ($mode === 'pdf') {
		$readme = "CERTIFICATE FILES - PDF FORMAT
======================================

Generated on: " . date('Y-m-d H:i:s') . "
Certificate Name: {$certificateName}
Total Files: {$fileCount}

CONTENTS:
- excellence/      Excellence certificates (participants who passed all panels)
- participation/   Participation certificates (participants who submitted but didn't pass all)

These PDF certificates are ready to use - no conversion needed.
";
		$zip->addFromString('README.txt', $readme);
		$zip->close();

		if (file_exists($zipPath)) {
			return $urlToTempFolder . '/' . $zipFileName;
		}
		return null;
	}

	// Add PowerShell script (improved with progress, error handling, COM cleanup)
	// Using nowdoc to avoid PHP linter confusion with PowerShell syntax
	$powershellScript = <<<'POWERSHELL'
@echo off
setlocal
cd /d "%~dp0"
echo Converting DOCX certificates to PDF...
echo.

powershell -ExecutionPolicy Bypass -Command "& {
    $ErrorActionPreference = 'SilentlyContinue'
    $word = $null
    try {
        $word = New-Object -ComObject Word.Application
        $word.Visible = $false
        $word.DisplayAlerts = 0

        $docxFiles = Get-ChildItem -Path . -Filter *.docx -Recurse
        $total = $docxFiles.Count

        if ($total -eq 0) {
            Write-Host 'No DOCX files found.' -ForegroundColor Yellow
            Write-Host 'Make sure this script is in the folder with excellence/ and participation/ subfolders.'
        } else {
            $converted = 0
            $failed = 0

            foreach ($file in $docxFiles) {
                $current = $converted + $failed + 1
                Write-Host `"[$current/$total] Converting: $($file.Name)`"
                try {
                    $doc = $word.Documents.Open($file.FullName, $false, $true)
                    $pdfPath = $file.FullName -replace '\.docx$', '.pdf'
                    $doc.SaveAs2([ref]$pdfPath, [ref]17)
                    $doc.Close([ref]$false)
                    $converted++
                } catch {
                    Write-Host `"  FAILED: $_`" -ForegroundColor Red
                    $failed++
                }
            }

            Write-Host ''
            if ($failed -eq 0) {
                Write-Host `"Done! Converted $converted files successfully.`" -ForegroundColor Green
            } else {
                Write-Host `"Complete: $converted succeeded, $failed failed`" -ForegroundColor Yellow
            }
        }
    } catch {
        Write-Host 'Error: Microsoft Word not found or failed to start.' -ForegroundColor Red
        Write-Host 'Make sure Microsoft Word is installed.'
    } finally {
        if ($word) {
            $word.Quit()
            [System.Runtime.Interopservices.Marshal]::ReleaseComObject($word) | Out-Null
        }
    }
    Write-Host ''
    Write-Host 'Press any key to close...'
    $null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
}"
POWERSHELL;

	$zip->addFromString('CONVERT-TO-PDF.bat', $powershellScript);

	// Add bash script for Linux/macOS users with LibreOffice
	$bashScript = '#!/bin/bash
# Convert DOCX certificates to PDF using LibreOffice
# Works on Linux and macOS with LibreOffice installed

echo "Converting DOCX certificates to PDF..."
echo ""

cd "$(dirname "$0")"

# Find LibreOffice binary
if command -v libreoffice &> /dev/null; then
    SOFFICE="libreoffice"
elif command -v soffice &> /dev/null; then
    SOFFICE="soffice"
elif [ -x "/Applications/LibreOffice.app/Contents/MacOS/soffice" ]; then
    SOFFICE="/Applications/LibreOffice.app/Contents/MacOS/soffice"
else
    echo "ERROR: LibreOffice not found."
    echo "Please install LibreOffice or use the Word-based scripts instead."
    exit 1
fi

converted=0
failed=0

find . -name "*.docx" | while read -r file; do
    echo "Converting: $(basename "$file")"
    dir=$(dirname "$file")
    if $SOFFICE --headless --convert-to pdf --outdir "$dir" "$file" > /dev/null 2>&1; then
        ((converted++))
    else
        echo "  FAILED: $file"
        ((failed++))
    fi
done

echo ""
echo "Conversion complete!"
echo "Check the excellence/ and participation/ folders for PDF files."
';

	$zip->addFromString('convert-to-pdf.sh', $bashScript);

	// Add AppleScript for macOS
	$applescript = 'tell application "Finder"
    -- Ask user to select the folder containing excellence and participation folders
    set selectedFolder to choose folder with prompt "Select the folder containing the extracted certificate files:"
    
    set docFiles to {}
    
    -- Get all DOCX files in excellence folder
    try
        set excellenceFolder to folder "excellence" of selectedFolder
        set excellenceFiles to files of excellenceFolder whose name ends with ".docx"
        repeat with docFile in excellenceFiles
            set end of docFiles to {docFile as alias, container of docFile}
        end repeat
    on error
        -- Excellence folder not found or no DOCX files
    end try
    
    -- Get all DOCX files in participation folder  
    try
        set participationFolder to folder "participation" of selectedFolder
        set participationFiles to files of participationFolder whose name ends with ".docx"
        repeat with docFile in participationFiles
            set end of docFiles to {docFile as alias, container of docFile}
        end repeat
    on error
        -- Participation folder not found or no DOCX files
    end try
    
    if (count of docFiles) = 0 then
        display dialog "No DOCX files found in excellence/ or participation/ folders." & return & return & "Make sure you selected the correct folder containing the extracted files."
        return
    end if
    
end tell

tell application "Microsoft Word"
    set convertedCount to 0
    
    repeat with fileInfo in docFiles
        try
            set docFile to item 1 of fileInfo
            set docFolder to item 2 of fileInfo
            
            -- Open document
            open docFile
            
            -- Get the document name and create PDF path
            set docName to name of active document
            set pdfName to (text 1 thru -6 of docName) & ".pdf"
            set pdfPath to (docFolder as string) & pdfName
            
            -- Save as PDF
            save as active document file name pdfPath file format format PDF
            
            -- Close document
            close active document saving no
            
            set convertedCount to convertedCount + 1
            
        on error errMsg
            try
                close active document saving no
            end try
            display dialog "Failed to convert: " & (item 1 of fileInfo as string) & return & "Error: " & errMsg buttons {"Continue"} default button "Continue"
        end try
    end repeat
    
    display dialog "Conversion complete!" & return & "Converted " & convertedCount & " files to PDF." & return & "Check the excellence/ and participation/ folders."
    
end tell';

	$zip->addFromString('Convert-Certificates.scpt', $applescript);

	// Add README with updated instructions
	$readme = "CERTIFICATE FILES - DOCX FORMAT
======================================

Generated on: " . date('Y-m-d H:i:s') . "
Certificate Name: {$certificateName}
Total Files: {$fileCount}

BULK PDF CONVERSION - READY-TO-RUN SCRIPTS
==========================================

WINDOWS USERS:
1. Double-click \"CONVERT-TO-PDF.bat\"
2. Wait for conversion to complete
3. PDF files will appear next to DOCX files
(Requires Microsoft Word)

macOS USERS (with Microsoft Word):
1. Double-click \"Convert-Certificates.scpt\"
2. Select the extracted folder when prompted
3. Allow script to run (may need to enable in Security settings)

macOS/LINUX USERS (with LibreOffice):
1. Open Terminal
2. Run: chmod +x convert-to-pdf.sh && ./convert-to-pdf.sh
3. PDF files will appear next to DOCX files

MANUAL CONVERSION:
- Windows: Open in Word → File → Export → Create PDF/XPS
- macOS: Open in Word → File → Save As → PDF

WHAT'S INCLUDED:
- CONVERT-TO-PDF.bat    (Windows + Microsoft Word)
- Convert-Certificates.scpt (macOS + Microsoft Word)
- convert-to-pdf.sh     (Linux/macOS + LibreOffice)
- excellence/           (Excellence certificates)
- participation/        (Participation certificates)

TROUBLESHOOTING:
- Windows/macOS scripts require Microsoft Word
- Linux script requires LibreOffice (apt install libreoffice)
- If formatting looks wrong with LibreOffice, use Microsoft Word instead
";

	$zip->addFromString('README.txt', $readme);
	$zip->close();

	if (file_exists($zipPath)) {
		return $urlToTempFolder . '/' . $zipFileName;
	}

	return null;
}


try {
	$db = Zend_Db::factory($conf->resources->db);
	$domain = rtrim($conf->domain, "/");
	$urlToTempFolder = "$domain/temporary/certificates/$certificateName";
	Zend_Db_Table::setDefaultAdapter($db);

	$output = [];

	$query = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',])
		->where("shipment_id IN ($shipmentsToGenerate)")
		->order("s.scheme_type");
	$shipmentResult = $db->fetchAll($query);

	$shipmentIdArray = [];
	$shipmentCodeArray = [];
	foreach ($shipmentResult as $val) {
		$shipmentIdArray[] = (int) $val['shipment_id'];
		$shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
	}

	$allShipmentsProcessed = array_unique(
		array_merge(...array_values($shipmentCodeArray))
	);

	$impShipmentId = implode(",", $shipmentIdArray);

	$sQuery = $db->select()->from(['spm' => 'shipment_participant_map'], ['spm.map_id', 'spm.attributes', 'spm.shipment_test_report_date', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result'])
		->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['shipment_code', 'scheme_type', 'lastdate_response', 'shipment_date'])
		->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['unique_identifier', 'first_name', 'last_name', 'email', 'city', 'state', 'address', 'country', 'institute_name'])
		// ->where("spm.final_result = 1 OR spm.final_result = 2")
		// ->where("spm.is_excluded NOT LIKE 'yes'")
		->order("unique_identifier ASC")
		->order("scheme_type ASC");

	$sQuery->where("spm.shipment_id IN ($impShipmentId)");


	$shipmentParticipantResult = $db->fetchAll($sQuery);
	$participants = [];

	foreach ($shipmentParticipantResult as $shipment) {

		$participantName = Pt_Commons_MiscUtility::toUtf8([
			'first_name' => $shipment['first_name'] ?? '',
			'last_name' => $shipment['last_name'] ?? '',
		]);

		$fullNameParts = array_filter($participantName); // remove empty strings
		$participants[$shipment['unique_identifier']]['labName'] = implode(' ', $fullNameParts);
		$participants[$shipment['unique_identifier']]['city'] = $shipment['city'];
		$participants[$shipment['unique_identifier']]['country'] = $shipment['country'];
		$participants[$shipment['unique_identifier']]['shipment_year'] = date('Y', strtotime($shipment['shipment_date']));
		//$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float) ($shipment['shipment_score'] + $shipment['documentation_score']);
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'];
		$participants[$shipment['unique_identifier']]['attribs'] = json_decode($shipment['attributes'] ?? '', true);
		//$participants[$shipment['unique_identifier']][$shipment['shipment_code']]=$shipment['shipment_score'];

	}

	// Stats tracking
	$stats = ['excellence' => 0, 'participation' => 0, 'skipped' => 0];
	$totalParticipants = count($participants);
	$currentParticipant = 0;

	foreach ($participants as $participantUID => $arrayVal) {
		$currentParticipant++;
		echo "\r[{$currentParticipant}/{$totalParticipants}] Processing for Participant Code : {$participantUID}                    ";

		foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
			if (!isset($arrayVal[$shipmentType]))
				continue;

			$certificate = true;
			$participated = true;
			$assayName = '';
			$attribs = $arrayVal['attribs'] ?? [];

			foreach ($shipmentsList as $shipmentCode) {
				// Determine assayName from participant/shipment attributes
				if ($shipmentType === 'vl' && !empty($attribs['vl_assay'])) {
					$assayName = $vlAssayArray[$attribs['vl_assay']] ?? '';
				} elseif ($shipmentType === 'eid' && !empty($attribs['extraction_assay'])) {
					$assayName = $eidAssayArray[$attribs['extraction_assay']] ?? '';
				}

				// result/participation checks
				if (
					!empty($arrayVal[$shipmentType][$shipmentCode]['result']) &&
					$arrayVal[$shipmentType][$shipmentCode]['result'] != 3
				) {
					if ($arrayVal[$shipmentType][$shipmentCode]['result'] != 1) {
						$certificate = false;
					}
				} else {
					$certificate = false;
				}

				if (empty($arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
					$participated = false;
				}
			}


			$fields = [
				'participant_name' => $arrayVal['labName'],
				'participantname' => $arrayVal['labName'],
				'labname' => $arrayVal['labName'],
				'participant' => $arrayVal['labName'],
				'city' => $arrayVal['city'],
				'country' => $arrayVal['country'],
				'assay' => $assayName,
				'assayname' => $assayName,
				'shipment_year' => $arrayVal['shipment_year'],
				'shipmentyear' => $arrayVal['shipment_year'],
			];

			$attribs = $arrayVal['attribs'] ?? [];
			if ($shipmentType === 'vl') {
				if (($attribs['vl_assay'] ?? null) == 6) {
					$assay = $attribs['other_assay'] ?? 'Other';
				} else {
					$assay = (isset($attribs['vl_assay']) && isset($vlAssayArray[$attribs['vl_assay']]))
						? $vlAssayArray[$attribs['vl_assay']]
						: ' Other ';
				}
				$fields['assay'] = $assay ?? '';
			}

			if ($certificate && $participated) {
				$base = $excellenceCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName;
				generateCertificate($shipmentType, 'excellence', $fields, $base, $templateMode);
				$stats['excellence']++;
			} elseif ($participated) {
				$base = $participationCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName;
				generateCertificate($shipmentType, 'participation', $fields, $base, $templateMode);
				$stats['participation']++;
			} else {
				$stats['skipped']++;
			}
		}
	}

	// Print generation summary
	echo "\n\n=== Generation Summary ===\n";
	echo "Excellence certificates:    {$stats['excellence']}\n";
	echo "Participation certificates: {$stats['participation']}\n";
	echo "Skipped (no submission):    {$stats['skipped']}\n";
	echo "Total processed:            " . ($stats['excellence'] + $stats['participation'] + $stats['skipped']) . "\n";

	// Create ZIP for download (both PDF and DOCX modes)
	echo "\n=== Creating ZIP file for download... ===\n";
	$downloadUrl = createZipAndGetDownloadUrl($folderPath, $certificateName, $urlToTempFolder, $templateMode);

	if ($downloadUrl) {
		echo "\n✅ SUCCESS: Certificates packaged successfully!\n";
		echo "\n📁 DOWNLOAD LINK:\n";
		echo "$downloadUrl\n\n";
		if ($templateMode === 'docx') {
			echo "📋 INSTRUCTIONS:\n";
			echo "1. Download the ZIP file from the link above\n";
			echo "2. Extract the ZIP file\n";
			echo "3. Read the README.txt file for PDF conversion instructions\n";
			echo "4. Use the included PowerShell script for bulk conversion\n\n";
			echo "💡 TIP: The PowerShell script will convert all DOCX files to PDF automatically\n";
		}
	} else {
		echo "\n❌ ERROR: Failed to create ZIP file\n";
		echo "Individual files are available in: $folderPath\n";
	}

	if (!empty($allShipmentsProcessed)) {
		$downloadUrlForNotification = null;
		if ($templateMode === 'docx' && isset($downloadUrl) && $downloadUrl) {
			$downloadUrlForNotification = $downloadUrl;
		}

		sendNotification(
			$certificates ?? null,
			array_unique($allShipmentsProcessed),
			$downloadUrlForNotification
		);
	}

	// Update batch status on success
	$certificateBatchesModel->updateStatus($batchId, 'generated', [
		'excellence_count' => $stats['excellence'],
		'participation_count' => $stats['participation'],
		'skipped_count' => $stats['skipped'],
		'download_url' => $downloadUrl ?? null,
		'folder_path' => $folderPath
	]);
} catch (Exception $e) {
	error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
	error_log($e->getTraceAsString());

	// Update batch status on failure
	$certificateBatchesModel->updateStatus($batchId, 'failed', [
		'error_message' => $e->getMessage()
	]);
}
