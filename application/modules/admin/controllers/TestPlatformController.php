<?php

class Admin_TestPlatformController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-test-platform', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllCovid19TestTypeInGrid($params);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->addTestType($params);
            $this->redirect("/admin/test-platform");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->updateTestType($params);
            $this->redirect("/admin/test-platform");
        } elseif ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getCovid19TestType($id);
        } else {
            $this->redirect('admin/test-platform/index');
        }
    }

    public function standardTypeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        if ($request->isPost()) {
            $params = $request->getPost();
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
