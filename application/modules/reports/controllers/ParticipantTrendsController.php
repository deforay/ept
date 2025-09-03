<?php

class Reports_ParticipantTrendsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /* Initialize action controller here */
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantTrendsReport($params);
            $labPerformanceReport = $reportService->getLabPerformanceReportWithScore($params);
           // echo '<pre>';print_r($labPerformanceReport); die;
            $this->view->labPerformanceReport = $labPerformanceReport;
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportParticipantTrendsReport($params);
        }
    }

    public function chartAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        //if ($request->isPost()) {
        //    $params = $this->getAllParams();
        //    $reportService = new Application_Service_Reports();
        //    $response=$reportService->getPerformancePieChart($params);
        //    $this->view->response = $response;
        //}
    }

    public function participantTrendsExportPdfAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantTrendsRegionWiseReport($params);
            $this->view->response = $response;
        }
    }

    public function participantTrendsRegionWiseExportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportParticipantTrendsRegionReport($params);
        }
    }

    public function participantTrendsTimelinessBarchartAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getChartInfo($params);
        }
    }

    public function aberrantTestResultsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getAberrantChartInfo($params);
        }
    }

    public function participantLabPerformanceReportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->_helper->layout()->disableLayout();
            $this->view->result = $reportService->getLabPerformanceReportWithScore($params);
        }
    }

    public function exportLabPerformanceReportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->result = $reportService->exportLabPerformanceReportDetails($params);
        } else {
            return false;
        }
    }
}
