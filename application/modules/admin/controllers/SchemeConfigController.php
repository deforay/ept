<?php

/**
 * Scheme Config hub.
 *
 *   GET|POST /admin/scheme-config/:scheme
 *
 * One page per scheme, reached from a persistent sidebar listing every scheme
 * the instance runs. Replaces the six separate *-settings controllers, which
 * now redirect here.
 *
 * Each scheme keeps its own save path and its own view partial — the schemes
 * are genuinely different (DTS carries national-algorithm test-kit rules,
 * SARS-CoV-2 carries recommended platforms, EID validates its passing score),
 * so this dispatches per scheme rather than pretending they are uniform.
 *
 * Only one scheme's markup is ever in the DOM, which is why the partials can
 * keep their original field ids (several reuse `effectiveDate`/`reportVersion`).
 */
class Admin_SchemeConfigController extends Zend_Controller_Action
{
    /**
     * Scheme key => sidebar/heading label. Order sets the sidebar order.
     *
     * Labels reuse the old Configure-menu wording minus the word "Settings", so
     * someone who knew the entry as "HIV Serology Settings" still recognises it.
     * Translated at render time, not here.
     */
    public const SCHEME_LABELS = [
        'dts'     => 'HIV Serology',
        'vl'      => 'VL',
        'eid'     => 'EID',
        'tb'      => 'TB',
        'covid19' => 'SARS-CoV-2',
        'recency' => 'HIV Recency (RTRI)',
    ];

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges ?? '');
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
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $adminSession = new Zend_Session_Namespace('administrators');
        $activeSchemes = (array) ($adminSession->activeSchemes ?? []);

        // Only schemes this instance actually runs are reachable — the sidebar
        // hides the rest, and a hand-typed URL must not get past that either.
        $selectable = array_values(array_filter(
            array_keys(self::SCHEME_LABELS),
            static fn ($scheme) => in_array($scheme, $activeSchemes)
        ));

        if (empty($selectable)) {
            $this->redirect('/admin');
            return;
        }

        $scheme = (string) $request->getParam('scheme', '');
        if (!in_array($scheme, $selectable)) {
            // No scheme (or a bogus one): land on the first the instance runs.
            $this->redirect('/admin/scheme-config/' . $selectable[0]);
            return;
        }

        if ($request->isPost()) {
            $this->saveScheme($scheme, $request);
        }

        // The sidebar resolves its own state from the session and request — see
        // partials/scheme-config-nav.phtml. Only the heading needs these.
        $this->view->currentScheme = $scheme;
        $this->view->schemeLabels = self::SCHEME_LABELS;
        $this->loadSchemeView($scheme);
    }

    /**
     * Per-scheme save. Bodies are lifted from the old *-settings controllers.
     */
    private function saveScheme(string $scheme, Zend_Controller_Request_Http $request): void
    {
        $common = new Application_Service_Common();
        $params = $this->getAllParams();
        $before = $this->settingsSnapshot($scheme);

        switch ($scheme) {
            case 'dts':
                $schemeService = new Application_Service_Schemes();
                // Recommended national-algorithm kits, one set per DTS variant.
                foreach (['dts' => 'dtsTestkit', 'dts+syphilis' => 'dtsSyphilisTestkit', 'dts+rtri' => 'dtsRtriTestkit'] as $variant => $field) {
                    $testKits = [
                        1 => $request->getPost($field . '1'),
                        2 => $request->getPost($field . '2'),
                        3 => $request->getPost($field . '3'),
                    ];
                    $schemeService->setRecommededDtsTestkit($testKits, $variant);
                }
                if (!empty($params['dts'])) {
                    $common->saveSchemeConfigByName(json_encode($params['dts']), 'dts');
                }
                $this->audit('Updated HIV serology settings', $scheme, $before);
                break;

            case 'covid19':
                $schemeService = new Application_Service_Schemes();
                $testPlatforms = [
                    1 => $request->getPost('testPlatform1'),
                    2 => $request->getPost('testPlatform2'),
                    3 => $request->getPost('testPlatform3'),
                ];
                $schemeService->setRecommededCovid19TestTypes($testPlatforms);
                if (!empty($params['covid19'])) {
                    $common->saveSchemeConfigByName(json_encode($params['covid19']), 'covid19');
                }
                $this->audit('Updated SARS-CoV-2 settings', $scheme, $before);
                break;

            case 'eid':
                $eid = (isset($params['eid']) && is_array($params['eid'])) ? $params['eid'] : [];
                // Passing Score is optional. Blank or out-of-range is stored as blank so
                // evaluation falls back to 100 (see Application_Model_Eid). A valid value
                // must be a whole number between 1 and 100.
                $passingScore = isset($eid['passPercentage']) ? trim((string) $eid['passPercentage']) : '';
                if ($passingScore === '' || !is_numeric($passingScore)) {
                    $eid['passPercentage'] = '';
                } else {
                    $passingScore = (int) $passingScore;
                    $eid['passPercentage'] = ($passingScore >= 1 && $passingScore <= 100) ? $passingScore : '';
                }
                $common->saveSchemeConfigByName(json_encode($eid), 'eid');
                $this->audit('Updated EID settings', $scheme, $before);
                break;

            default:
                // vl, tb, recency: plain JSON blob, no extra handling.
                if (!empty($params[$scheme])) {
                    $common->saveSchemeConfigByName(json_encode($params[$scheme]), $scheme);
                }
                $this->audit('Updated ' . strtoupper($scheme) . ' settings', $scheme, $before);
                break;
        }

        // Settings just changed: any already-scored shipment for this scheme still holds
        // a score computed against the OLD settings. Surface them so the admin can opt to
        // re-evaluate. Finalized shipments are intentionally excluded (locked).
        $this->view->reEvalScheme = $scheme;
        $this->view->reEvalShipmentIds = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds($scheme);
    }

    /**
     * Log a settings save with what changed. Scoring changes are flagged and
     * list the already-scored shipments that still hold scores from the old
     * settings.
     *
     * @param array{values: array<string, string>, labels: array<string, string>} $before
     */
    private function audit(string $message, string $scheme, array $before): void
    {
        $after = $this->settingsSnapshot($scheme);
        $labels = $before['labels'] + $after['labels'] + Application_Model_DbTable_SchemeConfig::FIELD_LABELS;
        $changes = Application_Model_DbTable_AuditLog::changes($before['values'], $after['values'], $labels);

        $scoringKeys = Application_Model_DbTable_SchemeConfig::SCORING_FIELDS;
        foreach (array_keys($after['values'] + $before['values']) as $key) {
            // Recommended kits decide whether a result can pass, so they count as scoring.
            if (str_starts_with((string) $key, 'recommended')) {
                $scoringKeys[] = (string) $key;
            }
        }

        $extra = [
            'entity' => ['type' => 'scheme', 'id' => $scheme, 'label' => self::SCHEME_LABELS[$scheme] ?? strtoupper($scheme)],
            'changes' => $changes,
            'importantFields' => $scoringKeys,
        ];
        if ($changes !== []) {
            $codes = $this->reEvaluatableShipmentCodes($scheme);
            if ($codes !== []) {
                $extra['list'] = ['title' => 'Shipments scored with the earlier settings', 'rows' => $codes];
            }
        }
        Application_Model_DbTable_AuditLog::record($message, 'config', $extra);
    }

    /**
     * The scheme's settings and recommended kits as flat key => text pairs,
     * with a readable label for each generated key.
     *
     * @return array{values: array<string, string>, labels: array<string, string>}
     */
    private function settingsSnapshot(string $scheme): array
    {
        $values = [];
        $labels = [];
        $config = Pt_Commons_SchemeConfig::get($scheme, false);
        if ($config instanceof Zend_Config) {
            $config = $config->toArray();
        }
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                $values[(string) $key] = is_array($value) ? implode(', ', array_map('strval', array_filter($value, 'is_scalar'))) : (string) $value;
            }
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($scheme === 'dts') {
            $panels = ['dts' => 'Standard DTS Panel', 'dts+syphilis' => 'DTS + Syphilis Combined Panel', 'dts+rtri' => 'DTS + RTRI Combined Panel'];
            $rows = $db->fetchAll(
                $db->select()
                    ->from(['r' => 'dts_recommended_testkits'], ['test_no', 'dts_test_mode'])
                    ->joinLeft(['k' => 'r_testkitnames'], 'k.TestKitName_ID = r.testkit', ['name' => new Zend_Db_Expr('COALESCE(k.TestKit_Name, r.testkit)')])
                    ->order(['r.dts_test_mode', 'r.test_no', 'name'])
            );
            foreach ($rows as $row) {
                $key = 'recommendedKits.' . $row['dts_test_mode'] . '.' . $row['test_no'];
                $values[$key] = isset($values[$key]) ? $values[$key] . ', ' . $row['name'] : (string) $row['name'];
                $labels[$key] = ($panels[$row['dts_test_mode']] ?? $row['dts_test_mode']) . ' › Test ' . $row['test_no'];
            }
            foreach ($panels as $mode => $panel) {
                foreach ([1, 2, 3] as $testNo) {
                    $key = 'recommendedKits.' . $mode . '.' . $testNo;
                    $values += [$key => ''];
                    $labels[$key] = $panel . ' › Test ' . $testNo;
                }
            }
        } elseif ($scheme === 'covid19') {
            $rows = $db->fetchAll(
                $db->select()
                    ->from(['r' => 'covid19_recommended_test_types'], ['test_no'])
                    ->joinLeft(['t' => 'r_test_type_covid19'], 't.test_type_id = r.test_type', ['name' => new Zend_Db_Expr('COALESCE(t.test_type_name, r.test_type)')])
                    ->order(['r.test_no', 'name'])
            );
            foreach ([1, 2, 3] as $testNo) {
                $values['recommendedTestTypes.' . $testNo] = '';
                $labels['recommendedTestTypes.' . $testNo] = 'Recommended TestType › ' . $testNo;
            }
            foreach ($rows as $row) {
                $key = 'recommendedTestTypes.' . $row['test_no'];
                $values[$key] = $values[$key] === '' ? (string) $row['name'] : $values[$key] . ', ' . $row['name'];
            }
        }
        return ['values' => $values, 'labels' => $labels];
    }

    /** @return string[] codes of scored, unfinalized shipments of this scheme */
    private function reEvaluatableShipmentCodes(string $scheme): array
    {
        $ids = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds($scheme);
        if (empty($ids)) {
            return [];
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return array_map('strval', $db->fetchCol(
            $db->select()->from('shipment', ['shipment_code'])->where('shipment_id IN (?)', array_map('intval', (array) $ids))->order('shipment_code')
        ));
    }

    /**
     * View vars each partial needs, matching what the old controllers set.
     */
    private function loadSchemeView(string $scheme): void
    {
        switch ($scheme) {
            case 'dts':
                $dtsModel = new Application_Model_Dts();
                $this->view->dtsConfig = Pt_Commons_SchemeConfig::get('dts');
                $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(true);
                $this->view->dtsRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts');
                $this->view->dtsSyphilisRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+syphilis');
                $this->view->dtsRtriRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+rtri');
                break;

            case 'covid19':
                $schemeService = new Application_Service_Schemes();
                $this->view->covid19Config = Pt_Commons_SchemeConfig::get('covid19');
                $this->view->allTestTypes = $schemeService->getAllCovid19TestTypeResponseWise(true);
                $this->view->recommendedTesttypes = $schemeService->getRecommededCovid19TestTypes();
                break;

            default:
                // vl, tb, eid, recency: config blob only.
                $this->view->{$scheme . 'Config'} = Pt_Commons_SchemeConfig::get($scheme);
                break;
        }
    }
}
