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
        $rawFilepath = (string) $this->_getParam('filepath');
        $exp = $this->_getParam('exp');
        $sig = $this->_getParam('sig');

        // Backward-compat: if the link carries exp + sig, validate them. Links
        // without either continue to work for now so existing emailed/cached
        // URLs keep functioning — flip the policy to require signatures once
        // every caller is migrated to Pt_Commons_DownloadUrlSigner::sign().
        if (Pt_Commons_DownloadUrlSigner::hasSignature($exp, $sig)) {
            if (!Pt_Commons_DownloadUrlSigner::verify($rawFilepath, $exp, $sig)) {
                throw new Zend_Controller_Action_Exception('Download link is invalid or has expired', 410);
            }
        }

        $filePath = base64_decode($rawFilepath);

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
