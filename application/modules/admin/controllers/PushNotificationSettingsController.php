<?php

class Admin_PushNotificationSettingsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        // config settings are in config file.
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {
            //    Zend_Debug::dump($request->getPost('serverKey'));die;
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->fcm = [];

            $config->$sec->fcm->url = $request->getPost('fireBaseUrl');
            $config->$sec->fcm->serverkey = $request->getPost('serverKey');
            $config->$sec->fcm->apiKey = $request->getPost('apiKey');
            $config->$sec->fcm->authDomain = $request->getPost('authDomain');
            $config->$sec->fcm->databaseURL = $request->getPost('databaseUrl');
            $config->$sec->fcm->projectId = $request->getPost('projectId');
            $config->$sec->fcm->storageBucket = $request->getPost('storageBucket');
            $config->$sec->fcm->messagingSenderId = $request->getPost('messagingSenderId');

            $writer = new Zend_Config_Writer_Ini();
            $writer->write($file, $config);
            $uploadDirectory = realpath(UPLOAD_PATH);
            if (isset($_FILES['googleServiceJson']['name']) && $_FILES['googleServiceJson']['name'] != "") {
                $extension = strtolower(pathinfo($_FILES['googleServiceJson']['name'], PATHINFO_EXTENSION));
                $fileName = "google-services." . $extension;
                if (move_uploaded_file($_FILES['googleServiceJson']['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                    // Alert Success
                }
            }
        }
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
