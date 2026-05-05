<?php

class Application_Model_DbTable_AuditLog extends Zend_Db_Table_Abstract
{
    protected $_name = 'audit_log';
    protected $_primary = 'audit_log_id';

    public function addNewAuditLog($stateMent, $type = null)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($stateMent) && $stateMent != "") {
            return $this->insert(array(
                "statement" => $stateMent,
                "created_by" => $authNameSpace->primary_email,
                "created_on" => new Zend_Db_Expr('now()'),
                "type" => $type
            ));
        }
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
            ->from(array('al' => $this->_name), array('statement', 'created_by', 'created_on', 'type'))
            ->joinLeft(
                array('sa' => 'system_admin'),
                'al.created_by = sa.primary_email',
                array(
                    'first_name' => 'sa.first_name',
                    'last_name' => 'sa.last_name'
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
                "al.statement LIKE $q OR al.created_by LIKE $q OR al.type LIKE $q OR sa.first_name LIKE $q OR sa.last_name LIKE $q OR CONCAT_WS(' ', sa.first_name, sa.last_name) LIKE $q"
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
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if ($name === '') {
                $name = $row['created_by'];
            }
            $ts = strtotime($row['created_on']);
            $items[] = array(
                'action' => $row['statement'],
                'actionType' => $this->classifyAction($row['statement']),
                'userName' => $name,
                'userEmail' => $row['created_by'],
                'userInitials' => $this->initials($name),
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
