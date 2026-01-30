<?php

ini_set('memory_limit', '-1');
ini_set('max_execution_time', 0);
require_once __DIR__ . '/../cli-bootstrap.php';

$isCli = php_sapi_name() === 'cli';
$console = Pt_Commons_MiscUtility::console();

$cliOptions = getopt("s:");
$shipmentsToEvaluate = $cliOptions['s'] ?? null;


if (empty($shipmentsToEvaluate)) {
	$console->getErrorOutput()->writeln("<error> ERROR </error> Please specify the shipment ids with the -s flag");
	exit(1);
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

	$jobCompletionAlertStatus = $commonService->getConfig('job_completion_alert_status');
	$jobCompletionAlertMails = $commonService->getConfig('job_completion_alert_mails');
	$totalShipments = count($shipmentsToEvaluate);
	$console->writeln("<info>Shipment Evaluation</info> Processing <comment>{$totalShipments}</comment> shipment(s)");

	foreach ($shipmentsToEvaluate as $index => $shipmentId) {
		$console->writeln("");
		$console->writeln("<info>Evaluating shipment:</info> ID <comment>{$shipmentId}</comment> (" . ($index + 1) . "/{$totalShipments})");

		// Update shipment status to processing
		$db->update('shipment', [
			'status' => 'processing',
			'updated_on_admin' => new Zend_Db_Expr('now()')
		], $db->quoteInto('shipment_id = ?', $shipmentId));

		// Do evaluation
		$timeStart = microtime(true);
		$shipmentResult = $evalService->getShipmentToEvaluate($shipmentId, true);
		$timeEnd = microtime(true);

		// Delete existing reports if they exist
		$reportsPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports';
		$shipmentCode = $shipmentResult[0]['shipment_code'];
		$console->writeln("  Shipment code: <comment>{$shipmentCode}</comment>");

		$shipmentCodePath = $reportsPath . DIRECTORY_SEPARATOR . $shipmentCode;
		if (file_exists($shipmentCodePath)) {
			Pt_Commons_General::rmdirRecursive($shipmentCodePath);
			mkdir($shipmentCodePath, 0777, true);
		}
		if (file_exists($reportsPath . DIRECTORY_SEPARATOR . $shipmentCode . ".zip")) {
			unlink($reportsPath . DIRECTORY_SEPARATOR . $shipmentCode . ".zip");
		}

		// Cleanup and notify
		$executionTime = ($timeEnd - $timeStart) / 60;
		$console->writeln("  <fg=green>✓</> Completed in <comment>" . round($executionTime, 2) . "</comment> mins");

		// Set evaluated_at milestone timestamp and update status
		// we also nullify reports_generated_at and finalized_at fields
		// so that any report generation or finalization jobs can be re-run if needed
		$db->update('shipment', [
			'status' => 'evaluated',
			'evaluated_at' => new Zend_Db_Expr('NOW()'),
			'reports_generated_at' => null,
			'finalized_at' => null,
		], $db->quoteInto('shipment_id = ?', $shipmentId));

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
	$console->getErrorOutput()->writeln("<error> ERROR </error> {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
	$console->writeln("<fg=gray>{$e->getTraceAsString()}</>");
}
