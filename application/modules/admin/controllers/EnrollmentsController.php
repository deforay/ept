<?php

class Admin_EnrollmentsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->initContext();        
        $this->_helper->layout()->pageName = 'manage';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getAllEnrollments($params);
        }
    }


}

