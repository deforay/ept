<?php

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
require_once __DIR__ . '/../cli-bootstrap.php';

$cliOptions = getopt("s:");
$shipmentsToEvaluate = $cliOptions['s'];


if (empty($shipmentsToEvaluate)) {
	error_log("Please specify the shipment ids with the -s flag");
	exit();
}

if (is_array($shipmentsToEvaluate)) {
	$shipmentsToEvaluate = implode(",", $shipmentsToEvaluate);
} else {
	$shipmentsToEvaluate = [$shipmentsToEvaluate];
}


$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$evalService = new Application_Service_Evaluation();
$commonService = new Application_Service_Common();
try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);


	$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);


	foreach ($shipmentsToEvaluate as $shipmentId) {
		// Do evaluation
		$timeStart = microtime(true);
		$shipmentResult = $evalService->getShipmentToEvaluate($shipmentId, true);
		$timeEnd = microtime(true);

		// Cleanup and notify
		$executionTime = ($timeEnd - $timeStart) / 60;
		$link = "/admin/evaluate/shipment/sid/" . base64_encode($shipmentResult[0]['shipment_id']);
		$db->insert('notify', [
			'title' => 'Shipment Evaluated',
			'description' => 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated in ' . round($executionTime, 2) . ' mins',
			'link' => $link
		]);
		if (
			isset($customConfig->jobCompletionAlert->status)
			&& $customConfig->jobCompletionAlert->status == "yes"
			&& isset($customConfig->jobCompletionAlert->mails)
			&& !empty($customConfig->jobCompletionAlert->mails)
		) {
			$emailSubject = "ePT | Shipment Evaluated";
			$emailContent = 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated <br><br> Please click on this link to see ' . $conf->domain .  $link;
			$emailContent .= "<br><br><br><small>This is a system generated email</small>";
			$commonService->insertTempMail($customConfig->jobCompletionAlert->mails, null, null, $emailSubject, $emailContent);
		}
	}
} catch (Exception $e) {
	error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
	error_log($e->getTraceAsString());
}
