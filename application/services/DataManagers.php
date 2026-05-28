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
        if (isset($params['oldpemail']) && !empty($params['oldpemail']) && isset($params['pemail']) && !empty($params['pemail']) && ($params['oldpemail'] != $params['pemail'])) {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $eptDomain = rtrim($conf->domain, '/');
            $common = new Application_Service_Common();
            $message = 'Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from ' . $params['oldpemail'] . ' to ' . $params['pemail'] . ". <br/><br/> Please confirm your new primary email by clicking on the following link: <br/><br/><a href='" . $eptDomain . '/auth/verify/email/' . base64_encode($params['pemail']) . "'>" . $eptDomain . '/auth/verify/email/' . base64_encode($params['pemail']) . '</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>';
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->insertTempMail($params['pemail'], null, null, 'ePT | Change of login email id', $message, $fromMail, $fromName);
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
        if ($params['oldEmail'] != $params['registeredEmail']) {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $common = new Application_Service_Common();
            $eptDomain = rtrim($conf->domain, '/');
            $message = 'Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from ' . $params['oldEmail'] . ' to ' . $params['registeredEmail'] . ". <br/><br/> Please confirm your new login email by clicking on the following link: <br/><br/><a href='" . $eptDomain . '/auth/verify/email/' . base64_encode($params['registeredEmail']) . "'>" . $eptDomain . '/auth/verify/email/' . base64_encode($params['registeredEmail']) . '</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>';
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->insertTempMail($params['registeredEmail'], null, null, 'ePT | Change of login email id', $message, $fromMail, $fromName);
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
        $row = $this->datamanagersDb->fetchRow('new_email = "' . $params['registeredEmail'] . '"');
        if ($row) {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $common = new Application_Service_Common();
            $eptDomain = rtrim($conf->domain, '/');
            $message = 'Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from ' . $params['oldEmail'] . ' to ' . $params['registeredEmail'] . ". <br/><br/> Please confirm your new login email by clicking on the following link: <br/><br/><a href='" . $eptDomain . '/auth/verify/email/' . base64_encode($params['registeredEmail']) . "'>" . $eptDomain . '/auth/verify/email/' . base64_encode($params['registeredEmail']) . '</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>';
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $send = $common->insertTempMail($params['registeredEmail'], null, null, 'ePT | Change of login email id', $message, $fromMail, $fromName);
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
        return $this->datamanagersDb->fetchRow('new_email IS NOT NULL AND new_email not like "" AND dm_id = ' . $dmId);
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
        $participantName = trim((string) ($participant->first_name . ' ' . $participant->last_name));
        $participantName = htmlspecialchars($participantName, ENT_QUOTES, 'UTF-8');

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

            $message = "Dear {$participantName},<br/><br/>"
                . 'You (or someone else) requested a password reset for your ePT account (<b>'
                . htmlspecialchars($participantMail, ENT_QUOTES, 'UTF-8')
                . '</b>).<br/><br/>'
                . 'If you requested this, click the link below or paste it into your browser:<br/>'
                . "<a href='{$resetUrl}'>{$resetUrl}</a><br/><br/>"
                . 'This link will expire in 24 hours and can only be used once.<br/><br/>'
                . 'If you did not request a password reset, you can safely ignore this email.<br/><br/>'
                . '<small>Thanks,<br/>ePT Support</small>';

            $common->insertTempMail(
                $participantMail,
                null,
                null,
                'Password Reset - ePT',
                $message,
                $adminMail,
                $adminName
            );

            // Generic response to the user/browser
            $sessionAlert->message = $genericOkMsg;
            $sessionAlert->status = 'success';
        } else {
            // Bad/temporary domain or no MX: notify admin, keep user message generic
            $adminMsg = "Participant <b>{$participantName}</b> requested a password reset, "
                . 'but their email appears invalid or undeliverable: <b>'
                . htmlspecialchars($participantMail, ENT_QUOTES, 'UTF-8')
                . '</b> (input: '
                . htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8') . ').';

            $common->insertTempMail($adminMail, null, null, 'Password Reset - ePT', $adminMsg);

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

        $historyHtml = '';
        if (!empty($priorHistory)) {
            $items = '';
            foreach ($priorHistory as $r) {
                $ts = strtotime((string) ($r['when'] ?? ''));
                $when = $ts ? date('d M Y, H:i', $ts) : (string) ($r['when'] ?? '');
                $whenH = htmlspecialchars($when, ENT_QUOTES, 'UTF-8');
                $kind = $r['kind'] ?? 'reset';
                if ($kind === 'login') {
                    $line = $whenH . ' &mdash; you logged in to ePT';
                } elseif ($kind === 'self_change') {
                    $line = $whenH . ' &mdash; you changed your password';
                } else { // reset
                    $actor = trim((string) ($r['actorName'] ?? '')) ?: trim((string) ($r['actorEmail'] ?? ''));
                    $role  = !empty($r['actorRole']) ? ' (' . $r['actorRole'] . ')' : '';
                    if ($actor !== '') {
                        $line = $whenH . ' &mdash; password reset by ' . htmlspecialchars($actor . $role, ENT_QUOTES, 'UTF-8');
                    } else {
                        $line = $whenH . ' &mdash; password reset';
                    }
                    if (!empty($r['emailSent'])) {
                        $line .= ' <em>(credentials email sent to you)</em>';
                    }
                }
                $items .= '<li>' . $line . '</li>';
            }
            $historyHtml = '<br/><br/>For your security &mdash; recent activity on this account:'
                . '<ul>' . $items . '</ul>'
                . 'For any questions or support reach out to ePT support.';
        }

        $subject = 'Your ePT Login Credentials';
        $message = 'Dear Participant,<br/><br/>'
            . 'Please use the following to log in to ePT:<br/><br/>'
            . 'URL: <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a><br/>'
            . 'Login ID: ' . htmlspecialchars($loginId, ENT_QUOTES, 'UTF-8') . '<br/>'
            . 'Password: ' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8')
            . $historyHtml
            . '<br/><br/>Thanks,<br/>ePT Support';

        $mailCfg = json_decode((string) Application_Service_Common::getConfig('mail'));
        $fromEmail = $mailCfg->fromEmail ?? Application_Service_Common::getConfig('admin_email');
        $fromName  = $mailCfg->fromName  ?? (Application_Service_Common::getConfig('admin_name') ?: 'ePT Support');

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

    /* Resolves a list of pasted identifiers (emails OR participant unique_identifier
       a.k.a. "Lab ID") into the set of DMs to reset. A single Lab ID can map to
       multiple DMs via participant_manager_map; emails resolve to one DM.
       Returns ['matched' => [dm rows], 'unresolved' => [original input lines]]. */
    public function resolveDmIdentifiers(array $lines): array
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
            $rows = $db->fetchAll(
                $db->select()
                    ->from('data_manager', ['dm_id', 'first_name', 'last_name', 'institute', 'primary_email', 'status'])
                    ->where('LOWER(primary_email) IN (?)', array_keys($emails))
            );
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
            $auditDb->addNewAuditLog("Reset password for {$email} (bulk)", 'password-reset', $actor);

            if ($sendEmail) {
                $this->queueBulkCredentialsEmail($common, $email, $name, $newPassword, $loginUrl, $cc, $bcc);
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

    private function queueBulkCredentialsEmail(Application_Service_Common $common, string $toEmail, string $name, string $password, string $loginUrl, array $cc, array $bcc): void
    {
        $greetingName = $name !== '' ? $name : 'Participant';
        $subject = 'Your ePT Login Credentials';
        $message = 'Dear ' . htmlspecialchars($greetingName, ENT_QUOTES, 'UTF-8') . ',<br/><br/>'
            . 'Please use the following to log in to ePT:<br/><br/>'
            . 'URL: <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a><br/>'
            . 'Login ID: ' . htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8') . '<br/>'
            . 'Password: ' . htmlspecialchars($password, ENT_QUOTES, 'UTF-8') . '<br/><br/>'
            . 'Thanks,<br/>ePT Support';

        $mailCfg = json_decode((string) Application_Service_Common::getConfig('mail'));
        $fromEmail = $mailCfg->fromEmail ?? Application_Service_Common::getConfig('admin_email');
        $fromName  = $mailCfg->fromName  ?? (Application_Service_Common::getConfig('admin_name') ?: 'ePT Support');

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

    public function verifyEmailById($email)
    {
        return $this->datamanagersDb->fetchVerifyEmailById($email);
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
    public function uploadBulkDatamanager($params)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        try {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $allowedExtensions = ['xls', 'xlsx', 'csv'];
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
            $fileName = str_replace(' ', '-', $fileName);
            $random = Pt_Commons_MiscUtility::generateRandomString(6);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName = $random . '-' . $fileName;
            $response = [];
            if (in_array($extension, $allowedExtensions)) {
                $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
                if (!file_exists($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                    if (move_uploaded_file($_FILES['fileName']['tmp_name'], $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                        $response = $this->datamanagersDb->processBulkImport($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName, false, $params);
                    } else {
                        $alertMsg->message = 'Data import failed';
                        return false;
                    }
                } else {
                    $alertMsg->message = 'File not uploaded. Please try again.';
                    return false;
                }
            } else {
                $alertMsg->message = 'File format not supported';
                return false;
            }
        } catch (Exception $exc) {
            error_log('IMPORT-PARTICIPANTS-DATA-EXCEL--' . $exc->getMessage());
            error_log($exc->getTraceAsString());
            $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return false;
        }
        return $response;
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
