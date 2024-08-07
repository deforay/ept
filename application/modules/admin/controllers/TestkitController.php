<?php

class Admin_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('update-status', 'html')->initContext();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-testkit', 'html')
            ->initContext();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllDtsTestKitInGrid($params);
        }
    }

    public function addAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->addTestkit($params);
            $this->redirect("/admin/testkit");
        }
    }

    public function editAction()
    {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestkit($params);
            $this->redirect("/admin/testkit");
        } else if ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getDtsTestkit($id);
        } else {
            $this->redirect('admin/testkit/index');
        }
    }

    public function standardKitAction()
    {
        $schemeService = new Application_Service_Schemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestkitStage($params);
            $this->redirect("/admin/testkit/standard-kit");
        }
        $this->view->schemeList = $schemeService->getGenericSchemeLists();
    }

    public function getTestkitAction()
    {
        if ($this->hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->getAllDtsTestKitList(true, $stage);
            $this->view->testkitStage = $stage;
        }
    }
    
    public function updateStatusAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->updateTestKitStatus($params);
            $this->view->testkitStage = $stage;
        }
    }
}
