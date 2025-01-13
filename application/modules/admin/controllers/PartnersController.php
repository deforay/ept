<?php

class Admin_PartnersController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $partnerService = new Application_Service_Partner();
            $partnerService->getAllPartner($parameters);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $partnerService = new Application_Service_Partner();
            $partnerService->addPartner($params);
            $this->redirect("/admin/partners");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $partnerService = new Application_Service_Partner();
        if ($request->isPost()) {
            $params = $request->getPost();
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
