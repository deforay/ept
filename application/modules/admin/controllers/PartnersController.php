<?php

class Admin_PartnersController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $partnerService = new Application_Service_Partner();
            $partnerService->getAllPartner($parameters);
        }
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $partnerService = new Application_Service_Partner();
            $partnerService->addPartner($params);
            $this->redirect("/admin/partners");
        }
    }

    public function editAction()
    {
        $partnerService = new Application_Service_Partner();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $partnerService->updatePartner($params);
            $this->redirect("/admin/partners");
        }
        if ($this->hasParam('id')) {
            $partnerId = (int)$this->_getParam('id');
            $this->view->partner = $partnerService->getPartner($partnerId);
        } else {
            $this->redirect("/admin/partners");
        }
    }
}
