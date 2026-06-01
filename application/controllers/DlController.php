<?php

/**
 * /dl/ — new signed-download route. URL: /dl/<encrypted-token>
 *
 * Token is an AES-256-GCM ciphertext produced by Pt_Commons_SignedDownload::url()
 * and carries the absolute path, an expiry timestamp, and an auth-required
 * flag. Replaces the legacy /d/<base64> route, which exposes the path in plain
 * text and has no expiry. /d/ remains operational until all callers migrate.
 */
class DlController extends Zend_Controller_Action
{
    public function init()
    {
    }

    public function preDispatch()
    {
    }

    public function indexAction()
    {
        $token = (string) $this->_getParam('token');
        if ($token === '') {
            throw new Zend_Controller_Action_Exception('Missing token', 400);
        }

        $decoded = Pt_Commons_SignedDownload::decode($token);
        if ($decoded === null) {
            throw new Zend_Controller_Action_Exception('Download link is invalid or has expired', 410);
        }

        if (!empty($decoded['auth']) && !self::isLoggedIn()) {
            // Auth-protected link, no session. Tell the caller; the front-end
            // can choose to bounce the user through login and back.
            throw new Zend_Controller_Action_Exception('Authentication required', 401);
        }

        $realPath = realpath($decoded['path']);

        $allowedBaseDirs = array_filter([
            realpath(DOWNLOADS_FOLDER),
            realpath(UPLOAD_PATH),
            realpath(TEMP_UPLOAD_PATH),
        ]);

        $isAllowed = false;
        if ($realPath !== false) {
            foreach ($allowedBaseDirs as $allowedBase) {
                if ($realPath === $allowedBase || strpos($realPath, $allowedBase . DIRECTORY_SEPARATOR) === 0) {
                    $isAllowed = true;
                    break;
                }
            }
        }
        if (!$isAllowed || !is_file($realPath)) {
            throw new Zend_Controller_Action_Exception('File not found', 404);
        }

        $this->view->filePath = $realPath;
        $this->_helper->layout()->disableLayout();
        // Reuse the existing /d/ view template — same Content-Disposition streaming.
        // setNoRender prevents Zend from auto-resolving dl/index.phtml.
        $this->_helper->viewRenderer->setNoRender(true);
        echo $this->view->render('download/index.phtml');

        $auditDb = new Application_Model_DbTable_AuditLog();
        $authTag = !empty($decoded['auth']) ? '[signed,auth]' : '[signed]';
        $auditDb->addNewAuditLog('Downloaded file ' . $authTag . ' - ' . basename($realPath), 'download');
    }

    /** True if any of the recognised ePT sessions has an identity. */
    private static function isLoggedIn(): bool
    {
        $candidates = ['administrators', 'datamanagers', 'loggedUser'];
        foreach ($candidates as $ns) {
            $session = new Zend_Session_Namespace($ns);
            if (!empty($session->dm_id) || !empty($session->admin_id) || !empty($session->partcipant_id) || !empty($session->participant_id)) {
                return true;
            }
        }
        return false;
    }
}
