<?php

class TbController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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

        /** @var $request Zend_Controller_Request_Http */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $shipmentService->updateTbResults($data);
            if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
                $this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
            } else {
                $this->redirect("/participant/current-schemes");
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

            $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb');
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
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');
        $assayId = $this->getRequest()->getParam('assayId');
        $type = $this->getRequest()->getParam('type');
        $assayType = $this->getRequest()->getParam('assayType');
        $assayDrug = $this->getRequest()->getParam('assayDrug');
        $reqFrom = $this->getRequest()->getParam('requestFrom');
        if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
            $evalService = new Application_Service_Evaluation();
            $this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'tb');
            $this->_helper->layout()->disableLayout();
        }
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $tbModel = new Application_Model_Tb();

        $participantService = new Application_Service_Participants();
        $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb');
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
