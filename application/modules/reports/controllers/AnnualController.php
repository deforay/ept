<?php

class Reports_AnnualController extends Zend_Controller_Action
{
    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {

            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();

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
            ->addActionContext('save-scheduled-jobs', 'html')
            ->addActionContext('get-certificate-status', 'json')
            ->addActionContext('approve-certificates', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getAnnualReport($params);
            $this->view->result = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function shipmentsAction()
    {
        $reportService = new Application_Service_Reports();
        $this->_helper->json($reportService->getAnnualReportShipmentOptions($this->getAllParams()));
    }

    public function saveScheduledJobsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->scheduleCertificationGeneration($params);
            $this->view->result = $response;
        }
    }

    /**
     * Get certificate batch status for polling
     * Returns JSON with: status, excellence_count, participation_count, skipped_count, download_url, error_message
     */
    public function getCertificateStatusAction()
    {
        $this->_helper->layout()->disableLayout();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $batchId = (int) $request->getParam('batch_id', 0);

        $response = [
            'success' => false,
            'status' => null,
            'excellence_count' => 0,
            'participation_count' => 0,
            'skipped_count' => 0,
            'download_url' => null,
            'error_message' => null,
        ];

        if ($batchId <= 0) {
            $response['error_message'] = 'Invalid batch ID';
            $this->view->assign($response);
            return;
        }

        $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
        $batch = $certificateBatchesDb->getBatch($batchId);

        if (!$batch) {
            $response['error_message'] = 'Batch not found';
            $this->view->assign($response);
            return;
        }

        $response['success'] = true;
        $response['status'] = $batch['status'];
        $response['excellence_count'] = (int) ($batch['excellence_count'] ?? 0);
        $response['participation_count'] = (int) ($batch['participation_count'] ?? 0);
        $response['skipped_count'] = (int) ($batch['skipped_count'] ?? 0);
        $response['download_url'] = Application_Model_DbTable_CertificateBatches::zipDownloadUrl($batch);
        $response['error_message'] = $batch['error_message'] ?? null;

        $this->view->assign($response);
    }

    /**
     * Approve certificates and schedule distribution
     * Updates batch status to 'approved' and schedules distribute-certificates.php job
     */
    public function approveCertificatesAction()
    {
        $this->_helper->layout()->disableLayout();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $response = [
            'success' => false,
            'message' => null,
        ];

        if (!$request->isPost()) {
            $response['message'] = 'Invalid request method';
            $this->view->assign($response);
            return;
        }

        $batchId = (int) $request->getParam('batch_id', 0);

        if ($batchId <= 0) {
            $response['message'] = 'Invalid batch ID';
            $this->view->assign($response);
            return;
        }

        $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
        $batch = $certificateBatchesDb->getBatch($batchId);

        if (!$batch) {
            $response['message'] = 'Batch not found';
            $this->view->assign($response);
            return;
        }

        $adminSession = new Zend_Session_Namespace('administrators');
        $error = $certificateBatchesDb->approveBatch($batchId, $adminSession->admin_id);
        $response['success'] = $error === null;
        $response['message'] = $error ?? 'Certificates approved and distribution scheduled';

        $this->view->assign($response);
    }
}
