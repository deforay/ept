<?php

class Admin_ProfileController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->pageName = 'dashboard';
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->initContext();
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminService = new Application_Service_SystemAdmin();
        $authNameSpace = new Zend_Session_Namespace('administrators');
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            if ($adminService->updateSystemAdmin($params)) {
                $alertMsg->message = 'Profile updated successfully.';
                $this->redirect("/admin");
            } else {
                $alertMsg->message = 'Profile not updated!';
                $this->redirect("/admin/profile");
            }
        }
        $this->view->result = $adminService->getSystemAdminDetails($authNameSpace->admin_id);
    }
}
