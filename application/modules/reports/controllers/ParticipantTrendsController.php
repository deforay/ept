<?php

class Reports_ParticipantTrendsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
        }
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    // Per-shipment rows for the filters, as JSON.
    public function summaryAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->_helper->json([]);
        }
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getParticipantTrendsSummary($this->getAllParams()));
    }

    // Region breakdown of one shipment, as JSON.
    public function regionsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->_helper->json([]);
        }
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getParticipantTrendsSummary($this->getAllParams(), true));
    }

    // Builds the Excel file and returns its name in the temporary folder, or ''.
    public function exportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $file = '';
        if ($request->isPost()) {
            $reportService = new Application_Service_Reports();
            $file = $reportService->exportParticipantTrendsSummary($this->getAllParams());
        }
        $this->_helper->json(['file' => $file]);
    }
}
