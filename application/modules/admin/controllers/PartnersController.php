<?php

class Admin_PartnersController extends Zend_Controller_Action
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
            $partnerService = new Application_Service_Partner();
            $partnerService->getAllPartner($parameters);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $partnerService = new Application_Service_Partner();
            $newId = $partnerService->addPartner($params);

            $name = trim((string) ($params['partnerName'] ?? ''));
            Application_Model_DbTable_AuditLog::record('Added a new partner - ' . ($name !== '' ? $name : '(unnamed)'), 'config', [
                'entity' => ['type' => 'config', 'id' => $newId ?: null, 'label' => 'Partner: ' . $name],
                'facts' => ['Link' => $params['link'] ?? ''],
            ]);

            $this->redirect('/admin/partners');
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $partnerService = new Application_Service_Partner();
        if ($request->isPost()) {
            $params = $request->getPost();
            $partnerId = trim((string) ($params['partnerId'] ?? ''));
            $before = $this->partnerAuditFields($partnerId);
            $partnerService->updatePartner($params);

            $name = trim((string) ($params['partnerName'] ?? ''));
            $after = $this->partnerAuditFields($partnerId);
            $extra = ['entity' => ['type' => 'config', 'id' => $partnerId, 'label' => 'Partner: ' . $name]];
            if ($before !== null && $after !== null) {
                $extra['changes'] = Application_Model_DbTable_AuditLog::changes($before, $after, self::AUDIT_LABELS);
            }
            Application_Model_DbTable_AuditLog::record('Updated partner - ' . ($name !== '' ? $name : '(unnamed)'), 'config', $extra);

            $this->redirect('/admin/partners');
        }
        if ($this->hasParam('id')) {
            $partnerId = (int)$this->_getParam('id');
            $this->view->partner = $partnerService->getPartner($partnerId);
        } else {
            $this->redirect('/admin/partners');
        }
    }

    private const AUDIT_LABELS = [
        'partner_name' => 'Partner Name',
        'link' => 'Link',
        'logo_image' => 'Logo',
        'sort_order' => 'Sort Order',
        'status' => 'Status',
    ];

    /** @return array<string, string>|null */
    private function partnerAuditFields(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()->from('partners', array_keys(self::AUDIT_LABELS))->where('partner_id = ?', $id)
            );
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
