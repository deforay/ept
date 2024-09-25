<?php

class Reports_CommonController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-shipments-by-scheme', 'html')
            ->addActionContext('get-shipments-by-date', 'html')
            ->addActionContext('get-options-by-value', 'html')
            ->addActionContext('get-finalised-shipments-by-scheme', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function getShipmentsBySchemeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentsByScheme($schemeType, $startDate, $endDate);
            $this->view->shipmentList = $response;
        }
    }

    public function getShipmentsByDateAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $notFinalized = $this->_getParam('notFinalized');
            $distributionId = base64_decode($this->_getParam('distributionId'));
            $reportService = new Application_Service_Reports();
            $shipment = new Application_Service_Shipments();
            $this->view->shipmentDetails = $shipment->getShipmentByDistributionId($distributionId);
            $notFinalized = (bool)($notFinalized == 'false') ? false : true;
            $response = $reportService->getShipmentsByDate($schemeType, $startDate, $endDate, $notFinalized);
            $this->view->shipmentList = $response;
        }
    }

    public function getOptionsByValueAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $commonService = new Application_Service_Common();
            $params = $this->getAllParams();
            $this->view->result = $commonService->getOptionsByValue($params);
            $this->view->params = $params;
        }
    }

    public function getFinalisedShipmentsBySchemeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $schemeType = $this->_getParam('schemeType');
            $startDate = $this->_getParam('startDate');
            $endDate = $this->_getParam('endDate');
            $reportService = new Application_Service_Reports();
            $response = $reportService->getFinalisedShipmentsByScheme($schemeType, $startDate, $endDate);
            $this->view->shipmentList = $response;
        }
    }

    public function validatePasswordAction()
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $commonService = new Application_Service_Common();
            $password = $this->_getParam('password');
            $name = $this->_getParam('name') ?? null;
            $email = $this->_getParam('email') ?? null;
            $length = $commonService->getConfig('participant_login_password_length');
            $passwordCheck = $commonService->validatePassword($password, $name, $email, $length);
            $this->view->result = $passwordCheck;
        }
    }
}
