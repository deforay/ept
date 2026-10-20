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

        self::normalizeGridSearch();

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

        // reqAccessFrom=admin lets a response save skip the shipment lock (finalized,
        // closed or cancelled). It comes from the form, so a POST that claims it without
        // an administrator session is rejected here, before any response form handles it.
        if (!$loggedInAsAdmin && $request->isPost() && $request->getPost('reqAccessFrom') === 'admin') {
            $translate = Zend_Registry::get('translate');
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = $translate->_('You are not allowed to update the response for this participant.');

            $response = $this->getResponse();
            $response->setRedirect('/participant/dashboard', 302);
            $response->sendResponse();
            exit;
        }

        // Idle timeout: inactivity ends an authenticated session (2 hours, or
        // session.idleMinutes in application.ini). Sliding window — only
        // requests *within the same module* count as activity, so an idle admin
        // tab doesn't keep a participant session alive (and vice versa).
        $idleLimitSec = self::idleLimitSeconds();
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

        // A data manager only ever works on the labs mapped to them. Every front-end page
        // that takes a lab or shipment-map id from the request is checked here, so a
        // changed id in a URL or form can't open, change or delete another lab's data.
        if ($request->getModuleName() == 'default' && !$loggedInAsAdmin) {
            $dmId = $loggedInAsParticipant ? (int) $authNameSpace->dm_id : 0;
            if (!self::participantScopeAllowed($request, $dmId)) {
                if ($request->isXmlHttpRequest()) {
                    $response = $this->getResponse();
                    $response->setHttpResponseCode(403)->setBody('');
                    $response->sendResponse();
                    exit;
                }
                $translate = Zend_Registry::get('translate');
                $alertMsg = new Zend_Session_Namespace('alertSpace');
                $alertMsg->message = $translate->_('You do not have access to that participant.');
                $response = $this->getResponse();
                $response->setRedirect($dmId > 0 ? '/participant/dashboard' : '/auth/login', 302);
                $response->sendResponse();
                exit;
            }
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

    /** Front-end test controllers whose `pid` is a participant id */
    private const SCHEME_CONTROLLERS = ['dts', 'vl', 'eid', 'tb', 'dbs', 'recency', 'covid19', 'custom-test'];

    /**
     * Whether every lab (participant) and shipment-map id in the request belongs to
     * this data manager. `participantId`, `smid` and `mapId` are ids wherever they
     * appear; `pid` and `mid` only on the pages where they are (elsewhere `pid` is a
     * typed Lab ID, for example). Ids may be plain or base64. A request without these
     * params is allowed; one with them and no data manager ($dmId 0) is not, and
     * neither is the admin-only `from=admin` view.
     */
    private static function participantScopeAllowed(Zend_Controller_Request_Abstract $request, int $dmId): bool
    {
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        $from = [$request->getUserParam('from')];
        if ($request instanceof Zend_Controller_Request_Http) {
            $from[] = $request->getQuery('from');
            $from[] = $request->getPost('from');
        }
        if (in_array('admin', $from, true)) {
            return false;
        }
        // The public contact form's participantId is whatever the visitor typed
        if ($controller === 'index') {
            return true;
        }

        $participantParams = ['participantId'];
        $mapParams = ['smid', 'mapId'];
        if (
            in_array($controller, self::SCHEME_CONTROLLERS, true)
            || ($controller === 'participant' && in_array($action, ['feed-back', 'certificate', 'download-tb'], true))
            || ($controller === 'shipment-form' && $action === 'tb-download')
        ) {
            $participantParams[] = 'pid';
        }
        if ($controller === 'common' || ($controller === 'participant' && $action === 'feed-back')) {
            $mapParams[] = 'mid';
        }

        $participantIds = self::idsFromParams($request, $participantParams);
        $mapIds = self::idsFromParams($request, $mapParams);
        if ($participantIds === null || $mapIds === null) {
            return false;
        }

        if (empty($participantIds) && empty($mapIds)) {
            return true;
        }
        if ($dmId <= 0) {
            return false;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $allowed = array_map('intval', $db->fetchCol(
            $db->select()->from('participant_manager_map', 'participant_id')->where('dm_id = ?', $dmId)
        ));
        if (!empty($mapIds)) {
            $mapped = $db->fetchCol(
                $db->select()->from('shipment_participant_map', 'participant_id')->where('map_id IN (?)', array_unique($mapIds))
            );
            if (count($mapped) !== count(array_unique($mapIds))) {
                // A map id that doesn't exist
                return false;
            }
            $participantIds = array_merge($participantIds, array_map('intval', $mapped));
        }

        return empty(array_diff(array_unique($participantIds), $allowed));
    }

    /**
     * The ids in these params, or null when one holds something that isn't an id
     *
     * @return int[]|null
     */
    private static function idsFromParams(Zend_Controller_Request_Abstract $request, array $names): ?array
    {
        // Every source, not just the one getParam() picks (route, then query, then
        // post): a controller reading getPost() must not get an id this never saw
        $sources = [$request->getUserParams()];
        if ($request instanceof Zend_Controller_Request_Http) {
            $sources[] = $request->getQuery();
            $sources[] = $request->getPost();
        }

        $ids = [];
        foreach ($names as $name) {
            foreach ($sources as $source) {
                foreach ((array) ($source[$name] ?? []) as $value) {
                    if (is_array($value) || trim((string) $value) === '') {
                        continue;
                    }
                    $id = self::decodeId((string) $value);
                    if ($id === null) {
                        return null;
                    }
                    $ids[] = $id;
                }
            }
        }
        return $ids;
    }

    /** A positive integer id, sent plain or base64-encoded */
    private static function decodeId(string $value): ?int
    {
        $value = trim($value);
        if (!ctype_digit($value)) {
            $decoded = base64_decode($value, true);
            if ($decoded === false || !ctype_digit($decoded)) {
                return null;
            }
            $value = $decoded;
        }
        return (int) $value > 0 ? (int) $value : null;
    }

    /** Default idle limit when application.ini does not set session.idleMinutes */
    private const DEFAULT_IDLE_MINUTES = 120;

    /**
     * PHP keeps session data for 8 hours (session.gc_maxlifetime, set by
     * setup and upgrade), so a longer idle limit would be cut short anyway.
     */
    private const MAX_IDLE_MINUTES = 480;

    /** The page pings every 5 minutes while in use; a shorter limit would sign people out mid-typing */
    private const MIN_IDLE_MINUTES = 10;

    private static function idleLimitSeconds(): int
    {
        $minutes = self::DEFAULT_IDLE_MINUTES;
        $bootstrap = Zend_Controller_Front::getInstance()->getParam('bootstrap');
        if ($bootstrap !== null) {
            $configured = (int) ($bootstrap->getOptions()['session']['idleMinutes'] ?? 0);
            if ($configured > 0) {
                $minutes = max(self::MIN_IDLE_MINUTES, min($configured, self::MAX_IDLE_MINUTES));
            }
        }
        return $minutes * 60;
    }

    /* Grid searches split words on a plain space only. Text pasted from email or a
       spreadsheet often carries a tab, a non-breaking space or a zero-width space,
       which then sits inside the search word and matches no row. This turns all of
       them into plain spaces and trims the ends, for the global search (sSearch)
       and every column search (sSearch_N), before any controller reads them. */
    private static function normalizeGridSearch(): void
    {
        foreach (['_GET', '_POST'] as $source) {
            foreach ($GLOBALS[$source] as $key => $value) {
                if (!is_string($value) || !preg_match('/^sSearch(_\d+)?$/', (string) $key)) {
                    continue;
                }
                $clean = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]+/u', ' ', $value);
                $GLOBALS[$source][$key] = trim($clean ?? $value);
            }
        }
    }
}
