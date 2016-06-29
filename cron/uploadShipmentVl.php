<?php

include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    
        date_default_timezone_set('GMT');
        $filename = UPLOAD_PATH . DIRECTORY_SEPARATOR . "spm.csv";
        if (!file_exists($filename) || !is_readable($filename))
            return FALSE;    
        $data = array();
        ini_set('auto_detect_line_endings', TRUE);
        if (($handle = fopen($filename, 'r')) !== false) {


            while (($line = fgetcsv($handle)) !== false) {
                $data[] = ($line);
            }
            fclose($handle);
        }
        ini_set('auto_detect_line_endings', FALSE);    
    
    unset($data[0]);
    $shipmentService = new Application_Service_Shipments();
    foreach ($data as $row) {
        if($row[1] == "" || $row[1] == null) break;
        $arr = array( "hdLastDate" => "2016-07-31",
                 "smid" => $row[1],
                 "shipmentId" => $row[2],
                 "participantId" => $row[3],
                 "evId" => $row[4],
                 "schemeCode" => $row[5],
                 "participantName" => "",
                 "comingFrom" => "",
                 "1_hdSampleId" => $row[8],
                 "2_hdSampleId" => $row[9],
                 "3_hdSampleId" => $row[10],
                 "4_hdSampleId" => $row[11],
                 "5_hdSampleId" => $row[12],
                 "receiptDate" => $row[13],
                 "sampleRehydrationDate" => $row[14],
                 "testDate" => $row[15],
                 "specimenVolume" => "",
                 "vlAssay" => $row[17],
                 "otherAssay" =>"",
                 "assayExpirationDate" => $row[19],
                 "assayLotNumber" => $row[20],
                 "modeOfReceipt" => $row[21],
                 "sampleId" => array( "0" => $row[22], "1" => $row[23] ,"2" => $row[24], "3" => $row[25], "4" => $row[26] ),
                 "vlResult" => array ( "0" => $row[27], "1" => $row[28], "2" => $row[29] ,"3" => $row[30], "4" => $row[31] ),
                 "MAX_FILE_SIZE" => 5000000,
                 "supervisorApproval" => "no",
                 "participantSupervisor" => "",
                 "userComments" =>"",
                 "uploadedFilePath" => "");
        
        $shipmentService->updateVlResults($arr);
        
    }
    //
    //$arr = array( "hdLastDate" => "2016-07-31",
    //             "smid" => 105,
    //             "shipmentId" => 2,
    //             "participantId" => 3195,
    //             "evId" => "19121190",
    //             "schemeCode" => "VL0616-1",
    //             "comingFrom" => "",
    //             "1_hdSampleId" => 1,
    //             "2_hdSampleId" => 2,
    //             "3_hdSampleId" => 3,
    //             "4_hdSampleId" => 4,
    //             "5_hdSampleId" => 5,
    //             "receiptDate" => "19-Jun-2016",
    //             "sampleRehydrationDate" => "19-Jun-2016",
    //             "testDate" => "19-Jun-2016",
    //             "specimenVolume" => "",
    //             "vlAssay" => 2,
    //             "otherAssay" =>"",
    //             "assayExpirationDate" => "19-Jun-2016",
    //             "assayLotNumber" => "T12903",
    //             "modeOfReceipt" => 1,
    //             "sampleId" => array( "0" => 1, "1" => 2 ,"2" => 3, "3" => 4, "4" => 5 ),
    //             "vlResult" => array ( "0" => 3.08, "1" => 4.41, "2" => 0 ,"3" => 2.67, "4" => 4.41 ),
    //             "MAX_FILE_SIZE" => 5000000,
    //             "supervisorApproval" => "no",
    //             "participantSupervisor" => "",
    //             "userComments" =>"",
    //             "uploadedFilePath" => "");
    
    
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in cron/SendMailAlerts.php');
}
