<?php

class Admin_Covid19SettingsController extends Zend_Controller_Action
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
           // Zend_Debug::dump($this->getAllParams());die;
            $testTypes[1] =$this->getRequest()->getPost('testType1');
            $testTypes[2] =$this->getRequest()->getPost('testType2');
            $testTypes[3] =$this->getRequest()->getPost('testType3');
            
            $schemeService->setRecommededCovid19TestTypes($testTypes);
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;


            $allowedAlgorithms = $this->getRequest()->getPost('allowedAlgorithms');
            $allowedAlgorithms = implode(",",$allowedAlgorithms);

            

            $config->$sec->evaluation->covid19->passPercentage = $this->getRequest()->getPost('covid19PassPercentage');
            $config->$sec->evaluation->covid19->documentationScore = $this->getRequest()->getPost('covid19DocumentationScore');
            $config->$sec->evaluation->covid19->covid19MaximumTestAllowed = $this->getRequest()->getPost('covid19MaximumTestAllowed');
            $config->$sec->evaluation->covid19->covid19EnforceAlgorithmCheck = $this->getRequest()->getPost('covid19EnforceAlgorithmCheck');
            $config->$sec->evaluation->covid19->sampleRehydrateDays = $this->getRequest()->getPost('sampleRehydrateDays');
            $config->$sec->evaluation->covid19->allowedAlgorithms = !empty($allowedAlgorithms) ? $allowedAlgorithms : '';

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                    ->setFilename($file)
                    ->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $cehck = $config->$sec->evaluation->covid19->toArray();
            if(isset($cehck) && count($cehck) > 0){
                $alertMsg->message = 'Settings Saved';
            }

        }

        
        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $this->view->allTestTypes = $schemeService->getAllCovid19TestType(true);
        $this->view->recommendedTesttypes = $schemeService->getRecommededCovid19TestTypes();

    }

}
