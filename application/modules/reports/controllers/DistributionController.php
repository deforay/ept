<?php

class Reports_DistributionController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-shipments', 'html')
            ->addActionContext('generate-reports', 'html')
            ->addActionContext('generate-summary-reports', 'html')
            ->addActionContext('get-job-progress', 'json')
            ->addActionContext('cancel-job', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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

    public function getJobProgressAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        if ($this->hasParam('sid')) {
            $shipmentId = (int) base64_decode($this->_getParam('sid'));
            $evalService = new Application_Service_Evaluation();
            $progress = $evalService->getJobProgress($shipmentId);
            echo json_encode($progress);
        } else {
            echo json_encode(['error' => 'Missing shipment ID', 'in_progress' => false]);
        }
    }

    public function cancelJobAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        if ($this->hasParam('sid')) {
            $shipmentId = (int) base64_decode($this->_getParam('sid'));

            // Get the queue job ID for this shipment
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $job = $db->fetchRow(
                $db->select()
                    ->from('queue_report_generation', ['id'])
                    ->where('shipment_id = ?', $shipmentId)
                    ->where("status IN ('pending', 'not-evaluated', 'not-finalized')")
                    ->order('id DESC')
                    ->limit(1)
            );

            if ($job) {
                $evalService = new Application_Service_Evaluation();
                $result = $evalService->cancelJob((int) $job['id'], 'report_generation');
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'No active job found for this shipment']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing shipment ID']);
        }
    }
}
