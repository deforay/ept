<?php

class Reports_ParticipantPerformanceController extends Zend_Controller_Action
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
            ->addActionContext('participant-performance', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if (isset($_COOKIE['did']) && $_COOKIE['did'] != '' && $_COOKIE['did'] != null && $_COOKIE['did'] != 'NULL') {
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentDetails = $data = $shipmentService->getShipment($_COOKIE['did']);
            $schemeType = $data["scheme_type"] ?? null;
            $this->view->schemeDetails = $scheme->getScheme($schemeType);
        }
    }

    public function participantPerformanceAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getParticipantShipmentPerformanceReport($params);
        }
    }
}
