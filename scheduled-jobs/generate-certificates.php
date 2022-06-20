<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

$cliOptions = getopt("s:c:");
$shipmentsToGenerate = $cliOptions['s'];
$certificateName = (!empty($cliOptions['c']) ? $cliOptions['c'] : date('Y'));

if (is_array($shipmentsToGenerate)) {
	$shipmentsToGenerate = implode(",", $shipmentsToGenerate);
}


if (empty($shipmentsToGenerate)) {
	error_log("Please specify the shipment ids with the -s flag");
	exit();
}


use PhpOffice\PhpWord\TemplateProcessor;

$certificatePaths = array();
$folderPath = TEMP_UPLOAD_PATH . "/certificates/$certificateName";
$certificatePaths[] = $excellenceCertPath = $folderPath . "/excellence";
$certificatePaths[] = $participationCertPath = $folderPath . "/participation";

if (!file_exists($excellenceCertPath)) {
	mkdir($excellenceCertPath, 0777, true);
}
if (!file_exists($participationCertPath)) {
	mkdir($participationCertPath, 0777, true);
}




$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$common = new Application_Service_Common();
$participantsDb = new Application_Model_DbTable_Participants();
$dataManagerDb = new Application_Model_DbTable_DataManagers();
$schemesService = new Application_Service_Schemes();
$vlAssayArray = $schemesService->getVlAssay();
$generalModel = new Pt_Commons_General();


$libreOfficePath = (!empty($conf->libreoffice->path) ? $conf->libreoffice->path : "/usr/bin/libreoffice");


try {

	$db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);

	$output = array();

	$query = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
		->where("shipment_id IN (" . $shipmentsToGenerate . ")")
		->order("s.scheme_type");
	$shipmentResult = $db->fetchAll($query);

	$shipmentIDArray = array();
	foreach ($shipmentResult as $val) {
		$shipmentIdArray[] = $val['shipment_id'];
		$shipmentCodeArray[$val['scheme_type']][] = $val['shipment_code'];
		$impShipmentId = implode(",", $shipmentIdArray);
	}

	$sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.attributes', 'spm.shipment_test_report_date', 'spm.shipment_id', 'spm.participant_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.final_result'))
		->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('shipment_code', 'scheme_type', 'lastdate_response'))
		->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('unique_identifier', 'first_name', 'last_name', 'email', 'city', 'state', 'address', 'country', 'institute_name'))
		// ->where("spm.final_result = 1 OR spm.final_result = 2")
		// ->where("spm.is_excluded NOT LIKE 'yes'")
		->order("unique_identifier ASC")
		->order("scheme_type ASC");

	$sQuery->where('spm.shipment_id IN (' . $impShipmentId . ')');

	//Zend_Debug::dump($shipmentCodeArray);die;
	$shipmentParticipantResult = $db->fetchAll($sQuery);
	//Zend_Debug::dump($shipmentParticipantResult);die;
	$participants = array();

	foreach ($shipmentParticipantResult as $shipment) {

		//$assay = $vlAssayArray[$attribs]
		//Zend_Debug::dump($shipment);die;
		//echo count($participants);
		$participantName['first_name'] = utf8_encode($shipment['first_name']);
		$participantName['last_name'] = utf8_encode($shipment['last_name']);

		$participants[$shipment['unique_identifier']]['labName'] = implode(" ", $participantName);
		$participants[$shipment['unique_identifier']]['city'] = $shipment['city'];
		$participants[$shipment['unique_identifier']]['country'] = $shipment['country'];
		//$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score'] = (float) ($shipment['shipment_score'] + $shipment['documentation_score']);
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result'] = $shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['lastdate_response'] = $shipment['lastdate_response'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['shipment_test_report_date'] = $shipment['shipment_test_report_date'];
		$participants[$shipment['unique_identifier']]['attribs'] = json_decode($shipment['attributes'], true);
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
					} else if ($shipmentType == 'eid' && !empty($arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay'])) {
						$assayName = $eidAssayArray[$arrayVal[$shipmentType][$shipmentCode]['attributes']['extraction_assay']];
					}

					$firstSheetRow[] = $assayName;
					if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] != 3) {

						$firstSheetRow[] = $arrayVal[$shipmentType][$shipmentCode]['score'];

						if ($arrayVal[$shipmentType][$shipmentCode]['result'] != 1) {
							$certificate = false;
						}
					} else {
						if (!empty($arrayVal[$shipmentType][$shipmentCode]['result']) && $arrayVal[$shipmentType][$shipmentCode]['result'] == 3) {
							$firstSheetRow[] = 'Excluded';
						} else {
							$firstSheetRow[] = '-';
						}
						//$participated = false;
						$certificate = false;
					}

					if (empty($arrayVal[$shipmentType][$shipmentCode]['shipment_test_report_date'])) {
						$participated = false;
					}
				}

				if ($certificate && $participated) {
					$attribs = $arrayVal['attribs'];

					//Zend_Debug::dump($excellenceCertPath);die;
					//Zend_Debug::dump($participationCertPath);die;

					if ($shipmentType == 'dts') {
						if (!file_exists(__DIR__ . "/certificate-templates/dts-e.docx")) continue;
						$doc = new TemplateProcessor(__DIR__ . "/certificate-templates/dts-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("COUNTRY", $arrayVal['country']);
					} else if ($shipmentType == 'eid') {
						if (!file_exists(__DIR__ . "/certificate-templates/eid-e.docx")) continue;
						$doc = new TemplateProcessor(__DIR__ . "/certificate-templates/eid-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("COUNTRY", $arrayVal['country']);
					} else if ($shipmentType == 'vl') {
						if (!file_exists(__DIR__ . "/certificate-templates/vl-e.docx")) continue;
						if ($attribs["vl_assay"] == 6) {
							if (isset($attribs["other_assay"])) {
								$assay = $attribs["other_assay"];
							} else {
								$assay = "Other";
							}
						} else {
							$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
						}
						$doc = new TemplateProcessor(__DIR__ . "/certificate-templates/vl-e.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("COUNTRY", $arrayVal['country']);
						$doc->setValue("ASSAYNAME", $assay);
						//$doc->setValue("DATE","23 December 2018");
					}
					$doc->saveAs($excellenceCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName . ".docx");
				} else if ($participated) {

					$attribs = $arrayVal['attribs'];

					if ($shipmentType == 'dts') {
						if (!file_exists(__DIR__ . "/certificate-templates/dts-p.docx")) continue;
						$doc = new TemplateProcessor(__DIR__ . "/certificate-templates/dts-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("COUNTRY", $arrayVal['country']);
					} else if ($shipmentType == 'eid') {
						if (!file_exists(__DIR__ . "/certificate-templates/eid-p.docx")) continue;
						$doc = new TemplateProcessor(__DIR__ . "/certificate-templates/eid-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("COUNTRY", $arrayVal['country']);
						//$doc->setValue("DATE","09 January 2018");

					} else if ($shipmentType == 'vl') {
						if (!file_exists(__DIR__ . "/certificate-templates/vl-p.docx")) continue;
						if ($attribs["vl_assay"] == 6) {
							if (isset($attribs["other_assay"])) {
								$assay = $attribs["other_assay"];
							} else {
								$assay = "Other";
							}
						} else {
							$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";
						}

						$doc = new TemplateProcessor(__DIR__ . "/certificate-templates/vl-p.docx");
						$doc->setValue("LABNAME", $arrayVal['labName']);
						$doc->setValue("CITY", $arrayVal['city']);
						$doc->setValue("COUNTRY", $arrayVal['country']);
						$doc->setValue("ASSAYNAME", $assay);
					}
					$doc->saveAs($participationCertPath . DIRECTORY_SEPARATOR . str_replace('/', '_', $participantUID) . "-" . strtoupper($shipmentType) . "-" . $certificateName . ".docx");
				}
				/* Send admin notification about certificate generated */
				$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
				$config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
				$sec = APPLICATION_ENV;
				if (isset($config->$sec->jobCompletionAlert->status) && $config->$sec->jobCompletionAlert->status == "yes") {
					if (isset($config->$sec->jobCompletionAlert->mails) && !empty($config->$sec->jobCompletionAlert->mails)) {
						$common = new Application_Service_Common();
						$appConf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
						$mails = explode(",", $config->$sec->jobCompletionAlert->mails);
						if (isset($mails) && count($mails) > 0) {
							foreach ($mails as $mail) {
								// Params to send (to, cc, ,bcc, subj, msg, frommail, fromname);
								$common->insertTempMail($mail, null, null, "ePT | Certificate generated reminder mail", "Certificate for Shipment " . $shipmentsList . " are generated.", "example@example.com", "e-PT");
							}
						}
					}
				}
			}
		}
	}
	if (!empty($certificatePaths) && is_executable($libreOfficePath)) {
		$certificatePaths = array_unique($certificatePaths);
		//Zend_Debug::dump($certificatePaths);
		foreach ($certificatePaths as $certPath) {
			//echo ("cd $certPath && /usr/bin/libreoffice --headless --convert-to pdf *.docx --outdir ./ >/dev/null 2>&1 &" . PHP_EOL);
			$files = $generalModel->recuriveSearch($certPath, "*.docx");
			if (!empty($files)) {
				foreach ($files as $f) {
					$fileName = basename($f);
					exec("/usr/bin/libreoffice --headless --convert-to pdf $f --outdir $certPath");
				}
			}
		}
	}
} catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/generate-certificates.php');
}
