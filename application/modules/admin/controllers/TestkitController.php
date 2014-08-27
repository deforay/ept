<?php

class Admin_TestkitController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllDtsTestKitInGrid($params);
        }
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();            
            $schemeService = new Application_Service_Schemes();
            $schemeService->addTestkit($params);
            $this->_redirect("/admin/testkit");
        }
    }

    public function editAction()
    {
        $schemeService = new Application_Service_Schemes();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();            
            $schemeService->updateTestkit($params);
            $this->_redirect("/admin/testkit");
        }else if($this->_hasParam('53s5k85_8d')){
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getDtsTestkit($id);
        }else{
            $this->_redirect('admin/testkit/index');
        }
        
    }


}





