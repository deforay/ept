<?php

use Application_Service_SecurityService as SecurityService;

class Pt_Plugins_PreSetter extends Zend_Controller_Plugin_Abstract
{
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {

        $layout = Zend_Layout::getMvcInstance();

        /** @var Zend_Controller_Request_Http $request */
        if ($request->getControllerName() == 'error') {
            return;
        }

        $csrfCheck = SecurityService::checkCSRF($request);

        if (!$csrfCheck) {
            // Bounce to login as a real HTTP 302, NOT via setDispatched(false).
            // The front controller's dispatch loop re-fires preDispatch after every
            // setDispatched(false), but the request method survives — so on any POST
            // where checkCSRF returns false (token mismatch, expired form, leaked
            // PHPSESSID replayed by a bot scanning /), the next loop iteration sees
            // the same POST + same in-session csrf.token and fails again, looping at
            // 100% CPU forever until the worker is killed. A 302 sends the browser
            // to GET /admin/login (or /auth/login); GET isn't in $modifyingMethods,
            // so the follow-up trivially passes checkCSRF and the loop is broken.
            // Pattern matches the existing impersonation-expiry path below.
            $translate = Zend_Registry::get('translate');
            $expiredMessage = $translate->_('Your session has expired. Please sign in again.');
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = $expiredMessage;

            $loginUrl = in_array($request->getModuleName(), ['admin', 'reports'], true)
                ? '/admin/login'
                : '/auth/login';

            $response = $this->getResponse();
            $response->setRedirect($loginUrl, 302);
            $response->sendResponse();
            exit;
        }

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $loggedInAsParticipant = false;
        if (!empty($authNameSpace->dm_id)) {
            $loggedInAsParticipant = true;
        }

        $loggedInAsAdmin  = false;
        $adminAllowedOnFrontend = false;
        $adminAuthNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($adminAuthNameSpace->admin_id)) {
            $loggedInAsAdmin = true;

            // Admins may open any test type's participant-facing pages reached
            // from /admin/evaluate: the `response` page ("Edit" link) and the
            // `assay-formats` page (where the bulk of the TB form is built).
            // Rather than maintain a per-controller allowlist that silently
            // breaks every time a new test module is added (custom-test, dbs,
            // covid19 were all missing), allow these shared actions across every
            // front-end test controller.
            $adminAllowedActions = ['response', 'assay-formats'];
            if (in_array($request->getActionName(), $adminAllowedActions, true)) {
                $adminAllowedOnFrontend = true;
            }
        }

        // Idle timeout: 30 minutes of inactivity ends an authenticated session.
        // Sliding window — only requests *within the same module* count as
        // activity, so an idle admin tab doesn't keep a participant session
        // alive (and vice versa).
        $idleLimitSec = 1800;
        $now = time();
        $expiredKind = null;
        $moduleName = $request->getModuleName();
        $isAdminAreaRequest = in_array($moduleName, ['admin', 'reports'], true);
        $isParticipantAreaRequest = $moduleName === 'default';

        if ($loggedInAsAdmin) {
            $last = $adminAuthNameSpace->lastActivity ?? null;
            if ($last !== null && ($now - (int)$last) > $idleLimitSec) {
                $expiredKind = 'admin';
            } elseif ($isAdminAreaRequest) {
                $adminAuthNameSpace->lastActivity = $now;
            }
        }
        if ($expiredKind === null && $loggedInAsParticipant) {
            $last = $authNameSpace->lastActivity ?? null;
            // Impersonation sessions get a tighter idle TTL than normal logins.
            $effectiveIdleLimit = !empty($authNameSpace->impersonatedBy)
                ? (int)($authNameSpace->impersonationTtl ?? 900)
                : $idleLimitSec;
            if ($last !== null && ($now - (int)$last) > $effectiveIdleLimit) {
                $expiredKind = !empty($authNameSpace->impersonatedBy) ? 'impersonation' : 'participant';
            } elseif ($isParticipantAreaRequest) {
                $authNameSpace->lastActivity = $now;
            }
        }

        // Impersonation: audit every participant-module request, and on idle
        // expiry restore the admin's own participant session (if any).
        if (
            $expiredKind === null &&
            !empty($authNameSpace->impersonatedBy) &&
            $request->getModuleName() === 'default' &&
            $request->getControllerName() !== 'captcha' &&
            !$request->isXmlHttpRequest()
        ) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                sprintf(
                    'View-as-participant request: dm_id=%s, uri=%s',
                    (string)($authNameSpace->dm_id ?? ''),
                    $request->getRequestUri()
                ),
                'auth'
            );
        }

        if ($expiredKind === 'impersonation') {
            $impersonatedDmId = (string)($authNameSpace->dm_id ?? '');
            $impersonatedEmail = (string)($authNameSpace->primary_email ?? '');

            $loggedUser = new Zend_Session_Namespace('loggedUser');
            $backup = new Zend_Session_Namespace('impersonationBackup');
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

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                sprintf(
                    'View-as-participant expired (idle): dm_id=%s (%s)',
                    $impersonatedDmId,
                    $impersonatedEmail
                ),
                'auth'
            );

            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Impersonation session expired due to inactivity.';
            $alertMsg->status = 'success';
            $request->setModuleName('admin')->setControllerName('index')->setActionName('index');
            $request->setDispatched(false);
            return;
        }

        // Auto-teardown for admin hitting an admin-area URL while a
        // 'view-as-participant' session is still live. Without this, the
        // impersonated $datamanagers->dm_id silently scopes DbTable filters
        // across the admin module (participants list, shipments list, country
        // pickers, audit log queries, etc.) to whatever DM was last
        // impersonated — surfaced recently as /admin/participants showing
        // only the 4 labs mapped to the impersonated DM instead of all 800+.
        // Restore from the impersonationBackup before dispatch so this
        // request sees clean admin context. /admin/impersonate/* skips this
        // because that controller has its own teardown semantics (the user
        // explicitly clicked stop, or is mid-impersonation-start).
        if (
            $loggedInAsAdmin &&
            !empty($authNameSpace->impersonatedBy) &&
            $isAdminAreaRequest &&
            $request->getControllerName() !== 'impersonate'
        ) {
            $loggedUser = new Zend_Session_Namespace('loggedUser');
            $backup = new Zend_Session_Namespace('impersonationBackup');

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                sprintf(
                    'Auto-stopped view-as-participant on admin-area navigation: dm_id=%s (%s), target=%s',
                    (string)($authNameSpace->dm_id ?? ''),
                    (string)($authNameSpace->primary_email ?? ''),
                    $request->getRequestUri()
                ),
                'auth'
            );

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

            // The rest of preDispatch (and the dispatched controller) reads
            // $loggedInAsParticipant — keep it consistent with the cleared
            // session so downstream auth branches behave correctly.
            $loggedInAsParticipant = !empty($authNameSpace->dm_id);
        }

        if ($expiredKind !== null) {
            $loginUrl = $expiredKind === 'admin' ? '/admin' : '/auth/login';

            $authNameSpace->unsetAll();
            $adminAuthNameSpace->unsetAll();
            Zend_Auth::getInstance()->clearIdentity();

            if ($request->isXmlHttpRequest()) {
                $response = $this->getResponse();
                $response->setHttpResponseCode(401)
                    ->setHeader('Content-Type', 'application/json; charset=utf-8', true)
                    ->setBody(json_encode([
                        'status' => 'session_expired',
                        'loginUrl' => $loginUrl,
                    ]));
                $response->sendResponse();
                exit;
            }

            $translate = Zend_Registry::get('translate');
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = $translate->_('Your session has expired due to inactivity. Please sign in again.');

            if ($expiredKind === 'admin') {
                $request->setModuleName('admin')->setControllerName('login')->setActionName('index');
            } else {
                $request->setModuleName('default')->setControllerName('auth')->setActionName('login');
            }
            $request->setDispatched(false);
            return;
        }

        if (
            $request->getModuleName() == 'default' &&
            $request->getControllerName() != 'shipment-form'  &&
            $request->getControllerName() != 'auth' &&
            $request->getControllerName() != 'download'  &&
            $request->getControllerName() != 'error' &&
            $request->getControllerName() != 'index' &&
            $request->getControllerName() != 'captcha' &&
            $request->getControllerName() != 'common' &&
            !($request->getControllerName() == 'participant' &&
                $request->getActionName() == 'download-file')
        ) {
            if (!$loggedInAsParticipant && !$adminAllowedOnFrontend) {
                $request->setModuleName('default')->setControllerName('auth')->setActionName('login');
                $request->setDispatched(false);
            } elseif ($authNameSpace->forcePasswordReset == 1 || $authNameSpace->forcePasswordReset == '1') {
                if ($request->getControllerName() == 'participant' && $request->getActionName() == 'password') {
                    $sessionAlert = new Zend_Session_Namespace('alertSpace');
                    if ($_SESSION['profile_confirmed']) {
                        $sessionAlert->message = 'Please change your password to proceed.';
                    }
                } else {
                    $request->setModuleName('default')->setControllerName('participant')->setActionName('password');
                    $request->setDispatched(false);
                }
            }
        } elseif (($request->getModuleName() == 'admin'  && $request->getControllerName() != 'login') || $request->getModuleName() == 'reports') {

            if (!$loggedInAsAdmin) {
                $request->setModuleName('admin')->setControllerName('login')->setActionName('index');
                $request->setDispatched(false);
            }
            $layout->setLayout('admin');
        }
    }
}
