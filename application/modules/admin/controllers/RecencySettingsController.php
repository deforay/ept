<?php

class Admin_RecencySettingsController extends Zend_Controller_Action
{

    public function init() {
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction() {
        
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($this->getRequest()->isPost()) {
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->evaluation->recency->passPercentage = $this->getRequest()->getPost('recencyPassPercentage');
            $config->$sec->evaluation->recency->documentationScore = $this->getRequest()->getPost('recencyDocumentationScore');
            $config->$sec->evaluation->recency->sampleRehydrateDays = $this->getRequest()->getPost('sampleRehydrateDays');

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)->setFilename($file)->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

        }

        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }

}
