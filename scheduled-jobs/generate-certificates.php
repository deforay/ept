<?php
// scheduled-jobs/generate-certificates.php
//
// Generates excellence and participation certificates for shipment participants.
//
// =============================================================================
// HOW THIS SCRIPT WORKS
// =============================================================================
//
// When you run this script, it queries participants for the given shipments
// and generates a certificate for each eligible participant. For each
// participant × shipment-type combination, it:
//   1. Checks if the participant passed all panels → excellence certificate
//   2. Otherwise, if they submitted results → participation certificate
//   3. Otherwise → skipped
//
// Certificates are generated from templates (PDF or DOCX) and packaged
// into a ZIP file for download.
//
// TEMPLATE MODES (-t flag):
//   pdf  - Uses AcroForm PDF templates filled via pdftk
//   docx - Uses DOCX templates with placeholder substitution via PhpWord
//
// Templates are looked up in scheduled-jobs/certificate-templates/ with the
// naming convention: {scheme}-{type}.{ext} (e.g., vl-e.pdf, eid-p.docx)
//
// PARALLEL PROCESSING (--procs flag):
//   When generating for many participants, the script can split the work
//   across multiple CPU cores. The main process spawns worker subprocesses,
//   each handling a slice of participants.
//
//   The --worker flag is internal - it tells a subprocess which chunk to
//   process. You don't need to use --worker directly.
//
// =============================================================================
// USAGE
// =============================================================================
//
//   php generate-certificates.php [options]
//
// Options:
//   -s ID[,ID,...]    Shipment IDs to generate certificates for (required)
//   -c NAME           Certificate batch name (default: current year)
//   -t MODE           Template mode: pdf or docx (default: pdf)
//   -b ID             Existing batch ID to update (otherwise auto-created)
//   --procs=N         Number of parallel worker processes (default: CPU count)
//   --worker          Internal: run as subprocess for parallel processing
//   --offset=N        Internal: worker start offset
//   --limit=N         Internal: worker batch size
//
// Examples:
//   php generate-certificates.php -s 101
//   php generate-certificates.php -s 101,102,103 -c "2026-Round1"
//   php generate-certificates.php -s 101,102 -t docx
//   php generate-certificates.php -s 101 -t docx --procs=4
//   php generate-certificates.php -s 101,102 -b 5

require_once __DIR__ . '/../cli-bootstrap.php';

use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Process;

$cliOptions = getopt("s:c:t:b:", ["procs:", "worker", "offset:", "limit:"]);
$shipmentsToGenerate = $cliOptions['s'] ?? null;
$certificateName = !empty($cliOptions['c']) ? $cliOptions['c'] : date('Y');
$templateMode = strtolower($cliOptions['t'] ?? 'pdf');   // 'pdf' | 'docx'
$batchId = !empty($cliOptions['b']) ? (int) $cliOptions['b'] : null;
$isWorker = isset($cliOptions['worker']);
$workerOffset = isset($cliOptions['offset']) ? (int) $cliOptions['offset'] : 0;
$workerLimit = isset($cliOptions['limit']) ? (int) $cliOptions['limit'] : 0;
$procsCount = isset($cliOptions['procs']) ? (int) $cliOptions['procs'] : Pt_Commons_MiscUtility::getCpuCount();
if ($procsCount < 1)
	$procsCount = 1;

// Check proc_open availability for parallel processing
if (!$isWorker && $procsCount > 1) {
	$disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
	if (!function_exists('proc_open') || in_array('proc_open', $disabled, true)) {
		echo "Parallel processing disabled: proc_open not available. Using single process.\n";
		$procsCount = 1;
	}
}

// Workers skip batch management - handled by main process
if (!$isWorker) {
	$certificateBatchesModel = new Application_Model_DbTable_CertificateBatches();

	if ($batchId) {
		// An admin can cancel a batch while its job waits in the queue.
		if (($certificateBatchesModel->getBatch($batchId)['status'] ?? '') === 'cancelled') {
			echo "Batch #{$batchId} was cancelled. Nothing to generate.\n";
			exit(0);
		}
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
}

if (is_array($shipmentsToGenerate))
	$shipmentsToGenerate = implode(",", $shipmentsToGenerate);
if (empty($shipmentsToGenerate)) {
	Pt_Commons_LoggerUtility::logError("Please specify the shipment ids with -s");
	if (isset($certificateBatchesModel)) {
		$certificateBatchesModel->updateStatus($batchId, 'failed', [
			'error_message' => 'No shipment IDs specified'
		]);
	}
	exit(1);
}

/* ---------- Common helpers ---------- */

function sendNotification(?string $recipients, array $shipmentsList, string $batchesUrl)
{
	// global_config.certificates holds the comma-separated addresses set as
	// "Certificate Email" in Configure > Global Settings.
	$recipients = trim((string) $recipients);
	if ($recipients === '' || empty($shipmentsList)) {
		return;
	}
	$common = new Application_Service_Common();
	$emailSubject = "ePT | Certificates Generated";
	$emailContent = "Certificates for Shipment " . htmlspecialchars(implode(", ", $shipmentsList)) . " have been generated.";
	$emailContent .= "<br><br>Review and approve them on the Certificate Batches page: <a href=\"$batchesUrl\">$batchesUrl</a>";
	$emailContent .= "<br><br><br><small>This is a system generated email</small>";

	$common->insertTempMail($recipients, null, null, $emailSubject, $emailContent);
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


/* ---------- DOCX path (search & replace) ---------- */

function renderDocx(string $docxTemplate, array $fields, string $outDocx): bool
{
	if (!file_exists($docxTemplate)) {
		Pt_Commons_LoggerUtility::logError("Template missing: $docxTemplate");
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
			Pt_Commons_LoggerUtility::logWarning("LibreOffice attempt $attempt failed, retrying...");
			sleep(1); // Brief pause before retry
		}
	}

	Pt_Commons_LoggerUtility::logError("LibreOffice conversion failed after $maxRetries attempts (code=$code): " . implode("\n", $out));
	return null;
}

/* ---------- Setup ---------- */
// Each batch has its own folder outside the public web root. A reused batch
// name cannot overwrite an earlier batch, and the clean-up of public/temporary
// cannot delete a batch that is waiting for approval.
$folderPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-batches' . DIRECTORY_SEPARATOR . (int) $batchId;
if (!$isWorker) {
	Pt_Commons_General::rmdirRecursive($folderPath);
}
$excellenceCertPath = "$folderPath/excellence";
$participationCertPath = "$folderPath/participation";
foreach ([$excellenceCertPath, $participationCertPath] as $p)
	if (!is_dir($p))
		@mkdir($p, 0777, true);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$schemeService = new Application_Service_Schemes();
$vlModel = new Application_Model_Vl();
$vlAssayArray = $vlModel->getVlAssay();
$eidAssayArray = $schemeService->getEidExtractionAssay();

$certificateRecipients = Application_Service_Common::getConfig('certificates');

/* ---------- Templates ---------- */

// Configure > Certificate Templates saves each scheme's templates as
// {scheme}-e.pdf (excellence) and {scheme}-p.pdf (participation).
function certificateTemplatePath(string $shipmentType, string $certificateType, string $mode): string
{
	$suffix = $certificateType === 'excellence' ? 'e' : 'p';
	return SCHEDULED_JOBS_FOLDER . "/certificate-templates/{$shipmentType}-{$suffix}.{$mode}";
}
function generateCertificate(string $shipmentType, string $certificateType, array $fields, string $outputFileBase, string $mode): bool
{
	$tpl = certificateTemplatePath($shipmentType, $certificateType, $mode);

	if (!is_file($tpl)) {
		echo "WARNING: Template file not found: $tpl. Skipping.\n";
		return false;
	}

	if (!is_readable($tpl)) {
		echo "WARNING: Template file is not readable: $tpl. Skipping.\n";
		return false;
	}

	if ($mode === 'pdf') {
		$error = Application_Service_CertificateTemplates::fillPdf($tpl, $fields, $outputFileBase . ".pdf");
		if ($error !== null) {
			Pt_Commons_LoggerUtility::logError($error);
			echo "WARNING: Failed to fill PDF template $tpl. Skipping.\n";
			return false;
		}
		return true;
	}

	if ($mode === 'docx') {
		$outDocx = "$outputFileBase.docx";
		if (!renderDocx($tpl, $fields, $outDocx)) {
			echo "WARNING: Failed to render DOCX template $tpl. Skipping.\n";
			return false;
		}
		return true;
	}

	throw new Exception("Unknown template mode: $mode");
}
function createCertificatesZip(string $folderPath, string $certificateName, string $mode = 'pdf'): ?string
{
	$zipFileName = "certificates-{$certificateName}-" . date('Y-m-d-H-i-s') . ".zip";
	$zipPath = $folderPath . DIRECTORY_SEPARATOR . $zipFileName;


	$zip = new ZipArchive();
	if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
		Pt_Commons_LoggerUtility::logError("Cannot create ZIP file: $zipPath");
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
			return $zipPath;
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
		return $zipPath;
	}

	return null;
}


try {
	$db = Zend_Db::factory($conf->resources->db);
	$domain = rtrim($conf->domain, "/");
	Zend_Db_Table::setDefaultAdapter($db);

	$query = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',])
		->where("shipment_id IN (?)", array_map('trim', explode(",", (string) $shipmentsToGenerate)))
		->order("s.scheme_type")
		->order("s.shipment_date");
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

	// The same responses, name and country the review Excel shows
	// (Application_Service_Reports::getAnnualReport).
	$sQuery = $db->select()->from(['spm' => 'shipment_participant_map'], ['spm.attributes', 'spm.shipment_test_report_date', 'spm.final_result'])
		->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['shipment_code', 'scheme_type', 'shipment_date'])
		->join(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name'])
		->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', [
			'unique_identifier',
			'participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
			'city',
		])
		->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country_name' => 'c.iso_name'])
		->where("spm.shipment_id IN (?)", $shipmentIdArray)
		->where(Application_Service_Reports::ANNUAL_RESULT_FILTER)
		->order("unique_identifier ASC")
		->order("scheme_type ASC")
		->order("shipment_date ASC");

	$participants = [];
	foreach ($db->fetchAll($sQuery) as $row) {
		$uid = $row['unique_identifier'];
		$scheme = $row['scheme_type'];
		$participants[$uid]['name'] = Pt_Commons_MiscUtility::toUtf8((string) $row['participantName']);
		$participants[$uid]['city'] = $row['city'];
		$participants[$uid]['country'] = $row['country_name'] ?? '';
		$participants[$uid]['schemes'][$scheme]['entries'][$row['shipment_code']] = [
			'result' => $row['final_result'],
			'shipment_test_report_date' => $row['shipment_test_report_date'],
		];
		// Rows run oldest shipment first, so the assay and the year come
		// from the participant's latest shipment of the scheme.
		$participants[$uid]['schemes'][$scheme]['attributes'] = Pt_Commons_JsonUtility::safeDecode($row['attributes']);
		$participants[$uid]['schemes'][$scheme]['year'] = date('Y', strtotime($row['shipment_date']));
		$participants[$uid]['schemes'][$scheme]['scheme_name'] = $row['scheme_name'];
	}

	// The issue date and the signatory are the same on every certificate.
	$batchFields = Application_Service_CertificateTemplates::batchFields();

	// One plan item per certificate to generate. The main process and every
	// worker build the same plan from the same ordered query. Each worker
	// takes a slice, and the main process checks the files against the plan.
	$plan = [];
	$skipped = 0;
	foreach ($participants as $uid => $participant) {
		foreach ($participant['schemes'] as $scheme => $schemeData) {
			$certificateType = Application_Service_Reports::annualCertificateType($schemeData['entries'], $shipmentCodeArray[$scheme] ?? []);
			if ($certificateType === '') {
				$skipped++;
				continue;
			}
			$assay = Application_Service_Reports::annualReportAssayName($scheme, $schemeData['attributes'], $vlAssayArray, $eidAssayArray);
			$folder = $certificateType === 'excellence' ? $excellenceCertPath : $participationCertPath;
			$plan[] = [
				'uid' => (string) $uid,
				'scheme' => $scheme,
				'type' => $certificateType,
				'base' => $folder . DIRECTORY_SEPARATOR . str_replace('/', '_', (string) $uid) . "-" . strtoupper($scheme) . "-" . $certificateName,
				'fields' => Application_Service_CertificateTemplates::expandFields(array_merge([
					'participant_name' => $participant['name'],
					'lab_id' => (string) $uid,
					'city' => $participant['city'],
					'country' => $participant['country'],
					'scheme_name' => $schemeData['scheme_name'],
					'assay' => $assay,
					'shipment_year' => $schemeData['year'],
				], $batchFields)),
			];
		}
	}

	$ext = ($templateMode === 'docx') ? 'docx' : 'pdf';
	$totalParticipants = count($participants);
	$totalPlanned = count($plan);

	if ($isWorker) {
		$plan = array_slice($plan, $workerOffset, $workerLimit);
	}

	// Pre-check: verify templates exist for all scheme types in this run
	if (!$isWorker) {
		$templateService = new Application_Service_CertificateTemplates();
		$templateProblems = [];
		foreach (array_unique(array_column($plan, 'scheme')) as $scheme) {
			foreach (['excellence', 'participation'] as $certType) {
				$tpl = certificateTemplatePath($scheme, $certType, $templateMode);
				if (!is_file($tpl)) {
					$templateProblems[] = "Template file not found: {$tpl}";
				} elseif (!is_readable($tpl)) {
					$templateProblems[] = "Template file not readable: {$tpl}";
				} elseif ($templateMode === 'pdf') {
					// Templates copied onto the server by hand skip the upload check.
					$check = $templateService->validatePdfTemplate($tpl);
					if (!$check['valid']) {
						$templateProblems[] = "{$tpl}: {$check['error']}";
					}
				}
			}
		}
		if (!empty($templateProblems)) {
			echo "\nWARNING: Template issues detected:\n";
			foreach ($templateProblems as $problem) {
				echo "  - {$problem}\n";
			}
			echo "\n";
		}
	}

	if (!$isWorker && $procsCount > 1 && $totalPlanned > 1) {
		// ---- Parallel mode: spawn worker subprocesses ----
		echo "\nGenerating {$totalPlanned} certificates using {$procsCount} parallel processes...\n";

		$phpBinary = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
		$batchSize = (int) ceil($totalPlanned / $procsCount);
		$processes = [];

		for ($offset = 0; $offset < $totalPlanned; $offset += $batchSize) {
			$limit = min($batchSize, $totalPlanned - $offset);
			$cmd = [
				$phpBinary,
				__FILE__,
				'--worker',
				'-s',
				$shipmentsToGenerate,
				'-c',
				$certificateName,
				'-t',
				$templateMode,
				'-b',
				(string) $batchId,
				'--offset',
				(string) $offset,
				'--limit',
				(string) $limit,
			];

			try {
				$process = new Process($cmd);
				$process->setTimeout(null);
				$process->start();
				$processes[] = ['process' => $process, 'offset' => $offset];
			} catch (Throwable $e) {
				echo "Failed to start worker (offset {$offset}): {$e->getMessage()}\n";
			}
		}

		// Monitor workers and show progress
		while (!empty($processes)) {
			foreach ($processes as $key => $procData) {
				if (!$procData['process']->isRunning()) {
					unset($processes[$key]);
				}
			}

			$genCount = count(glob($excellenceCertPath . "/*.{$ext}") ?: [])
				+ count(glob($participationCertPath . "/*.{$ext}") ?: []);
			echo "\r  [{$genCount}/{$totalPlanned}] Certificates generated...    ";

			usleep(250000);
		}
		echo "\n";
	} else {
		// ---- Sequential mode (single process or worker subprocess) ----
		$current = 0;
		$count = count($plan);
		foreach ($plan as $item) {
			$current++;
			if (!$isWorker) {
				echo "\r[{$current}/{$count}] Generating certificate for Participant Code : {$item['uid']}                    ";
			}
			generateCertificate($item['scheme'], $item['type'], $item['fields'], $item['base'], $templateMode);
		}

		// Workers exit after generating their chunk
		if ($isWorker) {
			exit(0);
		}
	}

	// Count what was actually written, in both modes, and record which lab
	// each file belongs to. distribute-certificates.php reads the manifest
	// instead of guessing the Lab ID from the file name.
	$stats = ['excellence' => 0, 'participation' => 0, 'skipped' => $skipped, 'template_errors' => 0];
	$manifest = [];
	foreach ($plan as $item) {
		$file = $item['base'] . '.' . $ext;
		if (is_file($file)) {
			$stats[$item['type']]++;
			$manifest[] = [
				'file' => substr($file, strlen($folderPath) + 1),
				'unique_identifier' => $item['uid'],
				'scheme_type' => $item['scheme'],
				'certificate_type' => $item['type'],
			];
		} else {
			$stats['template_errors']++;
		}
	}
	file_put_contents($folderPath . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

	// Print generation summary
	$totalCerts = $stats['excellence'] + $stats['participation'];
	$templateErrors = $stats['template_errors'];

	echo "\n\n=== Generation Summary ===\n";
	echo "Participants matched:       {$totalParticipants}\n";
	echo "Excellence certificates:    {$stats['excellence']}\n";
	echo "Participation certificates: {$stats['participation']}\n";
	echo "Skipped (no submission):    {$stats['skipped']}\n";
	if ($templateErrors > 0) {
		echo "Template errors:            {$templateErrors}\n";
	}
	echo "Total certificates:         {$totalCerts}\n";

	if ($totalCerts === 0) {
		echo "\nNo certificates were generated. Skipping ZIP creation.\n";
		if ($templateErrors > 0) {
			echo "Please check that the certificate templates exist and are valid.\n";
		}
		// Check if multiple shipments of the same scheme type were selected
		foreach ($shipmentCodeArray as $schemeType => $codes) {
			if (count($codes) > 1) {
				echo "NOTE: Multiple {$schemeType} shipments were selected (" . implode(', ', $codes) . ").\n";
				echo "      A participant must have participated in ALL selected shipments to receive a certificate.\n";
			}
		}
	} else {
		// Create ZIP for download (both PDF and DOCX modes)
		echo "\n=== Creating ZIP file for download... ===\n";
		$zipPath = createCertificatesZip($folderPath, $certificateName, $templateMode);

		if ($zipPath) {
			echo "\nSUCCESS: Certificates packaged successfully!\n";
			echo "ZIP file: $zipPath\n";
			echo "Download it from Configure > Certificate Batches.\n\n";
			if ($templateMode === 'docx') {
				echo "INSTRUCTIONS:\n";
				echo "1. Download the ZIP file from Configure > Certificate Batches\n";
				echo "2. Extract the ZIP file\n";
				echo "3. Read the README.txt file for PDF conversion instructions\n";
				echo "4. Use the included PowerShell script for bulk conversion\n\n";
			}
		} else {
			echo "\nERROR: Failed to create ZIP file\n";
			echo "Individual files are available in: $folderPath\n";
		}
	}

	// An admin may have cancelled the batch while it was generating. Keep
	// the cancellation instead of marking the batch generated.
	$currentStatus = $certificateBatchesModel->getBatch($batchId)['status'] ?? '';
	if ($currentStatus === 'cancelled') {
		echo "\nBatch #{$batchId} was cancelled while generating. Its files are kept but it cannot be approved.\n";
		exit(0);
	}

	if (!empty($allShipmentsProcessed) && $totalCerts > 0) {
		sendNotification(
			$certificateRecipients,
			array_unique($allShipmentsProcessed),
			$domain . '/admin/certificate-batches'
		);
	}

	$batchStatus = $totalCerts > 0 ? 'generated' : 'failed';
	$errorMessage = null;
	if ($totalCerts === 0) {
		if ($templateErrors > 0) {
			$errorMessage = "No certificates generated. {$templateErrors} template error(s) encountered."
				. (!empty($templateProblems) ? ' ' . implode(' ', $templateProblems) : '');
		} else {
			$errorMessage = "No certificates generated. No eligible participants found.";
			foreach ($shipmentCodeArray as $schemeType => $codes) {
				if (count($codes) > 1) {
					$errorMessage .= " Multiple {$schemeType} shipments selected (" . implode(', ', $codes) . "). Participants must have participated in all of them.";
				}
			}
		}
	} elseif ($templateErrors > 0) {
		$errorMessage = "{$templateErrors} certificate(s) could not be generated. Check the certificate templates."
			. (!empty($templateProblems) ? ' ' . implode(' ', $templateProblems) : '');
	}

	$certificateBatchesModel->updateStatus($batchId, $batchStatus, [
		'excellence_count' => $stats['excellence'],
		'participation_count' => $stats['participation'],
		'skipped_count' => $stats['skipped'],
		'download_url' => null,
		'folder_path' => $folderPath,
		'error_message' => $errorMessage
	]);
	exit($totalCerts > 0 ? 0 : 1);
} catch (Throwable $e) {
	Pt_Commons_LoggerUtility::logError($e->getMessage(), [
	    'file'  => $e->getFile(),
	    'line'  => $e->getLine(),
	    'trace' => $e->getTraceAsString(),
	]);

	// Update batch status on failure
	if (isset($certificateBatchesModel)) {
		$certificateBatchesModel->updateStatus($batchId, 'failed', [
			'error_message' => $e->getMessage()
		]);
	}
	exit(1);
}
