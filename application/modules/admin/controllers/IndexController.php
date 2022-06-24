<?php

class Admin_IndexController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->pageName = 'dashboard';
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-scheme-participants', 'html')
            ->addActionContext('load-charts', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        $distributionService = new Application_Service_Distribution();
        $shipmentService = new Application_Service_Shipments();
        $scheme = new Application_Service_Schemes();
        $clientsServices = new Application_Service_Participants();

        $this->view->ptchart = $shipmentService->getShipmentListBasedOnScheme();
        $this->view->events = $distributionService->getAllDistributionStatus();
        $this->view->schemeCountResult = $scheme->countEnrollmentSchemes();
        $this->view->shipmentCountResult = $shipmentService->getParticipantCountBasedOnShipment();
        $this->view->pendingParticipants = $clientsServices->getPendingParticipants();

        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function getSchemeParticipantsAction()
    {
        if ($this->hasParam('schemeType')) {
            $schemeType = $this->_getParam('schemeType');
            $participantService = new Application_Service_Participants();
            $this->view->participants = $participantService->getSchemeWiseParticipants($schemeType);
        }
    }

    public function loadChartsAction()
    {
        $shipmentService = new Application_Service_Shipments();
        $scheme = new Application_Service_Schemes();
        if ($this->getRequest()->isPost()) {
            $this->view->type = $this->getParam('type');
        }
        $this->view->ptchart = $shipmentService->getShipmentListBasedOnScheme();
        $this->view->schemeCountResult = $scheme->countEnrollmentSchemes();
    }
}
