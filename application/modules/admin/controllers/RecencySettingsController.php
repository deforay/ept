<?php

class Admin_RecencySettingsController extends Zend_Controller_Action
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

        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->evaluation->recency = [];
            $config->$sec->evaluation->recency->passPercentage = $request->getPost('recencyPassPercentage');
            $config->$sec->evaluation->recency->panelScore = $request->getPost('recencyPanelScore');
            $config->$sec->evaluation->recency->documentationScore = $request->getPost('recencyDocumentationScore');
            $config->$sec->evaluation->recency->sampleRehydrateDays = $request->getPost('sampleRehydrateDays');

            $writer = new Zend_Config_Writer_Ini();
            $writer->write($file, $config);

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
        }

        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
    }
}
