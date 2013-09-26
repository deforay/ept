<?php

class Admin_UsersController extends Zend_Controller_Action
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
            $clientsServices = new Application_Service_Users();
            $clientsServices->getAllUsers($params);
        }
    }

    public function addAction()
    {
        $userService = new Application_Service_Users();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $userService->addUser($params);
            $this->_redirect("/admin/users");
        }else{

        }  
    }

    public function editAction()
    {
        $userService = new Application_Service_Users();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $userService->updateUser($params);
            $this->_redirect("/admin/users");
        }else{
            if($this->_hasParam('id')){
                $userId = (int)$this->_getParam('id');
                $this->view->rsUser = $userService->getUserInfoBySystemId($userId);
            }
        }     
        
    }


}





