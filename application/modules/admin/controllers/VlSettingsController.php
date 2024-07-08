<?php

class Admin_VlSettingsController extends Zend_Controller_Action
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
        try{
            /** @var Zend_Controller_Request_Http $request */
            $request = $this->getRequest();
            $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
            if ($request->isPost()) {
                $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
                $sec = APPLICATION_ENV;
                
                if (!isset($config->$sec->evaluation)) {
                    $config->$sec->evaluation = [];
                }
                
                if (!isset($config->$sec->evaluation->vl)) {
                    $config->$sec->evaluation->vl = [];
                }
                
                $config->$sec->evaluation->vl->passPercentage = $request->getPost('vlPassPercentage') ?? 95;
                $config->$sec->evaluation->vl->documentationScore = $request->getPost('vlDocumentationScore') ?? 10;
                $config->$sec->evaluation->vl->contentForIndividualVlReports = str_replace('"', "'", $request->getPost('contentForIndividualVlReports'));
                
                // Zend_Debug::dump($request->getPost());die;
                $writer = new Zend_Config_Writer_Ini();
                $writer->write($file, $config);
                $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Updated VL Settings", "config");
            }
        } catch (Exception $exc) {

            error_log("VL-SETTINGS-" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return "";
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
