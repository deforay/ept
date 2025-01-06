<?php

class VlController extends Zend_Controller_Action
{

	public function init() {}

	public function indexAction()
	{
		// action body
	}

	public function responseAction()
	{

		$vlModel       = new Application_Model_Vl();
		$schemeService = new Application_Service_Schemes();
		$shipmentService = new Application_Service_Shipments();

		$this->view->vlAssay = $schemeService->getVlAssay(false);
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();

		if ($request->isPost()) {

			$data = $request->getPost();
			$data['uploadedFilePath'] = "";
			if ((!empty($_FILES["uploadedFile"])) && ($_FILES['uploadedFile']['error'] == 0)) {
				$schemeCode = preg_replace('/[^a-zA-Z0-9-_]/', '', $data['schemeCode']);
				$participantId = preg_replace('/[^a-zA-Z0-9-_]/', '', $data['participantId']);
				$filename = basename($_FILES['uploadedFile']['name']);
				$ext = substr($filename, strrpos($filename, '.') + 1);
				if ($_FILES["uploadedFile"]["size"] < 5000000) {
					$dirpath = "dts-viral-load" . DIRECTORY_SEPARATOR . $schemeCode . DIRECTORY_SEPARATOR . $participantId;
					$uploadFolder = realpath(UPLOAD_PATH);
					$uploadDir = $uploadFolder . DIRECTORY_SEPARATOR . $dirpath;
					if (!is_dir($uploadDir)) {
						mkdir($uploadDir);
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

			$shipmentService->updateVlResults($data);

			if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
				$this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
			} elseif (isset($data['comingFrom']) && trim($data['comingFrom']) != '') {
				$this->redirect("/participant/" . $data['comingFrom']);
			} else {
				$this->redirect("/participant/current-schemes");
			}

			//die;
		} else {
			$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
			$sID = $request->getParam('sid');
			$pID = $request->getParam('pid');
			$eID = $request->getParam('eid');
			$uc = $request->getParam('uc');
			$reqFrom = $request->getParam('from');
			if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
				$evalService = new Application_Service_Evaluation();
				$this->view->vlRange = $vlModel->getVlRange($sID);
				$this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'vl', $uc);
				$this->_helper->layout()->setLayout('admin');
			}
			$common = new Application_Service_Common();
			$this->view->invalidVlResult = $common->checkAssayInvalid($sID, $pID, true);
			$this->view->comingFrom = $request->getParam('comingFrom');
			$participantService = new Application_Service_Participants();
			$this->view->participant = $participantService->getParticipantDetails($pID);
			//Zend_Debug::dump($schemeService->getVlSamples($sID,$pID));
			$this->view->allSamples = $schemeService->getVlSamples($sID, $pID);
			$this->view->allNotTestedReason = $schemeService->getNotTestedReasons("vl");
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
			$this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
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
		$this->view->referenceDetails = $schemeService->getVlReferenceData($sID);
		$this->view->allNotTestedReason = $schemeService->getNotTestedReasons("vl");
		$shipment = $schemeService->getShipmentData($sID, $pID);
		$common = new Application_Service_Common();
		$this->view->invalidVlResult = $common->checkAssayInvalid();
		$shipment['attributes'] = json_decode($shipment['attributes'], true);
		$this->view->shipment = $shipment;
	}

	public function deleteAction() {
		/** Need to do this function later */
	}
}
