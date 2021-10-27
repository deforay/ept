<?php

class Admin_GlobalConfigController extends Zend_Controller_Action
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
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($this->getRequest()->isPost()) {
            // Zend_Debug::dump($this->getAllParams());die;
            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;
            $config->$sec->map->center = $this->getRequest()->getPost('mapCenter');
            $config->$sec->map->zoom = $this->getRequest()->getPost('mapZoom');
            $config->$sec->instituteName = $this->getRequest()->getPost('instituteName');
            $config->$sec->instituteAddress = $this->getRequest()->getPost('instituteAddress');
            $writer = new Zend_Config_Writer_Ini();
            $writer->setConfig($config)
                ->setFilename($file)
                ->write();

            $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

            $params = $this->getAllParams();
            $commonServices->updateConfig($params);
        }

        $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

        $assign = $commonServices->getGlobalConfigDetails();

        $this->view->assign($assign);
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
    }
}
