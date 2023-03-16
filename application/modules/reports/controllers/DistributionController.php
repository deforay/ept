<?php

class Reports_DistributionController extends Zend_Controller_Action
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
            ->addActionContext('generate-reports', 'html')
            ->addActionContext('generate-summary-reports', 'html')
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
            $id = (int) ($this->_getParam('did'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipments = $shipmentService->getShipmentInReports($id);
        } else {
            $this->view->shipments = false;
        }
    }

    public function shipmentAction()
    {
        $shipmentService = new Application_Service_Shipments();
        if ($this->hasParam('sid')) {
            $id = (int) base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluateReports($id, $reEvaluate);
            $this->view->shipmentStatus = $evalService->getReportStatus($id, 'generateReport');
            $this->view->responseCount = $evalService->getResponseCount($id, $shipment[0]['distribution_id']);


            //$this->view->shipmentsUnderDistro = $evalService->getShipments($shipment[0]['distribution_id']);
            $this->view->shipmentsUnderDistro = $shipmentService->getShipmentInReports($shipment[0]['distribution_id']);
        } else {
            $this->redirect("/reports/distribution/");
        }
    }



    public function finalizeAction()
    {
        $shipmentService = new Application_Service_Shipments();
        if ($this->hasParam('sid')) {
            $id = (int) base64_decode($this->_getParam('sid'));
            $reEvaluate = true;
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluateReports($id, $reEvaluate);
            $this->view->shipmentStatus = $evalService->getReportStatus($id, 'finalized');
            $this->view->shipmentsUnderDistro = $shipmentService->getShipmentInReports($shipment[0]['distribution_id']);
            $this->view->responseCount = $evalService->getResponseCount($id, $shipment[0]['distribution_id']);
        } else {
            $this->redirect("/reports/finalize/");
        }
    }

    public function queueReportsGenerationAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('sid')) {
            $params = $this->getAllParams();
            $evalService = new Application_Service_Evaluation();
            $this->view->result = $evalService->queueReportsGeneration($params);
        } else {
            return false;
        }
    }
}
