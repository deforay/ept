<?php

class Admin_FilesForParticipantsController extends Zend_Controller_Action
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
        $participants = new Application_Service_Participants();
        $scheme = new Application_Service_Schemes();
        $shipmentParticipantMap = new Application_Model_DbTable_ShipmentParticipantMap();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            $result = $participants->confirmTemporaryFiles(
                $request->getPost('temp_id'),
                $request->getPost('participantId')
            );
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = $result['message'];
            // Redirect-after-post so a refresh cannot re-run the distribution.
            $this->redirect('/admin/files-for-participants');
            return;
        }

        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
        $this->view->results = $shipmentParticipantMap->fetchAllFinalResults();
    }

    public function getParticipantsForFilesAction()
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $participants = new Application_Service_Participants();
            $this->view->results = $participants->getParticipantsForFiles($request->getPost());
        }
    }

    public function uploadTempAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        try {
            if (empty($_FILES['fileName'])) {
                throw new Exception('No files were uploaded.');
            }

            // Reuse the batch id when the wizard already has one, so going
            // back to step 1 adds to the same batch instead of orphaning it.
            $tempId = $this->getRequest()->getParam('temp_id');
            if (Application_Service_Participants::filesForParticipantsTempPath($tempId) === null) {
                $tempId = uniqid('participant_', true);
            }

            $participantService = new Application_Service_Participants();
            $result = $participantService->uploadFilesForParticipants($_FILES['fileName'], $tempId);

            echo Zend_Json::encode([
                'status'   => true,
                'temp_id'  => $tempId,
                'files'    => $result['files'],
                'rejected' => $result['rejected'],
            ]);
        } catch (Exception $e) {
            echo Zend_Json::encode([
                'status'  => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function getTemporaryFilesAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $tempId = $this->getRequest()->getParam('temp_id');
        if (empty($tempId)) {
            echo Zend_Json::encode([
                'status'  => false,
                'message' => 'Temporary ID is required.',
            ]);
            return;
        }

        try {
            $participantService = new Application_Service_Participants();
            echo Zend_Json::encode([
                'status' => true,
                'files'  => $participantService->getTemporaryFiles($tempId),
            ]);
        } catch (Exception $e) {
            echo Zend_Json::encode([
                'status'  => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function viewTempFileAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $tempDir = Application_Service_Participants::filesForParticipantsTempPath(
            $this->getRequest()->getParam('temp_id')
        );
        $fileName = basename((string) $this->getRequest()->getParam('file'));

        if ($tempDir === null || $fileName === '' || $fileName === '.' || $fileName === '..') {
            $this->getResponse()->setHttpResponseCode(400);
            echo 'Invalid file request';
            return;
        }

        $filePath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($filePath)) {
            $this->getResponse()->setHttpResponseCode(404);
            echo 'Temporary file not found';
            return;
        }

        $mimeType = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detectedMime = mime_content_type($filePath);
            if ($detectedMime) {
                $mimeType = $detectedMime;
            }
        }

        $response = $this->getResponse();
        $response->setHeader('Content-Type', $mimeType);
        $response->setHeader('Content-Disposition', 'attachment; filename="' . addslashes($fileName) . '"');
        $response->setHeader('Content-Length', filesize($filePath));
        $response->setHeader('Cache-Control', 'private');
        $response->sendHeaders();

        readfile($filePath);
        exit;
    }

    public function removeTempFileAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $tempId = $this->getRequest()->getParam('temp_id');
        $fileName = $this->getRequest()->getParam('file');

        if (empty($tempId)) {
            echo Zend_Json::encode([
                'status'  => false,
                'message' => 'Temporary ID is required.',
            ]);
            return;
        }

        try {
            $participantService = new Application_Service_Participants();
            echo Zend_Json::encode([
                'status' => $participantService->removeTemporaryFiles($tempId, $fileName),
            ]);
        } catch (Exception $e) {
            echo Zend_Json::encode([
                'status'  => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
