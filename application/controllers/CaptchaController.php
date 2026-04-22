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
            $submitted = trim((string)($params['challenge_field'] ?? ''));
            $expected = (string)($captchaSession->code ?? '');
            // Case-insensitive compare; the mixed-case alphabet is there to confuse OCR,
            // not humans. hash_equals avoids timing leaks on the code value.
            if ($submitted !== '' && $expected !== '' && hash_equals(strtolower($expected), strtolower($submitted))) {
                $captchaSession->captchaStatus = 'success';
                $this->view->result = "success";
            } else {
                $captchaSession->captchaStatus = 'fail';
                $this->view->result = "fail";
            }
        }
    }
}
