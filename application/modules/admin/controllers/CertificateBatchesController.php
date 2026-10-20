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
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
            return;
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-all-batches', 'json')
            ->addActionContext('get-batch-status', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    /**
     * Every certificate batch, as history. Approving and discarding happen
     * on Reports > Annual Certificates only.
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
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

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
            echo json_encode($response);
            return;
        }

        $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
        $batch = $certificateBatchesDb->getBatch($batchId);

        if (!$batch) {
            $response['error_message'] = 'Batch not found';
            echo json_encode($response);
            return;
        }

        $response['success'] = true;
        $response['status'] = $batch['status'];
        $response['excellence_count'] = (int) ($batch['excellence_count'] ?? 0);
        $response['participation_count'] = (int) ($batch['participation_count'] ?? 0);
        $response['skipped_count'] = (int) ($batch['skipped_count'] ?? 0);
        $response['download_url'] = Application_Model_DbTable_CertificateBatches::zipDownloadUrl($batch);
        $response['error_message'] = $batch['error_message'] ?? null;

        echo json_encode($response);
    }
}
