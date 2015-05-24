<?php

class Admin_DataManagersController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $clientsServices = new Application_Service_DataManagers();
            $clientsServices->getAllUsers($params);
        }
    }

    public function addAction()
    {
        $userService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $userService->addUser($params);
            $this->_redirect("/admin/data-managers");
        }

        
        if($this->_hasParam('contact')){
            $contact = new Application_Model_DbTable_ContactUs();
            $this->view->contact = $contact->getContact($this->_getParam('contact'));
        }        
          
    }

    public function editAction()
    {
        $userService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $userService->updateUser($params);
            $this->_redirect("/admin/data-managers");
        }else{
            if($this->_hasParam('id')){
                $userId = (int)$this->_getParam('id');
                $this->view->rsUser = $userService->getUserInfoBySystemId($userId);
            }
        }     
        
    }


}





