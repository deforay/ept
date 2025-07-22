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
		$request = $this->getRequest();
		/** @var Zend_Controller_Request_Http $request */
		$request = $this->getRequest();
		if (!$request->isPost()) {
			// Handle GET request - show login form with configuration
			$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
			$this->view->loginBan = $globalConfigDb->getValue('enable_login_attempt_ban');
			$this->view->maxAttemptTempBan = $globalConfigDb->getValue('max_attempts_for_temp_ban');
			$this->view->maxAttemptPermBan = $globalConfigDb->getValue('max_attempts_for_perm_ban');
			return;
		}

		// Initialize services and session
		$dbUsersProfile = new Application_Service_Participants();
		$dataManager = new Application_Service_DataManagers();
		$dmDb = new Application_Model_DbTable_DataManagers();
		$globalConfigDb = new Application_Model_DbTable_GlobalConfig();
		$sessionAlert = new Zend_Session_Namespace('alertSpace');

		try {
			// Validate CAPTCHA
			$captchaSession = new Zend_Session_Namespace('DACAPTCHA');
			if (
				!isset($captchaSession->captchaStatus) ||
				empty($captchaSession->captchaStatus) ||
				$captchaSession->captchaStatus === 'fail'
			) {
				$sessionAlert->message = "Please enter the correct text from the image.";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
				return;
			}

			// Sanitize input
			$params = $request->getPost();
			$username = trim($params['username'] ?? '');
			$password = trim($params['password'] ?? '');

			if (empty($username) || empty($password)) {
				$sessionAlert->message = "Please enter both username and password.";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
				return;
			}

			// Get configuration values
			$loginBan = $globalConfigDb->getValue('enable_login_attempt_ban');
			$loginBanTime = (int)$globalConfigDb->getValue('temporary_login_ban_time');
			$maxAttemptTempBan = (int)$globalConfigDb->getValue('max_attempts_for_temp_ban');
			$maxAttemptPermBan = (int)$globalConfigDb->getValue('max_attempts_for_perm_ban');

			// Get user details and check permanent ban
			$userDetails = $dmDb->getUserDetails($username);
			$_SESSION['currentUser'] = $username;

			if (
				isset($userDetails) && !empty($userDetails) &&
				isset($userDetails['login_ban']) && $userDetails['login_ban'] === 'yes'
			) {
				$sessionAlert->message = "Your account has been permanently locked. Please contact the PT Administrator for support.";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
				return;
			}

			// Initialize session arrays if not exist
			if (!isset($_SESSION['loginAttempt'])) {
				$_SESSION['loginAttempt'] = [];
			}
			if (!isset($_SESSION['loginAttemptTimer'])) {
				$_SESSION['loginAttemptTimer'] = [];
			}

			// Initialize login attempts for this user
			if (
				!isset($_SESSION['loginAttempt'][$username]) ||
				empty($loginBan) ||
				$loginBan !== 'yes' ||
				!$userDetails ||
				empty($userDetails)
			) {
				$_SESSION['loginAttempt'][$username] = 0;
				$_SESSION['loginAttemptTimer'][$username] = null;
			}

			// Check if user is temporarily banned
			if (
				isset($_SESSION['loginAttemptTimer'][$username]) &&
				$_SESSION['loginAttemptTimer'][$username] !== null
			) {
				$banEndTime = strtotime($_SESSION['loginAttemptTimer'][$username]);
				if (time() < $banEndTime) {
					$remainingMinutes = ceil(($banEndTime - time()) / 60);
					$sessionAlert->message = "Your account is temporarily locked. Please try again in {$remainingMinutes} minutes.";
					$sessionAlert->status = "failure";
					$this->redirect($this->loginUri);
					return;
				} else {
					// Ban expired, reset attempts
					$_SESSION['loginAttempt'][$username] = 0;
					$_SESSION['loginAttemptTimer'][$username] = null;
				}
			}

			// Authenticate user
			$result = $dmDb->fethDataByCredentials($username, $password);
			$passwordVerify = false;

			if (isset($result) && !empty($result) && isset($result['password'])) {
				$passwordVerify = password_verify($password, $result['password']);
			}

			if (isset($result) && !empty($result) && $passwordVerify) {
				// Successful login - clear login attempts
				unset($_SESSION['loginAttempt'][$username]);
				unset($_SESSION['loginAttemptTimer'][$username]);

				// Regenerate session ID for security
				Zend_Session::regenerateId();
				Zend_Session::rememberMe(60 * 60 * 5); // 5 hours session

				// Set up user sessions
				$loggedUser = new Zend_Session_Namespace('loggedUser');
				$loggedUser->partcipant_id = $result['dm_id'];
				$loggedUser->primary_email = $result['primary_email'];
				$loggedUser->first_name = $result['first_name'];
				$loggedUser->last_name = $result['last_name'];

				$authNameSpace = new Zend_Session_Namespace('datamanagers');
				$authNameSpace->UserID = $username;
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

				// Check for profile updates
				$profileUpdate = $dbUsersProfile->checkParticipantsProfileUpdate($result['dm_id']);
				if (!empty($profileUpdate)) {
					$authNameSpace->force_profile_updation = 1;
					$authNameSpace->profile_updation_pid = $profileUpdate[0]['participant_id'];
				}

				// Set PTCC flag if applicable
				if (
					isset($result['data_manager_type']) &&
					$result['data_manager_type'] === 'ptcc'
				) {
					$authNameSpace->ptcc = 1;
				}

				// Update last login
				$userService = new Application_Service_DataManagers();
				$userService->updateLastLogin($result['dm_id']);
				$authNameSpace->announcementMsg = $userService->checkAnnouncementMessageShowing($result['dm_id']);

				// Check if profile review is needed
				$lastLogin = $result['last_login'];
				$lastLoginDate = date('Ymd', strtotime($lastLogin));
				$sixMonthsAgo = date("Ymd", strtotime("-6 months"));

				if ($authNameSpace->force_profile_check === 'yes' || ($sixMonthsAgo > $lastLoginDate)) {
					$authNameSpace->force_profile_check_primary = 'yes';
					$sessionAlert->message = "Please review your profile and primary email.";
					$sessionAlert->status = "failure";
					$this->redirect('participant/user-info');
					return;
				} else {
					$authNameSpace->force_profile_check_primary = 'no';
				}

				// Check for old email verification
				$oldMail = $dataManager->checkOldMail($result['dm_id']);
				if (isset($oldMail) && !empty($oldMail) && !empty($oldMail['new_email'])) {
					$sessionAlert->message = "Please verify your new email: " . $oldMail['new_email'];
					$sessionAlert->status = "failure";
					$this->redirect('participant/user-info');
					return;
				}

				// Successful login - redirect to dashboard
				$this->redirect('/participant/dashboard');
			} else {
				// Failed login - handle login attempts and banning
				if (
					isset($loginBan) && $loginBan === 'yes' &&
					isset($_SESSION['loginAttempt'][$username])
				) {

					$_SESSION['loginAttempt'][$username]++;

					// Check for temporary ban
					if (
						$_SESSION['loginAttempt'][$username] >= $maxAttemptTempBan &&
						$_SESSION['loginAttempt'][$username] < $maxAttemptPermBan
					) {
						$_SESSION['loginAttemptTimer'][$username] = date('M d, Y H:i:s', strtotime("+{$loginBanTime} minutes"));
						$sessionAlert->message = "Your account has been temporarily locked. Please try again in {$loginBanTime} minutes.";
						$sessionAlert->status = "failure";
						$this->redirect($this->loginUri);
						return;
					}

					// Check for permanent ban
					if ($_SESSION['loginAttempt'][$username] >= $maxAttemptPermBan) {
						if (
							isset($userDetails) && !empty($userDetails) &&
							isset($userDetails['primary_email'])
						) {
							$dmDb->setLoginAtempBan($userDetails['primary_email']);
						}
						$sessionAlert->message = "Your account has been permanently locked. Please contact the PT Administrator for support.";
						$sessionAlert->status = "failure";
						$this->redirect($this->loginUri);
						return;
					}
				}

				// Generic login failure message
				$sessionAlert->message = "Invalid username or password. Please try again.";
				$sessionAlert->status = "failure";
				$this->redirect($this->loginUri);
			}
		} catch (Exception $e) {
			// Log the error for debugging
			error_log('Login error: ' . $e->getMessage());

			$sessionAlert->message = "An unexpected error occurred. Please try again.";
			$sessionAlert->status = "failure";
			$this->redirect($this->loginUri);
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
