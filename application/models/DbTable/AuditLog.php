<?php

class Application_Model_DbTable_AuditLog extends Zend_Db_Table_Abstract
{
    protected $_name = 'audit_log';
    protected $_primary = 'audit_log_id';

    private static $columnsCache = null;

    /**
     * Detect which audit_log columns exist on this DB, so the code is safe to
     * run on instances where the 7.4.0 migration has not yet been applied.
     */
    private function availableColumns()
    {
        if (self::$columnsCache !== null) {
            return self::$columnsCache;
        }
        try {
            // describeTable uses SHOW COLUMNS under the hood — more reliable
            // and faster than an information_schema query, and it requires
            // only the SELECT grant the table already needs to function.
            $meta = $this->getAdapter()->describeTable($this->_name);
            $cols = array_keys($meta);
            self::$columnsCache = array_flip(array_map('strtolower', $cols));
            return self::$columnsCache;
        } catch (Throwable $e) {
            // Do NOT poison the cache on transient errors. A single failed
            // lookup used to set the cache to [] for the life of the
            // PHP-FPM worker, silently dropping ip_address / user_agent /
            // session_hash on every subsequent insert AND select.
            Pt_Commons_LoggerUtility::logWarning('AuditLog::availableColumns describeTable failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return [];
        }
    }

    private function hasColumn($name)
    {
        $cols = $this->availableColumns();
        return isset($cols[strtolower($name)]);
    }

    public function addNewAuditLog($stateMent, $type = null, $actor = null)
    {
        if (!isset($stateMent) || $stateMent === '') {
            return null;
        }
        if (is_array($actor) && !empty($actor['email'])) {
            $email = $actor['email'];
            $role = $actor['role'] ?? 'system';
        } else {
            [$email, $role] = $this->resolveActor();
        }
        $data = [
            'statement' => $stateMent,
            'created_by' => $email,
            'created_on' => new Zend_Db_Expr('now()'),
            'type' => $type,
        ];
        if ($this->hasColumn('created_by_role')) {
            $data['created_by_role'] = $role;
        }
        if ($this->hasColumn('ip_address')) {
            $data['ip_address'] = $this->captureIp();
        }
        if ($this->hasColumn('user_agent')) {
            $data['user_agent'] = $this->captureUserAgent();
        }
        if ($this->hasColumn('session_hash')) {
            $data['session_hash'] = Pt_Commons_General::sessionHash();
        }
        return $this->insert($data);
    }

    private function resolveActor()
    {
        $admin = new Zend_Session_Namespace('administrators');
        if (!empty($admin->primary_email)) {
            return [$admin->primary_email, 'admin'];
        }
        // DM login (AuthController) sets ->email, not ->primary_email. Honor
        // either so response-submission rows get the actor attached.
        $dm = new Zend_Session_Namespace('datamanagers');
        $dmEmail = !empty($dm->primary_email) ? $dm->primary_email : ($dm->email ?? null);
        if (!empty($dmEmail)) {
            $role = ($dm->data_manager_type ?? null) === 'ptcc' ? 'ptcc' : 'datamanager';
            return [$dmEmail, $role];
        }
        $participant = new Zend_Session_Namespace('participants');
        if (!empty($participant->primary_email)) {
            return [$participant->primary_email, 'participant'];
        }
        if (!empty($participant->email)) {
            return [$participant->email, 'participant'];
        }
        return [null, 'system'];
    }

    private function captureIp()
    {
        if (php_sapi_name() === 'cli') {
            return null;
        }
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $val = $_SERVER[$key];
                if ($key === 'HTTP_X_FORWARDED_FOR') {
                    $val = trim(explode(',', $val)[0]);
                }
                return substr($val, 0, 64);
            }
        }
        return null;
    }

    private function captureUserAgent()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }
        return substr($_SERVER['HTTP_USER_AGENT'], 0, 512);
    }

    private function classifyAction($statement)
    {
        $s = strtolower(trim($statement));
        if (strpos($s, 'deleted') === 0 || strpos($s, 'removed') === 0) {
            return 'delete';
        }
        if (strpos($s, 'bulk imported') === 0 || strpos($s, 'imported') === 0) {
            return 'import';
        }
        if (strpos($s, 'added') === 0 || strpos($s, 'created') === 0 || strpos($s, 'quick-created') === 0) {
            return 'create';
        }
        if (strpos($s, 'updated') === 0 || strpos($s, 'edited') === 0 || strpos($s, 'modified') === 0) {
            return 'update';
        }
        if (strpos($s, 'downloaded') === 0 || strpos($s, 'feedback downloaded') !== false || strpos($s, 'downloaded by') !== false || strpos($s, 'report downloaded') !== false) {
            return 'download';
        }
        if (strpos($s, 'email') === 0 || strpos($s, 'sent') === 0) {
            return 'message';
        }
        return 'other';
    }

    /**
     * Last N password-reset events targeting a given primary email.
     * Returns rows enriched with the actor's display name and role.
     */
    public function getRecentPasswordResetsForEmail($targetEmail, $limit = 3)
    {
        $targetEmail = trim((string) $targetEmail);
        if ($targetEmail === '') {
            return [];
        }
        $limit = max(1, (int) $limit);
        $db = $this->getAdapter();
        $needle = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $targetEmail) . '%';
        $cols = ['statement', 'created_by', 'created_on'];
        if ($this->hasColumn('ip_address')) {
            $cols[] = 'ip_address';
        }
        $select = $db->select()
            ->from(['al' => $this->_name], $cols)
            ->joinLeft(['sa' => 'system_admin'], 'al.created_by = sa.primary_email', [
                'sa_first_name' => 'sa.first_name',
                'sa_last_name' => 'sa.last_name',
            ])
            ->joinLeft(['dm' => 'data_manager'], 'al.created_by = dm.primary_email', [
                'dm_first_name' => 'dm.first_name',
                'dm_last_name' => 'dm.last_name',
            ])
            ->where('al.type = ?', 'password-reset')
            ->where('al.statement LIKE ?', $needle)
            ->order('al.created_on DESC')
            ->limit($limit);

        $rows = $db->fetchAll($select);
        $out = [];
        foreach ($rows as $row) {
            $name = '';
            $role = '';
            if (!empty($row['sa_first_name']) || !empty($row['sa_last_name'])) {
                $name = trim(($row['sa_first_name'] ?? '') . ' ' . ($row['sa_last_name'] ?? ''));
                $role = 'Admin';
            } elseif (!empty($row['dm_first_name']) || !empty($row['dm_last_name'])) {
                $name = trim(($row['dm_first_name'] ?? '') . ' ' . ($row['dm_last_name'] ?? ''));
                $role = 'Data Manager';
            }
            if ($name === '') {
                $name = $row['created_by'] ?: 'System';
            }
            $out[] = [
                'when' => $row['created_on'],
                'actorName' => $name,
                'actorRole' => $role,
                'actorEmail' => $row['created_by'],
                'statement' => $row['statement'],
                'source' => 'audit_log',
            ];
        }
        return $out;
    }

    /**
     * Recent DM self-initiated password changes for an email (NOT admin resets).
     * Self-change events are logged with type='auth' and statement starting
     * with 'Changed password' (see ParticipantController::changePasswordAction).
     */
    public function getRecentSelfPasswordChangesForEmail($targetEmail, $limit = 5)
    {
        $targetEmail = trim((string) $targetEmail);
        if ($targetEmail === '') {
            return [];
        }
        $limit = max(1, (int) $limit);
        $db = $this->getAdapter();
        $select = $db->select()
            ->from($this->_name, ['statement', 'created_by', 'created_on'])
            ->where('type = ?', 'auth')
            ->where('statement LIKE ?', 'Changed password%')
            ->where('created_by = ?', $targetEmail)
            ->order('created_on DESC')
            ->limit($limit);
        $rows = $db->fetchAll($select);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'when' => $row['created_on'],
                'statement' => $row['statement'],
            ];
        }
        return $out;
    }

    public function fetchAuditLogFeed($parameters)
    {
        $page = isset($parameters['page']) ? max(1, (int) $parameters['page']) : 1;
        $pageSize = isset($parameters['pageSize']) ? (int) $parameters['pageSize'] : 25;
        if ($pageSize < 5) {
            $pageSize = 5;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        $offset = ($page - 1) * $pageSize;

        $search = isset($parameters['search']) ? trim($parameters['search']) : '';
        $type = isset($parameters['type']) ? trim($parameters['type']) : '';
        $createdBy = isset($parameters['createdBy']) ? trim($parameters['createdBy']) : '';
        $actorEmail = isset($parameters['actorEmail']) ? trim($parameters['actorEmail']) : '';
        $labId = isset($parameters['labId']) ? trim($parameters['labId']) : '';
        $sessionHash = isset($parameters['sessionHash']) ? trim($parameters['sessionHash']) : '';
        $startDate = isset($parameters['startDate']) ? trim($parameters['startDate']) : '';
        $endDate = isset($parameters['endDate']) ? trim($parameters['endDate']) : '';

        // Which streams to show: 'actions' (audit_log only, the default and
        // historical behaviour), 'logins' (user_login_history only), or 'all'
        // (both, merged chronologically). The two tables remain separate; this
        // is a read-time union, opted into from the feed's source toggle.
        $source = isset($parameters['source']) ? trim($parameters['source']) : 'actions';
        if (!in_array($source, ['actions', 'logins', 'all'], true)) {
            $source = 'actions';
        }

        $db = $this->getAdapter();

        $hasRole = $this->hasColumn('created_by_role');
        $hasIp = $this->hasColumn('ip_address');
        $hasUa = $this->hasColumn('user_agent');
        $hasSess = $this->hasColumn('session_hash');

        // Source-aware FROM. The default ('actions') reads audit_log directly,
        // leaving the long-standing behaviour byte-for-byte unchanged. When
        // login history is opted in, both tables are normalised into one
        // derived table (still aliased `al`), so every join/filter/search/count
        // below keeps working verbatim against `al.<column>`.
        if ($source === 'actions') {
            $alCols = ['statement', 'created_by', 'created_on', 'type'];
            if ($hasRole) {
                $alCols[] = 'created_by_role';
            }
            if ($hasIp) {
                $alCols[] = 'ip_address';
            }
            if ($hasUa) {
                $alCols[] = 'user_agent';
            }
            if ($hasSess) {
                $alCols[] = 'session_hash';
            }
            $fromSource = $this->_name;
        } else {
            [$fromSource, $alCols] = $this->buildUnifiedFeedSource($source, $hasRole, $hasIp, $hasUa, $hasSess);
        }

        $select = $db->select()
            ->from(['al' => $fromSource], $alCols)
            ->joinLeft(
                ['sa' => 'system_admin'],
                'al.created_by = sa.primary_email',
                [
                    'sa_first_name' => 'sa.first_name',
                    'sa_last_name' => 'sa.last_name',
                ]
            )
            ->joinLeft(
                ['dm' => 'data_manager'],
                'al.created_by = dm.primary_email',
                [
                    'dm_first_name' => 'dm.first_name',
                    'dm_last_name' => 'dm.last_name',
                    'dm_type' => 'dm.data_manager_type',
                ]
            )
            ->joinLeft(
                ['p' => 'participant'],
                'al.created_by = p.email',
                [
                    'p_first_name' => 'p.first_name',
                    'p_last_name' => 'p.last_name',
                    'p_lab_name' => 'p.lab_name',
                    'p_unique_id' => 'p.unique_identifier',
                ]
            );

        if ($createdBy !== '') {
            $select->where('al.created_by = ?', $createdBy);
        }

        // Free-text actor-email filter: substring LIKE on created_by. Useful
        // for DM emails since the createdBy dropdown only lists system admins.
        if ($actorEmail !== '') {
            $select->where('al.created_by LIKE ?', '%' . $actorEmail . '%');
        }

        // Lab ID filter: resolve participant.unique_identifier -> set of DM
        // emails via participant_manager_map, then restrict to actions by
        // any of those DMs. IN (subquery) handles the empty case naturally
        // (no DMs -> zero rows, which is the right answer for "this lab").
        if ($labId !== '') {
            $dmEmailSub = $db->select()
                ->from(['dm' => 'data_manager'], ['primary_email'])
                ->joinInner(['pmm' => 'participant_manager_map'], 'dm.dm_id = pmm.dm_id', [])
                ->joinInner(['pl' => 'participant'], 'pmm.participant_id = pl.participant_id', [])
                ->where('pl.unique_identifier = ?', $labId);
            $select->where('al.created_by IN (?)', new Zend_Db_Expr((string) $dmEmailSub));
        }

        // Session-hash filter: group all rows from a single sitting (also
        // disambiguates users behind CGNAT, where IP alone collides).
        if ($sessionHash !== '' && $this->hasColumn('session_hash')) {
            $select->where('al.session_hash = ?', $sessionHash);
        }

        if ($startDate !== '' && $endDate !== '') {
            $common = new Application_Service_Common();
            $select->where('DATE(al.created_on) >= ?', $common->isoDateFormat($startDate));
            $select->where('DATE(al.created_on) <= ?', $common->isoDateFormat($endDate));
        }

        if ($type !== '') {
            $select->where('al.type = ?', $type);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q = $db->quote($like);
            $select->where(
                "al.statement LIKE $q OR al.created_by LIKE $q OR al.type LIKE $q "
                . "OR sa.first_name LIKE $q OR sa.last_name LIKE $q OR CONCAT_WS(' ', sa.first_name, sa.last_name) LIKE $q "
                . "OR dm.first_name LIKE $q OR dm.last_name LIKE $q OR CONCAT_WS(' ', dm.first_name, dm.last_name) LIKE $q "
                . "OR p.first_name LIKE $q OR p.last_name LIKE $q OR p.lab_name LIKE $q OR p.unique_identifier LIKE $q"
            );
        }

        $countSelect = clone $select;
        $countSelect->reset(Zend_Db_Select::COLUMNS);
        $countSelect->reset(Zend_Db_Select::ORDER);
        $countSelect->reset(Zend_Db_Select::LIMIT_COUNT);
        $countSelect->reset(Zend_Db_Select::LIMIT_OFFSET);
        $countSelect->columns(new Zend_Db_Expr('COUNT(*)'));
        $total = (int) $db->fetchOne($countSelect);

        $select->order('al.created_on DESC')->limit($pageSize, $offset);
        $rows = $db->fetchAll($select);

        $items = [];
        foreach ($rows as $row) {
            $role = 'Unknown';
            $name = '';
            if (!empty($row['sa_first_name']) || !empty($row['sa_last_name'])) {
                $name = trim(($row['sa_first_name'] ?? '') . ' ' . ($row['sa_last_name'] ?? ''));
                $role = 'Admin';
            } elseif (!empty($row['dm_first_name']) || !empty($row['dm_last_name'])) {
                $name = trim(($row['dm_first_name'] ?? '') . ' ' . ($row['dm_last_name'] ?? ''));
                $role = ($row['dm_type'] === 'ptcc') ? 'PTCC' : 'Data Manager';
            } elseif (!empty($row['p_first_name']) || !empty($row['p_last_name']) || !empty($row['p_lab_name'])) {
                $name = !empty($row['p_lab_name'])
                    ? $row['p_lab_name']
                    : trim(($row['p_first_name'] ?? '') . ' ' . ($row['p_last_name'] ?? ''));
                $role = 'Participant';
                if (!empty($row['p_unique_id']) && $name !== '') {
                    $name .= ' (' . $row['p_unique_id'] . ')';
                }
            }
            if ($name === '') {
                $name = $row['created_by'] ?? 'Unattributed';
            }
            // Login rows carry their own icon and, since user_login_history
            // already stores parsed browser/OS, their own display strings.
            $rowSource = $row['feed_source'] ?? 'action';
            if ($rowSource === 'login') {
                $loginStatus = strtolower((string) ($row['login_status'] ?? ''));
                $actionType = ($loginStatus === 'success') ? 'login' : 'login-fail';
            } else {
                $actionType = $this->classifyAction($row['statement']);
            }
            $ts = strtotime($row['created_on']);
            $items[] = [
                'action' => $row['statement'],
                'actionType' => $actionType,
                'source' => $rowSource,
                'userName' => $name,
                'userRole' => $role,
                'userEmail' => $row['created_by'],
                'userInitials' => $this->initials($name),
                'ipAddress' => $row['ip_address'] ?? '',
                'userAgent' => $row['user_agent'] ?? '',
                'browser' => $row['browser'] ?? '',
                'os' => $row['operating_system'] ?? '',
                'sessionHash' => $row['session_hash'] ?? '',
                'context' => $row['type'] ? ucwords(str_replace('-', ' ', $row['type'])) : '',
                'contextSlug' => $row['type'],
                'timestamp' => $row['created_on'],
                'time' => $ts ? date('g:i a', $ts) : '',
                'dateKey' => $ts ? date('Y-m-d', $ts) : '',
                'dateLabel' => $ts ? date('D, d M Y', $ts) : '',
            ];
        }

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 1,
            'items' => $items,
        ];
    }

    /**
     * Build the derived-table FROM source (and its column list) that merges
     * audit_log and/or user_login_history for the unified feed. Both branches
     * project an identical, aliased column set so a UNION ALL is legal and the
     * caller's joins/filters can keep referencing `al.<column>` unchanged.
     *
     * @return array{0: Zend_Db_Expr, 1: string[]}
     */
    private function buildUnifiedFeedSource($source, $hasRole, $hasIp, $hasUa, $hasSess)
    {
        $includeActions = ($source !== 'logins');
        $includeLogins = ($source === 'logins' || $source === 'all');

        // user_login_history.session_hash landed in the 7.4.7 migration; guard
        // it so the union is safe on instances that have not migrated yet.
        $loginHasSess = false;
        try {
            $loginMeta = $this->getAdapter()->describeTable('user_login_history');
            $loginHasSess = isset($loginMeta['session_hash']);
        } catch (Throwable $e) {
            $loginHasSess = false;
        }

        $loginStatusLabel = 'CASE l.login_status '
            . "WHEN 'success' THEN 'Logged in' "
            . "WHEN 'failed' THEN 'Login failed' "
            . "WHEN 'banned' THEN 'Login blocked' "
            . "WHEN 'locked' THEN 'Account locked' "
            . "ELSE CONCAT('Login attempt: ', COALESCE(l.login_status, 'unknown')) END";

        // Matched column order for both branches; aliases come from the first.
        $action = [
            'statement' => 'a.statement',
            'created_by' => 'a.created_by',
            'created_on' => 'a.created_on',
            'type' => 'a.type',
        ];
        $login = [
            'statement' => $loginStatusLabel,
            'created_by' => 'l.login_id',
            'created_on' => 'l.login_attempted_datetime',
            'type' => "'login'",
        ];
        if ($hasRole) {
            $action['created_by_role'] = 'a.created_by_role';
            $login['created_by_role'] = 'l.login_context';
        }
        if ($hasIp) {
            $action['ip_address'] = 'a.ip_address';
            $login['ip_address'] = 'l.ip_address';
        }
        if ($hasUa) {
            $action['user_agent'] = 'a.user_agent';
            $login['user_agent'] = 'NULL';
        }
        if ($hasSess) {
            $action['session_hash'] = 'a.session_hash';
            $login['session_hash'] = $loginHasSess ? 'l.session_hash' : 'NULL';
        }
        // Union-only columns: which stream a row came from, plus the login
        // display bits (browser/OS are already split in user_login_history;
        // audit rows derive them from user_agent client-side, so NULL here).
        $action['feed_source'] = "'action'";
        $login['feed_source'] = "'login'";
        $action['login_status'] = 'NULL';
        $login['login_status'] = 'l.login_status';
        $action['browser'] = 'NULL';
        $login['browser'] = 'l.browser';
        $action['operating_system'] = 'NULL';
        $login['operating_system'] = 'l.operating_system';

        $compile = function (array $parts, $table, $alias) {
            $cols = [];
            foreach ($parts as $name => $expr) {
                $cols[] = $expr . ' AS ' . $name;
            }
            return 'SELECT ' . implode(', ', $cols) . ' FROM ' . $table . ' ' . $alias;
        };

        $branches = [];
        if ($includeActions) {
            $branches[] = $compile($action, 'audit_log', 'a');
        }
        if ($includeLogins) {
            $branches[] = $compile($login, 'user_login_history', 'l');
        }

        return [
            new Zend_Db_Expr('(' . implode(' UNION ALL ', $branches) . ')'),
            array_keys($action),
        ];
    }

    private function initials($name)
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }
        $parts = preg_split('/\s+/', $name);
        $out = '';
        foreach ($parts as $p) {
            if ($p !== '' && ctype_alpha(substr($p, 0, 1))) {
                $out .= strtoupper(substr($p, 0, 1));
            }
            if (strlen($out) >= 2) {
                break;
            }
        }
        return $out !== '' ? $out : strtoupper(substr($name, 0, 1));
    }
}
