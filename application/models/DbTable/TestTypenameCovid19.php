<?php

class Application_Model_DbTable_TestTypenameCovid19 extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_test_type_covid19';
    protected $_primary = 'test_type_id';

    public function getTestTypeNameById($testPlatformId)
    {
        return $this->getAdapter()->fetchCol($this->getAdapter()->select()->from('r_test_type_covid19', 'test_type_name')
            ->where("test_type_id = '$testPlatformId'"));
    }

    public function getActiveTestTypesNamesForScheme($scheme, $countryAdapted = false)
    {


        $sql = $this->getAdapter()->select()->from(array($this->_name), array('test_type_id', 'test_type_name', 'test_type_1', 'test_type_2', 'test_type_3'))->where("scheme_type = '$scheme'");

        if ($countryAdapted) {
            $sql = $sql->where('country_adapted = 1');
        }

        $sql = $sql->order('test_type_name');

        $stmt = $this->getAdapter()->fetchAll($sql);

        foreach ($stmt as $type) {
            $retval[$type['test_type_id']] = $type['test_type_name'];
        }
        return $retval;
    }

    public function getActiveTestTypesNamesForSchemeResponseWise($scheme, $countryAdapted = false)
    {


        $sql = $this->getAdapter()->select()->from(array($this->_name), array('test_type_id', 'test_type_name', 'test_type_1', 'test_type_2', 'test_type_3'))->where("scheme_type = '$scheme'");

        if ($countryAdapted) {
            $sql = $sql->where('country_adapted = 1');
        }
        $sql = $sql->order('test_type_name');
        return $this->getAdapter()->fetchAll($sql);
    }

    public function addTestTypeDetails($params)
    {
        $randomStr = Application_Service_Common::generateRandomString(13);
        $testtypeId = "tt" . $randomStr;
        $tkId = $this->checkTestTypeId($testtypeId, $params['scheme']);

        $data = array(
            'test_type_id' => $tkId,
            'test_type_name' => $params['testPlatformName'],
            'test_type_short_name' => $params['shortTestTypeName'],
            'test_type_comments' => $params['comments'],
            'test_type_manufacturer' => $params['manufacturer'],
            'scheme_type' => $params['scheme'],
            'test_type_approval_agency' => $params['approvalAgency'],
            'source_reference' => $params['sourceReference'],
            'country_adapted' => $params['countryAdapted'],
            'approval' => '1',
            'created_on' => new Zend_Db_Expr('now()')
        );
        return $this->insert($data);
    }

    public function updateTestTypeDetails($params)
    {
        if (trim($params['testtypeId']) != "") {
            $data = array(
                'test_type_name' => $params['testPlatformName'],
                'test_type_short_name' => $params['shortTestTypeName'],
                'test_type_comments' => $params['comments'],
                'test_type_manufacturer' => $params['manufacturer'],
                'scheme_type' => $params['scheme'],
                'test_type_approval_agency' => $params['approvalAgency'],
                'source_reference' => $params['sourceReference'],
                'country_adapted' => $params['countryAdapted'],
                'approval' => $params['approved']
            );
            return $this->update($data, "test_type_id='" . $params['testtypeId'] . "'");
        }
    }

    public function updateTestTypeStageDetails($params)
    {
        if (trim($params['testPlatformStage']) != "") {
            $this->update(array($params['testPlatformStage'] => '0'), array());
            if (isset($params["testPlatformData"]) && $params["testPlatformData"] != '' && count($params["testPlatformData"]) > 0) {
                foreach ($params["testPlatformData"] as $data) {
                    $this->update(array($params['testPlatformStage'] => '1'), "test_type_id='" . $data . "'");
                }
            }
        }
    }

    public function checkTestTypeId($testtypeId, $scheme)
    {
        $result = $this->fetchRow($this->select()->where("test_type_id='" . $testtypeId . "'"));
        if ($result != "") {
            $randomStr = Application_Service_Common::generateRandomString(13);
            $testtypeId = "tt" . $randomStr;
            $this->checkTestTypeId($testtypeId, $scheme);
        } else {
            return $testtypeId;
        }
    }

    public function getAllTestTypesForAllSchemes($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('test_type_name', 'scheme_name', 'test_type_manufacturer', 'test_type_approval_agency', 'approval', 'DATE_FORMAT(created_on,"%d-%b-%Y %T")');

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




        $sQuery = $this->getAdapter()->select()->from(array('t' => $this->_name))
            ->join(array('s' => 'scheme_list'), "t.scheme_type=s.scheme_id", 'scheme_name');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['status']) && $parameters['status'] != "") {
            $sQuery = $sQuery->where("approval = ? ", $parameters['status']);
        }

        if (!empty($sOrder)) {
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
            $row = [];
            $approved = 'No';
            if (trim($aRow['approval']) == 1) {
                $approved = 'Yes';
            }
            $createdDate = explode(" ", $aRow['created_on']);
            $row[] = ucwords($aRow['test_type_name']);
            $row[] = $aRow['scheme_name'];
            $row[] = $aRow['test_type_manufacturer'];
            $row[] = $aRow['test_type_approval_agency'];
            $row[] = $approved;
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($createdDate[0]) . " " . $createdDate[1];
            $row[] = '<a href="/admin/test-platform/edit/53s5k85_8d/' . base64_encode($aRow['test_type_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getCovid19TestTypeDetails($testtypeId)
    {
        return $this->fetchRow($this->select()->where("test_type_id=?", $testtypeId));
    }

    public function addTestTypeInParticipant($oldName, $testtypeName, $scheme, $testtype = "")
    {

        if (trim($testtypeName) != "") {
            $randomStr = Application_Service_Common::generateRandomString(13);
            $testtypeId = "tt" . $randomStr;
            $tkId = $this->checkTestTypeId($testtypeId, $scheme);
            $result = $this->fetchRow($this->select()->where("test_type_name='" . $testtypeName . "'"));

            if ($result == "" && trim($oldName) == "") {
                $data = array(
                    'test_type_id' => $tkId,
                    'scheme_type' => $scheme,
                    'test_type_name' => trim($testtypeName),
                    'approval' => '0',
                    'test_type_1' => ($testtype == 1 && $testtype != "") ? '1' : '0',
                    'test_type_2' => ($testtype == 2 && $testtype != "") ? '1' : '0',
                    'test_type_3' => ($testtype == 3 && $testtype != "") ? '1' : '0',
                    'created_on' => new Zend_Db_Expr('now()')
                );
                $saveId = $this->insert($data);
                return $tkId;
            } else {
                $result = $this->fetchRow($this->select()->where("test_type_name='" . $oldName . "'"));
                if ($result != "") {
                    $data = array(
                        'test_type_name' => trim($testtypeName)
                    );
                    $saveId = $this->update($data, "test_type_id='" . $result['test_type_id'] . "'");
                    return $result['test_type_id'];
                }
            }
        }
    }

    public function addTestTypeInParticipantByAPI($oldName, $testtypeName, $scheme, $type = 0)
    {

        if (trim($testtypeName) != "") {
            $randomStr = Application_Service_Common::generateRandomString(13);
            $testtypeId = "tt" . $randomStr;
            $tkId = $this->checkTestTypeId($testtypeId, $scheme);
            $result = $this->fetchRow($this->select()->where("test_type_name='" . $testtypeName . "'"));

            if ($result == "" && trim($oldName) == "") {
                $data = array(
                    'test_type_id' => $tkId,
                    'scheme_type' => $scheme,
                    'test_type_name' => trim($testtypeName),
                    'approval' => '0',
                    'country_adapted' => '1',
                    'test_type_1' => ($type == 1) ? '1' : '0',
                    'test_type_2' => ($type == 2) ? '1' : '0',
                    'test_type_3' => ($type == 3) ? '1' : '0',
                    'created_on' => new Zend_Db_Expr('now()')
                );
                $saveId = $this->insert($data);
                return $tkId;
            } else {
                $result = $this->fetchRow($this->select()->where("test_type_name='" . $oldName . "'"));
                if ($result != "") {
                    $data = array(
                        'test_type_name' => trim($testtypeName),
                        'scheme_type' => $scheme,
                        'test_type_name' => trim($testtypeName),
                        'country_adapted' => '1',
                        'test_type_1' => ($type == 1) ? '1' : '0',
                        'test_type_2' => ($type == 2) ? '1' : '0',
                        'test_type_3' => ($type == 3) ? '1' : '0'
                    );
                    $saveId = $this->update($data, "test_type_id='" . $result['test_type_id'] . "'");
                    return $result['test_type_id'];
                }
            }
        }
    }
}
