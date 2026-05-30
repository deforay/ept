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
                $sessionAlert->message = 'Thank you. Your email has been verified successfully. You can now use your new email to login to ePT';
                $sessionAlert->status = 'success';
            } else {
                $sessionAlert = new Zend_Session_Namespace('alertSpace');
                $sessionAlert->message = $this->emailVerifyErrMsg;
                $sessionAlert->status = 'failure';
            }
        } else {
            $sessionAlert = new Zend_Session_Namespace('alertSpace');
            $sessionAlert->message = $this->emailVerifyErrMsg;
            $sessionAlert->status = 'failure';
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
            if (
                !empty($params['website_url']) ||
                !Application_Service_Common::consumeFormToken($params['form_token'] ?? null, 'authVerifyEmailTokens')
            ) {
                $this->redirect($this->loginUri);
                return;
            }
            if (!Application_Service_Common::consumeCaptcha()) {
                $sessionAlert = new Zend_Session_Namespace('alertSpace');
                $sessionAlert->message = 'Please enter the correct text from the image.';
                $sessionAlert->status = 'failure';
                $this->redirect($this->loginUri);
                return;
            }
            $userService->confirmPrimaryMail($params);
            $this->redirect('/');
        }
        if ($this->hasParam('t')) {
            $link = $this->_getParam('t');
            $result = $userService->checkForceProfileEmail($link);
            if ($result) {
                $this->view->result = $result;
                $this->view->formToken = Application_Service_Common::generateFormToken();
            } else {
                $sessionAlert = new Zend_Session_Namespace('alertSpace');
                $sessionAlert->message = $this->emailVerifyErrMsg;
                $sessionAlert->status = 'failure';
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
            $this->view->formToken = Application_Service_Common::generateFormToken();
            return;
        }

        // Initialize services and session
        $dbUsersProfile = new Application_Service_Participants();
        $dataManager = new Application_Service_DataManagers();
        $dmDb = new Application_Model_DbTable_DataManagers();
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $sessionAlert = new Zend_Session_Namespace('alertSpace');

        try {
            $params = $request->getPost();

            // Honeypot + single-use CSRF token. Silently redirect on failure so
            // bots can't tell the difference from a captcha miss.
            if (
                !empty($params['website_url']) ||
                !Application_Service_Common::consumeFormToken($params['form_token'] ?? null, 'authLoginTokens')
            ) {
                $this->redirect($this->loginUri);
                return;
            }

            if (!Application_Service_Common::consumeCaptcha()) {
                $sessionAlert->message = 'Please enter the correct text from the image.';
                $sessionAlert->status = 'failure';
                $this->redirect($this->loginUri);
                return;
            }

            // Sanitize input
            $username = trim($params['username'] ?? '');
            $password = trim($params['password'] ?? '');

            if (empty($username) || empty($password)) {
                $sessionAlert->message = 'Please enter both username and password.';
                $sessionAlert->status = 'failure';
                $this->redirect($this->loginUri);
                return;
            }

            // Get configuration values
            $loginBan = $globalConfigDb->getValue('enable_login_attempt_ban');
            $loginBanTime = (int) $globalConfigDb->getValue('temporary_login_ban_time');
            $maxAttemptTempBan = (int) $globalConfigDb->getValue('max_attempts_for_temp_ban');
            $maxAttemptPermBan = (int) $globalConfigDb->getValue('max_attempts_for_perm_ban');

            // Get user details and check permanent ban
            $userDetails = $dmDb->getUserDetails($username);
            $attemptNs = new Zend_Session_Namespace('loginAttempts');
            $attemptNs->currentUser = $username;
            $counts = is_array($attemptNs->counts ?? null) ? $attemptNs->counts : [];
            $timers = is_array($attemptNs->timers ?? null) ? $attemptNs->timers : [];

            if (
                isset($userDetails) && !empty($userDetails) &&
                isset($userDetails['login_ban']) && $userDetails['login_ban'] === 'yes'
            ) {
                $sessionAlert->message = 'Your account has been permanently locked. Please contact the PT Administrator for support.';
                $sessionAlert->status = 'failure';
                $this->redirect($this->loginUri);
                return;
            }

            // Initialize login attempts for this user
            if (
                !isset($counts[$username]) ||
                empty($loginBan) ||
                $loginBan !== 'yes' ||
                !$userDetails ||
                empty($userDetails)
            ) {
                $counts[$username] = 0;
                $timers[$username] = null;
            }

            // Check if user is temporarily banned
            if (isset($timers[$username]) && $timers[$username] !== null) {
                $banEndTime = strtotime($timers[$username]);
                if (time() < $banEndTime) {
                    $remainingMinutes = ceil(($banEndTime - time()) / 60);
                    $attemptNs->counts = $counts;
                    $attemptNs->timers = $timers;
                    $sessionAlert->message = "Your account is temporarily locked. Please try again in {$remainingMinutes} minutes.";
                    $sessionAlert->status = 'failure';
                    $this->redirect($this->loginUri);
                    return;
                }
                // Ban expired, reset attempts
                $counts[$username] = 0;
                $timers[$username] = null;
            }

            // Authenticate user
            $result = $dmDb->fethDataByCredentials($username, $password);
            $passwordVerify = false;

            if (isset($result) && !empty($result) && isset($result['password'])) {
                $passwordVerify = password_verify($password, $result['password']);
            }
            $userId = null;
            if (isset($result) && !empty($result) && $passwordVerify) {
                // Successful login - clear login attempts
                unset($counts[$username], $timers[$username]);
                $attemptNs->counts = $counts;
                $attemptNs->timers = $timers;
                unset($attemptNs->currentUser);

                // Regenerate session ID for security. Cookie is intentionally a
                // session cookie (no rememberMe) — the 30-min idle in PreSetter
                // is the real ceiling, and a closed browser should require login.
                Zend_Session::regenerateId();

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
                $sixMonthsAgo = date('Ymd', strtotime('-6 months'));

                if ($authNameSpace->force_profile_check === 'yes' || ($sixMonthsAgo > $lastLoginDate)) {
                    $authNameSpace->force_profile_check_primary = 'yes';
                    $sessionAlert->message = 'Please review your profile and primary email.';
                    $sessionAlert->status = 'failure';
                    $this->redirect('participant/user-info');
                    return;
                } else {
                    $authNameSpace->force_profile_check_primary = 'no';
                }

                // Check for old email verification
                $oldMail = $dataManager->checkOldMail($result['dm_id']);
                if (isset($oldMail) && !empty($oldMail) && !empty($oldMail['new_email'])) {
                    $sessionAlert->message = 'Please verify your new email: ' . $oldMail['new_email'];
                    $sessionAlert->status = 'failure';
                    $this->redirect('participant/user-info');
                    return;
                }

                // Insert login history
                $userId = $result['dm_id']; // Set this to the logged-in user's ID

                $loginHistoryModel = new Application_Model_DbTable_UserLoginHistory();
                $loginData = [
                    'user_id' => $userId,
                    'login_context' => 'participant', // or 'failed' if login failed
                    'login_status' => 'success', // Indicate failed login
                    'login_id' => $username, // This can be set to a unique ID if needed
                ];

                $loginHistoryModel->addLoginHistory($loginData);

                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog('Logged in', 'auth');
                // To check the re-drection for participant after logged in
                $dbUsersProfile = new Application_Service_Participants();
                $shipmentService = new Application_Service_Shipments();
                $authNameSpace = new Zend_Session_Namespace('datamanagers');
                $checkPendingParticipants = $dbUsersProfile->getNotRespondedParticipantsByDmId($authNameSpace->dm_id);
                $checkPendingReports = $shipmentService->getFinalizedShipmentReportByDmId($authNameSpace->dm_id);
                if ($checkPendingParticipants) {
                    $this->redirect('/participant/current-schemes');
                } elseif ($checkPendingReports) {
                    $this->redirect('/participant/report');
                } else {
                    $this->redirect('/participant/dashboard');
                }
            } else {
                // Failed login - handle login attempts and banning
                if (
                    isset($loginBan) && $loginBan === 'yes' &&
                    isset($counts[$username])
                ) {

                    $counts[$username]++;

                    // Check for temporary ban
                    if (
                        $counts[$username] >= $maxAttemptTempBan &&
                        $counts[$username] < $maxAttemptPermBan
                    ) {
                        $timers[$username] = date('M d, Y H:i:s', strtotime("+{$loginBanTime} minutes"));
                        $attemptNs->counts = $counts;
                        $attemptNs->timers = $timers;
                        $sessionAlert->message = "Your account has been temporarily locked. Please try again in {$loginBanTime} minutes.";
                        $sessionAlert->status = 'failure';
                        $this->redirect($this->loginUri);
                        return;
                    }

                    // Check for permanent ban
                    if ($counts[$username] >= $maxAttemptPermBan) {
                        if (
                            isset($userDetails) && !empty($userDetails) &&
                            isset($userDetails['primary_email'])
                        ) {
                            $dmDb->setLoginAtempBan($userDetails['primary_email']);
                        }
                        $attemptNs->counts = $counts;
                        $attemptNs->timers = $timers;
                        $sessionAlert->message = 'Your account has been permanently locked. Please contact the PT Administrator for support.';
                        $sessionAlert->status = 'failure';
                        $this->redirect($this->loginUri);
                        return;
                    }

                    // Persist incremented counter even when not yet banned.
                    $attemptNs->counts = $counts;
                    $attemptNs->timers = $timers;
                }

                // Insert login history
                //$userId = $authenticatedUserId; // Set this to the logged-in user's ID

                $loginHistoryModel = new Application_Model_DbTable_UserLoginHistory();
                $loginData = [
                    'user_id' => $userId,
                    'login_context' => 'participant', // or 'failed' if login failed
                    'login_status' => 'failed', // Indicate failed login
                    'login_id' => $username, // This can be set to a unique ID if needed
                ];

                $loginHistoryModel->addLoginHistory($loginData);

                // Generic login failure message
                $sessionAlert->message = 'Invalid username or password. Please try again.';
                $sessionAlert->status = 'failure';
                $this->redirect($this->loginUri);
            }
        } catch (Throwable $e) {
            // Log the error for debugging
            error_log('Login error: ' . $e->getMessage());

            $sessionAlert->message = 'An unexpected error occurred. Please try again.';
            $sessionAlert->status = 'failure';
            $this->redirect($this->loginUri);
        }
    }
    public function logoutAction()
    {
        // If this is an admin viewing as participant, route the click to the
        // impersonation-exit flow instead — Zend_Session::destroy() would
        // also wipe the admin's own session under the same cookie.
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->impersonatedBy)) {
            $this->redirect('/admin/impersonate/stop');
            return;
        }
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Logged out', 'auth');
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::destroy();
        $this->redirect('/');
    }

    public function resetPasswordAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            if (
                !empty($params['website_url']) ||
                !Application_Service_Common::consumeFormToken($params['form_token'] ?? null, 'authResetPwdTokens')
            ) {
                $this->redirect('/auth/reset-password');
                return;
            }
            if (!Application_Service_Common::consumeCaptcha()) {
                $sessionAlert = new Zend_Session_Namespace('alertSpace');
                $sessionAlert->message = 'Please enter the correct text from the image.';
                $sessionAlert->status = 'failure';
                $this->redirect('/auth/reset-password');
                return;
            }
            $userService = new Application_Service_DataManagers();
            $email = trim((string) ($params['registeredEmail'] ?? ''));
            $userService->resetPassword($email !== '' ? $email : null);
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Requested password reset' . ($email !== '' ? " - {$email}" : ''), 'auth');
            $this->redirect($this->loginUri);
        }
        $this->view->formToken = Application_Service_Common::generateFormToken();
    }

    public function newPasswordAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $userService = new Application_Service_DataManagers();
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $token = $this->hasParam('token') ? (string) $this->_getParam('token') : '';
        $resolved = $token !== '' ? $userService->resolveResetToken($token) : null;
        if (empty($resolved) || empty($resolved['primary_email']) || empty($resolved['dm_id'])) {
            $sessionAlert->message = 'This password reset link is invalid or has expired.';
            $sessionAlert->status = 'failure';
            $this->redirect($this->loginUri);
            return;
        }
        $verifiedEmail = $resolved['primary_email'];
        $verifiedDmId = (int) $resolved['dm_id'];
        if ($request->isPost()) {
            $params = $request->getPost();
            if (
                !empty($params['website_url']) ||
                !Application_Service_Common::consumeFormToken($params['form_token'] ?? null, 'authNewPwdTokens')
            ) {
                $this->redirect($request->getRequestUri());
                return;
            }
            if (!Application_Service_Common::consumeCaptcha()) {
                $sessionAlert->message = 'Please enter the correct text from the image.';
                $sessionAlert->status = 'failure';
                $this->redirect($request->getRequestUri());
                return;
            }
            $params['registeredEmail'] = $verifiedEmail;
            $params['dm_id'] = $verifiedDmId;
            $redirectTo = $userService->newPassword($params);
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Set new password via reset link - ' . $verifiedEmail, 'auth');
            $this->redirect($redirectTo);
        } else {
            $this->view->email = $resolved;
            $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
            $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
            $this->view->formToken = Application_Service_Common::generateFormToken();
        }
    }
}
