<?php

class Admin_HomeSectionLinksController extends Zend_Controller_Action
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
            $params = $this->getAllParams();
            $homeSectionService = new Application_Service_HomeSection();
            $homeSectionService->getAllHomeSectionInGrid($params);
        }

        // Get resource section headings for display
        $common = new Application_Service_Common();
        $this->view->home = json_decode($common->getConfig('home'));
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $homeSectionService->saveHomeSection($params);

            $heading = trim((string) ($params['heading'] ?? $params['link'] ?? ''));
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Added a new home section link' . ($heading !== '' ? " - {$heading}" : ''), 'config');

            $this->redirect('/admin/home-section-links');
        }

        // Get resource section headings for dropdown labels
        $common = new Application_Service_Common();
        $this->view->home = json_decode($common->getConfig('home'));
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $homeSectionService->saveHomeSection($params);

            $heading = trim((string) ($params['heading'] ?? $params['link'] ?? ''));
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Updated home section link' . ($heading !== '' ? " - {$heading}" : ''), 'config');

            $this->redirect('/admin/home-section-links');
        }
        if ($this->hasParam('id')) {
            $id = (int) base64_decode($this->_getParam('id'));
            $this->view->result = $homeSectionService->getHomeSectionById($id);
        }

        // Get resource section headings for dropdown labels
        $common = new Application_Service_Common();
        $this->view->home = json_decode($common->getConfig('home'));
    }

    public function getDisplayOrderAction()
    {
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $maxSortOrder = $homeSectionService->getDisplayOrder($params);
            // Send the response as JSON
            $this->_helper->json([
                'maxSortOrder' => $maxSortOrder,
            ]);
        }
    }
}
