<?php

class TbController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('assay-formats', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function responseAction()
    {
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();
        $tbModel = new Application_Model_Tb();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $shipmentService->updateTbResults($data);
            if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
                $this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
            } elseif (isset($data['confirmForm']) && trim($data['confirmForm']) == 'yes') {
                $this->redirect("/participant/current-schemes");
            } else {
                $_SESSION['confirmForm'] = "yes";
                $this->redirect("/tb/response/sid/" . $data['shipmentId'] . "/pid/" . $data['participantId'] . "/eid/" . $data['evId'] . "/uc/no");
            }
        } else {
            $sID = $request->getParam('sid');
            $pID = $request->getParam('pid');
            $eID = $request->getParam('eid');
            $uc = $request->getParam('uc');
            $reqFrom = $request->getParam('from');
            if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'tb', $uc);
                $this->_helper->layout()->setLayout('admin');
            }
            $participantService = new Application_Service_Participants();

            $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb', 'participant');
            $this->view->participant = $participantService->getParticipantDetails($pID);
            $shipment = $schemeService->getShipmentData($sID, $pID);
            $this->view->allNotTestedReason = $schemeService->getNotTestedReasons("tb");
            $this->view->allSamples = $tbModel->getTbSamplesForParticipant($sID, $pID);
            $shipment['attributes'] = json_decode($shipment['attributes'], true);
            $this->view->instruments = $participantService->getTbInstruments($shipment['map_id']);
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;
            $this->view->reqFrom = $reqFrom;

            $this->view->assay = $tbModel->getAllTbAssays();
            $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

            $commonService = new Application_Service_Common();
            $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
            $this->view->globalQcAccess = $commonService->getConfig('qc_access');
        }
    }

    public function assayFormatsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $sID = $request->getParam('sid');
        $pID = $request->getParam('pid');
        $eID = $request->getParam('eid');
        $assayId = $request->getParam('assayId');
        $type = $request->getParam('type');
        $assayType = $request->getParam('assayType');
        $assayDrug = $request->getParam('assayDrug');
        $reqFrom = $request->getParam('requestFrom');
        if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
            $evalService = new Application_Service_Evaluation();
            $this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'tb');
            $this->_helper->layout()->disableLayout();
        }
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $tbModel = new Application_Model_Tb();

        $participantService = new Application_Service_Participants();

        $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb', 'participant');
        $this->view->instruments = $participantService->getTbInstruments($pID);
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $tbModel->getTbSamplesForParticipant($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->instruments = $participantService->getTbInstruments($shipment['map_id']);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;
        $this->view->type = $type;
        $this->view->reqFrom = $reqFrom;
        $this->view->assayType = $assayType;
        $this->view->assayDrug = $assayDrug;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);
    }
}
