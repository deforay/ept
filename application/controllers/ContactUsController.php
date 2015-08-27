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
        $this->_helper->layout()->activeMenu = 'contact-us';
        if($this->getRequest()->isPost()){
            $params = $this->getRequest()->getPost();
            $common = new Application_Service_Common();
            $this->view->message = $common->contactForm($params);
        }else{
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if(!isset($authNameSpace->dm_id)){
                $this->_helper->layout()->setLayout('home');
            }
        }
    }


}

