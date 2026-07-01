<?php

/**
 * Lets a specifically-privileged admin view the participant UI as a given
 * data manager, without resetting their password. Read-only by intent — we
 * still hydrate the participant session because the participant module reads
 * from it everywhere, but writes are not blocked at the controller level;
 * the audit log captures every action taken during the window.
 *
 * Hidden: no UI links anywhere. Granted only via the system-admins form
 * (privilege slug "view-as-participant").
 */
class Admin_ImpersonateController extends Zend_Controller_Action
{
    /** Impersonation auto-expires after this many seconds of inactivity. */
    private const TTL_SECONDS = 900;

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', (string)($adminSession->privileges ?? ''));
        $privileges = array_map('trim', $privileges);

        // Exiting impersonation is always allowed if you have an admin
        // session — so that a privilege revocation mid-window can't strand
        // someone in the participant UI.
        if ($request->getActionName() === 'stop') {
            $this->_helper->layout()->pageName = 'configMenu';
            return;
        }

        if (!in_array('view-as-participant', $privileges, true)) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                'Denied view-as-participant attempt: ' . $request->getRequestUri(),
                'auth'
            );
            if ($request->isXmlHttpRequest()) {
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
            return;
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function startAction()
    {
        $dmId = (int)$this->_getParam('dm_id', 0);
        if ($dmId <= 0) {
            $this->redirect('/admin');
            return;
        }

        $dmDb = new Application_Model_DbTable_DataManagers();
        $result = $dmDb->getUserDetailsBySystemId($dmId);
        if (!$result || empty($result['dm_id'])) {
            $sessionAlert = new Zend_Session_Namespace('alertSpace');
            $sessionAlert->message = 'Data manager not found.';
            $sessionAlert->status = 'failure';
            $this->redirect('/admin');
            return;
        }

        $adminSession = new Zend_Session_Namespace('administrators');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $loggedUser = new Zend_Session_Namespace('loggedUser');
        $backup = new Zend_Session_Namespace('impersonationBackup');

        // If a previous impersonation is still live, end it first so its
        // backup isn't overwritten by another impersonated session.
        if (!empty($authNameSpace->impersonatedBy)) {
            $this->restoreFromBackup($authNameSpace, $loggedUser, $backup);
        }

        // Snapshot the admin's own participant session (if any) so we can
        // restore it on exit. Use getIterator() to capture all keys.
        $backup->unsetAll();
        $backup->datamanagers = iterator_to_array($authNameSpace->getIterator());
        $backup->loggedUser = iterator_to_array($loggedUser->getIterator());
        $backup->savedAt = time();

        $authNameSpace->unsetAll();
        $loggedUser->unsetAll();

        $loggedUser->partcipant_id = $result['dm_id'];
        $loggedUser->primary_email = $result['primary_email'];
        $loggedUser->first_name = $result['first_name'];
        $loggedUser->last_name = $result['last_name'];

        $authNameSpace->UserID = $result['primary_email'];
        $authNameSpace->dm_id = $result['dm_id'];
        $authNameSpace->first_name = $result['first_name'];
        $authNameSpace->last_name = $result['last_name'];
        $authNameSpace->phone = $result['phone'];
        $authNameSpace->email = $result['primary_email'];
        $authNameSpace->primary_email = $result['primary_email'];
        $authNameSpace->qc_access = $result['qc_access'];
        $authNameSpace->view_only_access = $result['view_only_access'];
        $authNameSpace->enable_adding_test_response_date = $result['enable_adding_test_response_date'];
        $authNameSpace->enable_choosing_mode_of_receipt = $result['enable_choosing_mode_of_receipt'];
        $authNameSpace->language = $result['language'];
        $authNameSpace->data_manager_type = $result['data_manager_type'];
        // Suppress the participant-side "verify profile / verify email" walls;
        // they're not relevant when an admin is troubleshooting.
        $authNameSpace->forcePasswordReset = 0;
        $authNameSpace->force_profile_check = 'no';
        $authNameSpace->force_profile_check_primary = 'no';
        if (($result['data_manager_type'] ?? null) === 'ptcc') {
            $authNameSpace->ptcc = 1;
        }

        $authNameSpace->impersonatedBy = (int)($adminSession->admin_id ?? 0);
        $authNameSpace->impersonatedByEmail = $adminSession->primary_email ?? null;
        $authNameSpace->impersonationStartedAt = time();
        $authNameSpace->lastActivity = time();
        $authNameSpace->impersonationTtl = self::TTL_SECONDS;

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog(
            sprintf(
                'Started view-as-participant: dm_id=%d (%s)',
                (int)$result['dm_id'],
                $result['primary_email']
            ),
            'auth'
        );

        $this->redirect('/participant/dashboard');
    }

    public function stopAction()
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $loggedUser = new Zend_Session_Namespace('loggedUser');
        $backup = new Zend_Session_Namespace('impersonationBackup');

        if (!empty($authNameSpace->impersonatedBy)) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                sprintf(
                    'Stopped view-as-participant: dm_id=%s (%s)',
                    (string)($authNameSpace->dm_id ?? ''),
                    (string)($authNameSpace->primary_email ?? '')
                ),
                'auth'
            );
        }

        $this->restoreFromBackup($authNameSpace, $loggedUser, $backup);
        $this->redirect('/admin');
    }

    private function restoreFromBackup(
        Zend_Session_Namespace $authNameSpace,
        Zend_Session_Namespace $loggedUser,
        Zend_Session_Namespace $backup
    ): void {
        $authNameSpace->unsetAll();
        $loggedUser->unsetAll();
        if (!empty($backup->datamanagers) && is_array($backup->datamanagers)) {
            foreach ($backup->datamanagers as $k => $v) {
                $authNameSpace->$k = $v;
            }
        }
        if (!empty($backup->loggedUser) && is_array($backup->loggedUser)) {
            foreach ($backup->loggedUser as $k => $v) {
                $loggedUser->$k = $v;
            }
        }
        $backup->unsetAll();
    }
}
