<?php

class Admin_AlertsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('email', 'json')  // Changed to 'json'
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction() {}

    public function emailAction()
    {
        if ($this->getRequest()->isPost()) {
            // Disable layout and view for AJAX requests
            $this->_helper->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);

            $params = $this->getRequest()->getPost();
            $service = new Application_Service_Common();
            $service->getEmailFailureInGrid($params);
        }
    }
}
