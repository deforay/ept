<?php

class Admin_GlobalConfigController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
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
        // some config settings are in config file and some in global_config table.
        $commonServices = new Application_Service_Common();
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        if ($request->isPost()) {

            $config = new Zend_Config_Ini($file, null, array('allowModifications' => true));
            $sec = APPLICATION_ENV;

            $config->$sec->instituteName = $request->getPost('instituteName');
            $config->$sec->instituteAddress = $request->getPost('instituteAddress');
            $config->$sec->additionalInstituteDetails = $request->getPost('additionalInstituteDetails');
            $config->$sec->jobCompletionAlert = new Zend_Config([], true);;
            $config->$sec->jobCompletionAlert->status = $request->getPost('jobCompletionAlertStatus');
            $config->$sec->jobCompletionAlert->mails = $request->getPost('jobCompletionAlertMails');
            $config->$sec->locale = new Zend_Config([], true);
            $config->$sec->locale = $request->getPost('locale');
            $writer = new Zend_Config_Writer_Ini();
            $writer->write($file, $config);

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
