<?php

class ContactUsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        $this->_helper->layout()->activeMenu = 'contact-us';
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $common = new Application_Service_Common();
            $this->view->message = $common->contactForm($params);
        } else {
            $this->redirect('/');
        }
    }
}
