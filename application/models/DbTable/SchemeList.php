<?php

class Application_Model_DbTable_SchemeList extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_list';
    protected $_primary = 'scheme_id';

    public function getAllSchemes()
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $schemes = [];
        if (isset($authNameSpace->activeScheme) && !empty($authNameSpace->activeScheme)) {
            foreach (explode(",", $authNameSpace->activeScheme) as $scheme) {
                $schemes[] = sprintf("'%s'", $scheme);;
            }
        }
        $sQuery = $this->getAdapter()->select()->from(array("s" => $this->_name), array('*'))->where("status='active'")->order("scheme_name");
        if (isset($authNameSpace->activeScheme) && !empty($authNameSpace->activeScheme)) {
            $sQuery = $sQuery->where("scheme_id IN(" . implode(",", $schemes) . ")");
        }
        return $this->getAdapter()->fetchAll($sQuery);
    }
    public function getFullSchemeList($toBind = false)
    {
        $result =  $this->fetchAll($this->select())->toArray();
        if($toBind){
            $response = [];
            foreach($result as $row){
                $response[$row['scheme_id']] = ucwords($row['scheme_name']);
            }
            return $response;
        }
        return $result;
    }

    public function countEnrollmentSchemes()
    {
        $result = array();;
        $sql = $this->fetchAll($this->select()->where("status='active'"));

        foreach ($sql as $scheme) {
            $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), array())
                ->join(array('e' => 'enrollments'), 'p.participant_id = e.participant_id', new Zend_Db_Expr("COUNT('e.participant_id')"))
                ->where("p.status='active'")
                ->where("e.scheme_id=?", $scheme['scheme_id']);
            $aResult = $this->getAdapter()->fetchCol($sQuery);
            $result[strtoupper($scheme['scheme_name'])] =  $aResult[0];
        }

        return $result;
    }

    public function fetchAllGenericTestInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('scheme_name', 'scheme_id', 'status');

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

        $sQuery = $this->getAdapter()->select()->from(array('s' => $this->_name))
            ->where('is_user_configured = "yes"')->group('scheme_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        // echo $sQuery;die;

        $rResult = $this->getAdapter()->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('*')"));
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
            $row[] = ucwords($aRow['scheme_name']);
            $row[] = $aRow['scheme_id'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/generic-test/edit/id/' . base64_encode($aRow['scheme_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function saveGenericTestDetails($params)
    {
        $data = array(
            'scheme_id' => $params['schemeCode'],
            'scheme_name' => $params['schemeName'],
            'is_user_configured' => 'yes',
            'user_test_config' => Zend_Json_Encoder::encode($params['genericConfig']),
            'status' => $params['status'],
        );
        if (isset($params['schemeId']) && !empty($params['schemeId'])) {
            $this->update($data, 'scheme_id = "' . base64_decode($params['schemeId']) . '"');
            $this->getAdapter()->delete('r_possibleresult', 'scheme_id = "' . base64_decode($params['schemeId']) . '"');
        } else {
            $this->insert($data);
        }
        if (isset($params['testType']) && !empty($params['testType'])) {
            $sortOrder = 1;
            foreach ($params['testType'] as $key => $test) {
                if (isset($params[$test]['expectedResult']) && isset($params[$test]['expectedResult'][$key][1]) && $test == 'qualitative' && count($params[$test]['expectedResult'][$key]) > 0) {
                    foreach ($params[$test]['expectedResult'][$key] as $ikey => $val) {
                        if (isset($val) && !empty($val)) {
                            $this->getAdapter()->insert('r_possibleresult', array(
                                'scheme_id'         => $params['schemeCode'],
                                'sub_scheme'        => $params['resultSubGroup'][$key],
                                'result_type'       => $test,
                                'response'          => $params[$test]['expectedResult'][$key][$ikey],
                                'result_code'       => $params[$test]['resultCode'][$key][$ikey],
                                'display_context'   => $params[$test]['displayContext'][$key][$ikey],
                                'sort_order'        => $params[$test]['sortOrder'][$key][$ikey],
                            ));
                        }
                        $sortOrder = $params[$test]['sortOrder'][$key][$ikey];
                    }
                } else if ($test == 'quantitative') {
                    $sortOrder++;
                    $this->getAdapter()->insert('r_possibleresult', array(
                        'scheme_id'         => $params['schemeCode'],
                        'sub_scheme'        => $params['resultSubGroup'][$key],
                        'result_type'       => $test,
                        'high_range'        => $params[$test]['highValue'][$key],
                        'threshold_range'   => $params[$test]['thresholdValue'][$key],
                        'low_range'         => $params[$test]['lowValue'][$key],
                        'sort_order'        => $sortOrder,
                    ));
                }
            }
        }
    }

    public function fetchGenericTest($id)
    {
        $response = [];
        if (!empty($id)) {
            $response['schemeResult'] = $this->fetchRow($this->select()->where('scheme_id = "' . $id . '"'))->toArray();
            $response['possibleResult'] = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from('r_possibleresult', array('*'))->where('scheme_id = "' . $id . '"')->order("sort_order asc"));
        }
        return $response;
    }

    public function checkUserConfig($id)
    {
        $scheme = $this->fetchRow($this->select()->where('scheme_id = "' . $id . '"'))->toArray();
        return $scheme['is_user_configured'];
    }
}
