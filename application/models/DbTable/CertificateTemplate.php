<?php

class Application_Model_DbTable_CertificateTemplate extends Zend_Db_Table_Abstract
{
    protected $_name = 'certificate_template';
    protected $_primary = 'ct_id';

    public function saveCertificateTemplate($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        if (isset($params['sType']) && $params['sType'] != "") {
            return $this->insert(array(
                "scheme_type"               => $params['sType'],
                "participation_certificate" => $params['pCertificate'],
                "excellence_certificate"    => $params['eCertificate'],
                "created_by"                => $authNameSpace->primary_email,
                "updated_on"                => new Zend_Db_Expr('now()')
            ));
        }
    }

    public function fetchAllCertificateTemplateInGrid($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_type', 'participation_certificate', 'excellence_certificate', 'created_by');
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

        $sQuery = $this->getAdapter()->select()->from(array('ct' => $this->_name))
            ->joinLeft(array('sa' => 'system_admin'), 'ct.created_by=sa.primary_email', array('name' => new Zend_Db_Expr("CONCAT(sa.first_name,' ',sa.last_name, ' - ', sa.primary_email)")));

        if (isset($parameters['createdBy']) && $parameters['createdBy'] != "") {
            $sQuery = $sQuery->where("ct.created_by = ? ", $parameters['createdBy']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(ct.updated_on) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(ct.updated_on) <= ?", $parameters['endDate']);
        }

        if (isset($parameters['type']) && $parameters['type'] != "") {
            $sQuery = $sQuery->where("ct.scheme_type = ? ", $parameters['type']);
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
        $sQuery = $this->getAdapter()->select()->from(array("ct" => $this->_name), new Zend_Db_Expr("COUNT('" . $this->_primary . "')"));

        if (isset($parameters['createdBy']) && $parameters['createdBy'] != "") {
            $sQuery = $sQuery->where("ct.created_by = ? ", $parameters['createdBy']);
        }

        if (isset($parameters['startDate']) && $parameters['startDate'] != "" && isset($parameters['endDate']) && $parameters['endDate'] != "") {
            $sQuery = $sQuery->where("DATE(ct.updated_on) >= ?", $parameters['startDate']);
            $sQuery = $sQuery->where("DATE(ct.updated_on) <= ?", $parameters['endDate']);
        }

        if (isset($parameters['type']) && $parameters['type'] != "") {
            $sQuery = $sQuery->where("ct.type = ? ", $parameters['type']);
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
            $row = array();
            $row[] = ucwords($aRow['scheme_type']);
            $row[] = $aRow['participation_certificate'];
            $row[] = $aRow['excellence_certificate'];
            $row[] = ucwords($aRow['name']);
            $row[] = date("d-M-Y (g:i:s a)", strtotime($aRow['updated_on']));

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
