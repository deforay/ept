<?php

class Admin_DtsSettingsController extends Zend_Controller_Action
{

    public function init() {
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction() {
        
        // some config settings are in config file and some in global_config table.
        $commonServices = new Application_Service_Common();
        $schemeService = new Application_Service_Schemes();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($this->getRequest()->isPost()) {
           // Zend_Debug::dump($this->_getAllParams());die;
            $testKits[1] =$this->getRequest()->getPost('testkit1');
            $testKits[2] =$this->getRequest()->getPost('testkit2');
            $testKits[3] =$this->getRequest()->getPost('testkit3');
            $schemeService->setRecommededDtsTestkit($testKits);
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->evaluation->dts->passPercentage = $this->getRequest()->getPost('dtsPassPercentage');
            $config->$sec->evaluation->dts->documentationScore = $this->getRequest()->getPost('dtsDocumentationScore');
            $config->$sec->evaluation->dts->dtsOptionalTest3 = $this->getRequest()->getPost('dtsOptionalTest3');
            $config->$sec->evaluation->dts->sampleRehydrateDays = $this->getRequest()->getPost('sampleRehydrateDays');

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                    ->setFilename($file)
                    ->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

        }

        
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $this->view->allTestKits = $schemeService->getAllDtsTestKitList(true);
        $this->view->recommendedTestkits = $schemeService->getRecommededDtsTestkit();

    }

}
