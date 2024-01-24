<?php

class Reports_ShipmentsController extends Zend_Controller_Action
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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
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
        if ($this->hasParam('id')) {
            //Zend_Debug::dump(base64_decode($this->_getParam('shipmentCode')));die;
            $shipmentId = (int) base64_decode($this->_getParam('id'));
            $reportService = new Application_Service_Reports();
            $this->view->responseCount = $reportService->getShipmentResponseCount($shipmentId, base64_decode($this->_getParam('shipmentDate')));
            $this->view->shipmentDate = base64_decode($this->_getParam('shipmentDate'));
            $this->view->shipmentCode = base64_decode($this->_getParam('shipmentCode'));
        } else {
            $this->redirect("/admin/index");
        }
    }

    public function getShipmentParticipantListAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->hasParam('shipmentId')) {
            $shipmentId = base64_decode($this->_getParam('shipmentId'));
            $schemeType = ($this->_getParam('schemeType'));
            $this->view->result = $reportService->getShipmentParticipant($shipmentId, $schemeType);
        }
    }

    public function shipmentsExportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportShipmentsReport($params);
        }
    }

    public function vlSampleAnalysisAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllVlAssayDistributionReports($params);
        }
    }
    public function vlSampleAnalysisResultAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->vlSampleResult = $reportService->getAllVlSampleResult($params);
        }
    }

    public function vlAssayDistributionAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllVlAssayDistributionReports($params);
        }
    }
    public function vlParticipantCountAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->vlAssayCount = $reportService->getAllVlAssayParticipantCount($params);
        }
    }
}
