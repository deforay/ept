<?php

class TbController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
$ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('assay-formats', 'html')
            ->addActionContext('download', 'html')
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
            $this->redirect("/participant/current-schemes");
        } else {
            $sID = $request->getParam('sid');
            $pID = $request->getParam('pid');
            $eID = $request->getParam('eid');

            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            $shipment = $schemeService->getShipmentData($sID, $pID);
            $this->view->allNotTestedReason = $schemeService->getNotTestedReasons("tb");
            $shipment['attributes'] = json_decode($shipment['attributes'], true);
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;

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

        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();

        $tbModel = new Application_Model_Tb();

        $participantService = new Application_Service_Participants();
        $this->view->participant = $participantService->getParticipantDetails($pID);
        $this->view->allSamples = $tbModel->getTbSamplesForParticipant($sID, $pID);
        $shipment = $schemeService->getShipmentData($sID, $pID);
        $shipment['attributes'] = json_decode($shipment['attributes'], true);
        $this->view->shipment = $shipment;
        $this->view->shipId = $sID;
        $this->view->participantId = $pID;
        $this->view->eID = $eID;
        $this->view->type = $type;
        $this->view->assayType = $assayType;
        $this->view->assayDrug = $assayDrug;
        
        $this->view->isEditable = $shipmentService->isShipmentEditable($sID, $pID);
    }

    public function downloadAction()
    {
        $this->_helper->layout()->disableLayout();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $sID = $request->getPost('sid');
            $pID = $request->getPost('pid');
    
            $reportService = new Application_Service_Reports();
            $tbModel = new Application_Model_Tb();
            $participantService = new Application_Service_Participants();
            $schemeService = new Application_Service_Schemes();
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    
            $this->view->header = $reportService->getReportConfigValue('report-header');
            $this->view->logo = $reportService->getReportConfigValue('logo');
            $this->view->logoRight = $reportService->getReportConfigValue('logo-right');
            $this->view->allSamples = $tbModel->getTbSamplesForParticipant($sID, $pID);
            $this->view->participant = $participantService->getParticipantDetails($pID);
            
            $shipment = $schemeService->getShipmentData($sID, $pID);
            $shipment['attributes'] = json_decode($shipment['attributes'], true);
            $this->view->shipment = $shipment;
        }
    }
}
