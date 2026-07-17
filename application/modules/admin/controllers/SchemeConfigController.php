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
                $this->audit('Updated HIV serology settings');
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
                $this->audit('Updated SARS-CoV-2 settings');
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
                $this->audit('Updated EID settings');
                break;

            default:
                // vl, tb, recency: plain JSON blob, no extra handling.
                if (!empty($params[$scheme])) {
                    $common->saveSchemeConfigByName(json_encode($params[$scheme]), $scheme);
                }
                $this->audit('Updated ' . strtoupper($scheme) . ' settings');
                break;
        }

        // Settings just changed: any already-scored shipment for this scheme still holds
        // a score computed against the OLD settings. Surface them so the admin can opt to
        // re-evaluate. Finalized shipments are intentionally excluded (locked).
        $this->view->reEvalScheme = $scheme;
        $this->view->reEvalShipmentIds = (new Application_Service_Evaluation())->getReEvaluatableShipmentIds($scheme);
    }

    private function audit(string $message): void
    {
        (new Application_Model_DbTable_AuditLog())->addNewAuditLog($message, 'config');
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
