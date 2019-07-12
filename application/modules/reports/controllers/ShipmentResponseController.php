<?php

class Reports_ShipmentResponseController extends Zend_Controller_Action
{

    public function init(){
        /* Initialize action controller here */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('shipments-export-pdf', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report'; 
    }

    public function indexAction(){
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $response=$reportService->getShipmentResponseReport($params);
            $this->view->response = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function shipmentsExportPdfAction()
    {
       $reportService = new Application_Service_Reports();
        if($this->getRequest()->isPost()){
            $params = $this->_getAllParams();
            $this->view->dateRange=$params['dateRange'];
            $this->view->shipmentName=$params['shipmentName'];
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
            $this->view->result=$reportService->exportShipmentsReportInPdf($params);
        }
    }

}

