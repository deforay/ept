<?php

class Application_Service_Shipments {
	
	public function getAllShipments($parameters){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $aColumns = array("SCHEME","shipment_code","DATE_FORMAT(shipment_date,'%d-%b-%Y')", 'distribution_code', 'distibution_date', 'no_of_samples');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "shipment_date";


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
		
		
		// Some long queries coming up !
		

		if(isset($parameters['scheme']) && $parameters['scheme'] !=""){
			$sQuery = $db->select()->from(array('s'=>'shipment_'.strtolower($parameters['scheme'])),array('s.shipment_date','s.shipment_code','SCHEME'=>new Zend_Db_Expr("'DTS'"),'s.number_of_samples'))
						->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'));			
		}else{
			
			$dtsSql = $db->select()->from(array('s'=>'shipment_dts'),array('s.shipment_date','s.shipment_code','SCHEME'=>new Zend_Db_Expr("'DTS'"),'s.number_of_samples'))
								->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'));
								
			$eidSql = $db->select()->from(array('s'=>'shipment_eid'),array('s.shipment_date','s.shipment_code','SCHEME'=>new Zend_Db_Expr("'EID'"),'s.number_of_samples'))
								->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'));
								
			$vlSql = $db->select()->from(array('s'=>'shipment_vl'),array('s.shipment_date','s.shipment_code','SCHEME'=>new Zend_Db_Expr("'VL'"),'s.number_of_samples'))
								->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'));			
			$sQuery = $db->select()->union(array($dtsSql,$eidSql,$vlSql));	
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

        //error_log($sQuery);

        $rResult = $db->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $db->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
		if(isset($parameters['scheme']) && $parameters['scheme'] !=""){
			$sQuery = $db->select()->from('shipment_'.strtolower($parameters['scheme']), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
		}else{
			
			$dtsSql = $db->select()->from(array('s'=>'shipment_dts'), array('total'=>new Zend_Db_Expr("COUNT('dts_shipment_id')")));
			$eidSql = $db->select()->from(array('s'=>'shipment_eid'), array('total'=>new Zend_Db_Expr("COUNT('eid_shipment_id')")));
			$vlSql = $db->select()->from(array('s'=>'shipment_vl'), array('total'=>new Zend_Db_Expr("COUNT('vl_shipment_id')")));
			$subSql = $db->select()->union(array($dtsSql,$eidSql,$vlSql),Zend_Db_Select::SQL_UNION_ALL);
			
			$sQuery = $db->select()->from(array('t'=>$subSql),array('totalRows'=>new Zend_Db_expr("SUM(total)")));
			
		}
		
        $aResultTotal = $db->fetchCol($sQuery);
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

		//$aColumns = array("SCHEME","shipment_code","DATE_FORMAT(shipment_date,'%d-%b-%Y')", 'distribution_code', 'distibution_date', 'no_of_samples');
        foreach ($rResult as $aRow) {
            $row = array();
			$row[] = $aRow['SCHEME'];
			$row[] = $aRow['shipment_code'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['shipment_date']);			
			$row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
			$row[] = $aRow['number_of_samples'];
            $row[] = '';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
	
	
	
	
	
	
		
	
	
	}
	


}

