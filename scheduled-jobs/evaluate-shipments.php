<?php

ini_set('memory_limit', '-1');

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

$cliOptions = getopt("s:");
$shipmentsToEvaluate = $cliOptions['s'];


if (empty($shipmentsToEvaluate)) {
	error_log("Please specify the shipment ids with the -s flag");
	exit();
}

if (is_array($shipmentsToEvaluate)) {
	$shipmentsToEvaluate = implode(",", $shipmentsToEvaluate);
} else {
	$shipmentsToEvaluate = array($shipmentsToEvaluate);
}


$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$evalService = new Application_Service_Evaluation();

try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);




	foreach ($shipmentsToEvaluate as $shipmentId) {
		$timeStart = microtime(true);
		$shipmentResult = $evalService->getShipmentToEvaluate($shipmentId, true);
		$timeEnd = microtime(true);

		$executionTime = ($timeEnd - $timeStart) / 60;

		$db->insert('notify', array('title' => 'Shipment Evaluated', 'description' => 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated in ' . round($executionTime,2) . ' mins', 'link' => "/admin/evaluate/shipment/sid/" . base64_encode($shipmentResult[0]['shipment_id'])));
	}
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/evaluate-shipments.php');
}
