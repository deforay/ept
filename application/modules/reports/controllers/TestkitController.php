<?php

class Reports_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
       $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('chart', 'html')
                    ->addActionContext('participant', 'html')
                    ->addActionContext('generate-pdf', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report'; 
    }
    
    public function preDispatch(){
        $adminSession = new Zend_Session_Namespace('administrators');
        if(!in_array('dts',$adminSession->activeSchemes)){
            $this->_redirect("/admin");
        }
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getTestKitDetailedReport($params);
        }
        $participantService = new Application_Service_Participants();
            $this->view->enrolledProgramsList = $participantService->getEnrolledProgramsList();
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

    public function generatePdfAction()
    {
         $this->_helper->layout()->disableLayout();
         if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->result = $reportService->generatePdfTestKitDetailedReport($params);
            $this->view->header=$reportService->getReportConfigValue('report-header');
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
            $this->view->dateRange=$params['dateRange'];
            $this->view->reportType=$params['reportType'];
            $this->view->testkitName=$this->_getParam('testkitName');
        }
    }


}







