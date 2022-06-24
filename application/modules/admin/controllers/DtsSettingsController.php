<?php

class Admin_DtsSettingsController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->pageName = 'configMenu';

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
    }

    public function indexAction()
    {

        // some config settings are in config file and some in global_config table.
        $commonServices = new Application_Service_Common();
        $schemeService = new Application_Service_Schemes();
        $dtsModel = new Application_Model_Dts();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($this->getRequest()->isPost()) {
            // Zend_Debug::dump($this->getAllParams());die;
            $testKits = array();
            $testKits[1] = $this->getRequest()->getPost('dtsTestkit1');
            $testKits[2] = $this->getRequest()->getPost('dtsTestkit2');
            $testKits[3] = $this->getRequest()->getPost('dtsTestkit3');
            $schemeService->setRecommededDtsTestkit($testKits, 'dts');

            $dtsSyphilisTestKits = array();
            $dtsSyphilisTestKits[1] = $this->getRequest()->getPost('dtsSyphilisTestkit1');
            $dtsSyphilisTestKits[2] = $this->getRequest()->getPost('dtsSyphilisTestkit2');
            $dtsSyphilisTestKits[3] = $this->getRequest()->getPost('dtsSyphilisTestkit3');
            $schemeService->setRecommededDtsTestkit($dtsSyphilisTestKits, 'dts+syphilis');

            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;


            $allowedAlgorithms = $this->getRequest()->getPost('allowedAlgorithms');
            $allowedAlgorithms = implode(",", $allowedAlgorithms);



            $config->$sec->evaluation->dts = array();
            $config->$sec->evaluation->dts->passPercentage = $this->getRequest()->getPost('dtsPassPercentage');
            $config->$sec->evaluation->dts->panelScore = $this->getRequest()->getPost('dtsPanelScore');
            $config->$sec->evaluation->dts->documentationScore = $this->getRequest()->getPost('dtsDocumentationScore');
            $config->$sec->evaluation->dts->dtsOptionalTest3 = $this->getRequest()->getPost('dtsOptionalTest3');
            $config->$sec->evaluation->dts->dtsEnforceAlgorithmCheck = $this->getRequest()->getPost('dtsEnforceAlgorithmCheck');
            $config->$sec->evaluation->dts->sampleRehydrateDays = $this->getRequest()->getPost('sampleRehydrateDays');
            $config->$sec->evaluation->dts->allowedAlgorithms = !empty($allowedAlgorithms) ? $allowedAlgorithms : '';
            $config->$sec->evaluation->dts->displaySampleConditionFields = $this->getRequest()->getPost('conditionOfPtSample');
            $config->$sec->evaluation->dts->allowRepeatTests = $this->getRequest()->getPost('allowRepeatTest');
            $config->$sec->evaluation->dts->dtsSchemeType = $this->getRequest()->getPost('dtsSchemeType');

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                ->setFilename($file)
                ->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated DTS HIV Serology Settings", "config");
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(true);
        $this->view->dtsRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts');
        $this->view->dtsSyphilisRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+syphilis');
    }
}
