<?php

ini_set('memory_limit', '-1');

require_once __DIR__ . '/../cli-bootstrap.php';

$cliOptions = getopt("s:");
$shipmentToSendReport = $cliOptions['s'];


if (empty($shipmentToSendReport)) {
	Pt_Commons_LoggerUtility::logError("Please specify the shipment ids with the -s flag");
	exit();
}

if (is_array($shipmentToSendReport)) {
	$shipmentToSendReport = implode(",", $shipmentToSendReport);
} else {
	$shipmentToSendReport = array($shipmentToSendReport);
}


$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$shipmentService = new Application_Service_Shipments();
$commonService = new Application_Service_Common();
try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);
	foreach ($shipmentToSendReport as $shipmentId) {
		$timeStart = microtime(true);
		$shipmentResult = $shipmentService->fetchReportsMail($shipmentId, $conf);
		$timeEnd = microtime(true);
		$executionTime = ($timeEnd - $timeStart) / 60;
		$link = "/reports/finalize/shipments";
		$db->insert('notify', [
			'title' => 'Shipment Report Mail',
			'description' => 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been generated report in ' . round($executionTime, 2) . ' mins',
			'link' => $link
		]);
	}
} catch (Throwable $e) {
	Pt_Commons_LoggerUtility::logError($e->getMessage(), [
	    'file'  => $e->getFile(),
	    'line'  => $e->getLine(),
	    'trace' => $e->getTraceAsString(),
	]);
}
