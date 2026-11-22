<?php

class Admin_SampleNotTestedReasonsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
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
            ->addActionContext('get-testkit', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllSampleNotTeastedReasonsInGrid($params);
        } else {
            $schemeDb = new Application_Model_DbTable_SchemeList();
            $this->view->schemeList = $schemeDb->getFullSchemeList(true);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $commonServices = new Application_Service_Common();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->saveNotTestedReasons($params);

            $reasonCode = trim((string) ($params['ntReasonCode'] ?? ''));
            $reasonText = trim((string) ($params['ntReason'] ?? ''));
            $label = $reasonCode !== '' ? $reasonCode : ($reasonText !== '' ? $reasonText : '(unlabeled)');
            $newId = $this->findReasonId($reasonText);
            $after = $newId !== null ? $this->reasonAuditFields($newId) : null;
            $facts = [];
            foreach ($after ?? [] as $field => $value) {
                $facts[self::AUDIT_LABELS[$field]] = $value;
            }
            Application_Model_DbTable_AuditLog::record("Added a new not-tested reason - {$label}", 'config', [
                'entity' => ['type' => 'config', 'id' => $newId, 'label' => 'Not tested reason: ' . $reasonText],
                'facts' => $facts,
            ]);

            $this->redirect('/admin/sample-not-tested-reasons');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $commonServices = new Application_Service_Common();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        if ($request->isPost()) {
            $params = $request->getPost();
            $reasonId = (string) base64_decode((string) ($params['ntrId'] ?? ''));
            $before = $this->reasonAuditFields($reasonId);
            $schemeService->saveNotTestedReasons($params);

            $reasonCode = trim((string) ($params['ntReasonCode'] ?? ''));
            $reasonText = trim((string) ($params['ntReason'] ?? ''));
            $label = $reasonCode !== '' ? $reasonCode : ($reasonText !== '' ? $reasonText : '(unlabeled)');
            $after = $this->reasonAuditFields($reasonId);
            $extra = ['entity' => ['type' => 'config', 'id' => $reasonId, 'label' => 'Not tested reason: ' . $reasonText]];
            if ($before !== null && $after !== null) {
                $extra['changes'] = Application_Model_DbTable_AuditLog::changes($before, $after, self::AUDIT_LABELS);
            }
            Application_Model_DbTable_AuditLog::record("Updated not-tested reason - {$label}", 'config', $extra);

            $this->redirect('/admin/sample-not-tested-reasons');
        } elseif ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getNotTestedReasonById($id);
        } else {
            $this->redirect('admin/sample-not-tested-reasons/index');
        }
    }

    private const AUDIT_LABELS = [
        'ntr_reason' => 'Not Tested Reason',
        'reason_code' => 'Reason Code',
        'ntr_test_type' => 'Scheme Name',
        'collect_panel_receipt_date' => 'Collect Panel Receipt Date?',
        'ntr_status' => 'Status',
    ];

    /** @return array<string, mixed>|null */
    private function reasonAuditFields(string $id): ?array
    {
        if ($id === '' || !ctype_digit($id)) {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()->from('r_response_not_tested_reasons', array_keys(self::AUDIT_LABELS))->where('ntr_id = ?', (int) $id)
            );
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        $schemes = json_decode((string) $row['ntr_test_type'], true);
        $schemes = is_array($schemes) ? array_map('strval', array_values($schemes)) : [];
        sort($schemes);
        $row['ntr_test_type'] = $schemes;
        return $row;
    }

    private function findReasonId(string $reason): ?string
    {
        if ($reason === '') {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $id = $db->fetchOne(
                $db->select()
                    ->from('r_response_not_tested_reasons', ['ntr_id'])
                    ->where('ntr_reason = ?', $reason)
                    ->order('ntr_id DESC')
                    ->limit(1)
            );
            return $id !== false ? (string) $id : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
