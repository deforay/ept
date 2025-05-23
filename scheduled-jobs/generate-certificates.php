<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

$cliOptions = getopt("s:c:");
$shipmentsToGenerate = $cliOptions['s'];
$certificateName = !empty($cliOptions['c']) ? $cliOptions['c'] : date('Y');

if (is_array($shipmentsToGenerate)) {
	$shipmentsToGenerate = implode(",", $shipmentsToGenerate);
}


if (empty($shipmentsToGenerate)) {
	error_log("Please specify the shipment ids with the -s flag");
	exit();
}

function createFDF($data)
{
	$fdf = "%FDF-1.2\n1 0 obj\n<<\n/FDF\n  <<\n  /Fields [\n";

	foreach ($data as $key => $value) {
		// Replace line breaks with a carriage return
		$value = str_replace(["\r\n", "\r", "\n"], "\r", $value ?? '');
		// Escape special characters
		$fdf .= '    << /T (' . addcslashes($key, "\n\r\t\\()") . ') /V (' . addcslashes($value, "\n\r\t\\()") . ') >>' . "\n";
	}

	$fdf .= "  ]\n  >>\n>>\nendobj\ntrailer\n<<\n/Root 1 0 R\n>>\n%%EOF";
	$fdfFile = tempnam(TEMP_UPLOAD_PATH, 'fdf');
	file_put_contents($fdfFile, $fdf);

	return $fdfFile;
}
function createCertificateFile($templateFile, $fields, $outputFile)
{
	if (!file_exists($templateFile)) {
		return false;
	}
	$fdfFile = createFDF($fields);

	// Generate the filled and flatten PDF
	$command = "/usr/local/bin/pdftk " . escapeshellarg($templateFile) . " fill_form " . escapeshellarg($fdfFile) . " output " . escapeshellarg($outputFile) . " flatten";
	exec($command);

	unlink($fdfFile);
}


$certificatePaths = [];
$folderPath = TEMP_UPLOAD_PATH . "/certificates/$certificateName";
$certificatePaths[] = $excellenceCertPath = "$folderPath/excellence";
$certificatePaths[] = $participationCertPath = "$folderPath/participation";

if (!file_exists($excellenceCertPath)) {
	mkdir($excellenceCertPath, 0777, true);
}
if (!file_exists($participationCertPath)) {
	mkdir($participationCertPath, 0777, true);
}


$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$participantsDb = new Application_Model_DbTable_Participants();
$dataManagerDb = new Application_Model_DbTable_DataManagers();
$schemesService = new Application_Service_Schemes();
$vlModel       = new Application_Model_Vl();
$vlAssayArray = $vlModel->getVlAssay();
$generalModel = new Pt_Commons_General();

$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);
function generateCertificate($shipmentType, $certificateType, $fields, $outputFile)
{
	$templates = [
		'excellence' => [
			'dts' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/dts-e.pdf",
			'eid' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/eid-e.pdf",
			'vl'  => SCHEDULED_JOBS_FOLDER . "/certificate-templates/vl-e.pdf",
		],
		'participation' => [
			'dts' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/dts-p.pdf",
			'eid' => SCHEDULED_JOBS_FOLDER . "/certificate-templates/eid-p.pdf",
			'vl'  => SCHEDULED_JOBS_FOLDER . "/certificate-templates/vl-p.pdf",
		],
	];

	$templateFile = $templates[$certificateType][$shipmentType] ?? null;

	if (!$templateFile || !file_exists($templateFile)) {
		throw new Exception("$certificateType template file not found for $shipmentType");
	}

	createCertificateFile($templateFile, $fields, $outputFile);
}

function sendNotification($emailConfig, $shipmentsList)
{
	if (!empty($emailConfig) && !empty($shipmentsList) && $emailConfig->status == "yes" && !empty($emailConfig->mails)) {
		$common = new Application_Service_Common();
		$emailSubject = "ePT | Certificates Generated";
		$emailContent = "Certificates for Shipment " . implode(", ", $shipmentsList) . " have been generated.";
		$emailContent .= "<br><br><br><small>This is a system generated email</small>";
		$common->insertTempMail($emailConfig->mails, null, null, $emailSubject, $emailContent);
	}
}


try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);

	$output = [];

	$query = $db->select()->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',])
		->where("shipment_id IN ($shipmentsToGenerate)")
		->order("s.scheme_type");
	$shipmentResult = $db->fetchAll($query);

	$shipmentIDArray = [];
	foreach ($shipmentResult as $val) {
		$shipmentIdArray[] = $val['shipment_id'];
		$shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
		$impShipmentId = implode(",", $shipmentIdArray);
	}

	$sQuery = $db->select()->from(['spm' => 'shipment_participant_map'], ['spm.map_id', 'spm.attributes', 'spm.shipment_test_report_date', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result'])
		->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['shipment_code', 'scheme_type', 'lastdate_response'])
		->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['unique_identifier', 'first_name', 'last_name', 'email', 'city', 'state', 'address', 'country', 'institute_name'])
		// ->where("spm.final_result = 1 OR spm.final_result = 2")
		// ->where("spm.is_excluded NOT LIKE 'yes'")
		->order("unique_identifier ASC")
		->order("scheme_type ASC");

	$sQuery->where("spm.shipment_id IN ($impShipmentId)");

	//Zend_Debug::dump($shipmentCodeArray);die;
	$shipmentParticipantResult = $db->fetchAll($sQuery);
	//Zend_Debug::dump($shipmentParticipantResult);die;
	$participants = [];

	foreach ($shipmentParticipantResult as $shipment) {

		$participantName = Pt_Commons_MiscUtility::toUtf8([
			'first_name' => $shipment['first_name'] ?? '',
			'last_name' => $shipment['last_name'] ?? '',
		]);

		$fullNameParts = array_filter($participantName); // remove empty strings
		$participants[$shipment['unique_identifier']]['labName'] = implode(' ', $fullNameParts);
		$participants[$shipment['unique_identifier']]['city'] = $shipment['city'];
		$participants[$shipment['unique_identifier']]['country'] = $shipment['country'];
		//$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float) ($shipment['shipment_score'] + $shipment['documentation_score']);
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'];
		$participants[$shipment['unique_identifier']]['attribs'] = json_decode($shipment['attributes'] ?? '', true);
		//$participants[$shipment['unique_identifier']][$shipment['shipment_code']]=$shipment['shipment_score'];

	}

	//Zend_Debug::dump($participants);die;

	foreach ($participants as $participantUID => $arrayVal) {
		foreach ($shipmentCodeArray as $shipmentType => $shipmentsList) {
			if (isset($arrayVal[$shipmentType])) {
				$certificate = true;
				$participated = true;

				foreach ($shipmentsList as $shipmentCode) {
					$assayName = "";
					if ($shipmentType == 'vl' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['vl_assay'])) {
						$assayName = $vlAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['vl_assay']];
					} elseif ($shipmentType == 'eid' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
						$assayName = $eidAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
					}

					if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] != 3) {
						if ($arrayVal[$shipmentType][$shipmentCode]['result'] != 1) {
							$certificate = false;
						}
					} else {
						//$participated = false;
						$certificate = false;
					}

					if (empty($arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
						$participated = false;
					}
				}


				$fields = [
					'participant_name' => $arrayVal['labName'],
					'participantname' => $arrayVal['labName'],
					'participant' => $arrayVal['labName'],
					'city' => $arrayVal['city'],
					'country' => $arrayVal['country'],
					'assay' => $assayName
				];

				$attribs = $arrayVal['attribs'];

				if ($shipmentType == 'vl') {
					if ($attribs["vl_assay"] == 6) {
						if (isset($attribs["other_assay"])) {
							$assay = $attribs["other_assay"];
						} else {
							$assay = "Other";
						}
					} else {
						$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
					}
					$fields['assay'] = $assay ?? '';
				}

				if ($certificate && $participated) {

					$outputFile = $excellenceCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName . ".pdf";

					generateCertificate($shipmentType, 'excellence', $fields, $outputFile);
				} elseif ($participated) {

					$outputFile = $participationCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName . ".pdf";

					generateCertificate($shipmentType, 'participation', $fields, $outputFile);
				}
				/* Send admin notification emails */
				sendNotification($customConfig->email->certificates ?? null, $shipmentsList ?? null);
			}
		}
	}
} catch (Exception $e) {
	error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
	error_log($e->getTraceAsString());
}
