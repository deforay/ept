<?php
ini_set('memory_limit', '-1');

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

$cliOptions = getopt("s:");
$shipmentsToGenarateForm = $cliOptions['s'];
if (empty($shipmentsToGenarateForm)) {
    error_log("Please specify the shipment ids with the -s flag");
    exit();
}

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);
try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    if (isset($shipmentsToGenarateForm) && !empty($shipmentsToGenarateForm)) {
        $sQuery = $db->select()
            ->from(array('s' => 'shipment'), array('s.shipment_id'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id', array('spm.map_id'))
            ->joinLeft(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array("p.participant_id"))
            ->where("s.shipment_id = ?", $shipmentsToGenarateForm)
            ->group("p.participant_id");
        // die($sQuery);
        $tbResult = $db->fetchAll($sQuery);
        $tbDb = new Application_Model_Tb();
        foreach ($tbResult as $row) {
            $pdf = $tbDb->generateFormPDF($row['shipment_id'], $row['participant_id'], true, true);
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/evaluate-shipments.php');
}
