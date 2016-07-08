<?php

class CaptchaController extends Zend_Controller_Action
{

    public function init()
    {
        
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('check-captcha', 'html')->initContext();        
        
    }

    public function indexAction()
    {
        $this->_helper->layout()->disableLayout();
    }

    public function checkCaptchaAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $session = new Zend_Session_Namespace('DACAPTCHA');
            //$this->view->result = "success";
            if ($session->code == $params['challenge_field']) {
                 $this->view->result = "success";
            } else {
                 $this->view->result = "fail";
            }
        }
    }


}



