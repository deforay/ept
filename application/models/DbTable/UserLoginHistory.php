<?php

use Application_Service_Common as Common;

class Application_Model_DbTable_UserLoginHistory extends Zend_Db_Table_Abstract
{

    protected $_name = 'user_login_history';
    protected $_primary = 'history_id';

    /**
     * Add a new login history record
     * @param array $data
     * @return int|false - Insert ID on success, false on failure
     */
    public function addLoginHistory($data)
    {
        try {
            // Set default timestamp if not provided
            if (!isset($data['login_attempted_datetime'])) {
                $data['login_attempted_datetime'] = Common::getDateTime();
            }

            // Set IP address if not provided
            if (!isset($data['ip_address'])) {
                $data['ip_address'] = $this->getClientIpAddress();
            }

            // Set browser and OS info if not provided
            if (!isset($data['browser']) || !isset($data['operating_system'])) {
                $userAgentInfo = $this->parseUserAgent();
                if (!isset($data['browser'])) {
                    $data['browser'] = $userAgentInfo['browser'];
                }
                if (!isset($data['operating_system'])) {
                    $data['operating_system'] = $userAgentInfo['os'];
                }
            }

            return $this->insert($data);
        } catch (Exception $e) {
            error_log('Error adding login history: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get login history for a specific data manager
     * @param string $userId
     * @param int $limit
     * @return array
     */
    public function getLoginHistoryByDmId($userId, $limit = 50)
    {
        try {
            $select = $this->select()
                ->where('user_id = ?', $userId)
                ->order('login_attempted_datetime DESC')
                ->limit($limit);

            return $this->fetchAll($select)->toArray();
        } catch (Exception $e) {
            error_log('Error fetching login history: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get login history for a specific login ID
     * @param string $loginId
     * @param int $limit
     * @return array
     */
    public function getLoginHistoryByLoginId($loginId, $limit = 50)
    {
        try {
            $select = $this->select()
                ->where('login_id = ?', $loginId)
                ->order('login_attempted_datetime DESC')
                ->limit($limit);

            return $this->fetchAll($select)->toArray();
        } catch (Exception $e) {
            error_log('Error fetching login history by login ID: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get login history for DataTables display
     * @param array $parameters - DataTables parameters
     * @return void - Outputs JSON directly
     */
    public function getAllLoginHistory($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables */
        $aColumns = array(
            'login_id',
            'login_attempted_datetime',
            'login_status',
            'ip_address',
            'browser',
            'operating_system',
            'first_name',
            'primary_email'
        );

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;


        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }


        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
                     " . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
        }

        /*
         * Filtering
         */
        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        // Join with data_managers table to get user details
        $sQuery = $this->getAdapter()->select()
            ->from(array('dlh' => $this->_name))
            ->joinLeft(
                array('dm' => 'data_managers'),
                'dlh.user_id = dm.dm_id',
                array('first_name', 'last_name', 'primary_email')
            );

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        } else {
            $sQuery = $sQuery->order('dlh.login_attempted_datetime DESC');
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['login_id'];
            $row[] = date('Y-m-d H:i:s', strtotime($aRow['login_attempted_datetime']));
            $row[] = $this->getStatusBadge($aRow['login_status']);
            $row[] = $aRow['ip_address'];
            $row[] = $aRow['browser'];
            $row[] = $aRow['operating_system'];
            $row[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
            $row[] = $aRow['primary_email'];
            $row[] = '<a href="/admin/login-history/view/' . $aRow['history_id'] . '" class="btn btn-info btn-xs" style="margin-right: 2px;"><i class="icon-eye"></i> View Details</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    /**
     * Get unique IP addresses for a user (security monitoring)
     * @param string $userId
     * @param int $days
     * @return array
     */
    public function getUniqueIpAddresses($userId, $days = 30)
    {
        try {
            $fromDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $select = $this->getAdapter()->select()
                ->from($this->_name, array('ip_address', 'COUNT(*) as login_count'))
                ->where('user_id = ?', $userId)
                ->where('login_attempted_datetime >= ?', $fromDate)
                ->group('ip_address')
                ->order('login_count DESC');

            return $this->getAdapter()->fetchAll($select);
        } catch (Exception $e) {
            error_log('Error fetching unique IP addresses: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get daily login statistics for dashboard/reports
     * @param int $days - Number of days to look back
     * @return array
     */
    public function getDailyLoginStats($days = 30)
    {
        try {
            $fromDate = date('Y-m-d', strtotime("-{$days} days"));

            $select = $this->getAdapter()->select()
                ->from($this->_name, array(
                    'login_date' => 'DATE(login_attempted_datetime)',
                    'total_attempts' => 'COUNT(*)',
                    'successful_logins' => 'SUM(CASE WHEN login_status = "success" THEN 1 ELSE 0 END)',
                    'failed_logins' => 'SUM(CASE WHEN login_status = "failed" THEN 1 ELSE 0 END)',
                    'unique_users' => 'COUNT(DISTINCT dm_id)'
                ))
                ->where('DATE(login_attempted_datetime) >= ?', $fromDate)
                ->group('DATE(login_attempted_datetime)')
                ->order('login_date DESC');

            return $this->getAdapter()->fetchAll($select);
        } catch (Exception $e) {
            error_log('Error fetching daily login stats: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Clean old login history records
     * @param int $days - Keep records for this many days
     * @return int - Number of records deleted
     */
    public function cleanOldRecords($days = 365)
    {
        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $where = $this->getAdapter()->quoteInto('login_attempted_datetime < ?', $cutoffDate);
            return $this->delete($where);
        } catch (Exception $e) {
            error_log('Error cleaning old records: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get client IP address
     * @return string
     */
    private function getClientIpAddress()
    {
        $ipKeys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    ) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Parse user agent to extract browser and OS information
     * @return array
     */
    private function parseUserAgent()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = 'Unknown Browser';
        $os = 'Unknown OS';

        // Browser detection
        if (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edg') !== false) {
            $browser = 'Microsoft Edge';
        } elseif (strpos($userAgent, 'Opera') !== false || strpos($userAgent, 'OPR') !== false) {
            $browser = 'Opera';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        }

        // OS detection
        if (strpos($userAgent, 'Windows NT 10.0') !== false) {
            $os = 'Windows 10';
        } elseif (strpos($userAgent, 'Windows NT 6.3') !== false) {
            $os = 'Windows 8.1';
        } elseif (strpos($userAgent, 'Windows NT 6.2') !== false) {
            $os = 'Windows 8';
        } elseif (strpos($userAgent, 'Windows NT 6.1') !== false) {
            $os = 'Windows 7';
        } elseif (strpos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgent, 'Mac OS X') !== false) {
            $os = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        }

        return array(
            'browser' => $browser,
            'os' => $os
        );
    }

    /**
     * Get status badge HTML for display
     * @param string $status
     * @return string
     */
    private function getStatusBadge($status)
    {
        switch (strtolower($status)) {
            case 'success':
                return '<span class="badge badge-success">Success</span>';
            case 'failed':
            case 'failure':
                return '<span class="badge badge-danger">Failed</span>';
            case 'banned':
            case 'locked':
                return '<span class="badge badge-warning">Banned/Locked</span>';
            default:
                return '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
        }
    }
}
