<?php

class Reports_ShipmentsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('get-shipment-participant-list', 'html')
                    ->addActionContext('shipments-export', 'html')
                    ->addActionContext('vl-sample-analysis', 'html')
                    ->addActionContext('vl-sample-analysis-result', 'html')
                    ->addActionContext('vl-assay-distribution', 'html')
                    ->addActionContext('vl-participant-count', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report';                
    }

    public function indexAction()
    {
        
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $reportService = new Application_Service_Reports();
            $reportService->getAllShipments($params);
        }
        
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        
        $dataManagerService = new Application_Service_DataManagers();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        
    }

    public function responseChartAction()
    {
        if($this->_hasParam('id')){
            //Zend_Debug::dump(base64_decode($this->_getParam('shipmentCode')));die;
               $shipmentId = (int) base64_decode($this->_getParam('id'));
               $reportService = new Application_Service_Reports();
               $this->view->responseCount = $reportService->getShipmentResponseCount($shipmentId,base64_decode($this->_getParam('shipmentDate')));
               $this->view->shipmentDate= base64_decode($this->_getParam('shipmentDate'));
               $this->view->shipmentCode= base64_decode($this->_getParam('shipmentCode'));
        }else{
            $this->_redirect("/admin/index");
        }
        
    }

    public function getShipmentParticipantListAction()
    {
        $reportService = new Application_Service_Reports();
        if($this->_hasParam('shipmentId')){
            $shipmentId = base64_decode($this->_getParam('shipmentId'));
            $schemeType = ($this->_getParam('schemeType'));
            $this->view->result=$reportService->getShipmentParticipant($shipmentId,$schemeType);
        }
        
    }

    public function shipmentsExportAction()
    {
        $reportService = new Application_Service_Reports();
        if($this->getRequest()->isPost()){
            $params = $this->_getAllParams();
            $this->view->exported=$reportService->exportShipmentsReport($params);
        }
    }
    
    public function vlSampleAnalysisAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllVlAssayDistributionReports($params);
        }
    }
    public function vlSampleAnalysisResultAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
           $this->view->vlSampleResult= $reportService->getAllVlSampleResult($params);
        }
    }
    
     public function vlAssayDistributionAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllVlAssayDistributionReports($params);
        }
    }
    public function vlParticipantCountAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
           $this->view->vlAssayCount= $reportService->getAllVlAssayParticipantCount($params);
        }
    }

}





