<?php

class Reports_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
       $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('chart', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report'; 
    }

    public function indexAction()
    {
        $participantService = new Application_Service_Participants();
            $this->view->networkTierList = $participantService->getNetworkTierList();
            $this->view->affiliateList = $participantService->getAffiliateList();
            $this->view->regionList = $participantService->getAllParticipantRegion();
    }

    public function chartAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $shipmentService = new Application_Service_Reports();
            $response=$shipmentService->getTestKitReport($params);
            $this->view->response = $response;
        }
    }


}



