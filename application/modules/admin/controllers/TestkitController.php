<?php

class Admin_TestkitController extends Zend_Controller_Action {

    public function init() {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->addActionContext('get-testkit', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllDtsTestKitInGrid($params);
        }
    }

    public function addAction() {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();            
            $schemeService->addTestkit($params);
            $this->_redirect("/admin/testkit");
        }
        
    }

    public function editAction() {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestkit($params);
            $this->_redirect("/admin/testkit");
        } else if ($this->_hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getDtsTestkit($id);
        } else {
            $this->_redirect('admin/testkit/index');
        }
    }

    public function standardKitAction() {
        $schemeService = new Application_Service_Schemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestkitStage($params);
            $this->_redirect("/admin/testkit/standard-kit");
        }
    }

    public function getTestkitAction() {
        if ($this->_hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $schemeService = new Application_Service_Schemes();
            $this->view->testkitList = $schemeService->getAllDtsTestKitList(true);
            $this->view->testkitStage =$stage;
        }
    }

}
