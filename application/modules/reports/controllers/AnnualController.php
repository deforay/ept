<?php

class Reports_AnnualController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {

            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();

            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /* Initialize action controller here */
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('save-scheduled-jobs', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getAnnualReport($params);
            $this->view->result = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function saveScheduledJobsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->scheduleCertificationGeneration($params);
            $this->view->result = $response;
        }
    }
}
