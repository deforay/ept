<?php

class Admin_CustomTestController extends Zend_Controller_Action
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
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $service = new Application_Service_Schemes();
            $service->getAllGenericTestInGrid($parameters);
        } else {
            // Pick up the post-save re-evaluation nudge stashed by editAction (which
            // redirects here). Consumed once, then cleared.
            $reEvalNs = new Zend_Session_Namespace('schemeReEval');
            if (!empty($reEvalNs->ids)) {
                $this->view->reEvalScheme = $reEvalNs->scheme;
                $this->view->reEvalShipmentIds = $reEvalNs->ids;
                $reEvalNs->unsetAll();
            }
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $common = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeCode = $params['schemeCode'];

            $generic = [];
            if (isset($params['genericConfig']['passingScore']) && !empty($params['genericConfig']['passingScore'])) {
                $generic['passingScore'] = $params['genericConfig']['passingScore'];
            }
            if (isset($params['genericConfig']['disableOtherTestkit']) && !empty($params['genericConfig']['disableOtherTestkit'])) {
                $generic['disableOtherTestkit'] = $params['genericConfig']['disableOtherTestkit'];
            }
            if (isset($params['genericConfig']['reportVersion']) && !empty($params['genericConfig']['reportVersion'])) {
                $generic['reportVersion'] = $params['genericConfig']['reportVersion'];
            }
            if (isset($generic) && !empty($generic)) {
                $common->saveSchemeConfigByName(json_encode($generic), $schemeCode);
            }

            $schemeService->saveGenericTest($params);
            $schemeService->setRecommededCustomTestTypes($params);

            $this->recordCustomTestAdd((string) $schemeCode);

            $this->redirect('/admin/custom-test');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $common = new Application_Service_Common();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $schemeCode = $params['schemeCode'];
            $previousCode = (string) Pt_Commons_MiscUtility::decodeId((string) ($params['schemeId'] ?? ''));
            $before = $this->customTestSnapshot($previousCode !== '' ? $previousCode : (string) $schemeCode);
            $generic = [];
            if (isset($params['genericConfig']['passingScore']) && !empty($params['genericConfig']['passingScore'])) {
                $generic['passingScore'] = $params['genericConfig']['passingScore'];
            }
            if (isset($params['genericConfig']['disableOtherTestkit']) && !empty($params['genericConfig']['disableOtherTestkit'])) {
                $generic['disableOtherTestkit'] = $params['genericConfig']['disableOtherTestkit'];
            }
            if (isset($params['genericConfig']['reportVersion']) && !empty($params['genericConfig']['reportVersion'])) {
                $generic['reportVersion'] = $params['genericConfig']['reportVersion'];
            }
            if (isset($params['genericConfig']['effectiveDate']) && !empty($params['genericConfig']['effectiveDate'])) {
                $generic['effectiveDate'] = $params['genericConfig']['effectiveDate'];
            }
            if (isset($generic) && !empty($generic)) {
                $common->saveSchemeConfigByName(json_encode($generic), $schemeCode);
            }
            $schemeService->saveGenericTest($params);
            $schemeService->setRecommededCustomTestTypes($params);

            $this->recordCustomTestUpdate((string) $schemeCode, $before);

            // Config changed: stash any shipments scored under the old config so the
            // index page (we redirect there) can nudge the admin to re-evaluate.
            $reEvalIds = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds($schemeCode);
            if (!empty($reEvalIds)) {
                $reEvalNs = new Zend_Session_Namespace('schemeReEval');
                $reEvalNs->scheme = $schemeCode;
                $reEvalNs->ids = $reEvalIds;
            }

            $this->redirect('/admin/custom-test');
        } elseif ($this->hasParam('id')) {
            $id = Pt_Commons_MiscUtility::decodeId($this->_getParam('id'));
            $this->view->result = $result =  $schemeService->getGenericTest($id);
            $schemeCode = $result['schemeResult']['scheme_id'];
            $dtsModel = new Application_Model_Dts();
            $db = new Application_Model_CustomTest();
            $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(false, 'custom-tests');
            $this->view->customTestsRecommendedTestkits = $db->getRecommededGenericTestkits($schemeCode);
        }
        $this->view->disableOtherTestkit = Pt_Commons_SchemeConfig::get($schemeCode . '.disableOtherTestkit');
        $this->view->passingScore = Pt_Commons_SchemeConfig::get($schemeCode . '.passingScore');
        $this->view->reportVersion = Pt_Commons_SchemeConfig::get($schemeCode . '.reportVersion');
        $this->view->effectiveDate = Pt_Commons_SchemeConfig::get($schemeCode . '.effectiveDate');
    }

    public function cloneAction()
    {
        $schemeService = new Application_Service_Schemes();
        if (!$this->hasParam('id')) {
            $this->redirect('/admin/custom-test');
            return;
        }
        $id = Pt_Commons_MiscUtility::decodeId($this->_getParam('id'));
        $this->view->result = $result = $schemeService->getGenericTest($id);
        $schemeCode = $result['schemeResult']['scheme_id'];
        $dtsModel = new Application_Model_Dts();
        $db = new Application_Model_CustomTest();
        $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(false, 'custom-tests');
        $this->view->customTestsRecommendedTestkits = $db->getRecommededGenericTestkits($schemeCode);
        $this->view->disableOtherTestkit = Pt_Commons_SchemeConfig::get($schemeCode . '.disableOtherTestkit');
        $this->view->passingScore = Pt_Commons_SchemeConfig::get($schemeCode . '.passingScore');
        $this->view->reportVersion = Pt_Commons_SchemeConfig::get($schemeCode . '.reportVersion');
        $this->view->effectiveDate = Pt_Commons_SchemeConfig::get($schemeCode . '.effectiveDate');
        // Reuse the edit form, but in "clone" mode: test name & code are blanked and the
        // form posts to add (a fresh insert) instead of edit.
        $this->view->isClone = true;
        $this->_helper->viewRenderer->setScriptAction('edit');
    }

    public function exportAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        if (!$this->hasParam('id')) {
            $this->redirect('/admin/custom-test');
            return;
        }

        $id = Pt_Commons_MiscUtility::decodeId($this->_getParam('id'));
        $schemeService = new Application_Service_Schemes();
        $export = $schemeService->exportGenericTests([$id]);
        if (empty($export['tests'])) {
            $this->getResponse()->setHttpResponseCode(404);
            echo 'Custom test not found.';
            return;
        }

        $filename = 'custom-test-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $id), '-');
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        Application_Model_DbTable_AuditLog::record('Exported custom test - ' . $id, 'config', [
            'entity' => ['type' => 'scheme', 'id' => $id, 'label' => (string) ($export['tests'][0]['scheme']['scheme_name'] ?? $id)],
        ]);

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '.json"')
            ->setHeader('Content-Length', strlen($json))
            ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->setHeader('Pragma', 'public');

        echo $json;
    }

    public function importAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();

        if ($request->isPost()) {
            $overwrite = $request->getPost('overwrite') === 'yes';

            if (empty($_FILES['importFile']['tmp_name']) || !is_uploaded_file($_FILES['importFile']['tmp_name'])) {
                $this->view->error = 'Please choose a custom test export file (.json) to import.';
                return;
            }

            $raw = file_get_contents($_FILES['importFile']['tmp_name']);
            $payload = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                $this->view->error = 'The selected file is not valid JSON.';
                return;
            }

            $summary = $schemeService->importGenericTests($payload, $overwrite);
            $this->view->summary = $summary;

            $this->recordCustomTestImport($summary, $overwrite);
        }
    }

    public function importDetailsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            if (empty($_FILES['importFile']['tmp_name']) || !is_uploaded_file($_FILES['importFile']['tmp_name'])) {
                $this->view->error = 'Please choose a custom test export file (.json) to import.';
                return;
            }

            $raw = file_get_contents($_FILES['importFile']['tmp_name']);
            $payload = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                $this->view->error = 'The selected file is not valid JSON.';
                return;
            }
            $test = $payload['tests'][0];

            $this->view->result = [
                'schemeResult' => [
                    'scheme_id'        => $test['scheme']['scheme_id'],
                    'scheme_name'      => $test['scheme']['scheme_name'],
                    'status'           => $test['scheme']['status'],
                    'user_test_config' => json_encode($test['scheme']['user_test_config']),
                ],
                'possibleResult' => $test['possibleResults'],
            ];
        }
    }

    /** Labels for the settings stored in user_test_config and scheme_config. */
    private const CONFIG_LABELS = [
        'numberOfTests' => 'Number of Tests',
        'passingScore' => 'Passing Score',
        'disableOtherTestkit' => 'Disable Other Test Kit',
        'reportVersion' => 'Report Version',
        'effectiveDate' => 'Report Effective Date',
        'captureAdditionalDetails' => 'Capture Additional Detail',
        'additionalDetailLabel' => 'Additional Detail Label',
        'testType' => 'Select Test Format',
        'minNumberOfResponses' => 'Minimum Number of Responses',
    ];

    /** Settings whose change can alter how responses are scored. */
    private const SCORING_CONFIG = ['numberOfTests', 'passingScore', 'testType', 'minNumberOfResponses'];

    /** Labels for one possible result row. */
    private const RESULT_LABELS = [
        'response' => 'Expected Result',
        'result_code' => 'Result Code',
        'scheme_sub_group' => 'Result Type',
        'display_context' => 'Displayed To',
        'sort_order' => 'Sort Order',
        'status' => 'Status',
        'high_range' => 'High Range',
        'threshold_range' => 'Threshold Range',
        'low_range' => 'Low Range',
        'sd_scaling_factor' => 'SD Scaling Factor',
        'uncertainy_scaling_factor' => 'Uncertainty Scaling Factor',
        'uncertainy_threshold' => 'Uncertainty Threshold',
        'minimum_number_of_responses' => 'Minimum Number of Responses',
    ];

    /** Fields of a qualitative result row. Quantitative rows use the rest. */
    private const QUALITATIVE_FIELDS = ['response', 'result_code', 'scheme_sub_group', 'display_context', 'sort_order', 'status'];

    /** Result fields that do not change scoring. */
    private const RESULT_DISPLAY_ONLY = ['sort_order'];

    /**
     * The custom test as the edit form shows it, flattened for the audit log.
     *
     * @return array{name: string, values: array<string, string>, labels: array<string, string>, scoring: list<string>, inUse: array<string, true>}|null
     */
    private function customTestSnapshot(string $schemeId): ?array
    {
        if ($schemeId === '') {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $scheme = $db->fetchRow(
                $db->select()->from('scheme_list', ['scheme_name', 'status', 'user_test_config'])->where('scheme_id = ?', $schemeId)
            );
            if (!$scheme) {
                return null;
            }
            $results = (new Application_Service_Schemes())->getGenericTest($schemeId)['possibleResult'] ?? [];
            $configRaw = $db->fetchOne(
                $db->select()->from('scheme_config', ['scheme_config_value'])->where('scheme_config_name = ?', $schemeId)
            );
            $testkits = $db->fetchCol(
                $db->select()->from(['g' => 'generic_recommended_test_types'], [])
                    ->join(['t' => 'r_testkitnames'], 't.TestKitName_ID = g.testkit', ['TestKit_Name'])
                    ->where('g.scheme_id = ?', $schemeId)
            );
        } catch (Throwable $e) {
            return null;
        }

        $values = ['scheme_name' => (string) $scheme['scheme_name'], 'status' => (string) $scheme['status']];
        $labels = ['scheme_name' => 'Custom Test Name', 'status' => 'Status'];
        $scoring = [];

        $config = json_decode((string) $scheme['user_test_config'], true);
        $config = is_array($config) ? $config : [];
        $overlay = $configRaw ? json_decode((string) $configRaw, true) : null;
        if (is_array($overlay)) {
            $config = array_merge($config, $overlay);
        }
        foreach (self::CONFIG_LABELS as $key => $label) {
            $value = $config[$key] ?? '';
            $values['config.' . $key] = is_scalar($value) ? (string) $value : (string) json_encode($value);
            $labels['config.' . $key] = $label;
            if (in_array($key, self::SCORING_CONFIG, true)) {
                $scoring[] = 'config.' . $key;
            }
        }

        $testkits = array_map('strval', $testkits);
        sort($testkits);
        $values['testkits'] = implode(', ', $testkits);
        $labels['testkits'] = 'Enforce these Test kits';

        $groupNames = ['TEST' => 'Test Result', 'FINAL' => 'Final Interpretation'];
        $contextNames = ['participant' => 'Participant', 'admin' => 'Admin', 'all' => 'All'];
        $inUse = [];
        $seen = [];
        foreach ($results as $row) {
            $sub = trim((string) ($row['sub_scheme'] ?? ''));
            $quantitative = ($row['result_type'] ?? '') === 'quantitative';
            $code = trim((string) ($row['result_code'] ?? ''));
            $name = $quantitative ? 'Quantitative' : ($code !== '' ? $code : trim((string) ($row['response'] ?? '')));
            // Rows are rebuilt on every save, so match them by test name and code, not by id.
            $key = 'results.' . md5($sub . '|' . $name);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            if ($seen[$key] > 1) {
                $key .= '-' . $seen[$key];
            }
            $prefix = implode(' › ', array_filter([$sub, $name], static fn ($part) => $part !== ''));
            if (!empty($row['is_matched'])) {
                $inUse[$key] = true;
            }
            foreach (self::RESULT_LABELS as $field => $label) {
                $applies = $field === 'sort_order' || in_array($field, self::QUALITATIVE_FIELDS, true) !== $quantitative;
                if (!$applies || !array_key_exists($field, $row)) {
                    continue;
                }
                $value = trim((string) $row[$field]);
                if ($field === 'scheme_sub_group') {
                    $value = $groupNames[$value] ?? $value;
                } elseif ($field === 'display_context') {
                    $value = $contextNames[$value] ?? $value;
                } elseif ($field === 'status' && $value === '') {
                    $value = 'active';
                }
                $values[$key . '.' . $field] = $value;
                $labels[$key . '.' . $field] = ($prefix !== '' ? $prefix . ' › ' : '') . $label;
                if (!in_array($field, self::RESULT_DISPLAY_ONLY, true)) {
                    $scoring[] = $key . '.' . $field;
                }
            }
        }

        return [
            'name' => (string) $scheme['scheme_name'],
            'values' => $values,
            'labels' => $labels,
            'scoring' => $scoring,
            'inUse' => $inUse,
        ];
    }

    private function recordCustomTestAdd(string $schemeCode): void
    {
        $after = $this->customTestSnapshot($schemeCode);
        $facts = [];
        foreach (['config.passingScore', 'config.numberOfTests', 'testkits'] as $field) {
            if ($after !== null && ($after['values'][$field] ?? '') !== '') {
                $facts[$after['labels'][$field]] = $after['values'][$field];
            }
        }
        Application_Model_DbTable_AuditLog::record("Added a new generic test - {$schemeCode}", 'config', [
            'entity' => ['type' => 'scheme', 'id' => $schemeCode, 'label' => $after['name'] ?? $schemeCode],
            'facts' => $facts,
        ]);
    }

    /** @param array{name: string, values: array<string, string>, labels: array<string, string>, scoring: list<string>, inUse: array<string, true>}|null $before */
    private function recordCustomTestUpdate(string $schemeCode, ?array $before): void
    {
        $after = $this->customTestSnapshot($schemeCode);
        $extra = ['entity' => ['type' => 'scheme', 'id' => $schemeCode, 'label' => $after['name'] ?? $schemeCode]];
        if ($before !== null && $after !== null) {
            $labels = $before['labels'] + $after['labels'];
            $changes = Application_Model_DbTable_AuditLog::changes($before['values'], $after['values'], $labels);
            $scoringSet = array_flip(array_merge($before['scoring'], $after['scoring']));
            $scoringHits = [];
            $retiredInUse = [];
            foreach ($changes as $change) {
                if (isset($scoringSet[$change['field']])) {
                    $scoringHits[] = $change['label'];
                }
                $rowKey = substr($change['field'], 0, (int) strrpos($change['field'], '.'));
                if (isset($before['inUse'][$rowKey]) && str_ends_with($change['field'], '.status') && $change['to'] !== 'active') {
                    $retiredInUse[] = $change['label'];
                }
            }
            $extra['changes'] = $changes;
            if ($retiredInUse !== []) {
                $extra['important'] = true;
                $extra['reason'] = 'Retires results that shipments already use: ' . implode(', ', $retiredInUse);
            } elseif ($scoringHits !== []) {
                $extra['important'] = true;
                $extra['reason'] = 'Changes scoring: ' . implode(', ', $scoringHits);
            }
        }
        Application_Model_DbTable_AuditLog::record("Updated generic test - {$schemeCode}", 'config', $extra);
    }

    /** @param array<string, list<string>> $summary */
    private function recordCustomTestImport(array $summary, bool $overwrite): void
    {
        $rows = [];
        foreach (['imported' => 'Imported', 'skipped' => 'Skipped', 'errors' => 'Error'] as $key => $prefix) {
            foreach ($summary[$key] ?? [] as $line) {
                $rows[] = $prefix . ': ' . $line;
            }
        }
        $imported = $summary['imported'] ?? [];
        $replaced = array_filter($imported, static fn ($line) => str_ends_with((string) $line, '(updated)'));
        $extra = [
            'facts' => ['Overwrite' => $overwrite ? 'Yes' : 'No'],
            'list' => ['title' => 'Custom tests in the file', 'rows' => $rows],
        ];
        if (count($imported) === 1) {
            $code = trim((string) preg_replace('/\s*\((new|updated)\)$/', '', (string) $imported[0]));
            $extra['entity'] = ['type' => 'scheme', 'id' => $code, 'label' => $code];
        }
        if ($replaced !== []) {
            $extra['important'] = true;
            $extra['reason'] = 'Replaces the scoring settings of existing custom tests.';
        }
        Application_Model_DbTable_AuditLog::record('Imported ' . count($imported) . ' custom test(s)', 'config', $extra);
    }
}
