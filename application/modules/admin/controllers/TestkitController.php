<?php

class Admin_TestkitController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');

        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-testkit', 'html')
            ->addActionContext('update-status', 'html')
            ->initContext();

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

        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $schemeService = new Application_Service_Schemes();
            $schemeService->getAllDtsTestKitInGrid($params);
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
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $testkitId = $schemeService->addTestkit($params);
            if ($testkitId === '') {
                $this->redirect('/admin/testkit');
                return;
            }

            $name = trim((string) ($params['testKitName'] ?? ''));
            Application_Model_DbTable_AuditLog::record('Added a new test kit - ' . ($name !== '' ? $name : '(unnamed)'), 'config', [
                'entity' => ['type' => 'testkit', 'id' => $testkitId, 'label' => $name],
                'facts' => [
                    'Scheme' => implode(', ', (array) ($params['scheme'] ?? [])),
                    'Choose a Test Number to map test kit' => implode(', ', (array) ($params['testStages'] ?? [])),
                ],
            ]);

            $this->redirect('/admin/testkit');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        $this->view->schemeList = $schemeService->getFullSchemeList();
        if ($request->isPost()) {
            $params = $request->getPost();
            $testkitId = trim((string) ($params['testkitId'] ?? ''));
            $kits = new Application_Model_DbTable_Testkitnames();
            $before = $kits->auditSnapshot($testkitId);
            $schemeService->updateTestkit($params);
            $after = $kits->auditSnapshot($testkitId);

            $name = trim((string) ($params['testKitName'] ?? ''));
            $testFields = array_values(array_filter(
                array_keys($after['labels'] + $before['labels']),
                static fn (string $key) => str_starts_with($key, 'tests.')
            ));
            Application_Model_DbTable_AuditLog::record('Updated test kit - ' . ($name !== '' ? $name : '(unnamed)'), 'config', [
                'entity' => ['type' => 'testkit', 'id' => $testkitId, 'label' => $after['name']],
                'changes' => Application_Model_DbTable_AuditLog::changes($before['values'], $after['values'], $after['labels'] + $before['labels']),
                // Which tests a kit counts for, and whether it is active, decide what labs can report.
                'importantFields' => array_merge($testFields, ['Approval']),
            ]);

            $this->redirect('/admin/testkit');
        } elseif ($this->hasParam('53s5k85_8d')) {
            $id = base64_decode($this->_getParam('53s5k85_8d'));
            $this->view->result = $schemeService->getDtsTestkit($id);
        } else {
            $this->redirect('admin/testkit/index');
        }
    }

    public function standardKitAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $schemeService = new Application_Service_Schemes();
        if ($request->isPost()) {
            $params = $request->getPost();
            $stage = trim((string) ($params['testKitStage'] ?? ''));
            $kits = new Application_Model_DbTable_Testkitnames();
            $before = $stage !== '' ? $kits->stageKitNames($stage) : [];
            $schemeService->updateTestkitStage($params);
            $after = $stage !== '' ? $kits->stageKitNames($stage) : [];

            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));
            $rows = array_merge(
                array_map(static fn ($kit) => '+ ' . $kit, $added),
                array_map(static fn ($kit) => '− ' . $kit, $removed)
            );
            Application_Model_DbTable_AuditLog::record('Updated test kit stage settings' . ($stage !== '' ? ' - ' . $stage : ''), 'config', [
                'entity' => ['type' => 'config', 'id' => 'testkit-stage-' . $stage, 'label' => $stage],
                'facts' => ['Added' => count($added), 'Removed' => count($removed)],
                'list' => ['title' => 'Test kits added (+) and removed (−)', 'rows' => $rows],
                'important' => $removed !== [],
                'reason' => $removed !== [] ? 'Removes test kits that labs could report' : '',
            ]);

            $this->redirect('/admin/testkit/standard-kit');
        }
        $this->view->schemeList = $schemeService->getGenericSchemeLists();
    }

    public function getTestkitAction()
    {
        if ($this->hasParam('stage')) {
            $stage = $this->_getParam('stage');
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->getAllDtsTestKitList(true, $stage);
            $this->view->testkitStage = $stage;
        }
    }

    public function updateStatusAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $stage = $this->_getParam('stage');
            $params = $request->getPost();
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            // updateTestKitStatus() approves every pending kit, so list them first.
            $pending = array_map('strval', $db->fetchCol(
                $db->select()->from('r_testkitnames', ['TestKit_Name'])->where('testkit_status = ?', 'pending')->order('TestKit_Name')
            ));
            $dtsModel = new Application_Model_Dts();
            $this->view->testkitList = $dtsModel->updateTestKitStatus($params);
            $this->view->testkitStage = $stage;

            $status = (string) ($params['status'] ?? '');
            Application_Model_DbTable_AuditLog::record('Changed test kit status' . ($status !== '' ? " to '{$status}'" : ''), 'config', [
                'list' => $status === 'approve' ? ['title' => 'Test kits approved', 'rows' => $pending] : null,
            ]);
        }
    }
}
