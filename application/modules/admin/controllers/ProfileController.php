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
            // Only the signed-in admin's own record, and only profile fields. Never pass the
            // whole request to updateSystemAdmin(): it trusts adminId, status and privileges.
            $error = $adminService->updateOwnProfile((int) $authNameSpace->admin_id, $request->getPost());
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $translator = Zend_Registry::get('translate');
            if ($error === '') {
                $alertMsg->message = $translator->_('Profile updated successfully.');
                $this->redirect('/admin');
            } else {
                $alertMsg->message = $translator->_($error);
                $this->redirect('/admin/profile');
            }
        }
        $this->view->result = $adminService->getSystemAdminDetails($authNameSpace->admin_id);
    }
}
