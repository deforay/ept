<?php

class Application_Model_DbTable_RCovid19GeneTypes extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_covid19_gene_types';
    protected $_primary = 'gene_id';

    public function getgeneTypeNameById($geneTypeId)
    {
        return $this->getAdapter()->fetchCol($this->getAdapter()->select()->from('r_covid19_gene_types', 'gene_name')->where("gene_id = '$geneTypeId'"));
    }

    public function getActiveGeneTypesNamesForScheme($scheme, $countryAdapted = false)
    {


        $sql = $this->getAdapter()->select()->from(array($this->_name), array('gene_id', 'gene_name'))->where("scheme_type = '$scheme'");
        $stmt = $this->getAdapter()->fetchAll($sql);

        foreach ($stmt as $type) {
            $retval[$type['gene_id']] = $type['gene_name'];
        }
        return $retval;
    }

    public function getActiveGeneTypesNamesForSchemeResponseWise($scheme, $countryAdapted = false)
    {


        $sql = $this->getAdapter()->select()->from(array($this->_name), array('gene_id', 'gene_name'))->where("scheme_type = '$scheme'");

        if ($countryAdapted) {
            $sql = $sql->where('country_adapted = 1');
        }
        return $this->getAdapter()->fetchAll($sql);
    }

    public function addgeneTypeDetails($params)
    {
        $data = array(
            'gene_name'            => $params['geneTypeName'],
            'scheme_type'          => $params['scheme'],
            'gene_status'          => $params['geneStatus'],
            'created_on'           => new Zend_Db_Expr('now()')
        );
        return $this->insert($data);
    }

    public function updategeneTypeDetails($params)
    {
        if (trim($params['genetypeId']) != "") {
            $data = array(
                'gene_name'        => $params['geneTypeName'],
                'scheme_type'      => $params['scheme'],
                'gene_status'          => $params['geneStatus']
            );
            return $this->update($data, "gene_id='" . $params['genetypeId'] . "'");
        }
    }

    public function updateGeneTypeStageDetails($params)
    {
        if (trim($params['geneTypeStage']) != "") {
            $this->update(array($params['geneTypeStage'] => '0'), array());
            if (isset($params["geneTypeData"]) && $params["geneTypeData"] != '' && count($params["geneTypeData"]) > 0) {
                foreach ($params["geneTypeData"] as $data) {
                    $this->update(array($params['geneTypeStage'] => '1'), "gene_id='" . $data . "'");
                }
            }
        }
    }

    public function checkGeneTypeId($genetypeId, $scheme)
    {
        $result = $this->fetchRow($this->select()->where("gene_id='" . $genetypeId . "'"));
        if ($result != "") {
            $commonService = new Application_Service_Common();
            $randomStr = $commonService->getRandomString(13);
            $genetypeId = "tt" . $randomStr;
            $this->checkgeneTypeId($genetypeId, $scheme);
        } else {
            return $genetypeId;
        }
    }

    public function getCovid19geneTypeDetails($genetypeId)
    {
        return $this->fetchRow($this->select()->where("gene_id=?", $genetypeId));
    }

    public function fetchAllCovid19GeneTypeInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('gene_name', 'scheme_name', 'gene_status', 'DATE_FORMAT(created_on,"%d-%b-%Y %T")');

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

        $sQuery = $this->getAdapter()->select()->from(array('t' => $this->_name))
            ->join(array('s' => 'scheme_list'), "t.scheme_type=s.scheme_id", 'scheme_name')
            ->group('gene_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['status']) && $parameters['status'] != "") {
            $sQuery = $sQuery->where("approval = ? ", $parameters['status']);
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
            $row = [];
            $createdDate = explode(" ", $aRow['created_on']);
            $row[] = ucwords($aRow['gene_name']);
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($createdDate[0]) . " " . $createdDate[1];
            $row[] = '<a href="/admin/covid19-gene-type/edit/53s5k85_8d/' . base64_encode($aRow['gene_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchAllCovid19GeneTypeResponseWise($scheme)
    {


        $sql = $this->getAdapter()->select()->from(array($this->_name), array('gene_id', 'gene_name'))->where("scheme_type = '$scheme'")->order('gene_name');
        $result = $this->getAdapter()->fetchAll($sql);
        $geneTypeOptions = [];
        foreach ($result as $geneType) {
            $geneTypeOptions[$geneType['gene_id']] = $geneType['gene_name'];
        }
        return $geneTypeOptions;
    }
}
