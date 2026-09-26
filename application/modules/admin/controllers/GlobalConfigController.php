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
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
            return;
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            // App timezone lives in application.ini, not global_config — handle it
            // separately so it isn't fed to the global_config update path.
            if (array_key_exists('app_timezone', $params)) {
                $this->view->appTimezoneSaved = $commonServices->updateApplicationTimezone($params['app_timezone']);
                unset($params['app_timezone']);
            }
            // Mail settings and the instance URL live in application.ini as well.
            if (isset($params['emailConfig']) && is_array($params['emailConfig'])) {
                $emailConfig = $params['emailConfig'];
                if (array_key_exists('domain', $emailConfig)) {
                    $this->view->appDomainSaved = $commonServices->updateApplicationDomain($emailConfig['domain']);
                    unset($emailConfig['domain']);
                }
                $this->view->mailSaveResult = $commonServices->updateMailSettings($emailConfig);
                if ($this->view->mailSaveResult['ok']) {
                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog('Updated email settings', 'config');
                }
                unset($params['emailConfig']);
            }
            if (isset($params['bounceConfig']) && is_array($params['bounceConfig'])) {
                $this->view->bounceSaveResult = $commonServices->updateBounceSettings($params['bounceConfig']);
                if ($this->view->bounceSaveResult['ok'] && $this->view->bounceSaveResult['message'] !== '') {
                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog('Updated bounce inbox settings', 'config');
                }
                unset($params['bounceConfig']);
            }
            $commonServices->updateConfig($params);
        }
        $assign = $commonServices->getGlobalConfigDetails();
        $this->view->assign($assign);
        $this->view->app_timezone = $commonServices->getApplicationTimezone();
        $this->view->appDomain = $commonServices->getApplicationDomain();
        $this->view->mailSettings = Application_Service_Common::getMailSettings();
        $this->view->bounceSettings = Application_Service_Common::getBounceSettings();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
    }
}
