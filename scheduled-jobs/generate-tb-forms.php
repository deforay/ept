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
            ->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id', array('spm.map_id'))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array("p.participant_id", "p.unique_identifier"))
            ->where("s.shipment_id = ?", $shipmentsToGenarateForm)
            ->group("p.participant_id")
            ->order("p.unique_identifier ASC");
        $tbResult = $db->fetchAll($sQuery);

        $folderPath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $tbResult[0]['shipment_code'];
        if (is_dir($folderPath)) {
            $generalModel->rmdirRecursive($folderPath);
        }
        mkdir($folderPath, 0777, true);

        if (file_exists($folderPath . ".zip")) {
            unlink($folderPath . ".zip");
        }


        $tbDb = new Application_Model_Tb();
        $pdfsToMerge = [];
        foreach ($tbResult as $key => $row) {
            $pdfFile = $tbDb->generateFormPDF($row['shipment_id'], $row['participant_id'], true, true);
            $pdfsToMerge[] = $folderPath . DIRECTORY_SEPARATOR . $pdfFile;
        }

        $generalModel->zipFolder($folderPath, $folderPath . ".zip");

        //Merge $pdfFiles into a single PDF using Tcpdf
        $pdf = new Fpdi();
        // Loop through each PDF to merge
        foreach ($pdfsToMerge as $file) {
            // get the page count
            $pageCount = $pdf->setSourceFile($file);

            // iterate through all pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                // import a page
                $templateId = $pdf->importPage($pageNo);
                // get the size of the imported page
                $size = $pdf->getTemplateSize($templateId);
                // create a page (landscape or portrait depending on the imported page size)
                if ((isset($size['width']) && isset($size['height']) && $size['width'] > $size['height']) || isset($size['orientation']) && $size['orientation'] == 'L') {
                    $pdf->AddPage('L', [$size['width'], $size['height']]);
                } else {
                    $pdf->AddPage('P', [$size['width'], $size['height']]);
                }

                // use the imported page
                $pdf->useTemplate($templateId);
            }
        }
        // Print compine pdf into single pdf
        $pdf->Output($folderPath . DIRECTORY_SEPARATOR . 'TB-FORM-'.$tbResult[0]['shipment_code'].'-All-participant-form.pdf', "F");
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/evaluate-shipments.php');
}
