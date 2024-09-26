<?php

class Admin_GenericTestController extends Zend_Controller_Action
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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
            $service = new Application_Service_Schemes();
            $service->getAllGenericTestInGrid($parameters);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->saveGenericTest($params);
            $this->redirect("/admin/generic-test");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->saveGenericTest($params);
            $this->redirect('admin/generic-test');
        } elseif ($this->hasParam('id')) {
            $id = base64_decode($this->_getParam('id'));
            $this->view->result = $schemeService->getGenericTest($id);
        }
    }
}
