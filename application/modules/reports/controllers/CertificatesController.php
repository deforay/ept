<?php

/**
 * Reports > Annual Certificates: one guided page that takes an admin from
 * choosing the shipments to certificates the participants can download.
 */
class Reports_CertificatesController extends Zend_Controller_Action
{
    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', (string) $adminSession->privileges);
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
            return;
        }
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        $service = new Application_Service_AnnualCertificates();
        $years = $service->years();
        $this->view->years = $years;
        $this->view->defaultYear = in_array((int) date('Y') - 1, $years, true) ? (int) date('Y') - 1 : ($years[0] ?? (int) date('Y'));
        $this->view->activeBatch = $service->activeBatch();
    }

    /** The year's finalized shipments, grouped by scheme. */
    public function shipmentsAction()
    {
        $service = new Application_Service_AnnualCertificates();
        $this->_helper->json($service->shipmentsForYear((int) $this->_getParam('year')));
    }

    /**
     * The checks before generating: each scheme's templates, the number of
     * certificates of each type, the participants who get none and why, and
     * a batch name.
     */
    public function checkAction()
    {
        $shipmentIds = array_map('intval', (array) $this->_getParam('shipmentId', []));
        $service = new Application_Service_AnnualCertificates();
        $templates = new Application_Service_CertificateTemplates();
        $plan = $service->plan($shipmentIds);

        $schemes = [];
        foreach ($plan['schemes'] as $scheme => $codes) {
            $schemes[$scheme] = [
                'shipments' => $codes,
                'excellence' => $templates->templateState($scheme, 'excellence'),
                'participation' => $templates->templateState($scheme, 'participation'),
            ];
        }
        $this->_helper->json([
            'schemes' => $schemes,
            'counts' => Application_Service_AnnualCertificates::counts($plan),
            'skipped' => $plan['skipped'],
            'name' => $service->suggestName((int) $this->_getParam('year'), array_keys($plan['schemes'])),
        ]);
    }

    /** Queue certificate generation for the checked shipments. */
    public function generateAction()
    {
        $request = $this->getRequest();
        $shipmentIds = array_map('intval', (array) $request->getPost('shipmentId', []));
        $name = (string) $request->getPost('name');
        if (!$request->isPost() || empty($shipmentIds) || !preg_match('/^[A-Za-z0-9-]{1,100}$/', $name)) {
            $this->_helper->json(['success' => false, 'message' => 'Pick the shipments and a name with letters, numbers and hyphens only.']);
            return;
        }

        // Refuse here too, not only in the browser, when a template is not ready.
        $plan = (new Application_Service_AnnualCertificates())->plan($shipmentIds);
        $templates = new Application_Service_CertificateTemplates();
        foreach (array_keys($plan['schemes']) as $scheme) {
            foreach (['excellence', 'participation'] as $type) {
                if ($templates->templateState($scheme, $type) !== 'ready') {
                    $this->_helper->json(['success' => false, 'message' => "The $type template for $scheme is not ready."]);
                    return;
                }
            }
        }

        $batchId = (new Application_Model_DbTable_ScheduledJobs())->scheduleCertificationGeneration([
            'shipmentId' => $shipmentIds,
            'certificateName' => $name,
        ]);
        if ($batchId > 0) {
            (new Application_Model_DbTable_AuditLog())->addNewAuditLog("Queued certificate batch - {$name}", 'certificate');
        }
        $this->_helper->json(['success' => $batchId > 0, 'batchId' => (int) $batchId]);
    }

    /** A batch's status and counts, for the progress steps. */
    public function statusAction()
    {
        $batch = (new Application_Model_DbTable_CertificateBatches())->getBatch((int) $this->_getParam('batch'));
        if (!$batch) {
            $this->_helper->json(['success' => false]);
            return;
        }
        $this->_helper->json([
            'success' => true,
            'batch_id' => (int) $batch['batch_id'],
            'name' => $batch['batch_name'],
            'status' => $batch['status'],
            'excellence' => (int) $batch['excellence_count'],
            'participation' => (int) $batch['participation_count'],
            'skipped' => (int) $batch['skipped_count'],
            'error' => $batch['error_message'],
            'download_url' => Application_Model_DbTable_CertificateBatches::zipDownloadUrl($batch),
        ]);
    }

    /** A generated batch's certificates and skipped participants, for review. */
    public function reviewAction()
    {
        $batch = (new Application_Model_DbTable_CertificateBatches())->getBatch((int) $this->_getParam('batch'));
        if (!$batch) {
            $this->_helper->json(['certificates' => [], 'skipped' => []]);
            return;
        }
        $service = new Application_Service_AnnualCertificates();
        $this->_helper->json([
            'certificates' => $service->batchCertificates($batch),
            'skipped' => $service->batchSkipped($batch),
        ]);
    }

    /** Approve a generated batch, which queues its distribution. */
    public function approveAction()
    {
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $error = $request->isPost()
            ? (new Application_Model_DbTable_CertificateBatches())->approveBatch((int) $request->getPost('batch'), $adminSession->admin_id)
            : 'Invalid request';
        $this->_helper->json(['success' => $error === null, 'message' => $error]);
    }

    /**
     * Discard a batch: cancel it while it waits or generates, reject it once
     * generated. Either way it is never distributed.
     */
    public function discardAction()
    {
        $request = $this->getRequest();
        $batchId = (int) $request->getPost('batch');
        $model = new Application_Model_DbTable_CertificateBatches();
        $batch = $request->isPost() ? $model->getBatch($batchId) : null;
        $adminSession = new Zend_Session_Namespace('administrators');
        $done = false;
        if ($batch && in_array($batch['status'], ['pending', 'generating'], true)) {
            $done = $model->cancelBatch($batchId, $adminSession->admin_id);
        } elseif ($batch && $batch['status'] === 'generated') {
            $done = $model->rejectBatch($batchId, $adminSession->admin_id);
        }
        if ($done) {
            (new Application_Model_DbTable_AuditLog())->addNewAuditLog("Discarded certificate batch - {$batch['batch_name']}", 'certificate');
        }
        $this->_helper->json(['success' => $done]);
    }

    /** The review spreadsheet for the checked shipments. */
    public function spreadsheetAction()
    {
        $year = (int) $this->_getParam('year');
        $result = json_decode((new Application_Service_Reports())->getAnnualReport([
            'startDate' => "$year-01-01",
            'endDate' => "$year-12-31",
            'shipmentId' => array_map('intval', (array) $this->_getParam('shipmentId', [])),
            'reportType' => 'excel',
        ]), true);
        $this->_helper->json(['fileName' => $result['fileName'] ?? '']);
    }
}
