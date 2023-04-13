<?php

class Admin_Covid19SettingsController extends Zend_Controller_Action
{

    public function init()
    {

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {

        // some config settings are in config file and some in global_config table.
        $commonServices = new Application_Service_Common();
        $schemeService = new Application_Service_Schemes();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($this->getRequest()->isPost()) {
            // Zend_Debug::dump($this->getAllParams());die;
            $testPlatforms[1] = $this->getRequest()->getPost('testPlatform1');
            $testPlatforms[2] = $this->getRequest()->getPost('testPlatform2');
            $testPlatforms[3] = $this->getRequest()->getPost('testPlatform3');

            $schemeService->setRecommededCovid19TestTypes($testPlatforms);
            $config = new Zend_Config_Ini($file, null, array('skipExtends' => true, 'allowModifications' => true));
            $sec = APPLICATION_ENV;


            $config->$sec->evaluation->covid19 = [];
            $config->$sec->evaluation->covid19->passPercentage = $this->getRequest()->getPost('covid19PassPercentage');
            $config->$sec->evaluation->covid19->documentationScore = $this->getRequest()->getPost('covid19DocumentationScore');
            $config->$sec->evaluation->covid19->covid19MaximumTestAllowed = $this->getRequest()->getPost('covid19MaximumTestAllowed');
            $config->$sec->evaluation->covid19->covid19EnforceAlgorithmCheck = $this->getRequest()->getPost('covid19EnforceAlgorithmCheck');
            $config->$sec->evaluation->covid19->sampleRehydrateDays = $this->getRequest()->getPost('sampleRehydrateDays');

            $writer = new Zend_Config_Writer_Ini(array(
                'config'   => $config,
                'filename' => $file
            ));

            $writer->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $cehck = $config->$sec->evaluation->covid19->toArray();
            if (isset($cehck) && count($cehck) > 0) {
                $alertMsg->message = 'Settings Saved';
            }
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $this->view->allTestTypes = $schemeService->getAllCovid19TestTypeResponseWise(true);
        $this->view->recommendedTesttypes = $schemeService->getRecommededCovid19TestTypes();
    }
}
