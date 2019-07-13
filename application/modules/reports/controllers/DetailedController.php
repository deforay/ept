<?php

class Reports_DetailedController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('report', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'report'; 
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $response=$reportService->getParticipantDetailedReport($params);
            $this->view->response = $response;
            $this->view->type= $params['reportType'];
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function reportAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $reportService = new Application_Service_Reports();
            $reportService->getAllParticipantDetailedReport($params);
        }
    }


}



