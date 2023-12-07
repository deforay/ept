<?php

class Admin_DtsSettingsController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
    }

    public function indexAction()
    {

        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();

        // some config settings are in config file and some in global_config table.
        //$commonServices = new Application_Service_Common();
        $schemeService = new Application_Service_Schemes();
        $dtsModel = new Application_Model_Dts();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {
            // Zend_Debug::dump($this->getAllParams());die;
            $testKits = [];
            $testKits[1] = $request->getPost('dtsTestkit1');
            $testKits[2] = $request->getPost('dtsTestkit2');
            $testKits[3] = $request->getPost('dtsTestkit3');
            $schemeService->setRecommededDtsTestkit($testKits, 'dts');

            $dtsSyphilisTestKits = [];
            $dtsSyphilisTestKits[1] = $request->getPost('dtsSyphilisTestkit1');
            $dtsSyphilisTestKits[2] = $request->getPost('dtsSyphilisTestkit2');
            $dtsSyphilisTestKits[3] = $request->getPost('dtsSyphilisTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsSyphilisTestKits, 'dts+syphilis');

            $dtsRtriTestKits = [];
            $dtsRtriTestKits[1] = $request->getPost('dtsRtriTestkit1');
            $dtsRtriTestKits[2] = $request->getPost('dtsRtriTestkit2');
            $dtsRtriTestKits[3] = $request->getPost('dtsRtriTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsRtriTestKits, 'dts+rtri');

            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;


            $allowedAlgorithms = $request->getPost('allowedAlgorithms');
            if($allowedAlgorithms){
                $allowedAlgorithms = implode(",", $allowedAlgorithms);
            }



            $config->$sec->evaluation->dts = [];
            $config->$sec->evaluation->dts->passPercentage = $request->getPost('dtsPassPercentage');
            $config->$sec->evaluation->dts->panelScore = $request->getPost('dtsPanelScore');
            $config->$sec->evaluation->dts->documentationScore = $request->getPost('dtsDocumentationScore');
            $config->$sec->evaluation->dts->dtsOptionalTest3 = $request->getPost('dtsOptionalTest3');
            $config->$sec->evaluation->dts->dtsEnforceAlgorithmCheck = $request->getPost('dtsEnforceAlgorithmCheck');
            $config->$sec->evaluation->dts->sampleRehydrateDays = $request->getPost('sampleRehydrateDays');
            $config->$sec->evaluation->dts->allowedAlgorithms = !empty($allowedAlgorithms) ? $allowedAlgorithms : '';
            $config->$sec->evaluation->dts->displaySampleConditionFields = $request->getPost('conditionOfPtSample');
            $config->$sec->evaluation->dts->allowRepeatTests = $request->getPost('allowRepeatTest');
            $config->$sec->evaluation->dts->dtsSchemeType = $request->getPost('dtsSchemeType');

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                ->setFilename($file)
                ->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated HIV Serology Settings", "config");
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(true);
        $this->view->dtsRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts');
        $this->view->dtsSyphilisRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+syphilis');
        $this->view->dtsRtriRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+rtri');
    }
}
