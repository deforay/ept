<?php

class Admin_EidAssayController extends Zend_Controller_Action
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
            ->addActionContext('change-status', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $vlAssayService = new Application_Service_VlAssay();
            if (isset($parameters['fromSource']) && $parameters['fromSource'] == 'extraction') {
                $vlAssayService->getAllEidExtractionAssay($parameters);
            } elseif (isset($parameters['fromSource']) && $parameters['fromSource'] == 'detection') {
                $vlAssayService->getAllEidDetectionAssay($parameters);
            }
        } else {
            $this->view->source = '';
            if ($this->hasParam('fromSource')) {
                $this->view->source = $this->_getParam('fromSource');
            }
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $name = trim((string) ($params['name'] ?? ''));
            $nameLabel = $name !== '' ? $name : '(unnamed)';
            if (isset($params['category']) && trim($params['category']) == 'extraction') {
                $newId = $vlAssayService->addEidExtractionAssay($params);
                Application_Model_DbTable_AuditLog::record("Added a new EID extraction assay - {$nameLabel}", 'config', [
                    'entity' => ['type' => 'config', 'id' => $newId ?: null, 'label' => 'EID extraction assay: ' . $name],
                ]);
                $this->redirect('/admin/eid-assay/index/fromSource/' . $params['category']);
            } elseif (isset($params['category']) && trim($params['category']) == 'detection') {
                $newId = $vlAssayService->addEidDetectionAssay($params);
                Application_Model_DbTable_AuditLog::record("Added a new EID detection assay - {$nameLabel}", 'config', [
                    'entity' => ['type' => 'config', 'id' => $newId ?: null, 'label' => 'EID detection assay: ' . $name],
                ]);
                $this->redirect('/admin/eid-assay/index/fromSource/' . $params['category']);
            }
            $this->redirect('/admin/eid-assay/');
        } else {
            $this->view->source = '';
            if ($this->hasParam('source')) {
                $this->view->source = $this->_getParam('source');
            }
        }
    }

    public function editAction()
    {
        $this->redirect('/admin/eid-assay');
    }

    public function changeStatusAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $status = (string) ($params['switchStatus'] ?? '');
            $source = (string) ($params['formSource'] ?? '');
            $before = $this->assayRow($source, (string) ($params['id'] ?? ''));
            if ($source == 'extraction') {
                $this->view->result = $vlAssayService->changeEidExtractionNameStatus($params);
            } elseif ($source == 'detection') {
                $this->view->result = $vlAssayService->changeEidDetectionNameStatus($params);
            }
            if ($source !== '') {
                $this->recordStatusChange($source, (string) ($params['id'] ?? ''), $status, $before);
            }
        }
    }

    private const ASSAY_TABLES = [
        'extraction' => 'r_eid_extraction_assay',
        'detection' => 'r_eid_detection_assay',
    ];

    /** @return array{name: string, status: string}|null */
    private function assayRow(string $source, string $id): ?array
    {
        if (!isset(self::ASSAY_TABLES[$source]) || $id === '') {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()->from(self::ASSAY_TABLES[$source], ['name', 'status'])->where('id = ?', $id)
            );
            return $row ? ['name' => (string) $row['name'], 'status' => (string) $row['status']] : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @param array{name: string, status: string}|null $before */
    private function recordStatusChange(string $source, string $id, string $status, ?array $before): void
    {
        $after = $this->assayRow($source, $id);
        $name = $after['name'] ?? ($before['name'] ?? '');
        $extra = ['entity' => ['type' => 'config', 'id' => $id, 'label' => "EID {$source} assay: {$name}"]];
        if ($before !== null && $after !== null) {
            $extra['changes'] = Application_Model_DbTable_AuditLog::changes(
                ['status' => $before['status']],
                ['status' => $after['status']],
                ['status' => 'Status']
            );
            if ($before['status'] === 'active' && $after['status'] !== 'active') {
                $extra['important'] = true;
                $extra['reason'] = 'The assay is no longer offered in result entry.';
            }
        }
        Application_Model_DbTable_AuditLog::record(
            "Changed EID {$source} assay status" . ($status !== '' ? " to '{$status}'" : '') . ($name !== '' ? " - {$name}" : ''),
            'config',
            $extra
        );
    }
}
