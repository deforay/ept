<?php

class Admin_TestPlatformController extends Zend_Controller_Action
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
            ->addActionContext('get-test-platform', 'html')
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
            $schemeService->getAllCovid19TestTypeInGrid($params);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeService->addTestType($params);

            $name = trim((string) ($params['testPlatformName'] ?? ''));
            $newId = $this->findNewTestPlatformId($name, (string) ($params['scheme'] ?? ''));
            Application_Model_DbTable_AuditLog::record('Added a new test platform - ' . ($name !== '' ? $name : '(unnamed)'), 'config', [
                'entity' => ['type' => 'config', 'id' => $newId, 'label' => 'Test platform: ' . $name],
                'facts' => [
                    'Scheme' => $params['scheme'] ?? '',
                    'Test Platform Manufacturer' => $params['manufacturer'] ?? '',
                    'Country Approved/Validated' => $this->yesNo($params['countryAdapted'] ?? ''),
                ],
            ]);

            $this->redirect('/admin/test-platform');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getAllSchemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $testTypeId = trim((string) ($params['testtypeId'] ?? ''));
            $before = $this->testPlatformAuditFields($testTypeId);
            $schemeService->updateTestType($params);

            $name = trim((string) ($params['testPlatformName'] ?? ''));
            $this->recordTestPlatformUpdate($testTypeId, $name, $before);

            $this->redirect('/admin/test-platform');
        } elseif ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getCovid19TestType($id);
        } else {
            $this->redirect('admin/test-platform/index');
        }
    }

    public function standardTypeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $stage = trim((string) ($params['testPlatformStage'] ?? ''));
            $before = $this->platformsInStage($stage);
            $schemeService->updateTestTypeStage($params);

            $this->recordStageUpdate($stage, $before);

            $this->redirect('/admin/test-platform/standard-Type');
        }
    }

    public function getTestPlatformAction()
    {
        if ($this->hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $schemeService = new Application_Service_Schemes();
            $this->view->testPlatformList = $schemeService->getAllCovid19TestTypeList(true);
            $this->view->testPlatformStage = $stage;
        }
    }

    private const AUDIT_LABELS = [
        'test_type_name' => 'Test Platform Name',
        'test_type_short_name' => 'Short Test Platform Name',
        'test_type_approval_agency' => 'Approval Agency',
        'scheme_type' => 'Scheme',
        'source_reference' => 'Source Reference',
        'test_type_manufacturer' => 'Test Platform Manufacturer',
        'test_type_comments' => 'Comments',
        'approval' => 'Approved',
        'country_adapted' => 'Country Approved/Validated',
    ];

    private const STAGE_LABELS = [
        'test_type_1' => 'Test 1',
        'test_type_2' => 'Test 2',
        'test_type_3' => 'Test 3',
    ];

    private function yesNo($value): string
    {
        $value = trim((string) $value);
        if ($value === '1') {
            return 'Yes';
        }
        return $value === '0' ? 'No' : $value;
    }

    /** @return array<string, string>|null */
    private function testPlatformAuditFields(string $testTypeId): ?array
    {
        if ($testTypeId === '') {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()
                    ->from('r_test_type_covid19', array_keys(self::AUDIT_LABELS))
                    ->where('test_type_id = ?', $testTypeId)
            );
        } catch (Throwable $e) {
            return null;
        }
        if (!$row) {
            return null;
        }
        $row['approval'] = $this->yesNo($row['approval']);
        $row['country_adapted'] = $this->yesNo($row['country_adapted']);
        return $row;
    }

    /** @param array<string, string>|null $before */
    private function recordTestPlatformUpdate(string $testTypeId, string $name, ?array $before): void
    {
        $after = $this->testPlatformAuditFields($testTypeId);
        $extra = ['entity' => ['type' => 'config', 'id' => $testTypeId, 'label' => 'Test platform: ' . $name]];
        if ($before !== null && $after !== null) {
            $extra['changes'] = Application_Model_DbTable_AuditLog::changes($before, $after, self::AUDIT_LABELS);
            $removed = [];
            foreach (['approval', 'country_adapted'] as $field) {
                if ($before[$field] === 'Yes' && $after[$field] !== 'Yes') {
                    $removed[] = self::AUDIT_LABELS[$field];
                }
            }
            if ($removed !== []) {
                $extra['important'] = true;
                $extra['reason'] = 'Removes the platform from result entry: ' . implode(', ', $removed);
            }
        }
        Application_Model_DbTable_AuditLog::record('Updated test platform - ' . ($name !== '' ? $name : '(unnamed)'), 'config', $extra);
    }

    private function findNewTestPlatformId(string $name, string $scheme): ?string
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $id = $db->fetchOne(
                $db->select()
                    ->from('r_test_type_covid19', ['test_type_id'])
                    ->where('test_type_name = ?', $name)
                    ->where('scheme_type = ?', $scheme)
                    ->order('created_on DESC')
                    ->limit(1)
            );
            return $id !== false ? (string) $id : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string, string> test_type_id => name */
    private function platformsInStage(string $stage): array
    {
        if (!isset(self::STAGE_LABELS[$stage])) {
            return [];
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            return $db->fetchPairs(
                $db->select()
                    ->from('r_test_type_covid19', ['test_type_id', 'test_type_name'])
                    ->where("$stage = 1")
                    ->order('test_type_name')
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @param array<string, string> $before */
    private function recordStageUpdate(string $stage, array $before): void
    {
        $label = self::STAGE_LABELS[$stage] ?? $stage;
        $after = $this->platformsInStage($stage);
        $removed = array_values(array_diff_key($before, $after));
        $extra = [
            'entity' => ['type' => 'config', 'id' => $stage, 'label' => 'Test platform setup: ' . $label],
            'changes' => Application_Model_DbTable_AuditLog::changes(
                [$stage => array_values($before)],
                [$stage => array_values($after)],
                [$stage => $label]
            ),
        ];
        if ($removed !== []) {
            $extra['important'] = true;
            $extra['reason'] = 'Removes platforms from result entry: ' . $label;
            $extra['list'] = ['title' => 'Platforms removed', 'rows' => $removed];
        }
        Application_Model_DbTable_AuditLog::record('Updated test platform stage settings - ' . $label, 'config', $extra);
    }
}
