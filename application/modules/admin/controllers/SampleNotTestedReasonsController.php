<?php

class Admin_SampleNotTestedReasonsController extends Zend_Controller_Action
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
            ->addActionContext('get-testkit', 'html')
            ->addActionContext('update-status', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllSampleNotTeastedReasonsInGrid($params);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $commonServices = new Application_Service_Common();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->saveNotTestedReasons($params);

            $reasonCode = trim((string) ($params['ntReasonCode'] ?? ''));
            $reasonText = trim((string) ($params['ntReason'] ?? ''));
            $label = $reasonCode !== '' ? $reasonCode : ($reasonText !== '' ? $reasonText : '(unlabeled)');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Added a new not-tested reason - {$label}", 'config');

            $this->redirect('/admin/sample-not-tested-reasons');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $commonServices = new Application_Service_Common();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->saveNotTestedReasons($params);

            $reasonCode = trim((string) ($params['ntReasonCode'] ?? ''));
            $reasonText = trim((string) ($params['ntReason'] ?? ''));
            $label = $reasonCode !== '' ? $reasonCode : ($reasonText !== '' ? $reasonText : '(unlabeled)');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated not-tested reason - {$label}", 'config');

            $this->redirect('/admin/sample-not-tested-reasons');
        } elseif ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getNotTestedReasonById($id);
        } else {
            $this->redirect('admin/sample-not-tested-reasons/index');
        }
    }

    public function updateStatusAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->updateTestKitStatus($params);
            $this->view->testkitStage = $stage;
        }
    }
}
