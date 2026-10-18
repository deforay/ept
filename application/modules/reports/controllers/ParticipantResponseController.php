<?php

class Reports_ParticipantResponseController extends Zend_Controller_Action
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

    public function dataAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getParticipantResponseGroups($this->getAllParams()));
    }

    public function shipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getParticipantResponseGroups($this->getAllParams(), true));
    }

    public function exportAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['file' => $reportService->exportParticipantResponseGroups($this->getAllParams())]);
    }
}
