<?php

class Admin_PushNotificationController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction(){
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $shipmentService = new Application_Service_Common();
            $shipmentService->getAllPushNotify($params);
        }
    }
    
    public function approveAction(){
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $shipmentService = new Application_Service_Common();
            $shipmentService->approve($params);
        }
    }
}