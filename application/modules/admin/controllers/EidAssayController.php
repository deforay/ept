<?php

class Admin_EidAssayController extends Zend_Controller_Action
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
            ->addActionContext('change-status', 'html')
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
            if (isset($parameters['fromSource']) && $parameters['fromSource'] == 'extraction') {
                $vlAssayService->getAllEidExtractionAssay($parameters);
            } elseif (isset($parameters['fromSource']) && $parameters['fromSource'] == 'detection') {
                $vlAssayService->getAllEidDetectionAssay($parameters);
            }
        } else {
            $this->view->source = '';
            if ($this->hasParam('fromSource')) {
                $this->view->source = $this->_getParam('fromSource');
            }
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $name = trim((string) ($params['name'] ?? ''));
            $nameLabel = $name !== '' ? $name : '(unnamed)';
            if (isset($params['category']) && trim($params['category']) == 'extraction') {
                $vlAssayService->addEidExtractionAssay($params);
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Added a new EID extraction assay - {$nameLabel}", 'config');
                $this->redirect('/admin/eid-assay/index/fromSource/' . $params['category']);
            } elseif (isset($params['category']) && trim($params['category']) == 'detection') {
                $vlAssayService->addEidDetectionAssay($params);
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Added a new EID detection assay - {$nameLabel}", 'config');
                $this->redirect('/admin/eid-assay/index/fromSource/' . $params['category']);
            }
            $this->redirect('/admin/eid-assay/');
        } else {
            $this->view->source = '';
            if ($this->hasParam('source')) {
                $this->view->source = $this->_getParam('source');
            }
        }
    }

    public function editAction()
    {
        $this->redirect('/admin/eid-assay');
    }

    public function changeStatusAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $status = (string) ($params['status'] ?? '');
            $source = (string) ($params['formSource'] ?? '');
            if ($source == 'extraction') {
                $this->view->result = $vlAssayService->changeEidExtractionNameStatus($params);
            } elseif ($source == 'detection') {
                $this->view->result = $vlAssayService->changeEidDetectionNameStatus($params);
            }
            if ($source !== '') {
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Changed EID {$source} assay status" . ($status !== '' ? " to '{$status}'" : ''), 'config');
            }
        }
    }
}
