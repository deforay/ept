<?php

class Reports_FinalizeController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-shipments', 'html')
            ->addActionContext('shipments', 'html')
            ->addActionContext('get-finalized-shipments', 'html')
            ->addActionContext('send-report-mail', 'html')
            ->addActionContext('approve-replace-summary-report', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $distributionService = new Application_Service_Distribution();
            $distributionService->getAllDistributionReports($params);
        }
    }

    public function getShipmentsAction()
    {
        if ($this->hasParam('did')) {
            $id = (int)($this->_getParam('did'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipments = $shipmentService->getShipmentInReports($id);
        } else {
            $this->view->shipments = false;
        }
    }

    public function shipmentsAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getAllFinalizedShipments($params);
        }
    }

    public function getFinalizedShipmentsAction()
    {
        if ($this->hasParam('did')) {
            $id = (int)($this->_getParam('did'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipments = $shipmentService->getFinalizedShipmentInReports($id);
        } else {
            $this->view->shipments = false;
        }
    }

    public function sendReportMailAction()
    {
        if ($this->hasParam('sid')) {
            $id = (int)($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->result = $shipmentService->sendReportMailForParticiapnts($id);
        } else {
            $this->view->result = false;
        }
    }

    public function approveReplaceSummaryReportAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $this->view->result = $shipmentService->moveSummaryReport($params);
        } else {
            $this->view->result = false;
        }
    }

    public function viewFinalizedShipmentAction()
    {
        $shipmentService = new Application_Service_Shipments();
        if ($this->hasParam('sid')) {
            $id = (int)base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluateReports($id, $reEvaluate);
            $this->view->responseCount = $evalService->getResponseCount($id, $shipment[0]['distribution_id']);
            $this->view->shipmentsUnderDistro = $shipmentService->getShipmentInReports($shipment[0]['distribution_id']);
        } else {
            $this->redirect("/reports/finalize/");
        }
    }

    public function replaceSummaryReportAction()
    {
        $shipmentService = new Application_Service_Shipments();
        $evalService = new Application_Service_Evaluation();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService->replaceSummaryReport($params);
            $this->redirect("/reports/finalize/replace-summary-report/id/" . base64_encode($params['schipmentId']));
        } elseif ($this->hasParam('id')) {
            $id = (int)base64_decode($this->_getParam('id'));
            $this->view->shipment = $evalService->getShipmentToEvaluateReports($id, false);
            $this->view->id = $id;
        }
    }
}
