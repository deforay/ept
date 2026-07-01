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

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Added a new generic test - {$schemeCode}", 'config');

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

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated generic test - {$schemeCode}", 'config');

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
            $id = base64_decode($this->_getParam('id'));
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
        $id = base64_decode($this->_getParam('id'));
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

        $id = base64_decode($this->_getParam('id'));
        $schemeService = new Application_Service_Schemes();
        $export = $schemeService->exportGenericTests([$id]);
        if (empty($export['tests'])) {
            $this->getResponse()->setHttpResponseCode(404);
            echo 'Custom test not found.';
            return;
        }

        $filename = 'custom-test-' . trim(preg_replace('/[^A-Za-z0-9]+/', '-', $id), '-');
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Exported custom test - ' . $id, 'config');

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

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Imported ' . count($summary['imported']) . ' custom test(s)', 'config');
        }
    }

    public function importDetailsAction()
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
}
