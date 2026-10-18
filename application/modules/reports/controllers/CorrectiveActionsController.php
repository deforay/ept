<?php

class Reports_CorrectiveActionsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        $participants = new Application_Service_Participants();
        $scheme = new Application_Service_Schemes();
        // Corrective actions are recorded for DTS only.
        $this->view->schemes = array_values(array_filter($scheme->getAllSchemes(), fn ($s) => $s['scheme_id'] === 'dts'));
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
    }

    public function dataAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getCorrectiveActionSummary($this->getAllParams()));
    }

    public function responsesAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['html' => $reportService->getCorrectiveActionDrawer($this->getAllParams(), (int) $this->getParam('actionId'))]);
    }

    public function shipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getResponseReportShipments('dts', $this->getParam('startDate'), $this->getParam('endDate')));
    }

    public function exportAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['file' => $reportService->exportCorrectiveActions($this->getAllParams())]);
    }

    public function pdfAction()
    {
        $this->_helper->layout()->disableLayout();
        $reportService = new Application_Service_Reports();
        $params = $this->getAllParams();
        $this->view->header = $reportService->getReportConfigValue('report-header');
        $this->view->logo = $reportService->getReportConfigValue('logo');
        $this->view->summary = $reportService->getCorrectiveActionSummary($params);
        $this->view->filters = $params['filterSummary'] ?? '';
    }
}
