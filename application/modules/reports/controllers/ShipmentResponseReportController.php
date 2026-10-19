<?php

class Reports_ShipmentResponseReportController extends Zend_Controller_Action
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
        /* Initialize action controller here */
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('participant-response', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            // The summary only reads the session. Releasing it lets the
            // participant table load at the same time.
            Zend_Session::writeClose(false);
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentResponseReportReport($params);
            $this->view->result = $response;
        }
        $participants = new Application_Service_Participants();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
    }

    public function shipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getResponseReportShipments(
            $this->getParam('scheme'),
            $this->getParam('startDate'),
            $this->getParam('endDate')
        ));
    }

    public function participantResponseAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $this->view->response = $participantService->getShipmentResponseReport($parameters);
        }
    }

    public function exportParticipantsResponseDetailsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $this->view->result = $participantService->exportParticipantsResponseDetails($params);
        } else {
            return false;
        }
    }
}
