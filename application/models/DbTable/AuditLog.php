<?php

class Application_Model_DbTable_AuditLog extends Zend_Db_Table_Abstract
{
    protected $_name = 'audit_log';
    protected $_primary = 'audit_log_id';

    public function addNewAuditLog($stateMent, $type = null)
    {
        if (!isset($stateMent) || $stateMent === '') {
            return null;
        }
        [$email, $role] = $this->resolveActor();
        return $this->insert(array(
            "statement" => $stateMent,
            "created_by" => $email,
            "created_by_role" => $role,
            "created_on" => new Zend_Db_Expr('now()'),
            "type" => $type,
            "ip_address" => $this->captureIp(),
            "user_agent" => $this->captureUserAgent()
        ));
    }

    private function resolveActor()
    {
        $admin = new Zend_Session_Namespace('administrators');
        if (!empty($admin->primary_email)) {
            return [$admin->primary_email, 'admin'];
        }
        $dm = new Zend_Session_Namespace('datamanagers');
        if (!empty($dm->primary_email)) {
            $role = ($dm->data_manager_type ?? null) === 'ptcc' ? 'ptcc' : 'datamanager';
            return [$dm->primary_email, $role];
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

    public function fetchAuditLogFeed($parameters)
    {
        $page = isset($parameters['page']) ? max(1, (int)$parameters['page']) : 1;
        $pageSize = isset($parameters['pageSize']) ? (int)$parameters['pageSize'] : 25;
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
        $startDate = isset($parameters['startDate']) ? trim($parameters['startDate']) : '';
        $endDate = isset($parameters['endDate']) ? trim($parameters['endDate']) : '';

        $db = $this->getAdapter();
        $select = $db->select()
            ->from(array('al' => $this->_name), array('statement', 'created_by', 'created_by_role', 'created_on', 'type', 'ip_address', 'user_agent'))
            ->joinLeft(
                array('sa' => 'system_admin'),
                'al.created_by = sa.primary_email',
                array(
                    'sa_first_name' => 'sa.first_name',
                    'sa_last_name' => 'sa.last_name'
                )
            )
            ->joinLeft(
                array('dm' => 'data_manager'),
                'al.created_by = dm.primary_email',
                array(
                    'dm_first_name' => 'dm.first_name',
                    'dm_last_name' => 'dm.last_name',
                    'dm_type' => 'dm.data_manager_type'
                )
            )
            ->joinLeft(
                array('p' => 'participant'),
                'al.created_by = p.email',
                array(
                    'p_first_name' => 'p.first_name',
                    'p_last_name' => 'p.last_name',
                    'p_lab_name' => 'p.lab_name',
                    'p_unique_id' => 'p.unique_identifier'
                )
            );

        if ($createdBy !== '') {
            $select->where('al.created_by = ?', $createdBy);
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
        $total = (int)$db->fetchOne($countSelect);

        $select->order('al.created_on DESC')->limit($pageSize, $offset);
        $rows = $db->fetchAll($select);

        $items = array();
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
                $name = $row['created_by'] ?? 'System';
            }
            $ts = strtotime($row['created_on']);
            $items[] = array(
                'action' => $row['statement'],
                'actionType' => $this->classifyAction($row['statement']),
                'userName' => $name,
                'userRole' => $role,
                'userEmail' => $row['created_by'],
                'userInitials' => $this->initials($name),
                'ipAddress' => $row['ip_address'] ?? '',
                'userAgent' => $row['user_agent'] ?? '',
                'context' => $row['type'] ? ucwords(str_replace('-', ' ', $row['type'])) : '',
                'contextSlug' => $row['type'],
                'timestamp' => $row['created_on'],
                'time' => $ts ? date('g:i a', $ts) : '',
                'dateKey' => $ts ? date('Y-m-d', $ts) : '',
                'dateLabel' => $ts ? date('D, d M Y', $ts) : ''
            );
        }

        return array(
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 1,
            'items' => $items
        );
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
