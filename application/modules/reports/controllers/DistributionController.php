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
             $this->view->responseCount = $evalService->getResponseCount($id,$shipment[0]['distribution_id']);
            //$this->view->shipmentsUnderDistro = $evalService->getShipments($shipment[0]['distribution_id']);
            $this->view->shipmentsUnderDistro = $shipmentService->getShipmentInReports($shipment[0]['distribution_id']);
        }else{
            $this->_redirect("/reports/distribution/");
        }
    }

    public function generateReportsAction()
    {
        $this->_helper->layout()->disableLayout();
        if($this->_hasParam('sId')){
            ini_set('memory_limit', '-1');
            $id = (int)base64_decode($this->_getParam('sId'));
            $sLimit = (int)$this->_getParam('limitVal');
            $sOffset = (int)$this->_getParam('offsetVal');
             $startValue = (int)$this->_getParam('startVal');
            $endValue = (int)$this->_getParam('endVal');
            $this->view->bulkfileNameVal =$startValue.'-'.$endValue;
            $comingFrom = $this->_getParam('comingFrom');
            $reportService = new Application_Service_Reports();
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
            $evalService = new Application_Service_Evaluation();
            $this->view->result = $evalService->getEvaluateReportsInPdf($id,$sLimit,$sOffset);
            $commonService = new Application_Service_Common();
            $schemeService = new Application_Service_Schemes();
            $this->view->possibleDtsResults = $schemeService->getPossibleResults('dts');
            $this->view->passPercentage = $commonService->getConfig('pass_percentage');
            $this->view->comingFrom=$comingFrom;
            
                
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
            $this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
            $this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');
        
        }
    }

    public function generateSummaryReportsAction()
    {
        $this->_helper->layout()->disableLayout();
        if($this->_hasParam('sId')){
            $id = (int)base64_decode($this->_getParam('sId'));
            $comingFrom = $this->_getParam('comingFrom');
            $reportService = new Application_Service_Reports();
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
            $evalService = new Application_Service_Evaluation();
            $this->view->result= $evalService->getSummaryReportsInPdf($id);
            $this->view->responseResult= $evalService->getResponseReports($id);
            $this->view->participantPerformance = $reportService->getParticipantPerformanceReportByShipmentId($id);
            $this->view->correctiveness = $reportService->getCorrectiveActionReportByShipmentId($id);
            $this->view->comingFrom=$comingFrom;
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
            $this->view->responseCount = $evalService->getResponseCount($id,$shipment[0]['distribution_id']);
        }else{
            $this->_redirect("/reports/finalize/");
        }
    }
    
   

    
}





