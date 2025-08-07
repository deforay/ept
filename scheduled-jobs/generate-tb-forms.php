<?php
ini_set('memory_limit', '-1');

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

use setasign\Fpdi\Fpdi;


$cliOptions = getopt("s:");
$shipmentsToGenarateForm = $cliOptions['s'];
if (empty($shipmentsToGenarateForm)) {
    error_log("Please specify the shipment ids with the -s flag");
    exit();
}

$generalModel = new Pt_Commons_General();

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);
try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    if (isset($shipmentsToGenarateForm) && !empty($shipmentsToGenarateForm)) {

        $sQuery = $db->select()
            ->from(['s' => 'shipment'])
            ->joinLeft(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id', ['spm.map_id'])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=spm.participant_id', ["p.participant_id", "p.unique_identifier"])
            ->where("s.shipment_id = ?", $shipmentsToGenarateForm)
            ->group("p.participant_id")
            ->order("p.unique_identifier ASC");

        $tbResult = $db->fetchAll($sQuery);

        $folderPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $tbResult[0]['shipment_code'];
        if (is_dir($folderPath)) {
            $generalModel->rmdirRecursive($folderPath);
        }
        mkdir($folderPath, 0777, true);

        if (file_exists("$folderPath.zip")) {
            unlink("$folderPath.zip");
        }


        $tbDb = new Application_Model_Tb();
        $pdfsToMerge = [];
        foreach ($tbResult as $key => $row) {
            $pdfFile = $tbDb->generateFormPDF($row['shipment_id'], $row['participant_id'], true, true);
            $pdfsToMerge[] = $folderPath . DIRECTORY_SEPARATOR . $pdfFile;
        }
        if (isset($pdfsToMerge) && !empty($pdfsToMerge)) {
            $db->update(
                'shipment',
                [
                    'tb_form_generated' => 'yes',
                    'updated_on_admin' => new Zend_Db_Expr('now()'),
                ],
                'shipment_id = ' . $tbResult[0]['shipment_id']
            );
        }



        $batchSize = 50; // Number of PDFs to merge at a time
        $batchFiles = array_chunk($pdfsToMerge, $batchSize);
        // Array to hold the paths of intermediate files
        $intermediateFiles = [];

        // Generate intermediate files
        foreach ($batchFiles as $files) {
            $pdf = new Fpdi();
            foreach ($files as $file) {
                $pageCount = $pdf->setSourceFile($file);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    if ($size['width'] > $size['height']) {
                        $pdf->AddPage('L', [$size['width'], $size['height']]);
                    } else {
                        $pdf->AddPage('P', [$size['width'], $size['height']]);
                    }
                    $pdf->useTemplate($templateId);
                }
            }
            $intermediateFile = $folderPath . DIRECTORY_SEPARATOR . 'intermediate_' . uniqid() . '.pdf';
            $pdf->Output($intermediateFile, "F");
            $intermediateFiles[] = $intermediateFile;
            unset($pdf);
        }

        // Merge the intermediate files into the final PDF
        $finalPdf = new Fpdi();
        foreach ($intermediateFiles as $intermediateFile) {
            $pageCount = $finalPdf->setSourceFile($intermediateFile);
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $finalPdf->importPage($pageNo);
                $size = $finalPdf->getTemplateSize($templateId);
                if ($size['width'] > $size['height']) {
                    $finalPdf->AddPage('L', [$size['width'], $size['height']]);
                } else {
                    $finalPdf->AddPage('P', [$size['width'], $size['height']]);
                }
                $finalPdf->useTemplate($templateId);
            }
            // Optionally, delete the intermediate file to free up disk space
            unlink($intermediateFile);
        }

        // Output the final merged PDF
        $finalPdfPath = $folderPath . DIRECTORY_SEPARATOR . $tbResult[0]['shipment_code'].'-TB-Participant-Forms.pdf';
        $finalPdf->Output($finalPdfPath, "F");

        $generalModel->zipFolder($folderPath, $folderPath . "-TB-FORMS.zip");
    }
} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());
}
