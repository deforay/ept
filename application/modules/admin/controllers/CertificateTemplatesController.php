<?php

class Admin_CertificateTemplatesController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $privileges = explode(',', $adminSession->privileges ?? '');
        if (!in_array('config-ept', $privileges) && !in_array('manage-shipments', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
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
        $service = new Application_Service_CertificateTemplates();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $service->saveCertificateTemplate($params);
            $this->redirect("/admin/certificate-templates");
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->certificateTemplates = $service->getAllCertificateTemplates();
    }

    /**
     * Download/preview a certificate template PDF
     * GET params: scheme (scheme_id), type (participation|excellence)
     */
    public function downloadAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $scheme = $this->_getParam('scheme');
        $type = $this->_getParam('type');

        if (empty($scheme) || empty($type)) {
            $this->getResponse()->setHttpResponseCode(400);
            echo 'Missing scheme or type parameter';
            return;
        }

        // Validate type parameter
        if (!in_array($type, ['participation', 'excellence'])) {
            $this->getResponse()->setHttpResponseCode(400);
            echo 'Invalid type parameter. Must be participation or excellence';
            return;
        }

        $service = new Application_Service_CertificateTemplates();
        $filePath = $service->getTemplateFilePath($scheme, $type);

        if (!$filePath || !file_exists($filePath)) {
            $this->getResponse()->setHttpResponseCode(404);
            echo 'Template file not found';
            return;
        }

        // Serve the PDF file
        $filename = basename($filePath);
        $this->getResponse()
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setHeader('Content-Length', filesize($filePath))
            ->setHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->setHeader('Pragma', 'public');

        readfile($filePath);
    }

    /**
     * Validate an uploaded PDF template without saving
     * POST with file upload
     * Returns JSON: { valid: bool, fields: [], error: string }
     */
    public function validateAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->_helper->json([
                'valid' => false,
                'fields' => [],
                'error' => 'Invalid request method'
            ]);
            return;
        }

        // Check if file was uploaded
        if (empty($_FILES['template']) || $_FILES['template']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->_helper->json([
                'valid' => false,
                'fields' => [],
                'error' => 'No file uploaded'
            ]);
            return;
        }

        $file = $_FILES['template'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            $this->_helper->json([
                'valid' => false,
                'fields' => [],
                'error' => $errorMessages[$file['error']] ?? 'Unknown upload error'
            ]);
            return;
        }

        $service = new Application_Service_CertificateTemplates();
        $result = $service->validatePdfTemplate($file['tmp_name']);

        $this->_helper->json($result);
    }

    /**
     * Upload and save a validated certificate template
     * POST with file upload: scheme, type, template (file)
     * Returns JSON: { success: bool, message: string, fields: [], filename: string }
     */
    public function uploadAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->_helper->json([
                'success' => false,
                'message' => 'Invalid request method',
                'fields' => []
            ]);
            return;
        }

        $scheme = $this->_getParam('scheme');
        $type = $this->_getParam('type');

        if (empty($scheme) || empty($type)) {
            $this->_helper->json([
                'success' => false,
                'message' => 'Missing scheme or type parameter',
                'fields' => []
            ]);
            return;
        }

        // Validate type parameter
        if (!in_array($type, ['participation', 'excellence'])) {
            $this->_helper->json([
                'success' => false,
                'message' => 'Invalid type parameter. Must be participation or excellence',
                'fields' => []
            ]);
            return;
        }

        // Check if file was uploaded
        if (empty($_FILES['template']) || $_FILES['template']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->_helper->json([
                'success' => false,
                'message' => 'No file uploaded',
                'fields' => []
            ]);
            return;
        }

        $service = new Application_Service_CertificateTemplates();
        $result = $service->uploadTemplate($scheme, $type, $_FILES['template']);

        $this->_helper->json($result);
    }

    /**
     * Remove a certificate template
     * POST: scheme, type
     * Returns JSON: { success: bool, message: string }
     */
    public function removeAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if (!$request->isPost()) {
            $this->_helper->json([
                'success' => false,
                'message' => 'Invalid request method'
            ]);
            return;
        }

        $scheme = $this->_getParam('scheme');
        $type = $this->_getParam('type');

        if (empty($scheme) || empty($type)) {
            $this->_helper->json([
                'success' => false,
                'message' => 'Missing scheme or type parameter'
            ]);
            return;
        }

        // Validate type parameter
        if (!in_array($type, ['participation', 'excellence'])) {
            $this->_helper->json([
                'success' => false,
                'message' => 'Invalid type parameter. Must be participation or excellence'
            ]);
            return;
        }

        $service = new Application_Service_CertificateTemplates();
        $success = $service->removeTemplate($scheme, $type);

        $this->_helper->json([
            'success' => $success,
            'message' => $success ? 'Template removed successfully' : 'Failed to remove template'
        ]);
    }
}
