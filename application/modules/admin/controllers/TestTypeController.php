<?php

class Admin_TestTypeController extends Zend_Controller_Action {

    public function init() {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->addActionContext('get-test-type', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllCovid19TestTypeInGrid($params);
        }
    }

    public function addAction() {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();            
            $schemeService->addTestType($params);
            $this->redirect("/admin/test-type");
        }
        
    }

    public function editAction() {
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestType($params);
            $this->redirect("/admin/test-type");
        } else if ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getCovid19TestType($id);
        } else {
            $this->redirect('admin/test-type/index');
        }
    }

    public function standardTypeAction() {
        $schemeService = new Application_Service_Schemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $schemeService->updateTestTypeStage($params);
            $this->redirect("/admin/test-type/standard-Type");
        }
    }

    public function getTestTypeAction() {
        if ($this->hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $schemeService = new Application_Service_Schemes();
            $this->view->testTypeList = $schemeService->getAllCovid19TestTypeList(true);
            $this->view->testTypeStage =$stage;
        }
    }

}
