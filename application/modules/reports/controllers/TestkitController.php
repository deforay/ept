<?php

class Reports_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('chart', 'html')
            ->addActionContext('participant', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function preDispatch()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        if (!in_array('dts', $adminSession->activeSchemes)) {
            $this->redirect("/admin");
        }
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getTestKitReport($params);
            $this->view->response = $response;
        }
    }

    public function participantAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getTestKitParticipantReport($params);
        }
    }
}
