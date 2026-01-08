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
$adminService = new Application_Service_SystemAdmin();
$commonService = new Application_Service_Common();
$enabledAdminEmailReminder = $commonService->getConfig('enable_admin_email_notification');
try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);


	$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);
	$jobCompletionAlertStatus = $commonService->getConfig('job_completion_alert_status');
	$jobCompletionAlertMails = $commonService->getConfig('job_completion_alert_mails');

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
			isset($jobCompletionAlertStatus)
			&& $jobCompletionAlertStatus == "yes"
			&& isset($jobCompletionAlertMails)
			&& !empty($jobCompletionAlertMails)
		) {
			$emailSubject = "ePT | Shipment Evaluated";
			$emailContent = 'Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evaluated <br><br> Please click on this link to see ' . $conf->domain . $link;
			$emailContent .= "<br><br><br><small>This is a system generated email</small>";
			$commonService->insertTempMail($jobCompletionAlertMails, null, null, $emailSubject, $emailContent);
		}
		if ($enabledAdminEmailReminder == 'yes') {
			$queueResults = $db->fetchRow($db->select()
				->from('queue_report_generation')
				->where("shipment_id = ?", $shipmentId));
			/* Zend_Debug::dump($queueResults);
                die; */
			$adminDetails = $adminService->getSystemAdminDetails($queueResults['initated_by']);
			if (isset($adminDetails) && !empty($adminDetails) && $adminDetails['primary_email'] != "") {
				$link = $conf->domain . '/admin/evaluate/shipment/sid/' . base64_encode($shipmentId);
				$subject = 'Shipment for ' . $shipmentResult[0]['shipment_code'] . ' has been evalated';
				$message = 'Hello, ' . $adminDetails['first_name'] . ', <br>
                 Shipment ' . $shipmentResult[0]['shipment_code'] . ' has been evalated successfully. Kindly click the below link to see the evalation or copy paste into the brower address bar.<br>
                 <a href="' . $link . '">' . $link . '</a>.';

				$commonService->insertTempMail($adminDetails['primary_email'], null, null, $subject, $message, 'ePT System', 'ePT System Admin');
			}
		}
	}
} catch (Exception $e) {
	error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
	error_log($e->getTraceAsString());
}
