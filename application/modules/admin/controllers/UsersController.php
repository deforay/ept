<?php

class Admin_UsersController extends Zend_Controller_Action
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

    }

    public function addAction()
    {
 
    }

    public function editAction()
    {
  
        
    }


}





