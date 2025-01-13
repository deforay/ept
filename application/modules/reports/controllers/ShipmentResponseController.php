<?php

class Reports_ShipmentResponseController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('shipments-export-pdf', 'html')
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
            $response = $reportService->getShipmentResponseReport($params);
            $this->view->response = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function shipmentsExportPdfAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $reportService = new Application_Service_Reports();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->dateRange = $params['dateRange'];
            $this->view->shipmentName = $params['shipmentName'];
            $this->view->header = $reportService->getReportConfigValue('report-header');
            $this->view->logo = $reportService->getReportConfigValue('logo');
            $this->view->logoRight = $reportService->getReportConfigValue('logo-right');
            $this->view->result = $reportService->exportShipmentsReportInPdf();
        }
    }
}
