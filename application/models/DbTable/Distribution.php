<?php

class Application_Model_DbTable_Distribution extends Zend_Db_Table_Abstract
{

    protected $_name = 'distributions';
    protected $_primary = 'distribution_id';

    
    public function getAllDistributions($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('d.distribution_id',"DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code' ,'d.status');
        $orderColumns = array('d.distribution_id','distribution_date', 'distribution_code', 's.shipment_code' ,'d.status');

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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
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
                    if($aColumns[$i] == "" || $aColumns[$i] == null){
                        continue;
                    }
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

        $sQuery = $this->getAdapter()->select()->from(array('d' => $this->_name))
				     ->joinLeft(array('s'=>'shipment'),'s.distribution_id=d.distribution_id',array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")))
				     ->group('d.distribution_id');

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //die($sQuery);

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

        
        $shipmentDb = new Application_Model_DbTable_Shipments();

        foreach ($rResult as $aRow) {
            
            $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);
            
            $row = array();
            $row[] = '<a class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal" href="/admin/distributions/view-shipment/id/'.$aRow['distribution_id'].'"><span><i class="icon-search"></i></span></a>';
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = '<a href="/admin/shipment/index/searchString/'.$aRow['distribution_code'].'">'.$aRow['distribution_code'].'</a>';
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
	    $edit='<a class="btn btn-primary btn-xs" href="/admin/distributions/edit/d8s5_8d/'.base64_encode($aRow['distribution_id']).'"><span><i class="icon-pencil"></i> Edit</span></a>';
            if(isset($aRow['status']) && $aRow['status'] == 'configured'){
                $row[] = $edit.' '.'<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="shipDistribution(\''.base64_encode($aRow['distribution_id']).'\')"><span><i class="icon-ambulance"></i> Ship Now</span></a>';	    
            }else if(isset($aRow['status']) && $aRow['status'] == 'shipped'){
                $row[] = '<a class="btn btn-primary btn-xs" href="/admin/distributions/edit/d8s5_8d/'.base64_encode($aRow['distribution_id']).'/5h8pp3t/shipped"><span><i class="icon-pencil"></i> Edit</span></a>'.' '.'<a class="btn btn-primary btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ambulance"></i> Shipped</span></a>';	    
            }else{
                $row[] = $edit.' '.'<a class="btn btn-primary btn-xs" href="/admin/shipment/index/did/'.base64_encode($aRow['distribution_id']).'"><span><i class="icon-plus"></i> Add Scheme</span></a>';
            }
            

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    
    public function addDistribution($params){
	$authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array('distribution_code'=>$params['distributionCode'],
                      'distribution_date'=> Pt_Commons_General::dateFormat($params['distributionDate']),
                      'status' => 'created',
		      'created_by' => $authNameSpace->admin_id,
                      'created_on' => new Zend_Db_Expr('now()'));
        return $this->insert($data);
    }
    
    public function shipDistribution($params){

    }
    
    public function getDistributionDates(){
        return $this->getAdapter()->fetchCol($this->select()->from($this->_name,new Zend_Db_Expr("DATE_FORMAT(distribution_date,'%d-%b-%Y')")));
    }
    
    public function getDistribution($did){
        return $this->fetchRow("distribution_id = ".$did);
    }
    
    public function updateDistribution($params){
	$authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array('distribution_code'=>$params['distributionCode'],
                      'distribution_date'=> Pt_Commons_General::dateFormat($params['distributionDate']),
		      'updated_by' => $authNameSpace->admin_id,
                      'updated_on' => new Zend_Db_Expr('now()'));
        return $this->update($data,"distribution_id=".base64_decode($params['distributionId']));
    }
    public function getUnshippedDistributions(){
        return $this->fetchAll($this->select()->where("status != 'shipped'"));
    }
    
    public function updateDistributionStatus($distributionId,$status){
        if(isset($status) && $status != null && $status != ""){
            return $this->update(array('status'=>$status),"distribution_id=".$distributionId);
        }else{
            return 0;
        }
    }
    
    public function getAllDistributionReports($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
	
        $aColumns = array("DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code' ,'d.status');
        $orderColumns = array('distribution_date', 'distribution_code', 's.shipment_code' ,'d.status');
	
        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'distribution_id';
	
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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
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
                    if($aColumns[$i] == "" || $aColumns[$i] == null){
                        continue;
                    }
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
		
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array('d' => 'distributions'))
				->joinLeft(array('s'=>'shipment'),'s.distribution_id=d.distribution_id',array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')"),'not_finalized_count' => new Zend_Db_Expr("SUM(IF(s.status!='finalized',1,0))")))
				->where("d.status='shipped'")
				->group('d.distribution_id');
				
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
	
        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }
	
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
		
		$sQuery = $dbAdapter->select()->from(array('temp' => $sQuery))->where("not_finalized_count>0");
		
		//die($sQuery);
        $rResult = $dbAdapter->fetchAll($sQuery);
	
        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);
	
        /* Total data set length */
        //$sQuery = $dbAdapter->select()->from('distributions', new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))->where("status='shipped'");
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);
		
        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
        $shipmentDb = new Application_Model_DbTable_Shipments();
        foreach ($rResult as $aRow) {
            
            $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);
            
            $row = array();
	    $row['DT_RowId']="dist".$aRow['distribution_id'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipmentInReports(\''.($aRow['distribution_id']).'\')"><span><i class="icon-search"></i> View</span></a>';	    
            
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function getAllDistributionStatusDetails(){
        return $this->fetchAll($this->select());
    }
}

