<?php

class EidController extends Zend_Controller_Action
{

	public function init() {}

	public function indexAction()
	{
		// action body
	}

	public function responseAction()
	{

		$schemeService = new Application_Service_Schemes();
		$shipmentService = new Application_Service_Shipments();

		$this->view->extractionAssay = $schemeService->getEidExtractionAssay();
		$this->view->detectionAssay = $schemeService->getEidDetectionAssay();
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if ($request->isPost()) {
			$data = $request->getPost();
			$data['uploadedFilePath'] = "";

			if ((!empty($_FILES["uploadedFile"])) && ($_FILES['uploadedFile']['error'] == 0)) {
				$schemeCode = preg_replace('/[^a-zA-Z0-9-_]/', '', $data['schemeCode']);
				$participantId = preg_replace('/[^a-zA-Z0-9-_]/', '', $data['participantId']);
				$filename = basename($_FILES['uploadedFile']['name']);

				if ($_FILES["uploadedFile"]["size"] < 5000000) {
					$dirpath = "dts-early-infant-diagnosis" . DIRECTORY_SEPARATOR . $schemeCode . DIRECTORY_SEPARATOR . $participantId;
					$uploadFolder = realpath(UPLOAD_PATH);
					$uploadDir = $uploadFolder . DIRECTORY_SEPARATOR . $dirpath;
					if (!is_dir($uploadDir)) {
						mkdir($uploadDir, 0777, true);
					}

					// Let us clear the folder before uploading the file
					$files = glob($uploadDir . '/*{,.}*', GLOB_BRACE); // get all file names
					foreach ($files as $file) { // iterate files
						if (is_file($file)) {
							unlink($file); // delete file
						}
					}

					//Determine the path to which we want to save this file
					$data['uploadedFilePath'] = $dirpath . DIRECTORY_SEPARATOR . $filename;
					$newname = $uploadDir . DIRECTORY_SEPARATOR . $filename;

					move_uploaded_file($_FILES['uploadedFile']['tmp_name'], $newname);
				}
			}


			$shipmentService->updateEidResults($data);
			if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
				$this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
			} elseif (isset($data['confirmForm']) && trim($data['confirmForm']) == 'yes') {
				$this->redirect("/participant/current-schemes");
			} else {
				$_SESSION['confirmForm'] = "yes";
				$this->redirect("/eid/response/sid/" . $data['shipmentId'] . "/pid/" . $data['participantId'] . "/eid/" . $data['evId'] . "/uc/no");
			}
		} else {
			$sID = $request->getParam('sid');
			$pID = $request->getParam('pid');
			$eID = $request->getParam('eid');
			$uc = $request->getParam('uc');
			$reqFrom = $request->getParam('from');
			if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
				$evalService = new Application_Service_Evaluation();
				$this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'eid', $uc);
				$this->_helper->layout()->setLayout('admin');
			}
			$participantService = new Application_Service_Participants();
			$this->view->participant = $participantService->getParticipantDetails($pID);

			$this->view->eidPossibleResults = $schemeService->getPossibleResults('eid', 'participant');

			$this->view->allSamples = $schemeService->getEidSamples($sID, $pID);
			$this->view->allNotTestedReason = $schemeService->getNotTestedReasons("eid");
			$shipment = $schemeService->getShipmentData($sID, $pID);
			$shipment['attributes'] = json_decode($shipment['attributes'], true);
			$this->view->shipment = $shipment;
			$this->view->shipId = $sID;
			$this->view->participantId = $pID;
			$this->view->eID = $eID;
			$this->view->reqFrom = $reqFrom;
			$this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

			$commonService = new Application_Service_Common();
			$this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
			$this->view->globalQcAccess = $commonService->getConfig('qc_access');
		}
	}

	public function downloadAction()
	{
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		$this->_helper->layout()->disableLayout();
		$sID = $request->getParam('sid');
		$pID = $request->getParam('pid');

		$reportService = new Application_Service_Reports();
		$this->view->header = $reportService->getReportConfigValue('report-header');
		$this->view->logo = $reportService->getReportConfigValue('logo');
		$this->view->logoRight = $reportService->getReportConfigValue('logo-right');

		$participantService = new Application_Service_Participants();
		$this->view->participant = $participantService->getParticipantDetails($pID);
		$schemeService = new Application_Service_Schemes();
		$this->view->referenceDetails = $schemeService->getEidReferenceData($sID);

		$shipment = $schemeService->getShipmentData($sID, $pID);
		$shipment['attributes'] = json_decode($shipment['attributes'], true);
		$this->view->shipment = $shipment;
	}

	public function deleteAction() {}
}
