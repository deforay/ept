<?php

use Application_Service_SecurityService as SecurityService;

class Admin_LoginController extends Zend_Controller_Action
{

	public function init()
	{
		$this->_helper->layout()->disableLayout();
	}

	public function indexAction()
	{
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$captchaSession = new Zend_Session_Namespace('DACAPTCHA');
			if (!isset($captchaSession->captchaStatus) || $captchaSession->captchaStatus == 'fail') {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check the text from image";
				$sessionAlert->status = "failure";
				$this->redirect('/admin');
			}
			$systemAdminDb = new Application_Model_DbTable_SystemAdmin();
			/* $db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$adapter = new Zend_Auth_Adapter_DbTable($db, "system_admin", "primary_email", "password");
			$common = new Application_Service_Common();
			$select = $adapter->getDbSelect();
			$select->where('status = "active"');
			$adapter->setIdentity($params['username']);
			// $adapter->setCredential($params['password']);

			$auth = Zend_Auth::getInstance();
			$res = $auth->authenticate($adapter); */

			$result = $systemAdminDb->fetchSystemAdminByMail($params['username'], $params['password']);
			$passwordVerify = true;
			if (isset($result) && !empty($result)) {
				$passwordVerify = password_verify((string) $params['password'], (string) $result['password']);
			}
			if (isset($result) && !empty($result) && $passwordVerify) {
				Zend_Session::rememberMe(36000); // keeping the session cookie active for 10 hours

				$authNameSpace 							= new Zend_Session_Namespace('administrators');
				$authNameSpace->primary_email 			= $params['username'];
				$authNameSpace->admin_id 				= $result['admin_id'];
				$authNameSpace->first_name 				= $result['first_name'];
				$authNameSpace->last_name 				= $result['last_name'];
				$authNameSpace->phone 					= $result['phone'];
				$authNameSpace->secondary_email 		= $result['secondary_email'];
				$authNameSpace->forcePasswordReset 		= $result['force_password_reset'];
				$authNameSpace->privileges 				= $result['privileges'];
				$authNameSpace->activeScheme 			= $result['scheme'];

				$schemeService = new Application_Service_Schemes();
				$allSchemes = $schemeService->getAllSchemes();
				$schemeList = [];
				foreach ($allSchemes as $scheme) {
					$schemeList[] = $scheme['scheme_id'];
				}
				$authNameSpace->activeSchemes = $schemeList;

				// Insert login history
				$userId = $result['admin_id']; // Set this to the logged-in user's ID

				$loginHistoryModel = new Application_Model_DbTable_UserLoginHistory();
				$loginData = array(
					'user_id' => $userId,
					'login_context' => 'admin', // or 'failed' if login failed
					'login_status' => 'success', // Indicate failed login
					'login_id' => $params['username'], // This can be set to a unique ID if needed
				);

				$loginHistoryModel->addLoginHistory($loginData);

				$this->redirect('/admin');
			} else {
				// Insert login history

				$loginHistoryModel = new Application_Model_DbTable_UserLoginHistory();
				$loginData = array(
					'user_id' => NULL,
					'login_context' => 'admin', // or 'failed' if login failed
					'login_status' => 'failed', // Indicate failed login
					'login_id' => $params['username'], // This can be set to a unique ID if needed
				);

				$loginHistoryModel->addLoginHistory($loginData);
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check your login credentials";
				$sessionAlert->status = "failure";
			}
		} else {
			$commonServices = new Application_Service_Common();
			$this->view->instituteName = $commonServices->getConfig('institute_name');
			// We are destroying the session here in case this person has
			// logged in as a User as well..
			// We don't want that
			Zend_Auth::getInstance()->clearIdentity();
			SecurityService::rotateCSRF();
		}
	}

	public function logOutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		Zend_Session::destroy();
		$this->redirect('/admin');
	}
}
