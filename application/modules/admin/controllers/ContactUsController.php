<?php

class Admin_ContactUsController extends Zend_Controller_Action
{

    public function init()
    {
         $ajaxContext = $this->_helper->getHelper('AjaxContext');
            $ajaxContext->addActionContext('index', 'html')
                        ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if($this->getRequest()->isPost()){
            $params = $this->getRequest()->getPost();
            $contactUs = new Application_Model_DbTable_ContactUs();
            $contactUs->getAllContacts($params);
        }
    }


}

