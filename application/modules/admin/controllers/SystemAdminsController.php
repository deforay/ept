<?php

class Admin_SystemAdminsController extends Zend_Controller_Action
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
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_SystemAdmin();
            $clientsServices->getAllAdmin($params);
        }
    }


    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminService = new Application_Service_SystemAdmin();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $request->getPost();
            $adminService->addSystemAdmin($params);
            $this->redirect("/admin/system-admins");
        }
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminService = new Application_Service_SystemAdmin();
        if ($request->isPost()) {
            $params = $request->getPost();
            $adminService->updateSystemAdmin($params);
            $this->redirect("/admin/system-admins");
        } else {
            if ($this->hasParam('id')) {
                $commonServices = new Application_Service_Common();
                $adminId = (int)$this->_getParam('id');
                $this->view->admin = $adminService->getSystemAdminDetails($adminId);
                $this->view->allSchemes = $commonServices->getFullSchemesDetails();
                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
            }
        }
    }
}
