<?php

class Admin_HomeSectionLinksController extends Zend_Controller_Action
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
            $params = $this->getAllParams();
            $homeSectionService = new Application_Service_HomeSection();
            $homeSectionService->getAllHomeSectionInGrid($params);
        }

        // Get resource section headings for display
        $common = new Application_Service_Common();
        $this->view->home = json_decode($common->getConfig('home'));
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $newId = $homeSectionService->saveHomeSection($params);
            if ($newId === false) {
                $this->redirect('/admin/home-section-links/add');
            }

            $heading = $this->linkHeading($params);
            $after = $this->linkAuditFields((string) $newId);
            $facts = [];
            foreach ($after ?? [] as $field => $value) {
                $facts[self::AUDIT_LABELS[$field]] = $value;
            }
            Application_Model_DbTable_AuditLog::record('Added a new home section link' . ($heading !== '' ? " - {$heading}" : ''), 'config', [
                'entity' => ['type' => 'config', 'id' => $newId ?: null, 'label' => 'Resource link: ' . $heading],
                'facts' => $facts,
            ]);

            $this->redirect('/admin/home-section-links');
        }

        // Get resource section headings for dropdown labels
        $common = new Application_Service_Common();
        $this->view->home = json_decode($common->getConfig('home'));
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $linkId = trim((string) ($params['homeSectionId'] ?? ''));
            $before = $this->linkAuditFields($linkId);
            if ($homeSectionService->saveHomeSection($params) === false) {
                $this->redirect('/admin/home-section-links/edit/id/' . base64_encode($linkId));
            }

            $heading = $this->linkHeading($params);
            $after = $this->linkAuditFields($linkId);
            $extra = ['entity' => ['type' => 'config', 'id' => $linkId, 'label' => 'Resource link: ' . $heading]];
            if ($before !== null && $after !== null) {
                $extra['changes'] = Application_Model_DbTable_AuditLog::changes($before, $after, self::AUDIT_LABELS);
            }
            Application_Model_DbTable_AuditLog::record('Updated home section link' . ($heading !== '' ? " - {$heading}" : ''), 'config', $extra);

            $this->redirect('/admin/home-section-links');
        }
        if ($this->hasParam('id')) {
            $id = (int) base64_decode($this->_getParam('id'));
            $this->view->result = $homeSectionService->getHomeSectionById($id);
        }

        // Get resource section headings for dropdown labels
        $common = new Application_Service_Common();
        $this->view->home = json_decode($common->getConfig('home'));
    }

    public function getDisplayOrderAction()
    {
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $maxSortOrder = $homeSectionService->getDisplayOrder($params);
            // Send the response as JSON
            $this->_helper->json([
                'maxSortOrder' => $maxSortOrder,
            ]);
        }
    }

    private const AUDIT_LABELS = [
        'section' => 'Resource Section',
        'type' => 'Type',
        'link' => 'Link',
        'section_file' => 'File',
        'text' => 'Display Text',
        'icon' => 'Icon',
        'display_order' => 'Display Order',
        'status' => 'Status',
    ];

    /** @param array<string, mixed> $params */
    private function linkHeading(array $params): string
    {
        foreach (['heading', 'displayText', 'link'] as $key) {
            $value = trim((string) ($params[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    /** @return array<string, string>|null */
    private function linkAuditFields(string $id): ?array
    {
        if ($id === '' || !ctype_digit($id)) {
            return null;
        }
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $row = $db->fetchRow(
                $db->select()->from('home_sections', array_keys(self::AUDIT_LABELS))->where('id = ?', (int) $id)
            );
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
