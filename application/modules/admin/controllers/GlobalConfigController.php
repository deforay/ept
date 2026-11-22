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
                $mailBefore = Application_Service_Common::getMailSettings();
                $this->view->mailSaveResult = $commonServices->updateMailSettings($emailConfig);
                if ($this->view->mailSaveResult['ok']) {
                    Application_Model_DbTable_AuditLog::record('Updated email settings', 'config', [
                        'entity' => ['type' => 'config', 'id' => 'mail', 'label' => 'Email settings'],
                        'changes' => Application_Model_DbTable_AuditLog::changes($mailBefore, Application_Service_Common::getMailSettings(), [
                            'host' => 'SMTP Host', 'port' => 'SMTP Port', 'ssl' => 'Encryption', 'auth' => 'Authentication Type',
                            'username' => 'Username', 'fromName' => 'From Name', 'fromEmail' => 'From Email', 'cc' => 'CC', 'bcc' => 'BCC',
                        ]),
                    ]);
                }
                unset($params['emailConfig']);
            }
            if (isset($params['bounceConfig']) && is_array($params['bounceConfig'])) {
                $bounceBefore = Application_Service_Common::getBounceSettings();
                $this->view->bounceSaveResult = $commonServices->updateBounceSettings($params['bounceConfig']);
                if ($this->view->bounceSaveResult['ok'] && $this->view->bounceSaveResult['message'] !== '') {
                    Application_Model_DbTable_AuditLog::record('Updated bounce inbox settings', 'config', [
                        'entity' => ['type' => 'config', 'id' => 'bounce', 'label' => 'Bounce inbox settings'],
                        'changes' => Application_Model_DbTable_AuditLog::changes($bounceBefore, Application_Service_Common::getBounceSettings(), [
                            'host' => 'IMAP Host', 'port' => 'IMAP Port', 'ssl' => 'Encryption',
                            'folder' => 'Folder or Gmail Label', 'username' => 'Username',
                        ]),
                    ]);
                }
                unset($params['bounceConfig']);
            }
            Application_Service_Common::forgetMailDeliveryProblem();
            // Participants are told to write to these, so an address that cannot receive
            // mail (a placeholder such as exampleadmin@example.com) is not saved
            $contactEmailErrors = [];
            foreach (['admin_email', 'support_email'] as $field) {
                if (isset($params[$field])) {
                    $unusable = Application_Service_Common::partitionEmails((string) $params[$field])['unusable'];
                    if ($unusable !== []) {
                        $contactEmailErrors[$field] = $unusable;
                        unset($params[$field]);
                    }
                }
            }
            $this->view->contactEmailErrors = $contactEmailErrors;
            $commonServices->updateConfig($params);
        }
        $assign = $commonServices->getGlobalConfigDetails();
        $this->view->assign($assign);
        $this->view->app_timezone = $commonServices->getApplicationTimezone();
        $this->view->appDomain = $commonServices->getApplicationDomain();
        $this->view->mailSettings = Application_Service_Common::getMailSettings();
        $this->view->bounceSettings = Application_Service_Common::getBounceSettings();
        $this->view->bounceStatus = $commonServices->getBounceStatus();
        $this->view->lastMailVerification = Application_Service_Common::lastMailVerification();
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
    }

    /** Verify Email Setup (XHR POST): test the saved mail settings and return each step as JSON. */
    public function verifyEmailAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!$request->isPost() || !$request->isXmlHttpRequest()) {
            $this->redirect('/admin/global-config');
            return;
        }
        $adminSession = new Zend_Session_Namespace('administrators');
        $adminEmail = (string) ($adminSession->primary_email ?? '');
        // A login-only admin address may not be a real inbox, so the admin picks the recipient
        $recipient = trim((string) $request->getPost('recipient', ''));
        $result = Application_Service_Common::verifyMailSetup($recipient, $adminEmail);
        Application_Service_Common::forgetMailDeliveryProblem();
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog($result['ok'] ? 'Verified email setup: passed' : 'Verified email setup: failed', 'config', null, [
            'entity' => ['type' => 'config', 'id' => 'mail', 'label' => 'Email settings'],
        ]);
        $result['atText'] = Pt_Commons_DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s', $result['at']), true);
        $this->_helper->json($result);
    }
}
