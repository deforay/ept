<?php

class Admin_VlAssayController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
            return;
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
            $vlAssayService = new Application_Service_VlAssay();
            $vlAssayService->getAllVlAssay($parameters);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $vlAssayService->addVlAssay($params);

            $name = trim((string) ($params['name'] ?? ''));
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Added a new VL assay - ' . ($name !== '' ? $name : '(unnamed)'), 'config');

            $this->redirect('/admin/vl-assay');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $vlAssayService = new Application_Service_VlAssay();
        if ($request->isPost()) {
            $params = $request->getPost();
            $vlAssayService->updateVlAssay($params);

            $name = trim((string) ($params['name'] ?? ''));
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Updated VL assay - ' . ($name !== '' ? $name : '(unnamed)'), 'config');

            $this->redirect('/admin/vl-assay');
        }
        if ($this->hasParam('id')) {
            $id = (int)$this->_getParam('id');
            $this->view->vlAssay = $vlAssayService->getVlAssay($id);
        } else {
            $this->redirect('/admin/vl-assay');
        }
    }
}
