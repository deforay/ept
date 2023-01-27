<?php

class Admin_SystemAdminsController extends Zend_Controller_Action
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
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_SystemAdmin();
            $clientsServices->getAllAdmin($params);
        }
    }


    public function addAction()
    {
        $adminService = new Application_Service_SystemAdmin();
        $commonServices = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $adminService->addSystemAdmin($params);
            $this->redirect("/admin/system-admins");
        }
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
    }

    public function editAction()
    {
        $adminService = new Application_Service_SystemAdmin();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $adminService->updateSystemAdmin($params);
            $this->redirect("/admin/system-admins");
        } else {
            if ($this->hasParam('id')) {
                $commonServices = new Application_Service_Common();
                $adminId = (int)$this->_getParam('id');
                $this->view->admin = $adminService->getSystemAdminDetails($adminId);
                $this->view->allSchemes = $commonServices->getFullSchemesDetails();
            }
        }
    }
}
