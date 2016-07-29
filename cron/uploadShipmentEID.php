<?php

include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    
        date_default_timezone_set('GMT');
        $filename = UPLOAD_PATH . DIRECTORY_SEPARATOR . "eid-spm.csv";
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
        //Zend_Debug::dump($row);continue;
        if($row[1] == "" || $row[1] == null) break;
        $arr = array( "hdLastDate" => $row[0],
                     "shipmentId" => $row[1],
                     "participantId" => $row[2],
                    "smid" => $row[3],
                    "evId" => $row[4],
                    "schemeCode" => $row[5],
                    "participantName" => "",
                    "comingFrom" => "",
                    "receiptDate" => $row[7],
                    "sampleRehydrationDate" => $row[8],
                    "testDate" => $row[9],
                    "extractionAssay" =>  $row[10],
                    "detectionAssay" => $row[11],
                    "extractionAssayLotNo" => $row[12],
                    "detectionAssayLotNo" => $row[13],
                    "extractionAssayExpiryDate" => $row[14],
                    "detectionAssayExpiryDate" => $row[15],
                    "testReceiptDate" => $row[16],
                    "modeOfReceipt" => $row[17],
                    "sampleId" => array( "0" => $row[18], "1" => $row[19] ,"2" => $row[20], "3" => $row[21], "4" => $row[22], "5" => $row[23], "6" => $row[24] , "7" => $row[25], "8" => $row[26], "9" => $row[27], "10" => $row[28]),
                    //"sampleId" => array( "0" => "1", "1" => "2" ,"2" => "3", "3" => "4", "4" => "5", "5" => "6", "6" => "7" , "7" => "8", "8" => "9", "9" => "10", "10" => "11"),
                    "result" => array( "0" => $row[29], "1" => $row[30] ,"2" => $row[31], "3" => $row[32], "4" => $row[33], "5" => $row[34], "6" => $row[35] , "7" => $row[36], "8" => $row[37], "9" => $row[38], "10" => $row[39]),
                    "hivCtOd" => array ( "0" => 0, "1" => 0, "2" => 0 ,"3" => 0, "4" => 0 , "5" => 0, "6" => 0 , "7" => 0, "8" => 0, "9" => 0, "10" => 0),
                    "icQs" => array ( "0" => 0, "1" => 0, "2" => 0 ,"3" => 0, "4" => 0 , "5" => 0, "6" => 0 , "7" => 0, "8" => 0, "9" => 0, "10" => 0),
                    "MAX_FILE_SIZE" => 5000000,
                    "supervisorApproval" => "no",
                    "participantSupervisor" => "",
                    "userComments" =>"",
                    "uploadedFilePath" => "");
        
        $shipmentService->updateEidResults($arr);
        //Zend_Debug::dump($arr);
        
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
