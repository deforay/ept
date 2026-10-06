<?php

class Application_Model_DbTable_ResponseNotTestedReasons extends Zend_Db_Table_Abstract
{
    protected $_name = 'r_response_not_tested_reasons';
    protected $_primary = 'ntr_id';

    public function fetchAllSampleNotTeastedReasonsInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['ntr_reason', 'reason_code', 'ntr_test_type', 'collect_panel_receipt_date', 'ntr_status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
				 	' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['a' => $this->_name]);

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }
        // Same match as Application_Service_Schemes::getNotTestedReasons(), so
        // filtering by a test lists exactly what that test's response form offers.
        if (!empty($parameters['scheme'])) {
            $sQuery = $sQuery->where("JSON_SEARCH(`ntr_test_type`, 'all', ?) IS NOT NULL", (string) $parameters['scheme']);
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
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        $schemeDb = new Application_Model_DbTable_SchemeList();
        $schemeList = $schemeDb->getFullSchemeList(true);
        // Full scheme names ("Dried Tube Specimen - HIV Viral Load") made this
        // column unreadable, so each scheme shows as a short chip with the full
        // name on hover. Built-in schemes get the short labels the Scheme Config
        // sidebar uses; custom tests already have short ids (HBV, SYP).
        $shortLabels = [
            'dts'     => 'HIV Serology',
            'dbs'     => 'DBS',
            'vl'      => 'VL',
            'eid'     => 'EID',
            'tb'      => 'TB',
            'covid19' => 'SARS-CoV-2',
            'recency' => 'Recency',
        ];
        foreach ($rResult as $aRow) {
            $row = [];
            $chips = [];
            $scheme = (array) Pt_Commons_JsonUtility::safeDecode($aRow['ntr_test_type']);
            foreach ($scheme as $r) {
                $chips[] = '<span class="scheme-chip" title="' . htmlspecialchars($schemeList[$r] ?? $r, ENT_QUOTES) . '">'
                    . htmlspecialchars($shortLabels[$r] ?? $r, ENT_QUOTES) . '</span>';
            }
            $row[] = ucwords($aRow['ntr_reason']);
            $row[] = $aRow['reason_code'];
            $row[] = $chips ? '<div class="scheme-chips">' . implode('', $chips) . '</div>' : '';
            $row[] = ucwords($aRow['collect_panel_receipt_date']);
            $row[] = ucwords($aRow['ntr_status']);
            $row[] = '<a href="/admin/sample-not-tested-reasons/edit/53s5k85_8d/' . base64_encode($aRow['ntr_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function saveNotTestedReasonsDetails($params)
    {
        /* Check if the reason came as empty or not */
        if (!isset($params['ntReason']) || empty($params['ntReason'])) {
            return false;
        }

        $data = [
            'ntr_reason'                    => $params['ntReason'] ?? null,
            'ntr_test_type'                 => (isset($params['testType']) && !empty($params['testType'])) ? json_encode($params['testType'], true) : null,
            'collect_panel_receipt_date'    => $params['collectPanelReceiptDate'] ?? 'yes',
            'reason_code'                   => $params['ntReasonCode'] ?? null,
            'ntr_status'                    => $params['status'] ?? null,
        ];

        if (isset($params['ntrId']) && !empty($params['ntrId'])) {
            return  $this->update($data, 'ntr_id = ' . base64_decode($params['ntrId']));
        }
        return $this->insert($data);
    }

    public function fetchNotTestedReasonById($id)
    {
        return $this->fetchRow($this->select()->where('ntr_id=?', $id));
    }
}
