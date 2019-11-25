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
        $captchaSession = new Zend_Session_Namespace('DACAPTCHA');
        $captchaSession->captchaStatus = 'fail'; // keeping it as fail by default

        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $params['challenge_field'] = filter_var($params['challenge_field'], FILTER_SANITIZE_STRING);
            if (!empty($params['challenge_field']) && $captchaSession->code == $params['challenge_field']) {
                $captchaSession->captchaStatus = 'success';
                $this->view->result = "success";
            } else {
                $captchaSession->captchaStatus = 'fail';
                $this->view->result = "fail";
            }
        }
    }
}
