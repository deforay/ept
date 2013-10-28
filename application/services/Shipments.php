<?php

class Application_Service_Shipments {
	
	public function getAllShipments($parameters){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $aColumns = array("SCHEME","shipment_code","DATE_FORMAT(shipment_date,'%d-%b-%Y')", 'distribution_code', 'distribution_date', 'no_of_samples');
        $orderColumns = array("SCHEME","shipment_code","shipment_date", 'distribution_code', 'distribution_date', 'no_of_samples');

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
		
		
		
	$sQuery = $db->select()->from(array('s'=>'shipment'),array('s.shipment_id','s.shipment_date','s.shipment_code','s.number_of_samples'))
				->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'))
				->join(array('sl'=>'scheme_list'),'sl.scheme_id=s.scheme_type',array('SCHEME'=>'sl.scheme_name'));		

	if(isset($parameters['scheme']) && $parameters['scheme'] !=""){
		$sQuery = $sQuery->where("s.scheme_type = ?",$parameters['scheme']);
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
		
		$sQuery = $db->select()->from('shipment', new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
		
		if(isset($parameters['scheme']) && $parameters['scheme'] !=""){
			$sQuery = $sQuery->where("scheme_type = ?",$parameters['scheme']);
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
	    $row[] = $aRow['shipment_code'];
	    $row[] = $aRow['SCHEME'];	    
	    $row[] = $aRow['distribution_code'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
	    $row[] = $aRow['number_of_samples'];
            $row[] = '<a class="btn btn-primary btn-xs" href="/admin/shipment/ship-it/sid/'.base64_encode($aRow['shipment_id']).'"><span><i class="icon-share-alt"></i> Ship</span></a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
	
	}

	public function updateEidResults($params){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$eidShipmentDb = new Application_Model_DbTable_ShipmentEid();
			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
			$data = array(
						  "shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
						  "shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
						  "sample_rehydration_date"=>Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
						  "extraction_assay"=>$params['extractionAssay'],
						  "detection_assay"=>$params['detectionAssay'],
						  "supervisor_approval"=>$params['supervisorApproval'],
						  "participant_supervisor"=>$params['participantSupervisor'],
						  "user_comment"=>$params['userComments'],
						  "updated_by_user"=>$authNameSpace->dm_id,
						  "updated_on_user"=>new Zend_Db_Expr('now()')
						  );
			
			$noOfRowsAffected = $eidShipmentDb->updateShipmentEid($data,$params['hdshipId'], $params['hdparticipantId']);
			
			$eidResponseDb = new Application_Model_DbTable_ResponseEid();
			$eidResponseDb->updateResults($params);
			$db->commit();
		 
		} catch (Exception $e) {
			// If any of the queries failed and threw an exception,
			// we want to roll back the whole transaction, reversing
			// changes made in the transaction, even those that succeeded.
			// Thus all changes are committed together, or none are.
			$db->rollBack();
			error_log($e->getMessage());
			error_log($e->getTraceAsString());
		}
		
	}
	public function updateVlResults($params){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$vlShipmentDb = new Application_Model_DbTable_ShipmentVl();
			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
			$data = array(
						  "shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
						  "shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
						  "sample_rehydration_date"=>Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
						  "vl_assay"=>$params['vlAssay'],
						  "assay_lot_number"=>$params['assayLotNumber'],
						  "assay_expiration_date"=>Pt_Commons_General::dateFormat($params['assayExpirationDate']),
						  "specimen_volume"=>$params['specimenVolume'],
						  "supervisor_approval"=>$params['supervisorApproval'],
						  "participant_supervisor"=>$params['participantSupervisor'],
						  "user_comment"=>$params['userComments'],
						  "updated_by_user"=>$authNameSpace->dm_id,
						  "updated_on_user"=>new Zend_Db_Expr('now()')
						  );
			
			$noOfRowsAffected = $vlShipmentDb->updateShipmentVl($data,$params['hdshipId'], $params['hdparticipantId']);
			
			$eidResponseDb = new Application_Model_DbTable_ResponseVl();
			$eidResponseDb->updateResults($params);
			$db->commit();
		 
		} catch (Exception $e) {
			// If any of the queries failed and threw an exception,
			// we want to roll back the whole transaction, reversing
			// changes made in the transaction, even those that succeeded.
			// Thus all changes are committed together, or none are.
			$db->rollBack();
			error_log($e->getMessage());
		}
		
	}
		
	public function addShipment($params){
		//Zend_Debug::dump($params);die;
		$scheme = $params['schemeId'];
		$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
		$db = new Application_Model_DbTable_Shipments();
		$distroService = new Application_Service_Distribution();
		$distro = $distroService->getDistribution($params['distribution']);
		
		
		$data = array(
			'shipment_code'=>$params['shipmentCode'],
			'distribution_id'=>$params['distribution'],
			'scheme_type'=>$scheme,
			'shipment_date'=>$distro['distribution_date'],
			'number_of_samples'=>count($params['sampleName']),
			'lastdate_response'=>Pt_Commons_General::dateFormat($params['lastDate']),
			'created_on_admin'=>new Zend_Db_Expr('now()'),
			'created_by_admin'=>$authNameSpace->primary_email
			);
		$lastId = $db->insert($data);
		
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
		$size = count($params['sampleName']);
		if($params['schemeId'] == 'eid'){
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_eid',array(
									'shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['possibleResults'][$i],
									'reference_hiv_ct_od'=>$params['hivCtOd'][$i],
									'reference_ic_qs'=>$params['icQs'][$i],
									)
								  );
			}

		}
		else if($params['schemeId'] == 'vl'){
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_vl',array(
									'shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_viral_load'=>$params['vlResult'][$i]
									)
								  );
			}

		}
		else if($params['schemeId'] == 'dts'){
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_dts',array(
									'shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['possibleResults'][$i]
									)
								  );
			}

		}
	}
	
	public function getShipment($sid){
	    $db = new Application_Model_DbTable_Shipments();		
	    return $db->fetchRow($db->select()->where("shipment_id = ?",$sid));
	}
	
	public function shipItNow($params){
		$db = new Application_Model_DbTable_ShipmentParticipantMap();
		return $db->shipItNow($params);
	}

}

