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
            ->addActionContext('get-testkit', 'html')
            ->addActionContext('update-status', 'html')
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
            $params = $this->getAllParams();
            $response = $participants->confirmTemporaryFiles($params['temp_id'], $params['participantId']);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            if ($response === false) {
                $alertMsg->message = 'Files are not uploaded to this participants';
            } else {
                $alertMsg->message = 'Files are uploaded to participants successfully';
            }

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
       // $this->_helper->viewRenderer->setNoRender(true);
         /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $participants = new Application_Service_Participants();
            
            $this->view->results = $participants->getParticipantsForFiles($params);
        }

    }

    public function uploadTempAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

    try {

        if (empty($_FILES['fileName'])) {

            throw new Exception(
                'No files were uploaded.'
            );
        }


        if (empty($tempId)) {
            $tempId = uniqid('participant_', true);
        }

        $participantService = new Application_Service_Participants();

        $files = $participantService->uploadFilesForParticipants(
            $_FILES['fileName'],
            $tempId
        );

        echo Zend_Json::encode(array(
            'status'  => true,
            'temp_id' => $tempId,
            'files'   => $files
        ));

    } catch (Exception $e) {

        echo Zend_Json::encode(array(
            'status'  => false,
            'message' => $e->getMessage()
        ));
    }
    }

    public function getTemporaryFilesAction()
{
    $this->_helper->layout()->disableLayout();
    $this->_helper->viewRenderer->setNoRender(true);

    $tempId = $this->getRequest()
                   ->getParam('temp_id');

    if (empty($tempId)) {

        echo Zend_Json::encode(array(
            'status'  => false,
            'message' => 'Temporary ID is required.'
        ));

        return;
    }


    try {

        $participantService = new Application_Service_Participants();


        $files = $participantService->getTemporaryFiles($tempId);


        echo Zend_Json::encode(array(
            'status' => true,
            'files'  => $files
        ));

    } catch (Exception $e) {

        echo Zend_Json::encode(array(
            'status'  => false,
            'message' => $e->getMessage()
        ));
    }
}

public function viewTempFileAction()
{
    $this->_helper->layout()->disableLayout();
    $this->_helper->viewRenderer->setNoRender(true);

    $tempId = basename(
        $this->getRequest()->getParam('temp_id')
    );

    $fileName = basename(
        $this->getRequest()->getParam('file')
    );

    if (empty($tempId) || empty($fileName)) {

        $this->getResponse()
            ->setHttpResponseCode(400);

        echo 'Invalid file request';

        return;
    }

    /*
     * Temporary folder
     */
    $tempDir = APPLICATION_PATH .
        '/../public/uploads/temp/' .
        $tempId;

    $filePath =
        $tempDir .
        DIRECTORY_SEPARATOR .
        $fileName;


    /*
     * Check file exists
     */
    if (!is_file($filePath)) {

        $this->getResponse()
            ->setHttpResponseCode(404);

        echo 'Temporary file not found';

        return;
    }


    /*
     * Detect MIME type
     */
    $mimeType = 'application/octet-stream';

    if (function_exists('mime_content_type')) {

        $detectedMime =
            mime_content_type($filePath);

        if ($detectedMime) {
            $mimeType = $detectedMime;
        }
    }


    /*
     * Download headers
     */
    $response = $this->getResponse();

    $response->setHeader(
        'Content-Type',
        $mimeType
    );

    $response->setHeader(
        'Content-Disposition',
        'attachment; filename="' .
        addslashes($fileName) .
        '"'
    );

    $response->setHeader(
        'Content-Length',
        filesize($filePath)
    );

    $response->setHeader(
        'Cache-Control',
        'private'
    );

    $response->setHeader(
        'Pragma',
        'public'
    );


    /*
     * Send file
     */
    readfile($filePath);

    exit;
}

public function removeTempFileAction()
{
    $this->_helper->layout()->disableLayout();
    $this->_helper->viewRenderer->setNoRender(true);

    $tempId = $this->getRequest()
        ->getParam('temp_id');
    $fileName = $this->getRequest()
        ->getParam('file');

    if (empty($tempId)) {

        echo Zend_Json::encode(array(
            'status'  => false,
            'message' => 'Temporary ID is required.'
        ));

        return;
    }

    try {

        $participantService =  new Application_Service_Participants();

        $result = $participantService->removeTemporaryFiles($tempId, $fileName);

        echo Zend_Json::encode(array(
            'status' => $result
        ));

    } catch (Exception $e) {

        echo Zend_Json::encode(array(
            'status'  => false,
            'message' => $e->getMessage()
        ));
    }
}
}
