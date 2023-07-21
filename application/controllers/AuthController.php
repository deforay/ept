<?php

class AuthController extends Zend_Controller_Action
{

	public function init()
	{
		/* Initialize action controller here */
		$this->_helper->layout()->setLayout('home');
	}

	public function indexAction()
	{
		$this->redirect('/auth/login');
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
				$sessionAlert->message = "Sorry! Your email verification link has expired. Please contact the PT provider for further queries.";
				$sessionAlert->status = "failure";
			}
		} else {
			$sessionAlert = new Zend_Session_Namespace('alertSpace');
			$sessionAlert->message = "Sorry! Your email verification link has expired. Please contact the PT provider for further queries.";
			$sessionAlert->status = "failure";
		}
		$this->redirect('/auth/login');
	}

	public function verifyEmailAction()
	{
		$sessionAlert = new Zend_Session_Namespace('alertSpace');
		$userService = new Application_Service_DataManagers();
		if ($this->getRequest()->isPost()) {
			$params = $this->getRequest()->getPost();
			$userService->confirmPrimaryMail($params);
			// $sessionAlert->message = "Thank you. Please check your email for further instructions. ";
			$this->redirect('/');
		}
		if ($this->hasParam('t')) {
			$link = $this->_getParam('t');
			$result = $userService->checkForceProfileEmail($link);
			if ($result) {
				$this->view->result = $result;
			} else {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry! Your email verification link has expired. Please contact the PT provider for further queries.";
				$sessionAlert->status = "failure";
				$this->redirect('/auth/login');
			}
		} else {
			$this->redirect('/auth/login');
		}
	}

	public function loginAction()
	{
		$dbUsersProfile = new Application_Service_Participants();
		$dataManager = new Application_Service_DataManagers();
		// action body
		if ($this->getRequest()->isPost()) {
			//die;
			//echo "Post";
			$params = $this->getRequest()->getPost();
			//Zend_Debug::dump($params);die;

			$captchaSession = new Zend_Session_Namespace('DACAPTCHA');
			if (!isset($captchaSession->captchaStatus) || empty($captchaSession->captchaStatus) || $captchaSession->captchaStatus == 'fail') {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check if you entered the correct text from the image";
				$sessionAlert->status = "failure";
				$this->redirect('/auth/login');
			}

			$params['username'] = trim($params['username']);
			$params['password'] = trim($params['password']);
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$adapter = new Zend_Auth_Adapter_DbTable($db, "data_manager", "primary_email", "password");
			$adapter->setIdentity($params['username']);
			$adapter->setCredential($params['password']);

			$select = $adapter->getDbSelect();
			$select->where('status = "active"');

			// STEP 2 : Let's Authenticate
			$auth = Zend_Auth::getInstance();
			$res = $auth->authenticate($adapter); // -- METHOD 2 to authenticate , seems to work fine for me

			//echo "hi";
			if ($res->isValid()) {
				Zend_Session::rememberMe(60 * 60 * 5); // asking the session to be active for 5 hours

				$rs = $adapter->getResultRowObject();

				$authNameSpace = new Zend_Session_Namespace('datamanagers');
				$authNameSpace->UserID = $params['username'];
				$authNameSpace->dm_id = $rs->dm_id;
				$authNameSpace->first_name = $rs->first_name;
				$authNameSpace->last_name = $rs->last_name;
				$authNameSpace->phone = $rs->phone;
				$authNameSpace->email = $rs->primary_email;
				$authNameSpace->qc_access = $rs->qc_access;
				$authNameSpace->view_only_access = $rs->view_only_access;
				$authNameSpace->enable_adding_test_response_date = $rs->enable_adding_test_response_date;
				$authNameSpace->enable_choosing_mode_of_receipt = $rs->enable_choosing_mode_of_receipt;
				$authNameSpace->forcePasswordReset = $rs->force_password_reset;
				$authNameSpace->force_profile_check = $rs->force_profile_check;
				$authNameSpace->language = $rs->language;
				$lastLogin = $rs->last_login;
				$profileUpdate = $dbUsersProfile->checkParticipantsProfileUpdate($rs->dm_id);
				if (!empty($profileUpdate)) {
					$authNameSpace->force_profile_updation = 1;
					$authNameSpace->profile_updation_pid = $profileUpdate[0]['participant_id'];
				}
				if (isset($rs->ptcc) && !empty($rs->ptcc) && $rs->ptcc == 'yes') {
					$authNameSpace->ptcc = 1;
					$countries = $dataManager->getPtccCountryMap($rs->dm_id, 'implode');
					$authNameSpace->ptccMappedCountries = implode(",", $countries);
				}

				$participants = $dataManager->getDatamanagerParticipantListByDid($rs->dm_id);
				if (!empty($participants)) {
					$mappedParticipants = array();
					foreach ($participants as $parti) {
						$mappedParticipants[] = $parti['participant_id'];
					}
					$authNameSpace->mappedParticipants = implode(",", $mappedParticipants);
				}

				// PT Provider Dependent Configuration
				//$authNameSpace->UserFld1 = $rs->UserFld1;
				//$authNameSpace->UserFld2 = $rs->UserFld2;
				//$authNameSpace->UserFld3 = $rs->UserFld3;
				/* For force_profile_check start*/
				$lastLogin = date('Ymd', strtotime($lastLogin));
				$current = date("Ymd", strtotime(" -6 months"));
				if ($authNameSpace->force_profile_check == 'yes' || ($current > $lastLogin)) {
					$authNameSpace->force_profile_check_primary = 'yes';
					$sessionAlert = new Zend_Session_Namespace('alertSpace');
					$sessionAlert->message = "Please review your profile and primary email.";
					$sessionAlert->status = "failure";
					$userService = new Application_Service_DataManagers();
					$userService->updateLastLogin($rs->dm_id);
					$authNameSpace->announcementMsg = $userService->checkAnnouncementMessageShowing($rs->dm_id);
					$this->redirect('participant/user-info');
				} else {
					$userService = new Application_Service_DataManagers();
					$userService->updateLastLogin($rs->dm_id);
					$authNameSpace->announcementMsg = $userService->checkAnnouncementMessageShowing($rs->dm_id);
					$authNameSpace->force_profile_check_primary = 'no';
				}
				/* For force_profile_check end */
				/* Check Old mail login */
				$oldMail = $dataManager->checkOldMail($rs->dm_id);
				if (isset($oldMail) && $oldMail != "") {
					$sessionAlert = new Zend_Session_Namespace('alertSpace');
					$sessionAlert->message = "Please verify your new email " . $oldMail['new_email'] . " that you changed last login";
					$sessionAlert->status = "failure";
					$this->redirect('participant/user-info');
				}
				if (isset($params['redirectUrl']) && $params['redirectUrl'] != '/auth/login') {
				} else {
					$this->redirect('/participant/dashboard');
				}
			} else {
				$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check your login credentials";
				$sessionAlert->status = "failure";
			}
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
		if ($this->getRequest()->isPost()) {
			$email = $this->getRequest()->getPost('registeredEmail');
			$userService = new Application_Service_DataManagers();
			$userService->resetPassword($email);
			$this->redirect('/auth/login');
		}
	}

	public function newPasswordAction()
	{
		$userService = new Application_Service_DataManagers();
		if ($this->getRequest()->isPost()) {
			$params = $this->getRequest()->getPost();
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
			}
		}
	}
}
