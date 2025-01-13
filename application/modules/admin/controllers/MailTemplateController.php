<?php

class Admin_MailTemplateController extends Zend_Controller_Action
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
        /* Initialize action controller here */
        $this->_helper->layout()->pageName = 'configMenu';
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-mail-template', 'html')
            ->addActionContext('index', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $commonServices->updateTemplate($params);
        }
    }

    public function getMailTemplateAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $purpose = $this->_getParam('template');
            $this->view->mailTemplateDetails = $commonServices->getEmailTemplate($purpose);
            $this->view->mailPurpose = $purpose;
        }
    }
}
