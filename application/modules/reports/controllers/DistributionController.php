<?php

class Reports_DistributionController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('get-shipments', 'html')
                    ->addActionContext('generate-reports', 'html')
                    ->addActionContext('generate-summary-reports', 'html')
                    ->initContext();        
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $distributionService = new Application_Service_Distribution();
            $distributionService->getAllDistributionReports($params);
        }
    }

    public function getShipmentsAction()
    {
        if($this->_hasParam('did')){            
            $id = (int)($this->_getParam('did'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipments = $shipmentService->getShipmentInReports($id);            
        }else{
            $this->view->shipments = false;
        }
    }

    public function shipmentAction()
    {
        $shipmentService = new Application_Service_Shipments();
        if($this->_hasParam('sid')){            
            $id = (int)base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluateReports($id,$reEvaluate);
            //$this->view->shipmentsUnderDistro = $evalService->getShipments($shipment[0]['distribution_id']);
            $this->view->shipmentsUnderDistro = $shipmentService->getShipmentInReports($shipment[0]['distribution_id']);
        }else{
            $this->_redirect("/report/distribution/");
        }
    }

    public function generateReportsAction()
    {
        $this->_helper->layout()->disableLayout();
        if($this->_hasParam('sId')){
           
            $id = (int)base64_decode($this->_getParam('sId'));
            $reportService = new Application_Service_Reports();
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $evalService = new Application_Service_Evaluation();
            $this->view->result = $evalService->getEvaluateReportsInPdf($id);
            $commonService = new Application_Service_Common();
            $this->view->passPercentage = $commonService->getConfig('pass_percentage');
            
        }
    }

    public function generateSummaryReportsAction()
    {
        $this->_helper->layout()->disableLayout();
        if($this->_hasParam('sId')){
            $id = (int)base64_decode($this->_getParam('sId'));
            $reportService = new Application_Service_Reports();
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $evalService = new Application_Service_Evaluation();
            $this->view->result = $evalService->getSummaryReportsInPdf($id);
            $this->view->participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($id);
            $this->view->correctiveness = $reportService->getCorrectiveActionReportByShipmentId($id);
        }
    }

    public function finalizeAction()
    {
        $shipmentService = new Application_Service_Shipments();
         if($this->_hasParam('sid')){            
            $id = (int)base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluateReports($id,$reEvaluate);
            $this->view->shipmentsUnderDistro = $shipmentService->getShipmentInReports($shipment[0]['distribution_id']);
        }else{
            $this->_redirect("/report/finalize/");
        }
    }


}





