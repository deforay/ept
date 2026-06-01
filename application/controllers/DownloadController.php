<?php

class DownloadController extends Zend_Controller_Action
{
    public function init()
    {
    }

    public function preDispatch()
    {
    }

    public function indexAction()
    {
        // /d/ is the legacy download route — base64-encoded path, no expiry, no
        // signature. New code should use /dl/ via Pt_Commons_SignedDownload::url()
        // (encrypted payload with built-in expiry). This route remains for the
        // existing call sites until they're all migrated.
        $filePath = base64_decode($this->_getParam('filepath'));

        // Resolve the real path to prevent directory traversal attacks
        $realPath = realpath($filePath);

        // Define allowed base directories
        $allowedBaseDirs = [
            realpath(DOWNLOADS_FOLDER),      // downloads folder for reports
            realpath(UPLOAD_PATH),           // public/uploads folder
            realpath(TEMP_UPLOAD_PATH),      // public/temporary folder
        ];

        // Remove any false values (in case a directory doesn't exist)
        $allowedBaseDirs = array_filter($allowedBaseDirs);

        // Validate that the resolved path is within an allowed directory
        $isAllowed = false;
        if ($realPath !== false) {
            foreach ($allowedBaseDirs as $allowedBase) {
                if (strpos($realPath, $allowedBase . DIRECTORY_SEPARATOR) === 0 || $realPath === $allowedBase) {
                    $isAllowed = true;
                    break;
                }
            }
        }

        if (!$isAllowed) {
            throw new Zend_Controller_Action_Exception('File not found', 404);
        }

        // Additional check: ensure the file exists and is a regular file
        if (!is_file($realPath)) {
            throw new Zend_Controller_Action_Exception('File not found', 404);
        }

        $this->view->filePath = $realPath;
        $this->_helper->layout()->disableLayout();
        //$this->_helper->viewRenderer->setNoRender(true);

        $auditDb = new Application_Model_DbTable_AuditLog();
        $signedTag = Pt_Commons_DownloadUrlSigner::hasSignature($exp, $sig) ? '[signed]' : '[unsigned]';
        $auditDb->addNewAuditLog('Downloaded file ' . $signedTag . ' - ' . basename($realPath), 'download');
    }
}
