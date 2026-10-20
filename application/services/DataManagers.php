<?php

class Application_Service_DataManagers
{
    protected $datamanagersDb;
    protected $translator;
    public function __construct()
    {
        $this->datamanagersDb = new Application_Model_DbTable_DataManagers();
        $this->translator = Zend_Registry::get('translate');
    }

    public function addUser($params)
    {
        return $this->datamanagersDb->addUser($params);
    }

    /**
     * Minimal Data Manager creation used by the inline picker on the
     * participant add/edit form. Only email + password are required.
     *
     * @return array{dm_id?: int, label?: string, error?: string}
     */
    public function quickCreateDataManager($params)
    {
        $email = isset($params['email']) ? trim((string)$params['email']) : '';
        $password = isset($params['password']) ? (string)$params['password'] : '';
        $firstName = isset($params['first_name']) ? trim((string)$params['first_name']) : '';
        $lastName = isset($params['last_name']) ? trim((string)$params['last_name']) : '';
        $institute = isset($params['institute']) ? trim((string)$params['institute']) : '';

        $email = Pt_Commons_MiscUtility::sanitizeAndValidateEmail($email);
        if (!$email) {
            return ['error' => 'A valid email address is required.'];
        }

        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $minLen = (int)$globalConfigDb->getValue('participant_login_password_length');
        if ($minLen <= 0) {
            $minLen = 8;
        }
        if (strlen($password) < $minLen) {
            return ['error' => 'Password must be at least ' . $minLen . ' characters.'];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $existing = $db->fetchRow($db->select()->from('data_manager')->where('LOWER(primary_email) = ?', strtolower($email)));
        if ($existing) {
            return ['error' => 'A Data Manager with this email already exists.'];
        }

        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'institute' => $institute,
            'primary_email' => $email,
            'password' => Application_Service_Common::passwordHash($password),
            'data_manager_type' => 'manager',
            'status' => 'active',
            'force_password_reset' => 1,
            'created_by' => $authNameSpace->admin_id ?? null,
            'created_on' => new Zend_Db_Expr('now()'),
        ];
        $db->insert('data_manager', $data);
        $dmId = (int)$db->lastInsertId();
        if ($dmId <= 0) {
            return ['error' => 'Failed to create Data Manager.'];
        }

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Quick-created data manager - {$email}", 'participants');

        $labelParts = [];
        $name = trim($firstName . ' ' . $lastName);
        if ($name !== '') {
            $labelParts[] = $name;
        }
        if ($institute !== '') {
            $labelParts[] = $institute;
        }
        $labelParts[] = $email;

        return [
            'dm_id' => $dmId,
            'label' => implode(', ', $labelParts),
        ];
    }

    public function updateUser($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        /* Set lang in runtime */
        $authNameSpace->language = $params['language'];
        if (isset($params['oldpemail']) && !empty($params['oldpemail']) && isset($params['pemail']) && !empty($params['pemail']) && !Application_Model_DbTable_DataManagers::sameEmail($params['oldpemail'], $params['pemail'])) {
            $common = new Application_Service_Common();
            $token = $this->datamanagersDb->issueEmailChangeToken((int) $authNameSpace->dm_id, (string) $params['pemail']);
            $mail = self::loginEmailChangeEmail(trim($authNameSpace->first_name . ' ' . $authNameSpace->last_name), (string) $params['oldpemail'], (string) $params['pemail'], $token);
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->insertTempMail($params['pemail'], null, null, $mail['subject'], $mail['html'], $fromMail, $fromName);
            $sessionAlert->message = 'Please check your email “' . $params['pemail'] . '”. Once you verify, you can use “' . $params['pemail'] . '” to login to ePT.';
            $sessionAlert->status = 'success';
            // $this->datamanagersDb->setStatusByEmail('inactive',$params['oldpemail']);
        } else {
            if ($authNameSpace->force_profile_check_primary == 'yes') {
                $sessionAlert->status = 'failure';
                $this->datamanagersDb->updateForceProfileCheckByEmail(base64_encode($params['oldpemail']));
            } else {
                $sessionAlert->status = 'failure';
            }
        }
        return $this->datamanagersDb->updateUser($params);
    }

    public function confirmPrimaryMail($params, $showAlert = false)
    {
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $dm = $this->dataManagerByLoginEmail((string) $params['oldEmail']);
        if ($dm && !Application_Model_DbTable_DataManagers::sameEmail($params['oldEmail'], $params['registeredEmail'])) {
            $common = new Application_Service_Common();
            $token = $this->datamanagersDb->issueEmailChangeToken((int) $dm['dm_id'], (string) $params['registeredEmail']);
            $mail = self::loginEmailChangeEmail(trim($dm['first_name'] . ' ' . $dm['last_name']), (string) $params['oldEmail'], (string) $params['registeredEmail'], $token);
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->insertTempMail($params['registeredEmail'], null, null, $mail['subject'], $mail['html'], $fromMail, $fromName);
            $sessionAlert->message = 'Please check your email “' . $params['registeredEmail'] . '”. Once you verify, you can use “' . $params['registeredEmail'] . '” to login to ePT.';
            $sessionAlert->status = 'success';
            // $this->datamanagersDb->setStatusByEmail('inactive',$params['oldEmail']);
        }
        $status = $this->datamanagersDb->changeForceProfileCheckByEmail($params);
        if ($showAlert) {
            $sessionAlert = new Zend_Session_Namespace('alertSpace');
            if ($status) {
                $sessionAlert->message = 'You mail address has been changed. Please check your registered email id for the instructions.';
                $sessionAlert->status = 'success';
            } else {
                $sessionAlert->message = 'Yor are already used this address. Please try different mail!';
                $sessionAlert->status = 'failure';
            }
        }
        return $status;
    }

    public function resentDMVerifyMail($params)
    {
        $row = $this->datamanagersDb->fetchRow($this->datamanagersDb->getAdapter()->quoteInto('new_email = ?', $params['registeredEmail']));
        if (Application_Model_DbTable_DataManagers::hasPendingEmailChange($row)) {
            $common = new Application_Service_Common();
            // Use the stored addresses: the request only says which change to resend
            $token = $this->datamanagersDb->issueEmailChangeToken((int) $row['dm_id'], (string) $row['new_email']);
            $mail = self::loginEmailChangeEmail(trim($row['first_name'] . ' ' . $row['last_name']), (string) $row['primary_email'], (string) $row['new_email'], $token);
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $send = $common->insertTempMail((string) $row['new_email'], null, null, $mail['subject'], $mail['html'], $fromMail, $fromName);
            if (isset($send) && $send > 0) {
                return $send;
            }
        } else {
            return 0;
        }
    }

    public function updateLastLogin($dmId)
    {
        return $this->datamanagersDb->updateLastLogin($dmId);
    }

    public function checkOldMail($dmId)
    {
        $row = $this->datamanagersDb->fetchRow(['new_email IS NOT NULL AND new_email not like ""', 'dm_id = ?' => $dmId]);
        return Application_Model_DbTable_DataManagers::hasPendingEmailChange($row) ? $row : null;
    }

    public function getAllUsers($params)
    {
        return $this->datamanagersDb->getAllUsers($params);
    }

    public function getPtccCountryMap($userId = null, $type = null, $ptcc = false)
    {

        if ($userId == null) {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userId = $authNameSpace->UserID;
        }
        return $this->datamanagersDb->fetchUserCuntryMap($userId, $type, $ptcc);
    }

    public function getUserInfo($userId = null)
    {

        if ($userId == null) {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userId = $authNameSpace->UserID;
        }
        return $this->datamanagersDb->getUserDetails($userId);
    }
    public function getUserInfoBySystemId($userSystemId = null)
    {

        if ($userSystemId == null) {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userSystemId = $authNameSpace->dm_id;
        }
        return $this->datamanagersDb->getUserDetailsBySystemId($userSystemId);
    }

    public function resetPassword($email)
    {
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $eptDomain = rtrim((string) $conf->domain, '/');

        // Normalize/quick-validate the incoming email first
        $normalizedInput = Application_Service_Common::validateEmail((string) $email);
        // (We’ll still prefer the DB email below, but this prevents weird inputs early.)

        $participant = $this->datamanagersDb->issuePasswordResetToken($normalizedInput);

        // (Optional) prevent enumeration: always show a generic message
        $genericOkMsg = $this->translator->_('If the entered email is registered, you will receive a password reset link. If not, please contact your PT provider for assistance.');

        if ($participant === false) {
            // Avoid revealing whether the email exists
            $sessionAlert->message = $genericOkMsg;
            $sessionAlert->status = 'success';
            return;
        }

        $common = new Application_Service_Common();
        $participantNameRaw = trim((string) ($participant->first_name . ' ' . $participant->last_name));
        $participantName = htmlspecialchars($participantNameRaw, ENT_QUOTES, 'UTF-8');

        // Prefer the email stored in DB
        $participantMail = Application_Service_Common::validateEmail((string) $participant->primary_email);

        // Admin config (ensure keys match your config names)
        $adminName = Application_Service_Common::getConfig('admin_name') ?: 'ePT Admin';
        $adminMail = Application_Service_Common::getConfig('admin_email');

        // If DB email isn't even syntactically valid, fall back to generic success
        if ($participantMail === null) {
            // Optionally notify admin about bad record
            // $common->insertTempMail(
            //     $adminMail,
            //     null,
            //     null,
            //     "Password Reset - ePT",
            //     "Record has invalid email for participant <b>{$participantName}</b> (input: " . htmlspecialchars((string)$email, ENT_QUOTES, 'UTF-8') . ")."
            // );
            $sessionAlert->message = $genericOkMsg;
            $sessionAlert->status = 'success';
            return;
        }

        // Heavier validation: syntax + excluded domains + MX
        $excludedDomains = [
            'spam.com',
            'example.org',
            'example.com',
            '10minutemail.com',
            'guerrillamail.com',
            'tempmail.com',
            'mailinator.com',
            'yopmail.com',
            'throwawaymail.com',
            'fakeinbox.com',
            'test.com',
            'invalid.com',
            'noreply.com',
        ];
        $validMail = Application_Service_Common::isValidEmail($participantMail, $excludedDomains);

        if ($validMail === true) {
            $resetUrl = "$eptDomain/auth/new-password/token/{$participant->password_reset_token}";

            $mail = self::passwordResetLinkEmail($participantNameRaw, $participantMail, $resetUrl);
            $common->insertTempMail($participantMail, null, null, $mail['subject'], $mail['html'], $adminMail, $adminName);

            // Generic response to the user/browser
            $sessionAlert->message = $genericOkMsg;
            $sessionAlert->status = 'success';
        } else {
            // Bad/temporary domain or no MX: notify admin, keep user message generic
            $adminMsg = Application_Service_EmailLayout::page(
                Application_Service_EmailLayout::p("<b>{$participantName}</b> asked to reset their ePT password, but no email was sent. "
                    . 'Their login email looks invalid or cannot receive mail: <b>'
                    . htmlspecialchars($participantMail, ENT_QUOTES, 'UTF-8')
                    . '</b> (they entered ' . htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') . ').')
                . Application_Service_EmailLayout::p('Correct the address on their data manager record, or reset the password for them from the data manager list.', true)
            );

            $common->insertTempMail($adminMail, null, null, 'Password reset email not sent: ' . $participantNameRaw, $adminMsg);

            $sessionAlert->message = $genericOkMsg;
            $sessionAlert->status = 'success';
        }
    }

    /**
     * Admin-driven primary email change. The admin has already confirmed the
     * change in the UI, so we update directly without dispatching a verification
     * email — the user simply uses the new address on next login.
     */
    public function changePrimaryEmailFromAdmin($params)
    {
        $dmId = (int)($params['dmId'] ?? 0);
        $newEmail = Application_Service_Common::validateEmail((string)($params['newEmail'] ?? ''));
        $confirmEmail = Application_Service_Common::validateEmail((string)($params['confirmEmail'] ?? ''));
        if (!$newEmail || !$confirmEmail) {
            return ['ok' => false, 'message' => $this->translator->_('Please enter a valid email address.')];
        }
        if (strtolower($newEmail) !== strtolower($confirmEmail)) {
            return ['ok' => false, 'message' => $this->translator->_('The two email addresses do not match.')];
        }
        return $this->datamanagersDb->changePrimaryEmailById($dmId, $newEmail);
    }

    public function resetPasswordFromAdmin($params, $forcePasswordReset = false)
    {
        $result = $this->datamanagersDb->updatePasswordFromAdmin($params['primaryMail'], $params['password'], $forcePasswordReset);
        if (!$result) {
            return false;
        }

        // Capture history BEFORE the new audit row so the email lists prior
        // activity only — the email itself is notice of the current reset.
        $priorHistory = [];
        if (isset($params['mode']) && $params['mode'] === 'email') {
            $priorHistory = $this->getRecentAccountActivity((string) $params['primaryMail']);
        }

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Reset password for {$params['primaryMail']}", 'password-reset');

        // If admin chose "Reset & Email", queue a credentials email via temp_mail.
        if (isset($params['mode']) && $params['mode'] === 'email') {
            $this->queueCredentialsEmail($params, $priorHistory);
        }
        return true;
    }

    /* Merged + deduped recent account activity for an email, in strict
       datetime DESC order. Combines:
         - admin password resets (audit_log type='password-reset')
         - credentials emails (temp_mail) — paired ones collapse into the reset
         - DM self password changes (audit_log type='auth', "Changed password")
         - DM successful logins (user_login_history)
       Used by the admin Reset-Password modal banner and the outgoing
       credentials email. */
    public function getRecentAccountActivity(string $email, int $limit = 6, int $fetchEach = 5): array
    {
        $email = trim($email);
        if ($email === '') {
            return [];
        }
        $auditDb     = new Application_Model_DbTable_AuditLog();
        $tempMailDb  = new Application_Model_DbTable_TempMail();
        $loginHistDb = new Application_Model_DbTable_UserLoginHistory();

        $resetsConfirmed = $auditDb->getRecentPasswordResetsForEmail($email, $fetchEach);
        $resetsLikely    = $tempMailDb->getCredentialMailsForEmail($email, $fetchEach);
        $selfChanges     = $auditDb->getRecentSelfPasswordChangesForEmail($email, $fetchEach);
        $logins          = $loginHistDb->getRecentLoginsForEmail($email, $fetchEach);

        $events = [];
        foreach ($resetsConfirmed as $r) {
            $events[] = [
                'kind'       => 'reset',
                'when'       => $r['when'],
                'confirmed'  => true,
                'emailSent'  => false,
                'actorName'  => $r['actorName'] ?? '',
                'actorRole'  => $r['actorRole'] ?? '',
                'actorEmail' => $r['actorEmail'] ?? '',
            ];
        }
        foreach ($resetsLikely as $r) {
            $events[] = [
                'kind'       => 'reset',
                'when'       => $r['when'],
                'confirmed'  => false,
                'emailSent'  => true,
                'actorName'  => '',
                'actorRole'  => '',
                'actorEmail' => '',
            ];
        }
        foreach ($selfChanges as $r) {
            $events[] = [
                'kind' => 'self_change',
                'when' => $r['when'],
            ];
        }
        foreach ($logins as $r) {
            $events[] = [
                'kind' => 'login',
                'when' => $r['when'],
                'ip'   => $r['ip'] ?? '',
            ];
        }

        // Strict datetime DESC — same timestamp keeps source order (stable sort).
        usort($events, function ($a, $b) {
            return strtotime((string) $b['when']) <=> strtotime((string) $a['when']);
        });

        // Collapse: a 'likely' reset within 5 min of a confirmed reset is the
        // same event (admin used Reset & Email). Drop it and flag the kept one.
        $deduped = [];
        foreach ($events as $entry) {
            $ts = strtotime((string) $entry['when']);
            $isDup = false;
            // Sign-ins from the same IP under a minute apart are one sign-in
            // submitted twice. A different IP is always listed: this is a
            // security notice, and a second sign-in elsewhere must show.
            if ($entry['kind'] === 'login' && ($entry['ip'] ?? '') !== '') {
                foreach ($deduped as $kept) {
                    if (
                        $kept['kind'] === 'login'
                        && ($kept['ip'] ?? '') === $entry['ip']
                        && abs(strtotime((string) $kept['when']) - $ts) < 60
                    ) {
                        $isDup = true;
                        break;
                    }
                }
            }
            if ($entry['kind'] === 'reset' && empty($entry['confirmed'])) {
                foreach ($deduped as $i => $kept) {
                    if ($kept['kind'] === 'reset' && !empty($kept['confirmed'])
                        && abs(strtotime((string) $kept['when']) - $ts) <= 300) {
                        $deduped[$i]['emailSent'] = true;
                        $isDup = true;
                        break;
                    }
                }
            }
            if (!$isDup) {
                $deduped[] = $entry;
            }
        }

        return array_slice($deduped, 0, max(1, $limit));
    }

    /* Queues a login-credentials email through temp_mail. Recipients come from the
       admin (To defaults to the user's primary email; Bcc defaults to the admin
       email) — see views/scripts/data-managers/reset-password.phtml. */
    private function queueCredentialsEmail(array $params, array $priorHistory = []): void
    {
        $cleanList = function ($raw) {
            $out = [];
            foreach (preg_split('/[\s,;]+/', (string) $raw) ?: [] as $addr) {
                $addr = trim($addr);
                if ($addr === '') {
                    continue;
                }
                $valid = Application_Service_Common::validateEmail($addr);
                if ($valid !== null) {
                    $out[strtolower($valid)] = $valid;
                }
            }
            return array_values($out);
        };

        $to  = $cleanList($params['emailTo']  ?? ($params['primaryMail'] ?? ''));
        $cc  = $cleanList($params['emailCc']  ?? '');
        $bcc = $cleanList($params['emailBcc'] ?? '');

        if (empty($to)) {
            return;
        }

        $loginUrl = trim((string) ($params['loginUrl'] ?? ''));
        $loginId  = (string) ($params['primaryMail'] ?? $to[0]);
        $password = (string) ($params['password'] ?? '');

        // Greet the person, or their lab, rather than a generic "Participant"
        $dm = $this->datamanagersDb->getAdapter()->fetchRow(
            $this->datamanagersDb->getAdapter()->select()
                ->from('data_manager', ['first_name', 'last_name', 'institute'])
                ->where('primary_email = ?', $loginId)
        ) ?: [];
        $name = trim(($dm['first_name'] ?? '') . ' ' . ($dm['last_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($dm['institute'] ?? ''));
        }
        $greeting = $name !== ''
            ? Pt_Commons_TranslateUtility::safeTranslate('Dear') . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8', false)
            : Pt_Commons_TranslateUtility::safeTranslate('Dear Participant');
        $subject = Pt_Commons_TranslateUtility::safeTranslate('Your ePT password has been reset') . ' — ' . $loginId;
        $message = $this->renderCredentialsEmailHtml($greeting, $loginUrl, $loginId, $password, $priorHistory);

        $mailCfg = Application_Service_Common::getMailSettings();
        $fromEmail = $mailCfg['fromEmail'] ?: Application_Service_Common::getConfig('admin_email');
        $fromName  = $mailCfg['fromName'] ?: (Application_Service_Common::getConfig('admin_name') ?: 'ePT Support');

        $common = new Application_Service_Common();
        $common->insertTempMail(
            implode(',', $to),
            !empty($cc) ? implode(',', $cc) : null,
            !empty($bcc) ? implode(',', $bcc) : null,
            $subject,
            $message,
            $fromEmail,
            $fromName
        );

        $alert = new Zend_Session_Namespace('alertSpace');
        $alert->message = 'Password reset and login email queued for ' . $to[0];
    }

    /**
     * The email with a link to choose a new password, sent when someone asks
     * for a reset on the sign-in page or through the API.
     *
     * @return array{subject: string, html: string}
     */
    public static function passwordResetLinkEmail(?string $name, string $loginEmail, string $resetUrl): array
    {
        $l = Application_Service_EmailLayout::class;
        $body = $l::greeting($name)
            . $l::p(sprintf(
                Pt_Commons_TranslateUtility::safeTranslate('We received a request to reset the password for your ePT account, %s.'),
                '<strong>' . $l::e($loginEmail) . '</strong>'
            ))
            . $l::button($resetUrl, Pt_Commons_TranslateUtility::safeTranslate('Choose a new password'))
            . $l::p(Pt_Commons_TranslateUtility::safeTranslate('This link expires in 24 hours and works only once.'))
            . $l::fallbackLink($resetUrl)
            . $l::note(Pt_Commons_TranslateUtility::safeTranslate('If you did not ask to reset your password, ignore this email. Your password stays the same.'))
            . $l::signoff();

        return [
            'subject' => Pt_Commons_TranslateUtility::safeTranslate('Reset your ePT password'),
            'html' => $l::page($body, Pt_Commons_TranslateUtility::safeTranslate('Use this link within 24 hours to choose a new password.')),
        ];
    }

    /**
     * The email that asks someone to confirm a new login email address. It
     * goes to the new address with a one-time token from
     * issueEmailChangeToken(), so opening the link proves they own it.
     *
     * @return array{subject: string, html: string}
     */
    public static function loginEmailChangeEmail(?string $name, string $oldEmail, string $newEmail, string $token): array
    {
        $l = Application_Service_EmailLayout::class;
        $verifyUrl = $l::siteUrl() . '/auth/verify/token/' . $token;
        $body = $l::greeting($name)
            . $l::p(sprintf(
                Pt_Commons_TranslateUtility::safeTranslate('A request was made to change the login email for your ePT account from %1$s to %2$s.'),
                '<strong>' . $l::e($oldEmail) . '</strong>',
                '<strong>' . $l::e($newEmail) . '</strong>'
            ))
            . $l::p(Pt_Commons_TranslateUtility::safeTranslate('Confirm the new address to start using it to sign in.'))
            . $l::button($verifyUrl, Pt_Commons_TranslateUtility::safeTranslate('Confirm new login email'))
            . $l::p(Pt_Commons_TranslateUtility::safeTranslate('This link expires in 7 days and works only once.'))
            . $l::fallbackLink($verifyUrl)
            . $l::note(Pt_Commons_TranslateUtility::safeTranslate('If you did not ask for this change, ignore this email. Your login email stays the same.'))
            . $l::signoff();

        return [
            'subject' => Pt_Commons_TranslateUtility::safeTranslate('Confirm your new ePT login email'),
            'html' => $l::page($body, Pt_Commons_TranslateUtility::safeTranslate('Confirm this address to use it for signing in to ePT.')),
        ];
    }

    /** The data manager with this login email: id and name, or null. */
    private function dataManagerByLoginEmail(string $email): ?array
    {
        $db = $this->datamanagersDb->getAdapter();
        $row = $db->fetchRow($db->select()->from('data_manager', ['dm_id', 'first_name', 'last_name', 'primary_email', 'new_email'])->where('primary_email = ?', $email));
        return $row ?: null;
    }

    /* Body of the login-credentials email, shared by the single-user and bulk
       reset flows. Inline styles and tables only, since mail clients drop <style>
       and ignore most layout CSS. The newlines keep the strip_tags() plain-text
       part readable. $greeting is already escaped. */
    private function renderCredentialsEmailHtml(string $greeting, string $loginUrl, string $loginId, string $password, array $priorHistory): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $p = '<p style="margin:0 0 16px;">';
        $label = '<td style="padding:7px 18px 7px 0;color:#5f6b7a;white-space:nowrap;vertical-align:top;">';
        $value = '<td style="padding:7px 0;vertical-align:top;">';
        $mono  = 'font-family:Consolas,Menlo,monospace;font-size:15px;';
        $supportEmail = trim((string) Application_Service_Common::getConfig('admin_email'));
        $support = $supportEmail !== ''
            ? sprintf(Pt_Commons_TranslateUtility::safeTranslate('If you did not expect this email, or need help, contact ePT Support at %s.'), '<a href="mailto:' . $e($supportEmail) . '" style="color:#1a56db;">' . $e($supportEmail) . '</a>')
            : Pt_Commons_TranslateUtility::safeTranslate('If you did not expect this email, or need help, contact ePT Support.');

        return Application_Service_EmailLayout::page('<div>' . "\n"
            . $p . $greeting . ',</p>' . "\n"
            . $p . Pt_Commons_TranslateUtility::safeTranslate('Your password for ePT has been reset. Use these details to sign in.') . '</p>' . "\n"
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;background:#f5f7fa;border:1px solid #dde3ea;border-radius:6px;">'
            . '<tr><td style="padding:12px 18px;"><table cellpadding="0" cellspacing="0" style="border-collapse:collapse;">' . "\n"
            . '<tr>' . $label . Pt_Commons_TranslateUtility::safeTranslate('Sign-in page') . '</td> ' . $value . '<a href="' . $e($loginUrl) . '" style="color:#1a56db;">' . $e($loginUrl) . '</a></td></tr>' . "\n"
            . '<tr>' . $label . Pt_Commons_TranslateUtility::safeTranslate('Login ID') . '</td> ' . $value . '<span style="' . $mono . '">' . $e($loginId) . '</span></td></tr>' . "\n"
            . '<tr>' . $label . Pt_Commons_TranslateUtility::safeTranslate('Password') . '</td> ' . $value . '<span style="' . $mono . 'font-weight:bold;letter-spacing:1px;">' . $e($password) . '</span></td></tr>' . "\n"
            . '</table></td></tr></table>' . "\n"
            // A table-cell button, the form that renders as a button in Outlook too
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 22px;"><tr>'
            . '<td style="background:#1a56db;border-radius:5px;">'
            . '<a href="' . $e($loginUrl) . '" style="display:inline-block;padding:10px 22px;color:#ffffff;font-weight:bold;text-decoration:none;">' . Pt_Commons_TranslateUtility::safeTranslate('Sign in to ePT') . '</a>'
            . '</td></tr></table>' . "\n"
            . '<p style="margin:0 0 4px;">' . Pt_Commons_TranslateUtility::safeTranslate('After you sign in, change this password.') . '</p>' . "\n"
            . '<p style="margin:0 0 4px;">' . Pt_Commons_TranslateUtility::safeTranslate('Do not share your password with anyone.') . '</p>' . "\n"
            . $p . Pt_Commons_TranslateUtility::safeTranslate('The ePT team will never ask you for your password.') . '</p>' . "\n"
            . $this->renderActivityHistoryHtml($priorHistory)
            . $p . $support . '</p>' . "\n"
            . '<p style="margin:0;">' . Pt_Commons_TranslateUtility::safeTranslate('Thank you') . ',<br/>' . "\n" . Pt_Commons_TranslateUtility::safeTranslate('ePT Support') . '</p>' . "\n"
            . '</div>');
    }

    /* The "recent activity" table in credentials emails, shared by the single-user
       and bulk reset flows. Consecutive resets by the same actor collapse into one
       row, and resets without a named person read as "the ePT team", since a lab
       cannot act on "System". Returns '' when there is no prior history. */
    private function renderActivityHistoryHtml(array $priorHistory): string
    {
        if (empty($priorHistory)) {
            return '';
        }
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $groups = [];
        foreach ($priorHistory as $r) {
            $kind = $r['kind'] ?? 'reset';
            $ts = strtotime((string) ($r['when'] ?? ''));
            $actor = trim((string) ($r['actorName'] ?? '')) ?: trim((string) ($r['actorEmail'] ?? ''));
            if (strcasecmp($actor, 'System') === 0) {
                $actor = '';
            }
            $key = $kind === 'reset' ? 'reset|' . $actor . '|' . (empty($r['emailSent']) ? '' : 'e') : null;
            $last = count($groups) - 1;
            if ($key !== null && $last >= 0 && $groups[$last]['key'] === $key) {
                $groups[$last]['count']++;
                $groups[$last]['oldest'] = $ts;
                continue;
            }
            $groups[] = ['key' => $key ?? $kind . count($groups), 'kind' => $kind, 'newest' => $ts, 'oldest' => $ts,
                'count' => 1, 'actor' => $actor, 'emailSent' => !empty($r['emailSent']), 'raw' => (string) ($r['when'] ?? '')];
        }

        $cell = 'padding:5px 14px 5px 0;border-top:1px solid #eceff3;vertical-align:top;';
        $rows = '';
        foreach ($groups as $g) {
            $when = $g['newest'] ? date('d M Y, H:i', $g['newest']) : $g['raw'];
            if ($g['kind'] === 'login') {
                $what = Pt_Commons_TranslateUtility::safeTranslate('You signed in');
            } elseif ($g['kind'] === 'self_change') {
                $what = Pt_Commons_TranslateUtility::safeTranslate('You changed your password');
            } else {
                $what = $g['actor'] !== ''
                    ? sprintf(Pt_Commons_TranslateUtility::safeTranslate('Password reset by %s'), $e($g['actor']))
                    : Pt_Commons_TranslateUtility::safeTranslate('Password reset by the ePT team');
                if ($g['emailSent']) {
                    $what .= ', ' . Pt_Commons_TranslateUtility::safeTranslate('login details emailed to you');
                }
                if ($g['count'] > 1 && $g['oldest']) {
                    $what .= ' <span style="color:#7a8391;">(' . sprintf(Pt_Commons_TranslateUtility::safeTranslate('%d times since %s'), $g['count'], date('d M Y', $g['oldest'])) . ')</span>';
                }
            }
            $rows .= '<tr><td style="' . $cell . 'white-space:nowrap;color:#5f6b7a;">' . $e($when) . '</td><td style="' . $cell . '">' . $what . '</td></tr>' . "\n";
        }

        return '<div style="margin:0 0 18px;padding-top:14px;border-top:1px solid #e5e5e5;font-size:13px;color:#3a4656;">' . "\n"
            . '<p style="margin:0 0 2px;font-weight:bold;color:#222;">' . Pt_Commons_TranslateUtility::safeTranslate('Recent activity on your account') . '</p>' . "\n"
            . '<p style="margin:0 0 8px;color:#5f6b7a;">' . Pt_Commons_TranslateUtility::safeTranslate('If you do not recognise any of these, contact us straight away.') . '</p>' . "\n"
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">' . "\n"
            . '<tr><td style="padding:0 14px 4px 0;color:#7a8391;font-size:12px;">' . sprintf(Pt_Commons_TranslateUtility::safeTranslate('Date (UTC%s)'), date('P')) . '</td><td style="padding:0 0 4px;color:#7a8391;font-size:12px;">' . Pt_Commons_TranslateUtility::safeTranslate('Activity') . '</td></tr>' . "\n"
            . $rows
            . '</table>' . "\n"
            . '</div>' . "\n";
    }

    /* Resolves a list of pasted identifiers (emails OR participant unique_identifier
       a.k.a. "Lab ID") into the set of DMs to reset. A single Lab ID can map to
       multiple DMs via participant_manager_map; emails resolve to one DM.
       Returns ['matched' => [dm rows], 'unresolved' => [original input lines]]. */
    // $dmType, when 'ptcc' or 'manager', restricts matching to that kind of
    // login. The PTCC and regular data-manager pages share one data_manager
    // table, so a pasted list on the PTCC page must only resolve PTCC rows.
    public function resolveDmIdentifiers(array $lines, ?string $dmType = null): array
    {
        // Strip ALL internal whitespace + zero-width / BOM / control chars.
        // Excel pastes often include NBSP (U+00A0), ZWSP (U+200B), BOM (U+FEFF),
        // and stray spaces inside emails like "yonidany @yahoo.fr".
        $stripPattern = '/[\pZ\s\p{Cc}\x{200B}\x{200C}\x{200D}\x{FEFF}]+/u';
        $cleaned = [];
        foreach ($lines as $line) {
            $line = (string) $line;
            $line = preg_replace($stripPattern, '', $line);
            if ($line === '' || $line === null) {
                continue;
            }
            $cleaned[] = $line;
        }
        $cleaned = array_values(array_unique($cleaned));
        if (empty($cleaned)) {
            return ['matched' => [], 'unresolved' => []];
        }

        $emails = [];
        $labIds = [];
        foreach ($cleaned as $entry) {
            if (strpos($entry, '@') !== false) {
                $valid = Application_Service_Common::validateEmail($entry);
                if ($valid !== null) {
                    $emails[strtolower($valid)] = $valid;
                }
            } else {
                $labIds[$entry] = $entry;
            }
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $matched = [];
        $matchedDmIds = [];
        $matchedInputs = [];

        if (!empty($emails)) {
            $emailSelect = $db->select()
                ->from('data_manager', ['dm_id', 'first_name', 'last_name', 'institute', 'primary_email', 'status'])
                ->where('LOWER(primary_email) IN (?)', array_keys($emails));
            if ($dmType !== null) {
                $emailSelect->where('data_manager_type = ?', $dmType);
            }
            $rows = $db->fetchAll($emailSelect);
            foreach ($rows as $row) {
                $key = (int) $row['dm_id'];
                if (!isset($matchedDmIds[$key])) {
                    $matched[] = $row;
                    $matchedDmIds[$key] = true;
                }
                $matchedInputs[strtolower((string) $row['primary_email'])] = true;
            }
        }

        if (!empty($labIds)) {
            $select = $db->select()
                ->from(['p' => 'participant'], ['p_uid' => 'p.unique_identifier'])
                ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
                ->join(['dm' => 'data_manager'], 'dm.dm_id = pmm.dm_id', ['dm_id', 'first_name', 'last_name', 'institute', 'primary_email', 'status'])
                ->where('p.unique_identifier IN (?)', array_values($labIds));
            if ($dmType !== null) {
                $select->where('dm.data_manager_type = ?', $dmType);
            }
            $rows = $db->fetchAll($select);
            foreach ($rows as $row) {
                $key = (int) $row['dm_id'];
                if (!isset($matchedDmIds[$key])) {
                    $matched[] = [
                        'dm_id' => $row['dm_id'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'institute' => $row['institute'],
                        'primary_email' => $row['primary_email'],
                        'status' => $row['status'],
                    ];
                    $matchedDmIds[$key] = true;
                }
                $matchedInputs[(string) $row['p_uid']] = true;
            }
        }

        $unresolved = [];
        foreach ($cleaned as $entry) {
            if (strpos($entry, '@') !== false) {
                $valid = Application_Service_Common::validateEmail($entry);
                if ($valid === null) {
                    $unresolved[] = $entry;
                    continue;
                }
                if (empty($matchedInputs[strtolower($valid)])) {
                    $unresolved[] = $entry;
                }
            } else {
                if (empty($matchedInputs[$entry])) {
                    $unresolved[] = $entry;
                }
            }
        }

        usort($matched, fn ($a, $b) => strcasecmp(($a['first_name'] ?? '') . ($a['last_name'] ?? ''), ($b['first_name'] ?? '') . ($b['last_name'] ?? '')));

        return ['matched' => $matched, 'unresolved' => $unresolved];
    }

    /* Returns minimal info for the bulk-reset modal: dm_id, name, email, status. */
    public function getUsersByIds(array $dmIds): array
    {
        $dmIds = array_values(array_unique(array_filter(array_map('intval', $dmIds))));
        if (empty($dmIds)) {
            return [];
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $select = $db->select()
            ->from('data_manager', ['dm_id', 'first_name', 'last_name', 'institute', 'primary_email', 'status'])
            ->where('dm_id IN (?)', $dmIds)
            ->order(['first_name', 'last_name']);
        return $db->fetchAll($select);
    }

    /* Resets passwords for a list of DMs and (optionally) emails each user their
       new credentials. Each DM gets a unique generated password. Returns a summary
       array with counts and per-row outcomes. */
    public function bulkResetPasswordsFromAdmin(array $params): array
    {
        $dmIds = [];
        if (!empty($params['dmIds'])) {
            $raw = is_array($params['dmIds']) ? $params['dmIds'] : explode(',', (string)$params['dmIds']);
            $dmIds = array_values(array_unique(array_filter(array_map('intval', $raw))));
        }
        $sendEmail = !empty($params['sendEmail']);
        $forceReset = !empty($params['forcePasswordReset']);
        $loginUrl = trim((string) ($params['loginUrl'] ?? ''));

        $cleanList = function ($raw) {
            $out = [];
            foreach (preg_split('/[\s,;]+/', (string) $raw) ?: [] as $addr) {
                $addr = trim($addr);
                if ($addr === '') {
                    continue;
                }
                $valid = Application_Service_Common::validateEmail($addr);
                if ($valid !== null) {
                    $out[strtolower($valid)] = $valid;
                }
            }
            return array_values($out);
        };
        $cc  = $cleanList($params['emailCc']  ?? '');
        $bcc = $cleanList($params['emailBcc'] ?? '');

        $summary = [
            'total'    => count($dmIds),
            'updated'  => 0,
            'emailed'  => 0,
            'skipped'  => [],
            'success'  => [],
        ];

        if (empty($dmIds)) {
            return $summary;
        }

        $common = new Application_Service_Common();
        $passGen = new Application_Service_Common();
        $rows = $this->getUsersByIds($dmIds);

        $actor = null;
        if (!empty($params['actorEmail'])) {
            $actor = ['email' => (string) $params['actorEmail'], 'role' => (string) ($params['actorRole'] ?? 'admin')];
        }
        $auditDb = new Application_Model_DbTable_AuditLog();

        foreach ($rows as $row) {
            $email = trim((string)($row['primary_email'] ?? ''));
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($email === '' || Application_Service_Common::validateEmail($email) === null) {
                $summary['skipped'][] = ['dm_id' => (int)$row['dm_id'], 'email' => $email, 'reason' => 'invalid email'];
                continue;
            }
            $newPassword = $passGen->generatePassword();
            // One-time credential: user is force-reset on next login and the
            // password is delivered in plaintext via email anyway, so use a
            // lower bcrypt cost to keep bulk runs from blowing the request
            // budget. updatePasswordFromAdmin() detects an existing bcrypt
            // hash and passes it through without re-hashing.
            $hashed = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
            $ok = $this->datamanagersDb->updatePasswordFromAdmin($email, $hashed, $forceReset);
            if (!$ok) {
                $summary['skipped'][] = ['dm_id' => (int)$row['dm_id'], 'email' => $email, 'reason' => 'update failed'];
                continue;
            }
            $summary['updated']++;
            $summary['success'][] = ['dm_id' => (int)$row['dm_id'], 'name' => $name, 'email' => $email];

            // Capture prior activity BEFORE writing the audit row so the email
            // lists history only, not the reset we just did (matches single-user flow).
            $priorHistory = $sendEmail ? $this->getRecentAccountActivity($email) : [];

            $auditDb->addNewAuditLog("Reset password for {$email} (bulk)", 'password-reset', $actor);

            if ($sendEmail) {
                $this->queueBulkCredentialsEmail($common, $email, $name, $newPassword, $loginUrl, $cc, $bcc, $priorHistory);
                $summary['emailed']++;
            }
        }

        $alert = new Zend_Session_Namespace('alertSpace');
        if ($sendEmail) {
            $alert->message = sprintf('Reset %d password(s); queued %d credentials email(s).', $summary['updated'], $summary['emailed']);
        } else {
            $alert->message = sprintf('Reset %d password(s).', $summary['updated']);
        }
        return $summary;
    }

    private function queueBulkCredentialsEmail(Application_Service_Common $common, string $toEmail, string $name, string $password, string $loginUrl, array $cc, array $bcc, array $priorHistory = []): void
    {
        $greeting = $name !== ''
            ? Pt_Commons_TranslateUtility::safeTranslate('Dear') . ' ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            : Pt_Commons_TranslateUtility::safeTranslate('Dear Participant');
        $recipientLabel = $name !== '' ? $name . ' (' . $toEmail . ')' : $toEmail;
        $subject = Pt_Commons_TranslateUtility::safeTranslate('Your ePT password has been reset') . ' — ' . $recipientLabel;
        $message = $this->renderCredentialsEmailHtml($greeting, $loginUrl, $toEmail, $password, $priorHistory);

        $mailCfg = Application_Service_Common::getMailSettings();
        $fromEmail = $mailCfg['fromEmail'] ?: Application_Service_Common::getConfig('admin_email');
        $fromName  = $mailCfg['fromName'] ?: (Application_Service_Common::getConfig('admin_name') ?: 'ePT Support');

        $common->insertTempMail(
            $toEmail,
            !empty($cc) ? implode(',', $cc) : null,
            !empty($bcc) ? implode(',', $bcc) : null,
            $subject,
            $message,
            $fromEmail,
            $fromName
        );
    }

    public function getDataManagerList($ptcc = false)
    {
        return $this->datamanagersDb->getAllDataManagers($ptcc);
    }

    public function getParticipantDatamanagerListByPid($participantId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('participant_manager_map')->where('participant_id= ?', $participantId));
    }

    public function getDatamanagerParticipantListByDid($datamanagerId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('participant_manager_map')
            ->where('dm_id= ?', $datamanagerId));
    }

    public function getParticipantDatamanagerList($params = [])
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], ['participant_id', 'unique_identifier', 'first_name', 'last_name'])
            // ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
            // ->where("pmm.dm_id like ?", $params['datamanagerId'])
            ->where('p.status= ?', 'active');
        if (isset($params['country']) && $params['country'] != '') {
            $sql = $sql->where('country like ?', $params['country']);
        }
        if (isset($params['province']) && $params['province'] != '') {
            $sql = $sql->where('state like ?', $params['province']);
        }
        if (isset($params['district']) && $params['district'] != '') {
            $sql = $sql->where('district like ?', $params['district']);
        }
        if (isset($params['network']) && $params['network'] != '') {
            $sql = $sql->where('network_tier like ?', $params['network']);
        }
        if (isset($params['affiliation']) && $params['affiliation'] != '') {
            $sql = $sql->where('affiliation like ?', $params['affiliation']);
        }
        if (isset($params['institute']) && $params['institute'] != '') {
            $sql = $sql->where('institute_name like ?', $params['institute']);
        }

        $sql2 = $db->select()->from(['p' => 'participant'], ['participant_id', 'unique_identifier', 'first_name', 'last_name'])
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [''])
            ->where('pmm.dm_id like ?', $params['datamanagerId'])
            ->where('p.status= ?', 'active');

        $select = $db->select()
            ->union([$sql, $sql2]);

        return $db->fetchAll($select);
    }

    public function getDatamanagerParticipantList($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['pmm' => 'participant_manager_map'], ['participant_id'])
            ->join(['p' => 'participant'], 'pmm.participant_id=p.participant_id', [''])
            ->where('dm_id like ?', $params['datamanagerId'])->group('p.participant_id');

        return $db->fetchAll($sql);
    }

    public function getParticipantsByDM()
    {
        $dmNameSpace = new Zend_Session_Namespace('datamanagers');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['pmm' => 'participant_manager_map'])
            ->join(['p' => 'participant'], 'pmm.participant_id=p.participant_id', ['*'])
            ->where('dm_id= ?', $dmNameSpace->dm_id)
            ->group('p.participant_id');
        return $db->fetchAll($sql);
    }

    public function changePassword($oldPassword, $newPassword)
    {
        $newPassword = $this->datamanagersDb->updatePassword($oldPassword, $newPassword);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        if ($newPassword != false) {
            $sessionAlert->message = 'Your password has been updated.';
            $sessionAlert->status = 'success';
            return true;
        } else {
            if ($_SESSION['profile_confirmed']) {
                $sessionAlert->message = 'Sorry, we could not update your password(check you enter correct old password). Please try again';
                $sessionAlert->status = 'failure';
            }
            return false;
        }
    }

    public function checkAnnouncementMessageShowing($dmId)
    {
        $response = '';
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['pmm' => 'participant_manager_map', []])
            ->join(['p' => 'participant'], 'p.participant_id=pmm.participant_id', [])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['show_announcement_count' => new Zend_Db_Expr("SUM(show_announcement ='yes')")])
            ->where('pmm.dm_id = ?', $dmId)
            ->group('sp.participant_id');
        $result = $db->fetchRow($sql);
        if (isset($result['show_announcement_count']) && $result['show_announcement_count'] > 0) {
            $announcementMsg = $db->fetchRow($db->select()->from('announcements')->where("status = 'active' AND DATE(start_date) <= DATE(NOW()) AND DATE(end_date) >= DATE(NOW())"));
            if (isset($announcementMsg['announcement_msg']) && trim($announcementMsg['announcement_msg']) != '') {
                $response = $announcementMsg['announcement_msg'];
            }
        }
        return $response;
    }

    public function getParticipantDatamanagerSearch($participant)
    {
        return $this->datamanagersDb->fetchParticipantDatamanagerSearch($participant);
    }

    public function newPassword($params)
    {
        $newPassword = $this->datamanagersDb->saveNewPassword($params);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        if ($newPassword != false) {
            $sessionAlert->message = 'Your password has been updated.';
            $sessionAlert->status = 'success';
            return '/auth/login';
        } else {
            $sessionAlert->message = 'Sorry, we could not update your password. Please try again';
            $sessionAlert->status = 'failure';
            return '/auth/new-password';
        }
    }

    public function resolveResetToken($token)
    {
        return $this->datamanagersDb->fetchByPasswordResetToken($token);
    }

    public function checkForceProfileEmail($link)
    {
        return $this->datamanagersDb->fetchForceProfileEmail($link);
    }

    public function updateForceProfileCheck($email, $result = '')
    {
        return $this->datamanagersDb->updateForceProfileCheckByEmail($email, $result);
    }

    public function loginDatamanagerAPI($params)
    {
        return $this->datamanagersDb->loginDatamanagerByAPI($params);
    }

    public function changePasswordDatamanagerAPI($params)
    {
        return $this->datamanagersDb->changePasswordDatamanagerByAPI($params);
    }

    public function getLoggedInDetails($params)
    {
        return $this->datamanagersDb->fetchLoggedInDetails($params);
    }

    public function forgetPasswordDatamanagerAPI($params)
    {
        return $this->datamanagersDb->setForgetPasswordDatamanagerAPI($params);
    }

    public function setStatusByEmailDM($status, $email)
    {
        return $this->datamanagersDb->setStatusByEmail($status, $email);
    }

    public function checkSystemDuplicate($params) // This function created for checking ptcc and actual dm replacement using primary email
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['dm' => 'data_manager'], ['dm_id', 'data_manager_type'])
            ->where('dm.primary_email = ?', strtolower($params['value']));
        $result = $db->fetchRow($sql);
        if (isset($result['dm_id']) && !empty($result['dm_id'])) {
            if (isset($result['data_manager_type']) && $result['data_manager_type'] != 'ptcc') {
                return $result['dm_id'];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    /** Import options carried from the PTCC upload form to the confirmed import. */
    private const PTCC_IMPORT_OPTIONS = ['bulkUploadDuplicateSkip', 'deactivateExistingPTCC'];

    /**
     * Upload step: store the file and dry-run the PTCC import. The returned response
     * carries a review_token that importReviewedBulkDatamanager() redeems.
     */
    public function previewBulkDatamanager(array $params)
    {
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        if (empty($_FILES['fileName']['name']) || !is_uploaded_file($_FILES['fileName']['tmp_name'] ?? '')) {
            $alertMsg->message = 'File not uploaded. Please try again.';
            return false;
        }
        $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
            $alertMsg->message = 'File format not supported';
            return false;
        }
        $path = realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . Pt_Commons_MiscUtility::generateRandomString(6) . '-' . $fileName;
        if (file_exists($path) || !move_uploaded_file($_FILES['fileName']['tmp_name'], $path)) {
            $alertMsg->message = 'Data import failed';
            return false;
        }

        $options = array_intersect_key($params, array_flip(self::PTCC_IMPORT_OPTIONS));
        $response = $this->runBulkDatamanagerImport($path, $options, true);
        if (is_array($response) && empty($response['validation_error'])) {
            $response['review_token'] = Pt_Commons_ImportReview::stash('ptcc', $path, $options);
            $response['options'] = $options;
        }
        return $response;
    }

    /** Import step: run the reviewed PTCC file for real with its reviewed options. */
    public function importReviewedBulkDatamanager(string $token)
    {
        $pending = Pt_Commons_ImportReview::claim('ptcc', $token);
        if ($pending === null) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'This review has expired or was already imported. Please upload the file again.';
            return false;
        }
        return $this->runBulkDatamanagerImport($pending['path'], $pending['params'], false);
    }

    private function runBulkDatamanagerImport(string $path, array $params, bool $dryRun)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        try {
            return $this->datamanagersDb->processBulkImport($path, false, $params, 'ptcc', $dryRun);
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to import PTCC data (Excel): ' . $exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return false;
        }
    }

    public function exportPTCCDetails($params)
    {
        return $this->datamanagersDb->exportPTCCDetails($params);
    }

    public function mapPtccLogin($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $db->select()
            ->from(['u' => 'data_manager'], [''])
            ->joinLeft(['pcm' => 'ptcc_countries_map'], 'pcm.ptcc_id=u.dm_id', [
                'state',
                'district',
                'country' => 'country_id',
            ])
            ->joinLeft(['c' => 'countries'], 'c.id=pcm.country_id', ['c.iso_name'])
            ->where('dm_id = ?', $id)
            ->group('u.dm_id');
        $result = $db->fetchRow($sQuery);
        return $this->datamanagersDb->dmParticipantMap($result, $id, true);
    }

    public function getDataManagersByParticipantId($participantId)
    {
        return $this->datamanagersDb->fetchDataManaersByParticipantId($participantId);
    }
}
