<?php

class Reports_TbParticipantsPerCountryController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', (string) $adminSession->privileges);
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
        $this->view->schemes = array_values(array_filter($scheme->getAllSchemes(), fn ($s) => $s['scheme_id'] === 'tb'));
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
    }

    public function dataAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getTbParticipantsPerCountry($this->getAllParams()));
    }

    public function shipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getResponseReportShipments('tb', $this->getParam('startDate'), $this->getParam('endDate')));
    }

    public function exportAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json(['file' => $reportService->exportTbParticipantsPerCountry($this->getAllParams())]);
    }
}
