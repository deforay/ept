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

    public function fetchAllAuditLogDetailsByGrid($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */


        $aColumns = array('statement', 'created_by', 'created_on', 'type');
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
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
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
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

        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('al' => $this->_name))
            ->joinLeft(array('sa' => 'system_admin'), 'al.created_by=sa.primary_email', array('name' => new Zend_Db_Expr("CONCAT(sa.first_name,' ',sa.last_name, ' - ', sa.primary_email)")));

        if (isset($parameters['createdBy']) && $parameters['createdBy'] != "") {
            $sQuery = $sQuery->where("al.created_by = ? ", $parameters['createdBy']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $common = new Application_Service_Common();
            $sQuery = $sQuery->where("DATE(al.created_on) >= ?", $common->isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where("DATE(al.created_on) <= ?", $common->isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['type']) && $parameters['type'] != "") {
            $sQuery = $sQuery->where("al.type = ? ", $parameters['type']);
        }

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo ($sQuery);die;
        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array("al" => $this->_name), new Zend_Db_Expr("COUNT('" . $this->_primary . "')"));

        if (isset($parameters['createdBy']) && $parameters['createdBy'] != "") {
            $sQuery = $sQuery->where("al.created_by = ? ", $parameters['createdBy']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(al.created_on) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(al.created_on) <= ?", $parameters['endDate']);
        }

        if (isset($parameters['type']) && $parameters['type'] != "") {
            $sQuery = $sQuery->where("al.type = ? ", $parameters['type']);
        }
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
            $row[] = $aRow['statement'];
            $row[] = $aRow['name'];
            $row[] = date("d-M-Y (g:i:s a)", strtotime($aRow['created_on']));
            $row[] = ucwords($aRow['type']);

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
