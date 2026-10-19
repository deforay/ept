<?php

class Reports_TestkitController extends Zend_Controller_Action
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
        $participants = new Application_Service_Participants();
        $scheme = new Application_Service_Schemes();
        // Test kits are recorded for DTS (HIV serology) only.
        $this->view->schemes = array_values(array_filter($scheme->getAllSchemes(), fn ($s) => $s['scheme_id'] === 'dts'));
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
    }

    public function dataAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getTestKitSummary($this->getAllParams()));
    }

    public function responsesAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['html' => $reportService->getTestKitDrawer($this->getAllParams(), (string) $this->getParam('kitId'))]);
    }

    public function shipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getResponseReportShipments('dts', $this->getParam('startDate'), $this->getParam('endDate')));
    }

    public function exportAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['file' => $reportService->exportTestKitReport($this->getAllParams())]);
    }
}
