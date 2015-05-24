<?php

class Admin_SystemAdminsController extends Zend_Controller_Action
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
            $clientsServices = new Application_Service_SystemAdmin();
            $clientsServices->getAllAdmin($params);
        }
    }


    public function addAction()
    {
        $adminService = new Application_Service_SystemAdmin();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $adminService->addSystemAdmin($params);
            $this->_redirect("/admin/system-admins");
        }
    }

    public function editAction()
    {
        $adminService = new Application_Service_SystemAdmin();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $adminService->updateSystemAdmin($params);
            $this->_redirect("/admin/system-admins");
        }else{
            if($this->_hasParam('id')){
                $adminId = (int)$this->_getParam('id');
                $this->view->admin = $adminService->getSystemAdminDetails($adminId);
            }
        }
    }


}





