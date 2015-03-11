<?php

class Reports_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
       $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('chart', 'html')
                    ->addActionContext('participant', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report'; 
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getTestKitDetailedReport($params);
        }
        $participantService = new Application_Service_Participants();
            $this->view->networkTierList = $participantService->getNetworkTierList();
            $this->view->affiliateList = $participantService->getAffiliateList();
            $this->view->regionList = $participantService->getAllParticipantRegion();
    }

    public function chartAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $response=$reportService->getTestKitReport($params);
            $this->view->response = $response;
        }
    }

    public function participantAction()
    {
       if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getTestKitParticipantReport($params);
        }
    }


}





