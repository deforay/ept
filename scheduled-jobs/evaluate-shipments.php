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


//$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$evalService = new Application_Service_Evaluation();

try {

	// $db = Zend_Db::factory($conf->resources->db);
	// Zend_Db_Table::setDefaultAdapter($db);


	foreach ($shipmentsToEvaluate as $shipmentId) {
		$evalService->getShipmentToEvaluate($shipmentId, true);
	}
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/evaluate-shipments.php');
}
