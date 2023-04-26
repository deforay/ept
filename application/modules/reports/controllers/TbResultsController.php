<?php

class Reports_TbResultsController extends Zend_Controller_Action {

    public function init(){
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('report', 'html')
            ->addActionContext('results-count', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getResultsPerSiteReport($params);
            $this->view->response = $response;
        }
    }

    public function resultsCountAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->resultsCount = $reportService->getResultsPerSiteCount($params);
        }
    }
}

