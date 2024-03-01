<?php

class Reports_ParticipantTrendsController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /* Initialize action controller here */
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('participant-trends-export', 'html')
            ->addActionContext('region-wise-trends-report', 'html')
            ->addActionContext('participant-trends-export-pdf', 'html')
            ->addActionContext('participant-trends-region-wise-export', 'html')
            ->addActionContext('participant-trends-timeliness-barchart', 'html')
            ->addActionContext('aberrant-test-results', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantTrendsReport($params);
            $this->view->response = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if (isset($_COOKIE['did']) && $_COOKIE['did'] != '' && $_COOKIE['did'] != null && $_COOKIE['did'] != 'NULL') {
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentDetails = $data = $shipmentService->getShipment($_COOKIE['did']);
            $schemeType = $data["scheme_type"] ?? null;
            $this->view->schemeDetails = $scheme->getScheme($schemeType);
        }
    }

    public function participantTrendsExportAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportParticipantTrendsReport($params);
        }
    }

    public function chartAction()
    {
        //if ($this->getRequest()->isPost()) {
        //    $params = $this->getAllParams();
        //    $reportService = new Application_Service_Reports();
        //    $response=$reportService->getPerformancePieChart($params);
        //    $this->view->response = $response;
        //}
    }

    public function participantTrendsExportPdfAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $this->view->header = $reportService->getReportConfigValue('report-header');
            $this->view->logo = $reportService->getReportConfigValue('logo');
            $this->view->logoRight = $reportService->getReportConfigValue('logo-right');
            $this->view->result = $reportService->exportParticipantTrendsReportInPdf();
            $this->view->dateRange = $params['dateRange'];
            $this->view->shipmentName = $params['shipmentName'];
        }
    }

    public function regionWiseParticipantReportAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantTrendsRegionWiseReport($params);
            $this->view->response = $response;
        }
    }

    public function participantTrendsRegionWiseExportAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportParticipantTrendsRegionReport($params);
        }
    }
    
    public function participantTrendsTimelinessBarchartAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getChartInfo($params);
        }
    }
    
    public function aberrantTestResultsAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getAberrantChartInfo($params);
        }
    }
}
