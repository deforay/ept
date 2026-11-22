<?php

/**
 * Link to the Audit Log, filtered to the history of one item.
 *
 * Usage in views:
 *   <?= $this->auditHistoryLink('shipment', $shipmentId, $shipmentCode) ?>
 *
 * Returns an empty string for admins who cannot open the Audit Log, so a page
 * never shows a link that leads to a redirect.
 */
class Pt_Helper_View_AuditHistoryLink extends Zend_View_Helper_Abstract
{
    /** The privilege the Audit Log page requires. See Admin_AuditLogController::init(). */
    private const PRIVILEGE = 'analyze-generate-reports';

    /**
     * @param string $entityType shipment, participant, scheme, testkit, ...
     * @param int|string $entityId the id the audit log stores for the item
     * @param string $entityLabel the name shown in the history banner
     * @param string $text link text; defaults to "History"
     */
    public function auditHistoryLink(string $entityType, $entityId, string $entityLabel = '', string $text = ''): string
    {
        $entityId = (string) $entityId;
        if ($entityType === '' || $entityId === '' || !self::canView()) {
            return '';
        }
        $translator = $this->view->translate ?? Zend_Registry::get('translate');
        $text = $text !== '' ? $text : (string) $translator->_('History');
        $title = (string) $translator->_('Show every audit log entry for this item');
        $base = rtrim((string) Zend_Controller_Front::getInstance()->getBaseUrl(), '/');
        $url = $base . '/admin/audit-log?' . http_build_query(array_filter([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'entityLabel' => $entityLabel,
        ], static fn ($v) => $v !== ''));
        return '<a class="audit-history-link" href="' . htmlspecialchars($url, ENT_QUOTES) . '" title="'
            . htmlspecialchars($title, ENT_QUOTES) . '"><i class="icon-time"></i> '
            . htmlspecialchars($text, ENT_QUOTES) . '</a>';
    }

    private static function canView(): bool
    {
        $admin = new Zend_Session_Namespace('administrators');
        if (empty($admin->primary_email)) {
            return false;
        }
        $privileges = array_map('trim', explode(',', (string) ($admin->privileges ?? '')));
        return in_array(self::PRIVILEGE, $privileges, true);
    }
}
