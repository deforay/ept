<?php

class Admin_TestPlatformController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-test-platform', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllCovid19TestTypeInGrid($params);
        }
    }

    public function addAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->addTestType($params);
            $this->redirect("/admin/test-platform");
        }
    }

    public function editAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestType($params);
            $this->redirect("/admin/test-platform");
        } else if ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getCovid19TestType($id);
        } else {
            $this->redirect('admin/test-platform/index');
        }
    }

    public function standardTypeAction()
    {
        $schemeService = new Application_Service_Schemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestTypeStage($params);
            $this->redirect("/admin/test-platform/standard-Type");
        }
    }

    public function getTestPlatformAction()
    {
        if ($this->hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $schemeService = new Application_Service_Schemes();
            $this->view->testPlatformList = $schemeService->getAllCovid19TestTypeList(true);
            $this->view->testPlatformStage = $stage;
        }
    }
}
