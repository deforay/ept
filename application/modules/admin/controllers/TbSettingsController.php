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
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->evaluation->tb = [];
            $config->$sec->evaluation->tb->passPercentage = $request->getPost('tbPassPercentage') ?? 95;
            $config->$sec->evaluation->tb->contactInfo = htmlspecialchars($request->getPost('contactInfo'));

            $writer = new Zend_Config_Writer_Ini();
            $writer->write($file, $config);

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated TB Settings", "config");
        }


        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
