<?php

class Admin_MailTemplateController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        $this->_helper->layout()->pageName = 'configMenu';
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('get-mail-template', 'html')
                    ->addActionContext('get-push-notification', 'html')
                    ->addActionContext('save-push-notification', 'html')
                    ->addActionContext('index', 'html')
                    ->initContext();
    }

    public function indexAction()
    {
        $commonServices = new Application_Service_Common();
        if($this->getRequest()->isPost()){
           $params = $this->_getAllParams();
           $this->view->result = $commonServices->updateTemplate($params);
        }
    }
    
    public function savePushNotificationAction()
    {
        $commonServices = new Application_Service_Common();
        if($this->getRequest()->isPost()){
           $params = $this->_getAllParams();
           $this->view->result = $commonServices->updatePushTemplate($params);
        }
    }
    
    public function getMailTemplateAction()
    {
        $commonServices = new Application_Service_Common();
        if($this->getRequest()->isPost()){
            $purpose = $this->_getParam('template');
            $this->view->mailTemplateDetails=$commonServices->getEmailTemplate($purpose);
            $this->view->mailPurpose=$purpose;
        }
    }
    
    public function getPushNotificationAction()
    {
        $commonServices = new Application_Service_Common();
        if($this->getRequest()->isPost()){
            $purpose = $this->_getParam('template');
            $this->view->result=$commonServices->getPushTemplateByPurpose($purpose);
            $this->view->purpose=$purpose;
        }
    }
}

