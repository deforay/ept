<?php

class Application_Service_DataManagers
{
    protected $datamanagersDb;
    public function __construct()
    {
        $this->datamanagersDb = new Application_Model_DbTable_DataManagers();
    }

    public function addUser($params)
    {
        return $this->datamanagersDb->addUser($params);
    }

    public function updateUser($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        /* Set lang in runtime */
        $authNameSpace->language = $params['language'];
        if (isset($params['oldpemail']) && !empty($params['oldpemail']) && isset($params['pemail']) && !empty($params['pemail']) && ($params['oldpemail'] != $params['pemail'])) {
            $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            $eptDomain = rtrim($conf->domain, "/");
            $common = new Application_Service_Common();
            $message = "Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from " . $params['oldpemail'] . " to " . $params['pemail'] . ". <br/><br/> Please confirm your new primary email by clicking on the following link: <br/><br/><a href='" . $eptDomain . "/auth/verify/email/" . base64_encode($params['pemail']) . "'>" . $eptDomain . "/auth/verify/email/" . base64_encode($params['pemail']) . "</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>";
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->insertTempMail($params['pemail'], null, null, "ePT | Change of login email id", $message, $fromMail, $fromName);
            $sessionAlert->message = "Please check your email “" . $params['pemail'] . "”. Once you verify, you can use “" . $params['pemail'] . "” to login to ePT.";
            $sessionAlert->status = "success";
            // $this->datamanagersDb->setStatusByEmail('inactive',$params['oldpemail']);
        } else {
            if ($authNameSpace->force_profile_check_primary == 'yes') {
                $sessionAlert->status = "failure";
                $this->datamanagersDb->updateForceProfileCheckByEmail(base64_encode($params['oldpemail']));
            } else {
                $sessionAlert->status = "failure";
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
            $eptDomain = rtrim($conf->domain, "/");
            $message = "Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from " . $params['oldEmail'] . " to " . $params['registeredEmail'] . ". <br/><br/> Please confirm your new login email by clicking on the following link: <br/><br/><a href='" . $eptDomain . "/auth/verify/email/" . base64_encode($params['registeredEmail']) . "'>" . $eptDomain . "/auth/verify/email/" . base64_encode($params['registeredEmail']) . "</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>";
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $common->insertTempMail($params['registeredEmail'], null, null, "ePT | Change of login email id", $message, $fromMail, $fromName);
            $sessionAlert->message = "Please check your email “" . $params['registeredEmail'] . "”. Once you verify, you can use “" . $params['registeredEmail'] . "” to login to ePT.";
            $sessionAlert->status = "success";
            // $this->datamanagersDb->setStatusByEmail('inactive',$params['oldEmail']);
        }
        $status = $this->datamanagersDb->changeForceProfileCheckByEmail($params);
        if ($showAlert) {
            $sessionAlert = new Zend_Session_Namespace('alertSpace');
            if ($status) {
                $sessionAlert->message = "You mail address has been changed. Please check your registered email id for the instructions.";
                $sessionAlert->status = "success";
            } else {
                $sessionAlert->message = "Yor are already used this address. Please try different mail!";
                $sessionAlert->status = "failure";
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
            $eptDomain = rtrim($conf->domain, "/");
            $message = "Dear Participant,<br/><br/> You or someone using your email requested to change your ePT login email address from " . $params['oldEmail'] . " to " . $params['registeredEmail'] . ". <br/><br/> Please confirm your new login email by clicking on the following link: <br/><br/><a href='" . $eptDomain . "/auth/verify/email/" . base64_encode($params['registeredEmail']) . "'>" . $eptDomain . "/auth/verify/email/" . base64_encode($params['registeredEmail']) . "</a> <br/><br/> If you are not able to click the link, you can copy and paste it in a browser address bar.<br/><br/> If you did not request for this update, you can safely ignore this email.<br/><br/><small>Thanks,<br/> Online PT Team<br/> <i>Please note: This is a system generated email.</i></small>";
            $fromMail = Application_Service_Common::getConfig('admin_email');
            $fromName = Application_Service_Common::getConfig('admin-name');
            $send = $common->insertTempMail($params['registeredEmail'], null, null, "ePT | Change of login email id", $message, $fromMail, $fromName);
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

        $participant = $this->datamanagersDb->resetPasswordForEmail($email);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        //    echo "<pre>"; print_r($conf); die;


        $eptDomain = rtrim($conf->domain, "/");

        if ($participant != false) {



            $common = new Application_Service_Common();
            $participantName = $participant->first_name . $participant->last_name;
            $participantMail = $participant->primary_email;
            $adminName = Application_Service_Common::getConfig('admin-name');
            $adminMail = Application_Service_Common::getConfig('admin_email');
            $excludedDomains = [
                "spam.com",
                "example.org",
                "example.com",
                "10minutemail.com",
                "guerrillamail.com",
                "tempmail.com",
                "mailinator.com",
                "yopmail.com",
                "throwawaymail.com",
                "fakeinbox.com",
                "test.com",
                "invalid.com",
                "noreply.com"
            ];

            $validMail = $common->isValidEmail($participantMail, $excludedDomains);
            if ($validMail == 1) {
                $message = "Dear Participant,<br/><br/> You have requested a password reset for the PT account for email " . $email . ". <br/><br/>If you requested for the password reset, please click on the following link <a href='" . $eptDomain . "/auth/new-password/email/" . base64_encode($email) . "'>" . $eptDomain . "/auth/new-password/email/" . base64_encode($email) . "</a> or copy and paste it in a browser address bar.<br/><br/> If you did not request for password reset, you can safely ignore this email.<br/><br/><small>Thanks,<br/> ePT Support</small>";
                $common->insertTempMail($email, null, null, "Password Reset - e-PT", $message, $adminMail, $adminName);
                $sessionAlert->message = "Your password has been reset. Please check your registered email id for the instructions.";
                $sessionAlert->status = "success";
            } else {
                $message = "Dear " . $adminName . ",<br/><br/> Participant " . $participantName . " has requested to reset their password<br/><br/>";
                $common->insertTempMail($adminMail, null, null, "Password Reset - e-PT", $message);
                $sessionAlert->message = "Ept admin will contact you shortly!.";
                $sessionAlert->status = "success";
            }
        } else {
            $sessionAlert->message = "Sorry, we could not reset your password. Please make sure that you entered your registered primary email";
            $sessionAlert->status = "failure";
        }
    }

    public function resetPasswordFromAdmin($params, $forcePasswordReset = false)
    {
        $result = $this->datamanagersDb->updatePasswordFromAdmin($params['primaryMail'], $params['password'], $forcePasswordReset);
        //$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        //$eptDomain = rtrim($conf->domain, "/");
        if ($result) {
            // $common = new Application_Service_Common();
            // $message = "Dear Participant,<br/><br/> You have requested a password reset for the PT account for email " . $params['primaryMail'] . ". <br/><br/>If you requested for the password reset, please click on the following link <a href='" . $eptDomain . "/auth/new-password/email/" . base64_encode($email) . "'>" . $eptDomain . "/auth/new-password/email/" . base64_encode($email) . "</a> or copy and paste it in a browser address bar.<br/><br/> If you did not request for password reset, you can safely ignore this email.<br/><br/><small>Thanks,<br/> ePT Support</small>";
            // $fromMail = Application_Service_Common::getConfig('admin_email');
            // $fromName = Application_Service_Common::getConfig('admin-name');
            // $common->insertTempMail($params['primaryMail'], null, null, "Password Reset - e-PT", $message, $fromMail, $fromName);
            return true;
        } else {
            return false;
        }
    }


    public function getDataManagerList($ptcc = false)
    {
        return $this->datamanagersDb->getAllDataManagers($ptcc);
    }

    public function getParticipantDatamanagerListByPid($participantId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('participant_manager_map')->where("participant_id= ?", $participantId));
    }

    public function getDatamanagerParticipantListByDid($datamanagerId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('participant_manager_map')
            ->where("dm_id= ?", $datamanagerId));
    }

    public function getParticipantDatamanagerList($params = array())
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('p' => 'participant'), array('participant_id', 'unique_identifier', 'first_name', 'last_name'))
            // ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
            // ->where("pmm.dm_id like ?", $params['datamanagerId'])
            ->where("p.status= ?", 'active');
        if (isset($params['country']) && $params['country'] != "") {
            $sql = $sql->where("country like ?", $params['country']);
        }
        if (isset($params['province']) && $params['province'] != "") {
            $sql = $sql->where("state like ?", $params['province']);
        }
        if (isset($params['district']) && $params['district'] != "") {
            $sql = $sql->where("district like ?", $params['district']);
        }
        if (isset($params['network']) && $params['network'] != "") {
            $sql = $sql->where("network_tier like ?", $params['network']);
        }
        if (isset($params['affiliation']) && $params['affiliation'] != "") {
            $sql = $sql->where("affiliation like ?", $params['affiliation']);
        }
        if (isset($params['institute']) && $params['institute'] != "") {
            $sql = $sql->where("institute_name like ?", $params['institute']);
        }

        $sql2 = $db->select()->from(array('p' => 'participant'), array('participant_id', 'unique_identifier', 'first_name', 'last_name'))
            ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array(''))
            ->where("pmm.dm_id like ?", $params['datamanagerId'])
            ->where("p.status= ?", 'active');

        $select = $db->select()
            ->union(array($sql, $sql2));

        // echo $select;die;

        return $db->fetchAll($select);
    }

    public function getDatamanagerParticipantList($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pmm' => 'participant_manager_map'), array('participant_id'))
            ->join(array('p' => 'participant'), 'pmm.participant_id=p.participant_id', array(''))
            ->where("dm_id like ?", $params['datamanagerId'])->group('p.participant_id');
        // die($sql);
        return $db->fetchAll($sql);
    }

    public function getParticipantsByDM()
    {
        $dmNameSpace = new Zend_Session_Namespace('datamanagers');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pmm' => 'participant_manager_map'))
            ->join(array('p' => 'participant'), 'pmm.participant_id=p.participant_id', array('*'))
            ->where("dm_id= ?", $dmNameSpace->dm_id)
            ->group('p.participant_id');
        return $db->fetchAll($sql);
    }

    public function changePassword($oldPassword, $newPassword)
    {
        $newPassword = $this->datamanagersDb->updatePassword($oldPassword, $newPassword);
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        if ($newPassword != false) {
            $sessionAlert->message = "Your password has been updated.";
            $sessionAlert->status = "success";
            return true;
        } else {
            if ($_SESSION['profile_confirmed']) {
                $sessionAlert->message = "Sorry, we could not update your password(check you enter correct old password). Please try again";
                $sessionAlert->status = "failure";
            }
            return false;
        }
    }

    public function checkAnnouncementMessageShowing($dmId)
    {
        $response = '';
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pmm' => 'participant_manager_map', array()))
            ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array())
            ->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array('show_announcement_count' => new Zend_Db_Expr("SUM(show_announcement ='yes')")))
            ->where("pmm.dm_id = ?", $dmId)
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
        if ($newPassword  != false) {
            $sessionAlert->message = "Your password has been updated.";
            $sessionAlert->status = "success";
            return '/auth/login';
        } else {
            $sessionAlert->message = "Sorry, we could not update your password. Please try again";
            $sessionAlert->status = "failure";
            return '/auth/new-password';
        }
    }

    public function checkEmail($email)
    {
        return $this->datamanagersDb->fetchEmailById($email);
    }

    public function verifyEmailById($email)
    {
        return $this->datamanagersDb->fetchVerifyEmailById($email);
    }

    public function checkForceProfileEmail($link)
    {
        return $this->datamanagersDb->fetchForceProfileEmail($link);
    }

    public function updateForceProfileCheck($email, $result = "")
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
            ->where("dm.primary_email = ?", strtolower($params["value"]));
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
            $allowedExtensions = array('xls', 'xlsx', 'csv');
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
            $fileName = str_replace(" ", "-", $fileName);
            $random = Pt_Commons_MiscUtility::generateRandomString(6);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName = $random . "-" . $fileName;
            $response = [];
            if (in_array($extension, $allowedExtensions)) {
                $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
                if (!file_exists($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                    if (move_uploaded_file($_FILES['fileName']['tmp_name'], $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                        $response = $this->datamanagersDb->processBulkImport($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName,  false, $params);
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
            error_log("IMPORT-PARTICIPANTS-DATA-EXCEL--" . $exc->getMessage());
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
            ->from(array('u' => 'data_manager'), array(''))
            ->joinLeft(array('pcm' => 'ptcc_countries_map'), 'pcm.ptcc_id=u.dm_id', array(
                'state',
                'district',
                'country' => 'country_id'
            ))
            ->joinLeft(array('c' => 'countries'), 'c.id=pcm.country_id', array('c.iso_name'))
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
