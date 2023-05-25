<?php

class Admin_TbSettingsController extends Zend_Controller_Action
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
        // $schemeService = new Application_Service_Schemes();
        // $dtsModel = new Application_Model_Dts();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->evaluation->tb = [];
            $config->$sec->evaluation->tb->passPercentage = $request->getPost('tbPassPercentage');
            $config->$sec->evaluation->tb->contactInfo = htmlspecialchars($request->getPost('contactInfo'));

            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                ->setFilename($file)
                ->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated TB Settings", "config");
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        // $this->view->allTestKits = $dtsModel->getAllDtsTestKitList(true);
        // $this->view->dtsRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts');
        // $this->view->dtsSyphilisRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+syphilis');
        // $this->view->dtsRtriRecommendedTestkits = $dtsModel->getRecommededDtsTestkits('dts+rtri');
    }
}
