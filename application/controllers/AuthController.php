<?php

class AuthController extends Zend_Controller_Action
{
	private $loginUri = '/auth/login';
	private $emailVerifyErrMsg = 'Sorry! Your email verification link has expired. Please contact the PT provider for further queries.';

	public function init()
	{
		/* Initialize action controller here */
		$this->_helper->layout()->setLayout('home');
	}

	public function indexAction()
	{
		$this->redirect($this->loginUri);
	}

	public function verifyAction()
	{
		$userService = new Application_Service_DataManagers();
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		$authNameSpace->force_profile_check_primary = 'no';
		if ($this->hasParam('email')) {
			$email = $this->_getParam('email');
			$result = $userService->verifyEmailById($email);
			if ($result) {
				$userService->updateForceProfileCheck($email, $result);
				$userService->setStatusByEmailDM('active', base64_decode($email));
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Thank you. Your email has been verified successfully. You can now use your new email to login to ePT";
				$sessionAlert->status = "success";
			} else {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = $this->emailVerifyErrMsg;
				$sessionAlert->status = "failure";
			}
		} else {
			$sessionAlert = new Zend_Session_Namespace('alertSpace');
			$sessionAlert->message = $this->emailVerifyErrMsg;
			$sessionAlert->status = "failure";
		}
		$this->redirect($this->loginUri);
	}

	public function verifyEmailAction()
	{
		$userService = new Application_Service_DataManagers();
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();
			$userService->confirmPrimaryMail($params);
			$this->redirect('/');
		}
		if ($this->hasParam('t')) {
			$link = $this->_getParam('t');
			$result = $userService->checkForceProfileEmail($link);
			if ($result) {
				$this->view->result = $result;
			} else {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = $this->emailVerifyErrMsg;
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
			}
		} else {
			$this->redirect($this->loginUri);
		}
	}

	public function loginAction()
	{
		$dbUsersProfile = new Application_Service_Participants();
		$dataManager = new Application_Service_DataManagers();
		// action body

		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if ($request->isPost()) {
			$params = $request->getPost();

			$captchaSession = new Zend_Session_Namespace('DACAPTCHA');
			if (!isset($captchaSession->captchaStatus) || empty($captchaSession->captchaStatus) || $captchaSession->captchaStatus == 'fail') {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check if you entered the correct text from the image";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
			}

			$dmDb = new Application_Model_DbTable_DataManagers();
			$params['username'] = trim($params['username']);
			$params['password'] = trim($params['password']);
			$result = $dmDb->fethDataByCredentials(trim($params['username']), trim($params['password']));

			$passwordVerify = true;
			if (isset($result) && !empty($result)) {
				$passwordVerify = password_verify((string) $params['password'], (string) $result['password']);
			}

			$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
			$sessionAlert = new Zend_Session_Namespace('alertSpace');
			$loginBan = $globalConfigDb->getValue('enable_login_attempt_ban');
			$loginBanTime = $globalConfigDb->getValue('temporary_login_ban_time');
			$maxAttemptTempBan = $globalConfigDb->getValue('max_attempts_for_temp_ban');
			$maxAttemptPermBan = $globalConfigDb->getValue('max_attempts_for_perm_ban');

			$dmFound = $dmDb->getUserDetails($params['username']);
			$_SESSION['currentUser'] = $params['username'];
			if (isset($dmFound) && !empty($dmFound) && $dmFound['login_ban'] == 'yes') {
				$sessionAlert->message = "Your account has been permanently locked. Please reach out the PT Administrator for further support";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
			}
			// declare login attempt when it zero
			if (!isset($_SESSION['loginAttempt'][$_SESSION['currentUser']]) || empty($_SESSION['loginAttempt'][$_SESSION['currentUser']]) || !isset($loginBan) || empty($loginBan) || $loginBan != 'yes' || !$dmFound || empty($dmFound)) {
				$_SESSION['loginAttempt'][$_SESSION['currentUser']] = 0;
				$_SESSION['loginAttemptTimer'][$_SESSION['currentUser']] = null;
			}
			if (isset($result) && !empty($result) && $passwordVerify) {
				unset($_SESSION['loginAttempt']);

				Zend_Session::regenerateId();
				Zend_Session::rememberMe(60 * 60 * 5); // asking the session to be active for 5 hours

				$authNameSpace = new Zend_Session_Namespace('datamanagers');
				$authNameSpace->UserID = $params['username'];
				$authNameSpace->dm_id = $result['dm_id'];
				$authNameSpace->first_name = $result['first_name'];
				$authNameSpace->last_name = $result['last_name'];
				$authNameSpace->phone = $result['phone'];
				$authNameSpace->email = $result['primary_email'];
				$authNameSpace->qc_access = $result['qc_access'];
				$authNameSpace->view_only_access = $result['view_only_access'];
				$authNameSpace->enable_adding_test_response_date = $result['enable_adding_test_response_date'];
				$authNameSpace->enable_choosing_mode_of_receipt = $result['enable_choosing_mode_of_receipt'];
				$authNameSpace->forcePasswordReset = $result['force_password_reset'];
				$authNameSpace->force_profile_check = $result['force_profile_check'];
				$authNameSpace->language = $result['language'];
				$authNameSpace->data_manager_type = $result['data_manager_type'];
				$lastLogin = $result['last_login'];
				$profileUpdate = $dbUsersProfile->checkParticipantsProfileUpdate($result['dm_id']);
				if (!empty($profileUpdate)) {
					$authNameSpace->force_profile_updation = 1;
					$authNameSpace->profile_updation_pid = $profileUpdate[0]['participant_id'];
				}
				if (isset($result['ptcc']) && !empty($result['ptcc']) && $result['ptcc'] == 'yes') {
					$authNameSpace->ptcc = 1;
				}

				/* For force_profile_check start*/
				$lastLogin = date('Ymd', strtotime($lastLogin));
				$current = date("Ymd", strtotime(" -6 months"));
				if ($authNameSpace->force_profile_check == 'yes' || ($current > $lastLogin)) {
					$authNameSpace->force_profile_check_primary = 'yes';
					$sessionAlert->message = "Please review your profile and primary email.";
					$sessionAlert->status = "failure";
					$userService = new Application_Service_DataManagers();
					$userService->updateLastLogin($result['dm_id']);
					$authNameSpace->announcementMsg = $userService->checkAnnouncementMessageShowing($result['dm_id']);
					$this->redirect('participant/user-info');
				} else {
					$userService = new Application_Service_DataManagers();
					$userService->updateLastLogin($result['dm_id']);
					$authNameSpace->announcementMsg = $userService->checkAnnouncementMessageShowing($result['dm_id']);
					$authNameSpace->force_profile_check_primary = 'no';
				}
				/* For force_profile_check end */
				/* Check Old mail login */
				$oldMail = $dataManager->checkOldMail($result['dm_id']);
				if (isset($oldMail) && $oldMail != "") {
					$sessionAlert = new Zend_Session_Namespace('alertSpace');
					$sessionAlert->message = "Please verify your new email " . $oldMail['new_email'] . " that you changed last login";
					$sessionAlert->status = "failure";
					$this->redirect('participant/user-info');
				}
				$this->redirect('/participant/dashboard');
			} else {
				if (isset($loginBan) && !empty($loginBan) && $loginBan == 'yes') {
					$_SESSION['loginAttempt'][$_SESSION['currentUser']] = ($_SESSION['loginAttempt'][$_SESSION['currentUser']] + 1);
					if ($_SESSION['loginAttempt'][$_SESSION['currentUser']] == $maxAttemptTempBan) {
						$_SESSION['loginAttemptTimer'][$_SESSION['currentUser']] = date('M d, Y H:i:s', strtotime('+' . $loginBanTime . ' MINUTES'));
						$sessionAlert->message = "Your account has been temporarily locked. Please try in " . $loginBanTime . " minutes";
						$sessionAlert->status = "failure";
						$this->redirect($this->loginUri);
					}
				}
			}
			if (isset($loginBan) && !empty($loginBan) && $loginBan == 'yes' && isset($_SESSION['loginAttempt'][$_SESSION['currentUser']]) && !empty($_SESSION['loginAttempt'][$_SESSION['currentUser']]) && $_SESSION['loginAttempt'][$_SESSION['currentUser']] >= $maxAttemptPermBan) {
				$dmDb->setLoginAtempBan($dmFound['primary_email']);
				$sessionAlert->message = "Your account has been permanently locked. Please reach out the PT Administrator for further support";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
			}
			$sessionAlert->message = "Sorry. Unable to log you in. Please wait for some time to login.";
			$sessionAlert->status = "failure";
			$this->redirect($this->loginUri);
		} else {
			$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
			$this->view->loginBan = $globalConfigDb->getValue('enable_login_attempt_ban');
			$this->view->maxAttemptTempBan = $globalConfigDb->getValue('max_attempts_for_temp_ban');
			$this->view->maxAttemptPermBan = $globalConfigDb->getValue('max_attempts_for_perm_ban');
		}
	}

	public function logoutAction()
	{
		Zend_Auth::getInstance()->clearIdentity();
		Zend_Session::destroy();
		$this->redirect('/');
	}

	public function resetPasswordAction()
	{
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if ($request->isPost()) {
			$email = $request->getPost('registeredEmail');
			$userService = new Application_Service_DataManagers();
			$userService->resetPassword($email);
			$this->redirect($this->loginUri);
		}
	}

	public function newPasswordAction()
	{
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		$userService = new Application_Service_DataManagers();
		if ($request->isPost()) {
			$params = $request->getPost();
			$this->redirect($userService->newPassword($params));
		} else {
			if ($this->hasParam('email')) {
				$email = $this->_getParam('email');
				$result = $userService->checkEmail($email);
				if ($result) {
					$this->view->email = $result;
				} else {
					$this->view->email = "";
				}
				$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
				$this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
			}
		}
	}
}
