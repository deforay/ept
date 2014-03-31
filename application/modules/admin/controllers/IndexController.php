<?php

class Admin_IndexController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->pageName = 'dashboard';
    }

    public function indexAction()
    {
       $distributionService = new Application_Service_Distribution();
       $shipmentService = new Application_Service_Shipments();
       $participantService = new Application_Service_Participants();
       
       $this->view->events=$distributionService->getAllDistributionStatus();
       $this->view->schemeCountResult=$shipmentService->getParticipantCountBasedOnScheme();
       $this->view->shipmentCountResult=$shipmentService->getParticipantCountBasedOnShipment();
       $this->view->participants=$participantService->getAllActiveParticipants();
       
    }

    
}

