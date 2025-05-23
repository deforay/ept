<?php

class Application_Model_DbTable_Testkitnames extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_testkitnames';
    protected $_primary = 'TestKitName_ID';

    public function getTestKitNameById($testKitId)
    {
        return $this->getAdapter()->fetchCol($this->getAdapter()->select()->from('r_testkitnames', 'TestKit_Name')->where("TestKitName_ID = '$testKitId'"));
    }

    public function getActiveTestKitsNamesForScheme($scheme, $countryAdapted = false)
    {
        $sql = $this->getAdapter()->select()
            ->from(['t' => 'r_testkitnames'], ['TESTKITNAMEID' => 'TESTKITNAME_ID', 'TESTKITNAME' => 'TESTKIT_NAME'])
            ->joinLeft(['stm' => 'scheme_testkit_map'], 't.TestKitName_ID = stm.testkit_id', ['scheme_type', 'testkit_1', 'testkit_2', 'testkit_3'])
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

    public function addTestkitDetails($params)
    {
        $randomStr = Application_Service_Common::generateRandomString(13);
        $testkitId = "tk$randomStr";
        $tkId = $this->checkTestkitId($testkitId, $params['scheme']);
        $data = [
            'TestKitName_ID' => $tkId,
            'TestKit_Name' => $params['testKitName'],
            'TestKit_Name_Short' => $params['shortTestKitName'],
            'TestKit_Comments' => $params['comments'],
            'TestKit_Manufacturer' => $params['manufacturer'],
            'TestKit_ApprovalAgency' => $params['approvalAgency'],
            'source_reference' => $params['sourceReference'],
            'CountryAdapted' => $params['countryAdapted'],
            'attributes' => json_encode($params['attributes'], true),
            'Approval' => '1',
            'Created_On' => new Zend_Db_Expr('now()')
        ];
        if (isset($params['scheme']) && !empty($params['scheme'])) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $db->delete('scheme_testkit_map', 'scheme_type IN ("' . implode('", "', $params['scheme']) . '") AND testkit_id = "' . $params['testkitId'] . '"');
            foreach ($params['scheme'] as $scheme) {
                $mapData = [
                    'scheme_type' => $scheme,
                    'testkit_id' => $tkId,
                    'testkit_1' => (isset($params['testStages']) && in_array('testkit_1', $params['testStages'])) ? 1 : 0,
                    'testkit_2' => (isset($params['testStages']) && in_array('testkit_2', $params['testStages'])) ? 1 : 0,
                    'testkit_3' => (isset($params['testStages']) && in_array('testkit_3', $params['testStages'])) ? 1 : 0,
                ];
                $db->insert('scheme_testkit_map', $mapData);
            }
        }
        return $this->insert($data);
    }

    public function updateTestkitDetails($params)
    {
        if (trim($params['testkitId']) != "") {
            $data = [
                'TestKit_Name' => $params['testKitName'],
                'TestKit_Name_Short' => $params['shortTestKitName'],
                'TestKit_Comments' => $params['comments'],
                'TestKit_Manufacturer' => $params['manufacturer'],
                'TestKit_ApprovalAgency' => $params['approvalAgency'],
                'source_reference' => $params['sourceReference'],
                'CountryAdapted' => $params['countryAdapted'],
                'attributes' => json_encode($params['attributes'], true),
                'Approval' => $params['approved']
            ];
            if (isset($params['scheme']) && !empty($params['scheme'])) {
                $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $db->delete('scheme_testkit_map', 'scheme_type IN ("' . implode('", "', $params['scheme']) . '") AND testkit_id = "' . $params['testkitId'] . '"');
                foreach ($params['scheme'] as $scheme) {
                    $mapData = [
                        'scheme_type' => $scheme,
                        'testkit_id' => $params['testkitId'],
                        'testkit_1' => (isset($params['testStages']) && in_array('testkit_1', $params['testStages'])) ? 1 : 0,
                        'testkit_2' => (isset($params['testStages']) && in_array('testkit_2', $params['testStages'])) ? 1 : 0,
                        'testkit_3' => (isset($params['testStages']) && in_array('testkit_3', $params['testStages'])) ? 1 : 0,
                    ];
                    $db->insert('scheme_testkit_map', $mapData);
                }
            }
            return $this->update($data, "TestKitName_ID='" . $params['testkitId'] . "'");
        }
    }

    public function updateTestkitStageDetails($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (trim($params['testKitStage']) != "") {
            if (isset($params["testKitData"]) && $params["testKitData"] != '' && count($params["testKitData"]) > 0) {
                foreach ($params["testKitData"] as $data) {
                    if (in_array($params['testKitStage'], ['testkit_1', 'testkit_2', 'testkit_3'])) {
                        $db->update('scheme_testkit_map', array($params['testKitStage'] => '1'), "testkit_id='" . $data . "'");
                    } else {
                        $this->update(array('scheme_type' => $params['testKitStage']), "TestKitName_ID='" . $data . "'");
                    }
                }
            }
        }
    }

    public function checkTestkitId($testkitId, $scheme)
    {
        $result = $this->fetchRow($this->select()->where("TestKitName_ID='$testkitId'"));
        if ($result != "") {
            $randomStr = Application_Service_Common::generateRandomString(13);
            $testkitId = "tk$randomStr";
            $this->checkTestkitId($testkitId, $scheme);
        } else {
            return $testkitId;
        }
    }

    public function getAllTestKitsForAllSchemes($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['TestKit_Name', 'scheme_name', 'TestKit_Manufacturer', 'TestKit_ApprovalAgency', 'Approval', 'DATE_FORMAT(Created_On,"%d-%b-%Y %T")'];

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




        $sQuery = $this->getAdapter()->select()->from(array('a' => $this->_name))
            ->joinLeft(array('stm' => 'scheme_testkit_map'), "a.TestKitName_ID=stm.testkit_id", '')
            ->joinLeft(array('s' => 'scheme_list'), "stm.scheme_type=s.scheme_id", 'scheme_name')
            ->group('a.TestKitName_ID');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['status']) && $parameters['status'] != "" && $parameters['status'] != "pending") {
            $sQuery = $sQuery->where("Approval = ? ", $parameters['status']);
        }
        if (isset($parameters['status']) && $parameters['status'] == "pending") {
            $sQuery = $sQuery->where("testkit_status = 'pending' ");
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
        foreach ($rResult as $aRow) {
            $kitChkbox = "";
            $row = [];
            $approved = 'No';
            if (trim($aRow['Approval']) == 1) {
                $approved = 'Yes';
            }
            $createdDate = explode(" ", $aRow['Created_On']);
            if (isset($aRow['testkit_status']) && !empty($aRow['testkit_status']) && $aRow['testkit_status'] == 'pending') {
                $kitChkbox = '<input type="checkbox" class="checkTablePending" name="subchk[]" id="' . $aRow['TestKitName_ID'] . '"  value="' . $aRow['TestKitName_ID'] . '" onclick="addKit(\'' . $aRow['TestKitName_ID'] . '\',this);"  />';
            }

            // $row[] = $kitChkbox;
            $row[] = ucwords($aRow['TestKit_Name']);
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['TestKit_Manufacturer'];
            $row[] = $aRow['TestKit_ApprovalAgency'];
            $row[] = $approved;
            $row[] = Pt_Commons_General::humanReadableDateFormat($createdDate[0]) . " " . $createdDate[1];
            $row[] = '<a href="/admin/testkit/edit/53s5k85_8d/' . base64_encode($aRow['TestKitName_ID']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getDtsTestkitDetails($testkitId)
    {
        $sql = $this->getAdapter()->select()->from(array('t' => $this->_name))
            ->joinLeft(['stm' => 'scheme_testkit_map'], 't.TestKitName_ID = stm.testkit_id', ['scheme_type', 'testkit_1', 'testkit_2', 'testkit_3'])
            ->where("TestKitName_ID=?", $testkitId);
        return $this->getAdapter()->fetchRow($sql);
    }

    public function addTestkitInParticipant($oldName, $testkitName, $scheme, $testkit = "")
    {

        if (trim($testkitName) != "") {
            $randomStr = Application_Service_Common::generateRandomString(13);
            $testkitId = "tk" . $randomStr;
            $tkId = $this->checkTestkitId($testkitId, $scheme);
            $result = $this->fetchRow($this->select()->where("TestKit_Name='" . $testkitName . "'"));

            if ($result == "" && trim($oldName) == "") {
                $data = array(
                    'TestKitName_ID' => $tkId,
                    'TestKit_Name'  => trim($testkitName),
                    'COUNTRYADAPTED' => '1',
                    'Approval'      => '0',
                    'Created_On'    => new Zend_Db_Expr('now()')
                );
                $this->insert($data);
                if (isset($scheme) && !empty($scheme)) {
                    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                    $db->delete('scheme_testkit_map', 'scheme_type = "' . $scheme . '" AND testkit_id = "' . $tkId . '"');
                    $mapData = [
                        'scheme_type' => $scheme,
                        'testkit_id' => $tkId,
                        'testkit_1' => ($testkit == 1) ? '1' : '0',
                        'testkit_2' => ($testkit == 2) ? '1' : '0',
                        'testkit_3' => ($testkit == 3) ? '1' : '0'
                    ];
                    $db->insert('scheme_testkit_map', $mapData);
                }
                return $tkId;
            } else {
                $result = $this->fetchRow($this->select()->where("TestKit_Name='" . $oldName . "'"));
                if ($result != "") {
                    $data = array(
                        'TestKit_Name' => trim($testkitName)
                    );
                    $saveId = $this->update($data, "TestKitName_ID='" . $result['TestKitName_ID'] . "'");
                    return $result['TestKitName_ID'];
                }
            }
        }
    }

    public function addTestkitInParticipantByAPI($oldName, $testkitName, $scheme, $kit = 0)
    {

        if (trim($testkitName) != "") {
            $randomStr = Application_Service_Common::generateRandomString(13);
            $testkitId = "tk" . $randomStr;
            $tkId = $this->checkTestkitId($testkitId, $scheme);
            $result = $this->fetchRow($this->select()->where("TestKit_Name='" . $testkitName . "'"));

            if ($result == "" && trim($oldName) == "") {
                $data = array(
                    'TestKitName_ID' => $tkId,
                    'TestKit_Name'  => trim($testkitName),
                    'Approval'      => '0',
                    'COUNTRYADAPTED' => '1',
                    'Created_On'    => new Zend_Db_Expr('now()')
                );
                $this->insert($data);
                if (isset($scheme) && !empty($scheme)) {
                    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                    $db->delete('scheme_testkit_map', 'scheme_type IN ("' . implode('", "', $scheme) . '") AND testkit_id = "' . $tkId . '"');
                    $mapData = [
                        'scheme_type' => $scheme,
                        'testkit_id' => $tkId,
                        'testkit_1' => ($kit == 1) ? '1' : '0',
                        'testkit_2' => ($kit == 2) ? '1' : '0',
                        'testkit_3' => ($kit == 3) ? '1' : '0'
                    ];
                    $db->insert('scheme_testkit_map', $mapData);
                }
                return $tkId;
            } else {
                $result = $this->fetchRow($this->select()->where("TestKit_Name='" . $oldName . "'"));
                if ($result != "") {
                    $data = array(
                        'TestKit_Name' => trim($testkitName),
                        'TestKit_Name'  => trim($testkitName),
                        'COUNTRYADAPTED' => '1'
                    );
                    $this->update($data, "TestKitName_ID='" . $result['TestKitName_ID'] . "'");
                    if (isset($scheme) && !empty($scheme)) {
                        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                        $db->delete('scheme_testkit_map', 'scheme_type IN ("' . implode('", "', $scheme) . '") AND testkit_id = "' . $result['TestKitName_ID'] . '"');
                        $mapData = [
                            'scheme_type' => $scheme,
                            'testkit_id' => $result['TestKitName_ID'],
                            'testkit_1' => ($kit == 1) ? '1' : '0',
                            'testkit_2' => ($kit == 2) ? '1' : '0',
                            'testkit_3' => ($kit == 3) ? '1' : '0'
                        ];
                        $db->insert('scheme_testkit_map', $mapData);
                    }
                    return $result['TestKitName_ID'];
                }
            }
        }
    }

    public function fetchGivenKitApprovalStatus($kit)
    {
        return $this->fetchRow('TestKitName_ID = "' . $kit . '" OR ' . $this->_primary . ' = "' . $kit . '"');
    }

    public function getAllTestKitList($scheme = null, $countryAdapted = false, $isArray = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(
                ['t' => 'r_testkitnames'],
                [
                    'TESTKITNAMEID' => 'TESTKITNAME_ID',
                    'TESTKITNAME' => 'TESTKIT_NAME',
                    'attributes'
                ]
            )
            ->joinLeft(['stm' => 'scheme_testkit_map'], 't.TestKitName_ID = stm.testkit_id', ['scheme_type', 'testkit_1', 'testkit_2', 'testkit_3'])
            ->order("TESTKITNAME ASC");
        if (isset($scheme) && !empty($scheme)) {
            $sql = $sql->where("scheme_type = '$scheme'");
        }
        if ($countryAdapted) {
            $sql = $sql->where('COUNTRYADAPTED = 1');
        }
        $stmt = $db->fetchAll($sql);
        $response = [];
        if ($isArray) {
            $retval = [];
            foreach ($stmt as $kitName) {
                $retval[$kitName['TESTKITNAMEID']] = $kitName['TESTKITNAME'];
            }
            $response = $retval;
        } else {
            $response = $stmt;
        }
        return $response;
    }
}
