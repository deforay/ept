<?php

class DtsController extends Zend_Controller_Action
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
		$dtsModel = new Application_Model_Dts();
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if ($request->isPost()) {
			$data = $request->getPost();
			$shipmentService->updateDtsResults($data);
			if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
				$this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
			} elseif (isset($data['comingFrom']) && trim($data['comingFrom']) != '') {
				$this->redirect("/participant/" . $data['comingFrom']);
			} elseif (isset($data['confirmForm']) && trim($data['confirmForm']) == 'yes') {
				$this->redirect("/participant/current-schemes");
			} else {
				$_SESSION['confirmForm'] = "yes";
				$this->redirect("/dts/response/sid/" . $data['shipmentId'] . "/pid/" . $data['participantId'] . "/eid/" . $data['evId'] . "/uc/no");
			}
		} else {
			$sID = $request->getParam('sid');
			$pID = $request->getParam('pid');
			$eID = $request->getParam('eid');
			$uc = $request->getParam('uc');
			// To get where from access happen
			$this->view->comingFrom = $request->getParam('comingFrom');
			$reqFrom = $request->getParam('from');
			if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
				$evalService = new Application_Service_Evaluation();
				$this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'dts', $uc);
				$this->_helper->layout()->setLayout('admin');
			} else {
				if (isset($pID) && !empty($pID)) {
					$access = $shipmentService->checkParticipantAccess($pID);
					if ($access === false)
						$this->redirect("/participant/current-schemes");
				}
			}

			$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
			$this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

			$participantService = new Application_Service_Participants();
			$this->view->participant = $participantService->getParticipantDetails($pID);
			$response = $dtsModel->getDtsSamples($sID, $pID);
			$this->view->allSamples = $response;
			$this->view->allNotTestedReason = $schemeService->getNotTestedReasons("dts");

			$shipment = $schemeService->getShipmentData($sID, $pID);
			$shipment['attributes'] = ($shipment['attributes']) ? json_decode($shipment['attributes'], true) : null;
			$this->view->shipment = $shipment;

			//Zend_Debug::dump($this->view->shipment);
			$this->view->allTestKits = $dtsModel->getAllDtsTestKitList();
			$this->view->dtsPossibleResults = $schemeService->getPossibleResults('dts', 'participant');
			$shipmentAttributes = isset($shipment['shipment_attributes']) ? json_decode($shipment['shipment_attributes'], true) : array();
			if (isset($shipmentAttributes['enableRtri']) && $shipmentAttributes['enableRtri'] == 'yes') {
				$this->view->recencyPossibleResults = $schemeService->getPossibleResults('recency', 'participant');
			}

			$this->view->shipId = $sID;
			$this->view->participantId = $pID;
			$this->view->eID = $eID;
			$this->view->reqFrom = $reqFrom;
			//
			$this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

			$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
			$this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
			$this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
			$this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');

			$commonService = new Application_Service_Common();
			$this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
			$this->view->testkitList = $commonService->getMappedTestKits($pID, $sID);
			$this->view->globalQcAccess = $commonService->getConfig('qc_access');
		}
	}

	public function deleteAction() {}

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


		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);


		$participantService = new Application_Service_Participants();
		$this->view->participant = $participantService->getParticipantDetails($pID);
		$schemeService = new Application_Service_Schemes();
		$this->view->referenceDetails = $schemeService->getDtsReferenceData($sID);

		$shipment = $schemeService->getShipmentData($sID, $pID);
		$shipment['attributes'] = json_decode($shipment['attributes'], true);
		$this->view->shipment = $shipment;
	}
}
