<?php

class Admin_AuditLogController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
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
        $ajaxContext->addActionContext('feed', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $systemAdmin = new Application_Service_SystemAdmin();
        $this->view->systemAdmin = $systemAdmin->getSystemAllAdmin();

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $contextRows = $db->fetchCol(
            $db->select()
                ->from('audit_log', ['type'])
                ->where("type IS NOT NULL AND type != ''")
                ->group('type')
                ->order('type ASC')
        );
        $this->view->contextTypes = $contextRows;
    }

    public function feedAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $params = $this->getAllParams();
        $auditLog = new Application_Model_DbTable_AuditLog();
        $payload = $auditLog->fetchAuditLogFeed($params);

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($payload));
    }

    /**
     * Spreadsheet apps run cells that start with = + - @ as formulas. Entries
     * hold text users typed, such as lab names, so prefix those with a quote.
     */
    private static function csvCell($value): string
    {
        $text = (string) $value;
        return ($text !== '' && strpbrk($text[0], "=+-@\t\r") !== false) ? "'" . $text : $text;
    }

    /**
     * CSV of the entries that match the page's current filters, one row per
     * entry with changes, facts and lists flattened into text columns.
     */
    public function exportAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $params = $this->getAllParams();
        $auditLog = new Application_Model_DbTable_AuditLog();
        $payload = $auditLog->fetchAuditLogFeed($params, true);
        $translate = Zend_Registry::get('translate');

        Application_Model_DbTable_AuditLog::record(
            sprintf('Downloaded audit log (%d rows)', count($payload['items'])),
            'download'
        );

        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map([$translate, '_'], [
            'Date and time', 'Action', 'Important', 'Reason', 'Item type', 'Item',
            'Changes', 'Details', 'User', 'Email', 'Role', 'On behalf of',
            'Context', 'IP address', 'Browser', 'Channel', 'Page', 'Session hash', 'Request ID',
        ]), ',', '"', '');
        foreach ($payload['items'] as $item) {
            $changes = array_map(
                static fn ($c) => ($c['label'] ?? '') . ': ' . (($c['from'] ?? '') === '' ? '∅' : $c['from']) . ' → ' . (($c['to'] ?? '') === '' ? '∅' : $c['to']),
                $item['changes'] ?? []
            );
            $details = array_map(static fn ($f) => ($f['label'] ?? '') . ': ' . ($f['value'] ?? ''), $item['facts'] ?? []);
            if (!empty($item['list']['rows'])) {
                $details[] = ($item['list']['title'] ?? '') . ': ' . implode('; ', $item['list']['rows']);
            }
            fputcsv($out, array_map([self::class, 'csvCell'], [
                $item['timestamp'],
                $item['action'],
                !empty($item['important']) ? $translate->_('Yes') : '',
                $item['reason'] ?? '',
                $item['entityTypeLabel'] ?? '',
                $item['entityLabel'] ?? '',
                implode("\n", $changes),
                implode("\n", $details),
                $item['userName'],
                $item['userEmail'],
                $item['userRole'],
                $item['onBehalfOf'] ?? '',
                $item['context'],
                $item['ipAddress'],
                $item['userAgent'] ?: trim(($item['browser'] ?? '') . ' ' . ($item['os'] ?? '')),
                $item['channel'] ?? '',
                $item['route'] ?? '',
                $item['sessionHash'],
                $item['requestId'] ?? '',
            ]), ',', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $this->getResponse()
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="audit-log-' . date('Y-m-d-His') . '.csv"')
            ->setBody((string) $csv);
    }
}
