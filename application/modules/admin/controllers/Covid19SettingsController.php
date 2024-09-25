<?php

class Admin_Covid19SettingsController extends Zend_Controller_Action
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
        $schemeService = new Application_Service_Schemes();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {
            // Zend_Debug::dump($this->getAllParams());die;
            $testPlatforms[1] = $request->getPost('testPlatform1');
            $testPlatforms[2] = $request->getPost('testPlatform2');
            $testPlatforms[3] = $request->getPost('testPlatform3');

            $schemeService->setRecommededCovid19TestTypes($testPlatforms);
            $config = new Zend_Config_Ini($file, null, array('skipExtends' => true, 'allowModifications' => true));
            $sec = APPLICATION_ENV;


            $config->$sec->evaluation->covid19 = [];
            $config->$sec->evaluation->covid19->passPercentage = $request->getPost('covid19PassPercentage');
            $config->$sec->evaluation->covid19->documentationScore = $request->getPost('covid19DocumentationScore');
            $config->$sec->evaluation->covid19->covid19MaximumTestAllowed = $request->getPost('covid19MaximumTestAllowed');
            $config->$sec->evaluation->covid19->covid19EnforceAlgorithmCheck = $request->getPost('covid19EnforceAlgorithmCheck');
            $config->$sec->evaluation->covid19->sampleRehydrateDays = $request->getPost('sampleRehydrateDays');

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
