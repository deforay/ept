<?php

class ShipmentFormController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
$ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'shipmentForm';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            //SHIPMENT_CURRENT
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getAllShipmentForm($params);
        } else {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (!isset($authNameSpace->dm_id)) {
                $this->_helper->layout()->setLayout('home');
            }
        }
    }

    public function downloadAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('sId')) {
            $id = (int)base64_decode($this->_getParam('sId'));
            $reportService = new Application_Service_Reports();
            //$schemeService = new Application_Service_Schemes();
            //$this->view->referenceDetails = $schemeService->getDtsReferenceData($id);
            $this->view->header = $reportService->getReportConfigValue('report-header');
            $this->view->logo = $reportService->getReportConfigValue('logo');
            $this->view->logoRight = $reportService->getReportConfigValue('logo-right');
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipment = $shipment = $shipmentService->getShipmentRowData($id);
            // Zend_Debug::dump($shipment);
            // die;
            $configFile = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            $this->view->customConfig = new Zend_Config_Ini($configFile, APPLICATION_ENV);
        }
    }
}
