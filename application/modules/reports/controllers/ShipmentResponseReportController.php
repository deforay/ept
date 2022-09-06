<?php

class Reports_ShipmentResponseReportController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /* Initialize action controller here */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('participant-response', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
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


    public function participantResponseAction()
    {
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $this->view->response = $participantService->getShipmentResponseReport($parameters);
        }
    }
}
