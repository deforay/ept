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

    /** Keys whose values are never written to the log, only that they changed. */
    private const SECRET_KEY_PATTERN = '/(password|passwd|passphrase|secret|token|api[_-]?key|private[_-]?key|dsn)$/i';

    /** Longest value kept for one before or after value. */
    private const MAX_VALUE_LENGTH = 2000;

    /** Most rows kept in one details list. */
    private const MAX_LIST_ROWS = 1000;

    /** @var string|null one id per PHP request, shared by every entry it writes */
    private static $requestId = null;

    /**
     * Write one audit log entry.
     *
     * $extra keys, all optional:
     *   entity           ['type' => 'shipment', 'id' => 12, 'label' => 'DTS0826-1']
     *   changes          list from self::changes(): what changed, before and after
     *   facts            ['Pass mark' => '100', ...] context worth keeping
     *   list             ['title' => 'Participants removed', 'rows' => ['SLPT86 - Lab', ...]]
     *   important        true for changes that can alter scores or results
     *   reason           why the entry is important, shown next to the flag
     *   importantFields  field names that make the entry important when changed
     *   snapshot         ['key' => 'value', ...] kept for later comparison, not shown
     *
     * When `changes` is given but empty, the entry still records the save and
     * says that nothing changed.
     *
     * @param array{email?: string, role?: string, id?: int|null}|null $actor
     * @param array<string, mixed> $extra
     */
    public function addNewAuditLog($stateMent, $type = null, $actor = null, array $extra = [])
    {
        if (!isset($stateMent) || $stateMent === '') {
            return null;
        }
        $context = $this->resolveActor();
        if (is_array($actor) && !empty($actor['email'])) {
            $context['email'] = $actor['email'];
            $context['role'] = $actor['role'] ?? 'system';
            $context['id'] = $actor['id'] ?? null;
            $context['onBehalfOf'] = null;
        }

        $changes = $extra['changes'] ?? null;
        if (is_array($changes) && $changes === [] && array_key_exists('changes', $extra)) {
            $stateMent .= ' (no changes)';
        }
        [$important, $reason] = $this->importance($extra, is_array($changes) ? $changes : []);

        $data = [
            'statement' => $stateMent,
            'created_by' => $context['email'],
            'created_on' => new Zend_Db_Expr('now()'),
            'type' => $type,
        ];
        $optional = [
            'created_by_role' => $context['role'],
            'ip_address' => $this->captureIp(),
            'user_agent' => $this->captureUserAgent(),
            'session_hash' => Pt_Commons_General::sessionHash(),
            'actor_id' => $context['id'],
            'on_behalf_of' => $context['onBehalfOf'],
            'request_id' => self::requestId(),
            'route' => $this->captureRoute(),
            'channel' => $this->captureChannel(),
            'important' => $important ? 1 : 0,
        ];
        $entity = $extra['entity'] ?? null;
        if (is_array($entity)) {
            $optional['entity_type'] = isset($entity['type']) ? substr((string) $entity['type'], 0, 32) : null;
            $optional['entity_id'] = isset($entity['id']) && $entity['id'] !== '' ? substr((string) $entity['id'], 0, 64) : null;
            $optional['entity_label'] = isset($entity['label']) && $entity['label'] !== '' ? mb_substr((string) $entity['label'], 0, 255) : null;
        }
        $details = $this->buildDetails($extra, is_array($changes) ? $changes : [], $reason);
        if ($details !== null) {
            $optional['details'] = $details;
        }
        foreach ($optional as $column => $value) {
            if ($this->hasColumn($column)) {
                $data[$column] = $value;
            }
        }
        return $this->insert($data);
    }

    /**
     * Static shorthand for addNewAuditLog() with the current user as actor.
     *
     * @param array<string, mixed> $extra see addNewAuditLog()
     */
    public static function record(string $statement, ?string $type = null, array $extra = [])
    {
        try {
            return (new self())->addNewAuditLog($statement, $type, null, $extra);
        } catch (Throwable $e) {
            // The audit log must never break the action it records.
            Pt_Commons_LoggerUtility::logWarning('AuditLog::record failed: ' . $e->getMessage(), [
                'statement' => $statement,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return null;
        }
    }

    /**
     * Compare two versions of a record and list what changed.
     *
     * Nested arrays are compared field by field with dotted names, lists as a
     * whole. Values are compared as display strings, so "100" and 100 are the
     * same. Secret fields (passwords, tokens, keys) record that they changed,
     * never their values.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, string> $labels field => readable label
     * @param string[] $ignore fields to skip, such as timestamps
     * @return list<array{field: string, label: string, from: string, to: string}>
     */
    public static function changes(?array $before, ?array $after, array $labels = [], array $ignore = []): array
    {
        $old = self::flatten($before ?? []);
        $new = self::flatten($after ?? []);
        $ignoreSet = array_flip($ignore);
        $out = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
            $field = (string) $field;
            if (isset($ignoreSet[$field]) || isset($ignoreSet[strtok($field, '.')])) {
                continue;
            }
            $from = $old[$field] ?? '';
            $to = $new[$field] ?? '';
            if ($from === $to) {
                continue;
            }
            if (preg_match(self::SECRET_KEY_PATTERN, (string) substr((string) strrchr('.' . $field, '.'), 1))) {
                $from = $from === '' ? '' : '(hidden)';
                $to = $to === '' ? '' : '(hidden)';
            }
            $out[] = [
                'field' => $field,
                'label' => $labels[$field] ?? self::humanize($field),
                'from' => self::clip($from),
                'to' => self::clip($to),
            ];
        }
        return $out;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, string>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $out += self::flatten($value, $name);
                continue;
            }
            $out[$name] = self::display($value);
        }
        return $out;
    }

    private static function display($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $parts[] = is_array($item) ? (string) json_encode($item) : self::display($item);
            }
            return implode(', ', $parts);
        }
        if (is_object($value)) {
            return (string) json_encode($value);
        }
        return trim((string) $value);
    }

    private static function clip(string $value): string
    {
        return mb_strlen($value) > self::MAX_VALUE_LENGTH
            ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) . '…'
            : $value;
    }

    /** "passPercentage" or "pass_percentage" becomes "Pass percentage". */
    public static function humanize(string $field): string
    {
        $last = substr((string) strrchr('.' . $field, '.'), 1);
        $words = preg_replace(['/([a-z0-9])([A-Z])/', '/[_\-]+/'], ['$1 $2', ' '], $last);
        $words = strtolower(trim((string) $words));
        $label = ucfirst($words);
        $parent = strpos($field, '.') !== false ? substr($field, 0, (int) strrpos($field, '.')) : '';
        return $parent !== '' ? self::humanize($parent) . ' › ' . $label : $label;
    }

    /**
     * @param array<string, mixed> $extra
     * @param list<array{field: string, label: string, from: string, to: string}> $changes
     * @return array{0: bool, 1: string}
     */
    private function importance(array $extra, array $changes): array
    {
        $important = !empty($extra['important']);
        $reason = isset($extra['reason']) ? (string) $extra['reason'] : '';
        $watched = $extra['importantFields'] ?? [];
        if (is_array($watched) && $watched !== [] && $changes !== []) {
            $watchedSet = array_flip($watched);
            $hits = [];
            foreach ($changes as $change) {
                $top = strtok($change['field'], '.');
                if (isset($watchedSet[$change['field']]) || isset($watchedSet[$top])) {
                    $hits[] = $change['label'];
                }
            }
            if ($hits !== []) {
                $important = true;
                if ($reason === '') {
                    $reason = 'Changes scoring: ' . implode(', ', $hits);
                }
            }
        }
        return [$important, $important ? $reason : ''];
    }

    /**
     * @param array<string, mixed> $extra
     * @param list<array{field: string, label: string, from: string, to: string}> $changes
     */
    private function buildDetails(array $extra, array $changes, string $reason): ?string
    {
        $details = [];
        if ($changes !== []) {
            $details['changes'] = array_values($changes);
        }
        if (!empty($extra['facts']) && is_array($extra['facts'])) {
            $facts = [];
            foreach ($extra['facts'] as $label => $value) {
                $facts[] = ['label' => (string) $label, 'value' => self::clip(self::display($value))];
            }
            $details['facts'] = $facts;
        }
        if (!empty($extra['list']['rows']) && is_array($extra['list']['rows'])) {
            $rows = array_map(static fn ($row) => self::clip(self::display($row)), array_values($extra['list']['rows']));
            $details['list'] = [
                'title' => (string) ($extra['list']['title'] ?? ''),
                'total' => count($rows),
                'rows' => array_slice($rows, 0, self::MAX_LIST_ROWS),
            ];
        }
        if ($reason !== '') {
            $details['reason'] = $reason;
        }
        if (!empty($extra['snapshot']) && is_array($extra['snapshot'])) {
            $details['snapshot'] = array_map(static fn ($v) => self::clip(self::display($v)), $extra['snapshot']);
        }
        if ($details === []) {
            return null;
        }
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? null : $json;
    }

    /**
     * The snapshot stored with the latest entry about one record whose
     * statement starts with $statementPrefix, or null when there is none.
     *
     * @return array<string, string>|null
     */
    public function latestSnapshot(string $entityType, string $entityId, string $statementPrefix): ?array
    {
        if (!$this->hasColumn('details')) {
            return null;
        }
        $db = $this->getAdapter();
        $json = $db->fetchOne(
            $db->select()
                ->from($this->_name, ['details'])
                ->where('entity_type = ?', $entityType)
                ->where('entity_id = ?', $entityId)
                ->where('statement LIKE ?', addcslashes($statementPrefix, '%_\\') . '%')
                ->where("JSON_EXTRACT(details, '$.snapshot') IS NOT NULL")
                ->order('audit_log_id DESC')
                ->limit(1)
        );
        if (!$json) {
            return null;
        }
        $decoded = json_decode((string) $json, true);
        return is_array($decoded['snapshot'] ?? null) ? $decoded['snapshot'] : null;
    }

    /** Same id for every entry written during one PHP request. */
    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(8));
        }
        return self::$requestId;
    }

    private function captureChannel(): string
    {
        if (php_sapi_name() === 'cli') {
            return 'cli';
        }
        $module = $this->currentRequest()?->getModuleName();
        return $module === 'api' ? 'api' : 'web';
    }

    private function captureRoute(): ?string
    {
        if (php_sapi_name() === 'cli') {
            $script = $_SERVER['argv'][0] ?? ($_SERVER['SCRIPT_NAME'] ?? '');
            return $script !== '' ? substr(basename((string) $script), 0, 255) : null;
        }
        $request = $this->currentRequest();
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($request !== null && $request->getControllerName() !== null) {
            $path = implode('/', array_filter([
                $request->getModuleName(),
                $request->getControllerName(),
                $request->getActionName(),
            ], static fn ($part) => $part !== null && $part !== '' && $part !== 'default'));
        } else {
            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        }
        $route = trim($method . ' ' . $path);
        return $route !== '' ? substr($route, 0, 255) : null;
    }

    private function currentRequest(): ?Zend_Controller_Request_Abstract
    {
        try {
            return Zend_Controller_Front::getInstance()->getRequest();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * The signed-in user, from the session namespaces. During View as, the
     * admin is the actor and the data manager account goes in onBehalfOf.
     *
     * @return array{email: ?string, role: string, id: ?int, onBehalfOf: ?string}
     */
    private function resolveActor(): array
    {
        // CLI runs (scheduled jobs) have no session; starting one here throws
        // once any output has been emitted. Attribute these to the system.
        if (php_sapi_name() === 'cli') {
            return ['email' => null, 'role' => 'system', 'id' => null, 'onBehalfOf' => null];
        }
        $admin = new Zend_Session_Namespace('administrators');
        $dm = new Zend_Session_Namespace('datamanagers');
        // DM login (AuthController) sets ->email, not ->primary_email. Honor
        // either so response-submission rows get the actor attached.
        $dmEmail = !empty($dm->primary_email) ? $dm->primary_email : ($dm->email ?? null);
        if (!empty($admin->primary_email)) {
            $onBehalfOf = (!empty($dm->impersonatedBy) && !empty($dmEmail)) ? (string) $dmEmail : null;
            return [
                'email' => $admin->primary_email,
                'role' => 'admin',
                'id' => !empty($admin->admin_id) ? (int) $admin->admin_id : null,
                'onBehalfOf' => $onBehalfOf,
            ];
        }
        if (!empty($dmEmail)) {
            $role = ($dm->data_manager_type ?? null) === 'ptcc' ? 'ptcc' : 'datamanager';
            return [
                'email' => $dmEmail,
                'role' => $role,
                'id' => !empty($dm->dm_id) ? (int) $dm->dm_id : null,
                'onBehalfOf' => null,
            ];
        }
        $participant = new Zend_Session_Namespace('participants');
        $participantEmail = !empty($participant->primary_email) ? $participant->primary_email : ($participant->email ?? null);
        if (!empty($participantEmail)) {
            return ['email' => $participantEmail, 'role' => 'participant', 'id' => null, 'onBehalfOf' => null];
        }
        return ['email' => null, 'role' => 'system', 'id' => null, 'onBehalfOf' => null];
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
        $prefixes = [
            'evaluated' => 'evaluate',
            're-evaluated' => 'evaluate',
            'finalized' => 'finalize',
            'unfinalized' => 'finalize',
            'submitted' => 'submit',
            'started view-as' => 'impersonate',
            'ended view-as' => 'impersonate',
            'logged in' => 'login',
            'logged out' => 'logout',
            'changed password' => 'password',
            'reset password' => 'password',
            'requested password' => 'password',
            'set new password' => 'password',
            'cancelled' => 'cancel',
            'shipped' => 'ship',
            'generated' => 'generate',
            'queued' => 'generate',
        ];
        foreach ($prefixes as $prefix => $kind) {
            if (str_starts_with($s, $prefix)) {
                return $kind;
            }
        }
        if (strpos($s, 'deleted') === 0 || strpos($s, 'removed') === 0 || strpos($s, 'discarded') === 0) {
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

    /** Most rows one CSV export returns. */
    public const EXPORT_LIMIT = 50000;

    /**
     * @param array<string, mixed> $parameters filters from the Audit Log page
     * @param bool $export true returns up to EXPORT_LIMIT rows on one page
     */
    public function fetchAuditLogFeed($parameters, bool $export = false)
    {
        $page = isset($parameters['page']) ? max(1, (int) $parameters['page']) : 1;
        $pageSize = isset($parameters['pageSize']) ? (int) $parameters['pageSize'] : 25;
        if ($pageSize < 5) {
            $pageSize = 5;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        if ($export) {
            $page = 1;
            $pageSize = self::EXPORT_LIMIT;
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
        $entityType = isset($parameters['entityType']) ? trim((string) $parameters['entityType']) : '';
        $entityId = isset($parameters['entityId']) ? trim((string) $parameters['entityId']) : '';
        $importantOnly = !empty($parameters['important']) && $parameters['important'] !== 'false';
        $onBehalfOf = isset($parameters['onBehalfOf']) ? trim((string) $parameters['onBehalfOf']) : '';
        $requestId = isset($parameters['requestId']) ? trim((string) $parameters['requestId']) : '';

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
        // The 7.7.3 columns arrive together, so one check covers them all.
        $hasStructured = $this->hasColumn('details');

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
            if ($hasStructured) {
                array_push($alCols, ...self::STRUCTURED_COLUMNS);
            }
            $fromSource = $this->_name;
        } else {
            [$fromSource, $alCols] = $this->buildUnifiedFeedSource($source, $hasRole, $hasIp, $hasUa, $hasSess, $hasStructured);
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

        if ($hasStructured) {
            if ($entityType !== '') {
                $select->where('al.entity_type = ?', $entityType);
            }
            if ($entityId !== '') {
                $select->where('al.entity_id = ?', $entityId);
            }
            if ($importantOnly) {
                $select->where('al.important = 1');
            }
            if ($onBehalfOf !== '') {
                $select->where('al.on_behalf_of LIKE ?', '%' . $onBehalfOf . '%');
            }
            if ($requestId !== '') {
                $select->where('al.request_id = ?', $requestId);
            }
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $q = $db->quote($like);
            $select->where(
                "al.statement LIKE $q OR al.created_by LIKE $q OR al.type LIKE $q "
                . "OR sa.first_name LIKE $q OR sa.last_name LIKE $q OR CONCAT_WS(' ', sa.first_name, sa.last_name) LIKE $q "
                . "OR dm.first_name LIKE $q OR dm.last_name LIKE $q OR CONCAT_WS(' ', dm.first_name, dm.last_name) LIKE $q "
                . "OR p.first_name LIKE $q OR p.last_name LIKE $q OR p.lab_name LIKE $q OR p.unique_identifier LIKE $q"
                . ($hasStructured ? " OR al.entity_label LIKE $q OR al.on_behalf_of LIKE $q OR CAST(al.details AS CHAR) LIKE $q" : '')
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
            ] + $this->structuredFields($row);
        }

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $pageSize)),
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
    private function buildUnifiedFeedSource($source, $hasRole, $hasIp, $hasUa, $hasSess, $hasStructured = false)
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
        if ($hasStructured) {
            foreach (self::STRUCTURED_COLUMNS as $column) {
                $action[$column] = 'a.' . $column;
                $login[$column] = $column === 'important' ? '0' : 'NULL';
            }
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

    /** Columns added in 7.7.3, read by the feed. */
    private const STRUCTURED_COLUMNS = [
        'entity_type', 'entity_id', 'entity_label', 'details', 'important',
        'actor_id', 'on_behalf_of', 'request_id', 'route', 'channel',
    ];

    /**
     * The 7.7.3 fields of one feed row, shaped for the page.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function structuredFields(array $row): array
    {
        $details = [];
        if (!empty($row['details'])) {
            $decoded = json_decode((string) $row['details'], true);
            $details = is_array($decoded) ? $decoded : [];
        }
        $entityType = (string) ($row['entity_type'] ?? '');
        $entityId = (string) ($row['entity_id'] ?? '');
        // Labels are stored in English and translated for the reader.
        $t = $this->translator();
        $changes = [];
        foreach (($details['changes'] ?? []) as $change) {
            $change['label'] = $t((string) ($change['label'] ?? ''));
            $changes[] = $change;
        }
        $facts = [];
        foreach (($details['facts'] ?? []) as $fact) {
            $fact['label'] = $t((string) ($fact['label'] ?? ''));
            $facts[] = $fact;
        }
        $list = $details['list'] ?? null;
        if (is_array($list)) {
            $list['title'] = $t((string) ($list['title'] ?? ''));
        }
        return [
            'entityType' => $entityType,
            'entityId' => $entityId,
            'entityLabel' => (string) ($row['entity_label'] ?? ''),
            'entityTypeLabel' => $entityType !== '' ? $t(self::entityTypeLabel($entityType)) : '',
            'changes' => $changes,
            'facts' => $facts,
            'list' => $list,
            'important' => !empty($row['important']),
            'reason' => $this->translateReason((string) ($details['reason'] ?? '')),
            'onBehalfOf' => (string) ($row['on_behalf_of'] ?? ''),
            'requestId' => (string) ($row['request_id'] ?? ''),
            'route' => (string) ($row['route'] ?? ''),
            'channel' => (string) ($row['channel'] ?? ''),
        ];
    }

    /** @return callable(string): string */
    private function translator(): callable
    {
        try {
            $translate = Zend_Registry::isRegistered('translate') ? Zend_Registry::get('translate') : null;
        } catch (Throwable $e) {
            $translate = null;
        }
        return static function (string $text) use ($translate): string {
            return ($translate !== null && $text !== '') ? (string) $translate->_($text) : $text;
        };
    }

    /** "Changes scoring: A, B" translates the prefix and each label. */
    private function translateReason(string $reason): string
    {
        $t = $this->translator();
        $prefix = 'Changes scoring: ';
        if (str_starts_with($reason, $prefix)) {
            $labels = array_map($t, explode(', ', substr($reason, strlen($prefix))));
            return $t('Changes scoring') . ': ' . implode(', ', $labels);
        }
        return $t($reason);
    }

    /** Readable name for an entity_type value. */
    public static function entityTypeLabel(string $type): string
    {
        $labels = [
            'shipment' => 'Shipment',
            'participant' => 'Participant',
            'data-manager' => 'Data manager',
            'scheme' => 'Scheme',
            'testkit' => 'Test kit',
            'admin' => 'Admin',
            'config' => 'Settings',
            'enrollment-list' => 'Enrollment list',
            'distribution' => 'PT Survey',
            'certificate' => 'Certificate',
            'mail-template' => 'Mail template',
            'email' => 'Email',
        ];
        return $labels[$type] ?? self::humanize($type);
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
