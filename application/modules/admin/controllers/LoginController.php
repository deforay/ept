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
			if (
				!empty($params['website_url']) ||
				!Application_Service_Common::consumeFormToken($params['form_token'] ?? null, 'adminLoginTokens')
			) {
				$this->redirect('/admin');
				return;
			}
			if (!Application_Service_Common::consumeCaptcha()) {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check the text from image";
				$sessionAlert->status = "failure";
				$this->redirect('/admin');
			}
			$systemAdminDb = new Application_Model_DbTable_SystemAdmin();

			$result = $systemAdminDb->fetchSystemAdminByMail($params['username'], $params['password']);
			$passwordVerify = true;
			if (isset($result) && !empty($result)) {
				$passwordVerify = password_verify((string) $params['password'], (string) $result['password']);
			}
			if (isset($result) && !empty($result) && $passwordVerify) {
				// keeping the session cookie active for 10 hours
				Zend_Session::rememberMe(60 * 60 * 10);
				// regenerate and delete old session to prevent fixation, before setting session data
				Zend_Session::regenerateId();

				// Convert the row to an array so missing columns null-coalesce instead of
				// throwing mid-write. A partially-populated session (e.g. admin_id set but
				// privileges unset) presents as "logged in" but with no menus.
				$row = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : (array)$result;

				$schemeService = new Application_Service_Schemes();
				$schemeList = [];
				foreach ($schemeService->getAllSchemes() as $scheme) {
					$schemeList[] = $scheme['scheme_id'];
				}

				$sessionData = [
					'primary_email'      => $params['username'],
					'admin_id'           => $row['admin_id'] ?? null,
					'first_name'         => $row['first_name'] ?? null,
					'last_name'          => $row['last_name'] ?? null,
					'phone'              => $row['phone'] ?? null,
					'language'           => $row['language'] ?? 'en_US',
					'secondary_email'    => $row['secondary_email'] ?? null,
					'forcePasswordReset' => $row['force_password_reset'] ?? null,
					'privileges'         => $row['privileges'] ?? '',
					'activeScheme'       => $row['scheme'] ?? null,
					'activeSchemes'      => $schemeList,
				];

				$authNameSpace = new Zend_Session_Namespace('administrators');
				foreach ($sessionData as $key => $value) {
					$authNameSpace->$key = $value;
				}

				// Insert login history
				$userId = $row['admin_id'] ?? null;

				$loginHistoryModel = new Application_Model_DbTable_UserLoginHistory();
				$loginData = [
					'user_id' => $userId,
					'login_context' => 'admin', // or 'failed' if login failed
					'login_status' => 'success', // Indicate successful login
					'login_id' => $params['username'], // This can be set to a unique ID if needed
				];

				$loginHistoryModel->addLoginHistory($loginData);

				$this->redirect('/admin');
			} else {
				// Insert login history

				$loginHistoryModel = new Application_Model_DbTable_UserLoginHistory();
				$loginData = [
					'user_id' => NULL,
					'login_context' => 'admin', // or 'failed' if login failed
					'login_status' => 'failed', // Indicate failed login
					'login_id' => $params['username'], // This can be set to a unique ID if needed
				];

				$loginHistoryModel->addLoginHistory($loginData);
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check your login credentials";
				$sessionAlert->status = "failure";
			}
		} else {
			$commonServices = new Application_Service_Common();
			$this->view->instituteName = $commonServices->getConfig('institute_name');
			$this->view->formToken = Application_Service_Common::generateFormToken();
			// We are destroying the session here in case this person has
			// logged in as a User as well..
			// We don't want that
			Zend_Auth::getInstance()->clearIdentity();
			//Zend_Session::destroy();
			SecurityService::generateCSRF();
		}
	}

	public function logOutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		Zend_Session::destroy();
		$this->redirect('/admin');
	}
}
