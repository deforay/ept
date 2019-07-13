<?php

class Application_Model_DbTable_TestkitnameDts extends Zend_Db_Table_Abstract {

    protected $_name = 'r_testkitname_dts';
    protected $_primary = 'TestKitName_ID';
	
	public function getTestKitNameById($testKitId){
		return $this->getAdapter()->fetchCol($this->getAdapter()->select()->from('r_testkitname_dts', 'TestKit_Name')->where("TestKitName_ID = '$testKitId'"));
	}
	
	public function getActiveTestKitsNamesForScheme($scheme,$countryAdapted=false){
		
		
        $sql = $this->getAdapter()->select()->from(array('r_testkitname_dts'), array('TESTKITNAMEID' => 'TESTKITNAME_ID', 'TESTKITNAME' => 'TESTKIT_NAME'))
                            ->where("scheme_type = '$scheme'");

        if ($countryAdapted) {
            $sql = $sql->where('COUNTRYADAPTED = 1');
        }

        $stmt = $this->getAdapter()->fetchAll($sql);

        foreach ($stmt as $kitName) {
            $retval[$kitName['TESTKITNAMEID']] = $kitName['TESTKITNAME'];
        }
        return $retval;
			
	}

    public function addTestkitDetails($params) {
        $commonService = new Application_Service_Common();
        $randomStr = $commonService->getRandomString(13);
        $testkitId = "tk" . $randomStr;
        $tkId = $this->checkTestkitId($testkitId,$params['scheme']);

        $data = array(
            'TestKitName_ID' => $tkId,
            'TestKit_Name' => $params['testKitName'],
            'TestKit_Name_Short' => $params['shortTestKitName'],
            'TestKit_Comments' => $params['comments'],
            'TestKit_Manufacturer' => $params['manufacturer'],
            'scheme_type' => $params['scheme'],
            'TestKit_ApprovalAgency' => $params['approvalAgency'],
            'source_reference' => $params['sourceReference'],
            'CountryAdapted' => $params['countryAdapted'],
            'Approval' => '1',
            'Created_On' => new Zend_Db_Expr('now()'));
        return $this->insert($data);
    }

    public function updateTestkitDetails($params) {
        if (trim($params['testkitId']) != "") {
            $data = array(
                'TestKit_Name' => $params['testKitName'],
                'TestKit_Name_Short' => $params['shortTestKitName'],
                'TestKit_Comments' => $params['comments'],
                'TestKit_Manufacturer' => $params['manufacturer'],
				'scheme_type' => $params['scheme'],
                'TestKit_ApprovalAgency' => $params['approvalAgency'],
                'source_reference' => $params['sourceReference'],
                'CountryAdapted' => $params['countryAdapted'],
                'Approval' => $params['approved']
            );
            return $this->update($data, "TestKitName_ID='" . $params['testkitId'] . "'");
        }
    }

    public function updateTestkitStageDetails($params) {
        if (trim($params['testKitStage']) != "") {
            $this->update(array($params['testKitStage'] => '0'), array());
            if (isset($params["testKitData"]) && $params["testKitData"] != '' && count($params["testKitData"]) > 0) {
                foreach ($params["testKitData"] as $data) {
                    $this->update(array($params['testKitStage'] => '1'), "TestKitName_ID='" . $data . "'");
                }
            }
        }
    }

    public function checkTestkitId($testkitId,$scheme) {
        $result = $this->fetchRow($this->select()->where("TestKitName_ID='" . $testkitId . "'"));
        if ($result != "") {
            $commonService = new Application_Service_Common();
            $randomStr = $commonService->getRandomString(13);
            $testkitId = "tk" . $randomStr;
            $this->checkTestkitId($testkitId,$scheme);
        } else {
            return $testkitId;
        }
    }

    public function getAllTestKitsForAllSchemes($parameters) {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('TestKit_Name','scheme_name' ,'TestKit_Manufacturer', 'TestKit_ApprovalAgency', 'Approval', 'DATE_FORMAT(Created_On,"%d-%b-%Y %T")');

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


        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('a' => $this->_name))
								->join(array('s'=>'scheme_list'),"a.scheme_type=s.scheme_id",'scheme_name');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['status']) && $parameters['status'] != "") {
            $sQuery = $sQuery->where("Approval = ? ", $parameters['status']);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //echo $sQuery;

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
        foreach ($rResult as $aRow) {
            $row = array();
            $approved = 'No';
            if (trim($aRow['Approval']) == 1) {
                $approved = 'Yes';
            }
            $createdDate = explode(" ", $aRow['Created_On']);
            $row[] = ucwords($aRow['TestKit_Name']);
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['TestKit_Manufacturer'];
            $row[] = $aRow['TestKit_ApprovalAgency'];
            $row[] = $approved;
            $row[] = $general->humanDateFormat($createdDate[0]) . " " . $createdDate[1];
            $row[] = '<a href="/admin/testkit/edit/53s5k85_8d/' . base64_encode($aRow['TestKitName_ID']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getDtsTestkitDetails($testkitId) {
        $result = $this->fetchRow($this->select()->where("TestKitName_ID=?", $testkitId));
        return $result;
    }

    public function addTestkitInParticipant($oldName, $testkitName,$scheme) {
        if (trim($testkitName) != "") {
            $commonService = new Application_Service_Common();
            $randomStr = $commonService->getRandomString(13);
            $testkitId = "tk" . $randomStr;
            $tkId = $this->checkTestkitId($testkitId,$scheme);
            $result = $this->fetchRow($this->select()->where("TestKit_Name=?", $testkitName));

            if ($result == "" && trim($oldName) == "") {
                $data = array(
                    'TestKitName_ID' => $tkId,
                    'TestKit_Name' => trim($testkitName),
                    'Approval' => '0',
                    'Created_On' => new Zend_Db_Expr('now()')
                );
                $this->insert($data);
                return $tkId;
            } else {
                $result = $this->fetchRow($this->select()->where("TestKit_Name=?", $oldName));
                if ($result != "") {
                    $data = array(
                        'TestKit_Name' => trim($testkitName)
                    );
                    $this->update($data, "TestKitName_ID='" . $result['TestKitName_ID'] . "'");
                    return $result['TestKitName_ID'];
                }
            }
        }
    }

}
