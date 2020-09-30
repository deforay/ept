<?php

class Admin_PushNotificationSettingsController extends Zend_Controller_Action
{

    public function init() {
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction() {
        
        // config settings are in config file.
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($this->getRequest()->isPost()) {
        //    Zend_Debug::dump($this->getRequest()->getPost('serverKey'));die;
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->fcm->url = $this->getRequest()->getPost('fireBaseUrl');
            $config->$sec->fcm->serverkey = $this->getRequest()->getPost('serverKey');
            $config->$sec->fcm->apiKey = $this->getRequest()->getPost('apiKey');
            $config->$sec->fcm->authDomain = $this->getRequest()->getPost('authDomain');
            $config->$sec->fcm->databaseURL = $this->getRequest()->getPost('databaseUrl');
            $config->$sec->fcm->projectId = $this->getRequest()->getPost('projectId');
            $config->$sec->fcm->storageBucket = $this->getRequest()->getPost('storageBucket');
            $config->$sec->fcm->messagingSenderId = $this->getRequest()->getPost('messagingSenderId');

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)->setFilename($file)->write();
            
            if(isset($_FILES['googleServiceJson']['name']) && $_FILES['googleServiceJson']['name'] != ""){
                $extension = strtolower(pathinfo($_FILES['googleServiceJson']['name'], PATHINFO_EXTENSION));
                $fileName = "google-services." . $extension;
                if (move_uploaded_file($_FILES['googleServiceJson']['tmp_name'], UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {
                    // Alert Success
                }
            }
        }
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }

}
