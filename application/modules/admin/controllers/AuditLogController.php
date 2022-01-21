<?php

class Admin_AuditLogController extends Zend_Controller_Action
{

    public function init()
    {

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $service = new Application_Service_Common();
            $service->getAllAuditLogDetailsByGrid($params);
        }
        $systemAdmin = new Application_Service_SystemAdmin();
        $this->view->systemAdmin = $systemAdmin->getSystemAllAdmin();
    }
}
