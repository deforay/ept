<?php

class Reports_TbAllSitesResultsController extends Zend_Controller_Action {
    public function init(){
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction() {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            
            $evalService = new Application_Service_Evaluation();
            $evalService->getEvaluateReportsInPdf($params["shipmentId"], null, null);

            $reportService = new Application_Service_Reports();
            $response = $reportService->getTbAllSitesResultsReport($params);
            $this->view->response = $response;
        }
    }
}

