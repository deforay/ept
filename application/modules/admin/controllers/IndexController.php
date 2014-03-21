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
       $this->view->events=$distributionService->getAllDistributionStatus();
       $this->view->shipmentScheme=$shipmentService->getShipmentsBasedOnScheme();
    }


}

