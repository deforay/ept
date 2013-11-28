<?php

class Application_Service_Evaluation {
	
	public function getAllDistributions($parameters)
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
				     ->joinLeft(array('s'=>'shipment'),'s.distribution_id=d.distribution_id',array('shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")))
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

        //die($sQuery);

        $rResult = $dbAdapter->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $dbAdapter->select()->from('distributions', new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))->where("status='shipped'");
        $aResultTotal = $dbAdapter->fetchCol($sQuery);
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
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipments(\''.base64_encode($aRow['distribution_id']).'\')"><span><i class="icon-search"></i> View</span></a>';	    
            
            

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
	
	public function getShipments($distributionId){
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s'=>'shipment'))
							->join(array('d'=>'distributions'),'d.distribution_id=s.distribution_id')
							->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('participant_count' => new Zend_Db_Expr('count("participant_id")')))
							->where("s.distribution_id = ?",$distributionId)
							->group('s.shipment_id');
			  
	    return $db->fetchAll($sql);
	}
	
	public function getShipmentToEvaluate($shipmentId){
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s'=>'shipment'))
							->join(array('d'=>'distributions'),'d.distribution_id=s.distribution_id')
							->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id')
							->join(array('p'=>'participant'),'p.participant_id=sp.participant_id')
							->where("s.shipment_id = ?",$shipmentId)
							->where("substring(sp.evaluation_status,4,1) != '0'");
			  
	    $shipmentResult = $db->fetchAll($sql);
		
		
		$schemeService = new Application_Service_Schemes();
		
		if($shipmentResult[0]['scheme_type'] == 'eid'){
			$counter = 0;
			foreach($shipmentResult as $shipment){
				$results = $schemeService->getEidSamples($shipmentId,$shipment['participant_id']);
				$totalScore = 0;
				$maxScore = 0;
				$mandatoryResult = "";
				$scoreResult = "";
				foreach($results as $result){
					if(isset($result['reported_result']) && $result['reported_result'] !=null){						
						if($result['reference_result'] == $result['reported_result']){
							$totalScore += $result['sample_score'];
						}		
					}
					$maxScore  += $result['sample_score'];
					if($result['mandatory'] == 1){
						if((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null) || ($result['reference_result'] != $result['reported_result'])){
							$mandatoryResult = 'Fail';	
						}
					}
				}
				
				if($totalScore != $maxScore){
					$scoreResult = 'Fail';
				}else{
					$scoreResult = 'Pass';
				}
				
				if($scoreResult == 'Fail' || $mandatoryResult == 'Fail'){
					$finalResult = 'Fail';
				}else{
					$finalResult = 'Pass';
				}
				$shipmentResult[$counter]['shipment_score'] = $totalScore;
				$shipmentResult[$counter]['max_score'] = $maxScore;
				$shipmentResult[$counter]['final_result'] = $finalResult;
				// let us update the total score in DB
				$db->update('shipment_participant_map',array('shipment_score' => $totalScore,'final_result'=>$finalResult), "map_id = ".$shipment['map_id']);
				
			}
		}
		
		//Zend_Debug::dump($shipmentResult);
		return $shipmentResult;
		
		
	}
	
	public function viewEvaluation($shipmentId,$participantId,$scheme){
	//    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
	//	$sql = $db->select()->from(array('s'=>'shipment'))
	//						->join(array('d'=>'distributions'),'d.distribution_id=s.distribution_id')
	//						->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id')
	//						->join(array('p'=>'participant'),'p.participant_id=sp.participant_id')
	//						->where("sp.participant_id = ?",$participantId)
	//						->where("sp.shipment_id = ?",$shipmentId);
	//		  
	//    return $db->fetchAll($sql);
	

            $participantService = new Application_Service_Participants();
			$schemeService = new Application_Service_Schemes();
			$shipmentService = new Application_Service_Shipments();
			
			
            $participantData = $participantService->getParticipantDetails($participantId);
			$shipmentData = $schemeService->getShipmentData($shipmentId,$participantId);
			
			if($scheme == 'eid'){
				$possibleResults = $schemeService->getPossibleResults('eid');
				$results = $schemeService->getEidSamples($shipmentId,$participantId);
			} else if($scheme == 'vl'){
				$possibleResults = "";
				$results = $schemeService->getVlSamples($shipmentId,$participantId);
			} else if($scheme == 'dts'){
				$possibleResults = $schemeService->getPossibleResults('dts');
				$results = $schemeService->getDtsSamples($shipmentId,$participantId);
			}

			return array('participant'=>$participantData,
			             'shipment' => $shipmentData ,
						 'possibleResults' => $possibleResults,
						 'results' => $results );
	
	}
	
	public function updateShipmentResults($params){
		 $db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$authNameSpace = new Zend_Session_Namespace('administrators');
		$admin = $authNameSpace->primary_email;		 
		 $size = count($params['sampleId']);
		 if($params['scheme'] == 'eid'){
			for($i=0;$i<$size;$i++){
			   $db->update('response_result_eid',array('reported_result' => $params['reported'][$i], 'updated_by'=>$admin , 'updated_on' => new Zend_Db_Expr('now()')), "shipment_map_id = ".$params['smid']. " AND sample_id = ".$params['sampleId'][$i]);
			}
		 }
	}
}

