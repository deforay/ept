<?php

class Admin_CertificateBatchesController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-all-batches', 'json')
            ->addActionContext('get-batch-status', 'json')
            ->addActionContext('approve-batch', 'json')
            ->addActionContext('cancel-batch', 'json')
            ->addActionContext('reject-batch', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    /**
     * Render the certificate batches dashboard page
     */
    public function indexAction()
    {
        // Render the certificate batches dashboard
    }

    /**
     * AJAX endpoint for DataTables server-side processing
     * Returns JSON with batch data
     */
    public function getAllBatchesAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        $params = $this->getAllParams();
        $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
        $batches = $certificateBatchesDb->getAllBatchesByGrid($params);
        echo json_encode($batches);
    }

    /**
     * Get single batch status for polling
     * Returns JSON with batch status and counts
     */
    public function getBatchStatusAction()
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
            'error_message' => null
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
        $response['download_url'] = $batch['download_url'] ?? null;
        $response['error_message'] = $batch['error_message'] ?? null;

        $this->view->assign($response);
    }

    /**
     * Approve a batch and schedule certificate distribution
     * Reuses logic from AnnualController::approveCertificatesAction
     */
    public function approveBatchAction()
    {
        $this->_helper->layout()->disableLayout();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $response = [
            'success' => false,
            'message' => null
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

        if ($batch['status'] !== 'generated') {
            $response['message'] = 'Batch must be in generated status to approve';
            $this->view->assign($response);
            return;
        }

        // Update batch status to approved
        $adminSession = new Zend_Session_Namespace('administrators');
        $certificateBatchesDb->updateStatus($batchId, 'approved', [
            'approved_by' => $adminSession->admin_id,
            'approved_on' => new Zend_Db_Expr('NOW()')
        ]);

        // Schedule the distribution job
        $scheduledJobsDb = new Application_Model_DbTable_ScheduledJobs();
        $jobId = $scheduledJobsDb->scheduleCertificateDistribution($batchId);

        if ($jobId > 0) {
            $response['success'] = true;
            $response['message'] = 'Certificates approved and distribution scheduled';
        } else {
            $response['message'] = 'Failed to schedule distribution job';
        }

        $this->view->assign($response);
    }

    /**
     * Cancel a pending or generating batch
     */
    public function cancelBatchAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $response = [
            'success' => false,
            'message' => null
        ];

        if (!$request->isPost()) {
            $response['message'] = 'Invalid request method';
            echo json_encode($response);
            return;
        }

        $batchId = (int) $request->getParam('batch_id', 0);

        if ($batchId <= 0) {
            $response['message'] = 'Invalid batch ID';
            echo json_encode($response);
            return;
        }

        $adminSession = new Zend_Session_Namespace('administrators');
        $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
        $result = $certificateBatchesDb->cancelBatch($batchId, $adminSession->admin_id);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Batch cancelled successfully';
        } else {
            $response['message'] = 'Failed to cancel batch. It may already be processed or does not exist.';
        }

        echo json_encode($response);
    }

    /**
     * Reject a generated batch (don't distribute certificates)
     */
    public function rejectBatchAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $response = [
            'success' => false,
            'message' => null
        ];

        if (!$request->isPost()) {
            $response['message'] = 'Invalid request method';
            echo json_encode($response);
            return;
        }

        $batchId = (int) $request->getParam('batch_id', 0);

        if ($batchId <= 0) {
            $response['message'] = 'Invalid batch ID';
            echo json_encode($response);
            return;
        }

        $adminSession = new Zend_Session_Namespace('administrators');
        $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
        $result = $certificateBatchesDb->rejectBatch($batchId, $adminSession->admin_id);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Batch rejected successfully';
        } else {
            $response['message'] = 'Failed to reject batch. It may not be in generated status or does not exist.';
        }

        echo json_encode($response);
    }
}
