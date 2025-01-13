<?php

class CaptchaController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $params['challenge_field'] = htmlspecialchars($params['challenge_field']);
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
