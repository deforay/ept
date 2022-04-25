<?php

class TbController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('tcmt', 'html')
            ->addActionContext('xpert-mtb-rif', 'html')
            ->addActionContext('xpert-mtb-rif-ultra', 'html')
            ->addActionContext('ref-xpert-xdr', 'html')
            ->addActionContext('molbio-truenat-tb', 'html')
            ->addActionContext('molbio-truenat-plus', 'html')
            ->addActionContext('ref-molbio-tb-rif-dx', 'html')
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

        if ($this->getRequest()->isPost()) {

            $data = $this->getRequest()->getPost();

            //Zend_Debug::dump($data);die;

            $shipmentService->updateTbResults($data);
            $this->redirect("/participant/dashboard");

            //die;            
        } else {
            $sID = $this->getRequest()->getParam('sid');
            $pID = $this->getRequest()->getParam('pid');
            $eID = $this->getRequest()->getParam('eid');

            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
            $shipment = $schemeService->getShipmentData($sID, $pID);
            $shipment['attributes'] = json_decode($shipment['attributes'], true);
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;

            $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

            $commonService = new Application_Service_Common();
            $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
            $this->view->globalQcAccess = $commonService->getConfig('qc_access');
        }
    }

    public function tcmtAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
    public function xpertMtbRifAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
    public function xpertMtbRifUltraAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
    public function refXpertXdrAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
    public function molbioTruenatTbAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
    public function molbioTruenatPlusAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
    public function refMolbioTbRifDxAction()
    {
        $sID = $this->getRequest()->getParam('sid');
        $pID = $this->getRequest()->getParam('pid');
        $eID = $this->getRequest()->getParam('eid');

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $schemeService->getTbSamples($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;

        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);

        $commonService = new Application_Service_Common();
        $this->view->modeOfReceipt = $commonService->getAllModeOfReceipt();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }
}
