<?php

class Reports_XtptIndicatorsController extends Zend_Controller_Action
{
    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();

            $evalService = new Application_Service_Evaluation();
            $evalService->getEvaluateReportsInPdf($params["shipmentId"], null, null);

            $reportService = new Application_Service_Reports();
            $response = $reportService->getXtptIndicatorsReport($params);
            $this->view->response = $response;
        }
    }
}
