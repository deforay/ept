<?php

class ContactUsController extends Zend_Controller_Action
{

    public function init()
    {
         $ajaxContext = $this->_helper->getHelper('AjaxContext');
            $ajaxContext->addActionContext('index', 'html')
                ->initContext();
    }

    public function indexAction()
    {
        if($this->getRequest()->isPost()){
            $params = $this->getRequest()->getPost();
            $common = new Application_Service_Common();
            $this->view->message = $common->contactForm($params);
        }
    }


}

