<?php

use PhpOffice\PhpSpreadsheet\Style\NumberFormat\Wizard\Number;

class Application_Model_DbTable_ResponseNotTestedReasons extends Zend_Db_Table_Abstract
{
    protected $_name = 'r_response_not_tested_reasons';
    protected $_primary = 'ntr_id';

    public function fetchAllSampleNotTeastedReasonsInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('ntr_reason', 'reason_code', 'ntr_test_type', 'collect_panel_receipt_date', 'ntr_status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;


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




        $sQuery = $this->getAdapter()->select()->from(array('a' => $this->_name));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
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

        $general = new Pt_Commons_General();
        $schemeDb = new Application_Model_DbTable_SchemeList();
        $schemeList = $schemeDb->getFullSchemeList(true);
        foreach ($rResult as $aRow) {
            $row = [];
            $schemeData = [];
            $scheme = json_decode($aRow['ntr_test_type'], true);
            foreach($scheme as $r){
                $schemeData[] = $schemeList[$r];
            }
            $row[] = ucwords($aRow['ntr_reason']);
            $row[] = $aRow['reason_code'];
            $row[] = implode(",", $schemeData);
            $row[] = ucwords($aRow['collect_panel_receipt_date']);
            $row[] = ucwords($aRow['ntr_status']);
            $row[] = '<a href="/admin/sample-not-tested-reasons/edit/53s5k85_8d/' . base64_encode($aRow['ntr_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function saveNotTestedReasonsDetails($params) {
        /* Check if the reason came as empty or not */
        if(!isset($params['ntReason']) || empty($params['ntReason'])){
            return false;
        }

        $data = array(
            'ntr_reason'                    => $params['ntReason'] ?? null,
            'ntr_test_type'                 => (isset($params['testType']) && !empty($params['testType']))? json_encode($params['testType'], true) : null,
            'collect_panel_receipt_date'    => $params['collectPanelReceiptDate'] ?? 'yes',
            'reason_code'                   => $params['ntReasonCode'] ?? null,
            'ntr_status'                    => $params['status'] ?? null
        );

        if(isset($params['ntrId']) && !empty($params['ntrId'])){
            return  $this->update($data, 'ntr_id = '. base64_decode($params['ntrId']));
        }
        return $this->insert($data);
    }

    public function fetchNotTestedReasonById($id){
        return $this->fetchRow($this->select()->where("ntr_id=?", $id));
    }
}
