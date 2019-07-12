<?php

class Admin_MailTemplateController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
         $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $commonServices = new Application_Service_Common();
        if($this->getRequest()->isPost()){
           $params = $this->_getAllParams();
           $commonServices->updateTemplate($params);
           $this->_redirect("/admin/index");    
        }
        else if($this->_hasParam('9u690s3')){
            $purpose = $this->_getParam('9u690s3');
            $this->view->mailTemplateDetails=$commonServices->getEmailTemplate($purpose);
            $this->view->mailPurpose=$purpose;
            
        }
    }


}

