<?php

class Reports_ParticipantPerformanceController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('participant-performance-export', 'html')
                    ->addActionContext('region-wise-participant-report', 'html')
                    ->addActionContext('participant-performance-export-pdf', 'html')
                    ->addActionContext('participant-performance-region-wise-export', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report'; 
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $response=$reportService->getParticipantPerformanceReport($params);
            $this->view->response = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if(isset($_COOKIE['did']) && $_COOKIE['did']!='' && $_COOKIE['did']!=null && $_COOKIE['did']!='NULL') {
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentDetails=$data=$shipmentService->getShipment($_COOKIE['did']);
           $this->view->schemeDetails=$scheme->getScheme($data["scheme_type"]);
        }
    }

    public function participantPerformanceExportAction()
    {
       $reportService = new Application_Service_Reports();
        if($this->getRequest()->isPost()){
            $params = $this->_getAllParams();
            $this->view->exported=$reportService->exportParticipantPerformanceReport($params);
        }
    }

    public function chartAction()
    {
        //if ($this->getRequest()->isPost()) {
        //    $params = $this->_getAllParams();
        //    $reportService = new Application_Service_Reports();
        //    $response=$reportService->getPerformancePieChart($params);
        //    $this->view->response = $response;
        //}
    }

    public function participantPerformanceExportPdfAction()
    {
       $reportService = new Application_Service_Reports();
        if($this->getRequest()->isPost()){
            $params = $this->_getAllParams();
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
            $this->view->result=$reportService->exportParticipantPerformanceReportInPdf();
            $this->view->dateRange=$params['dateRange'];
            $this->view->shipmentName=$params['shipmentName'];
        }
    }

    public function regionWiseParticipantReportAction()
    {
         if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $response=$reportService->getParticipantPerformanceRegionWiseReport($params);
            $this->view->response = $response;
        }
    }

    public function participantPerformanceRegionWiseExportAction()
    {
        $reportService = new Application_Service_Reports();
        if($this->getRequest()->isPost()){
            $params = $this->_getAllParams();
            $this->view->exported=$reportService->exportParticipantPerformanceRegionReport($params);
        }
    }


}







