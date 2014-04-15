<?php

class Application_Service_Shipments {
	
	public function getAllShipments($parameters) {
		/* Array of database columns which should be read and sent back to DataTables. Use a space where
		 * you want to insert a non-database field (for example a counter or static image)
		*/
		
		//$aColumns = array('project_name','project_code','e.employee_name','client_name','architect_name','project_value','building_type_name','DATE_FORMAT(p.project_date,"%d-%b-%Y")','DATE_FORMAT(p.deadline,"%d-%b-%Y")','refered_by','emp.employee_name');
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
	
		$aColumns = array("sl.scheme_name","shipment_code", 'distribution_code', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'number_of_samples','s.status');
		$orderColumns = array("sl.scheme_name","shipment_code", 'distribution_code', 'distribution_date', 'number_of_samples','s.status');
	
		
		/* Indexed column (used for fast and accurate table cardinality) */
		 $sIndexColumn = "shipment_id";
		
		
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
				$sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' OR ";
			    } else {
				$sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search ) . "%' ";
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
		
		$sQuery=$db->select()->from(array('s'=>'shipment'))
			->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'))
			->join(array('sl'=>'scheme_list'),'sl.scheme_id=s.scheme_type',array('SCHEME'=>'sl.scheme_name'));
			       
		if(isset($parameters['scheme']) && $parameters['scheme'] !=""){
			$sQuery = $sQuery->where("s.scheme_type = ?",$parameters['scheme']);
		}
		
		if(isset($parameters['distribution']) && $parameters['distribution'] !="" && $parameters['distribution'] !=0){
			$sQuery = $sQuery->where("s.distribution_id = ?",$parameters['distribution']);
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
		$sQuery = $db->select()->from('shipment', new Zend_Db_Expr("COUNT('shipment_id')"));
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
        
		foreach ($rResult as $aRow) {
		    
		    $row = array();
		    if($aRow['status'] == 'ready'){
					$btn = "btn-success";
				}else if($aRow['status'] == 'pending'){
					$btn = "btn-danger";
				}else {
					$btn = "btn-primary";
				}
				
				//$row[] = $aRow['shipment_code'];
				$row[] = '<a href="/admin/shipment/view-enrollments/id/'.base64_encode($aRow['shipment_id']).'/shipmentCode/'.$aRow['shipment_code'].'" target="_blank">'.$aRow['shipment_code'].'</a>';
				$row[] = $aRow['SCHEME'];	    
				$row[] = $aRow['distribution_code'];
				$row[] = Pt_Commons_General::humanDateFormat($aRow['distribution_date']);
				$row[] = $aRow['number_of_samples'];
				$row[] = ucfirst($aRow['status']);
				if($aRow['status'] != null && $aRow['status'] != "" && $aRow['status'] != 'shipped' && $aRow['status'] != 'evaluated'  && $aRow['status'] != 'closed'){
					$row[] ='<a class="btn '.$btn.' btn-xs" href="/admin/shipment/ship-it/sid/'.base64_encode($aRow['shipment_id']).'"><span><i class="icon-user"></i> Enroll</span></a>'
							.'&nbsp;<a class="btn btn-primary btn-xs" href="/admin/shipment/edit/sid/'.base64_encode($aRow['shipment_id']).'"><span><i class="icon-edit"></i> Edit</span></a>'
						.'&nbsp;<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="removeShipment(\''.base64_encode($aRow['shipment_id']).'\')"><span><i class="icon-remove"></i> Delete</span></a>';
				}
				else if($aRow['status'] != null && $aRow['status'] != "" && $aRow['status'] == 'shipped' && $aRow['status'] != 'closed'){
					$row[] = '<a class="btn btn-primary btn-xs" href="/admin/shipment/edit/sid/'.base64_encode($aRow['shipment_id']).'"><span><i class="icon-edit"></i> Edit</span></a>';					
				}
				else{
					$row[] = '<a class="btn btn-primary btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ambulance"></i> Shipped</span></a>';	
				}
				
		    
		    $output['aaData'][] = $row;
		}
        
		echo json_encode($output);
	}
    
	

	public function updateEidResults($params){
		
		if(!$this->isShipmentEditable($params['shipmentId'],$params['participantId'])){
			return false;
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
			$authNameSpace = new Zend_Session_Namespace('datamanagers');
			$attributes = array("sample_rehydration_date"=>Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
						  "extraction_assay"=>$params['extractionAssay'],
						  "detection_assay"=>$params['detectionAssay']);
			$attributes = json_encode($attributes);
			$data = array(
					"shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
					"shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
					"shipment_test_report_date"=>new Zend_Db_Expr('now()'),
					"attributes" => $attributes,
					"supervisor_approval"=>$params['supervisorApproval'],
					"participant_supervisor"=>$params['participantSupervisor'],
					"user_comment"=>$params['userComments'],
					"updated_by_user"=>$authNameSpace->dm_id,
					"updated_on_user"=>new Zend_Db_Expr('now()')
					);
			
			$noOfRowsAffected = $shipmentParticipantDb->updateShipment($data,$params['smid'],$params['hdLastDate']);
			
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
	public function updateDtsResults($params){
		
		if(!$this->isShipmentEditable($params['shipmentId'],$params['participantId'])){
			return false;
		}		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			
			$shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
			$authNameSpace = new Zend_Session_Namespace('datamanagers');
			$attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
			$attributes["algorithm"] = $params['algorithm'];
			$attributes = json_encode($attributes);
			$data = array(
					"shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
					"shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
					"attributes" => $attributes,
					"shipment_test_report_date"=>new Zend_Db_Expr('now()'),
					"supervisor_approval"=>$params['supervisorApproval'],
					"participant_supervisor"=>$params['participantSupervisor'],
					"user_comment"=>$params['userComments'],
					"updated_by_user"=>$authNameSpace->dm_id,
					"updated_on_user"=>new Zend_Db_Expr('now()')
					);
			
			$noOfRowsAffected = $shipmentParticipantDb->updateShipment($data,$params['smid'],$params['hdLastDate']);
			
			$dtsResponseDb = new Application_Model_DbTable_ResponseDts();
			$dtsResponseDb->updateResults($params);
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
	public function updateDbsResults($params){
		
		if(!$this->isShipmentEditable($params['shipmentId'],$params['participantId'])){
			return false;
		}		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
			$authNameSpace = new Zend_Session_Namespace('datamanagers');
			$attributes["sample_rehydration_date"] = Pt_Commons_General::dateFormat($params['sampleRehydrationDate']);
			$attributes = json_encode($attributes);
			$data = array(
						"shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
						"shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
						"attributes" => $attributes,
						"shipment_test_report_date"=>new Zend_Db_Expr('now()'),
						"supervisor_approval"=>$params['supervisorApproval'],
						"participant_supervisor"=>$params['participantSupervisor'],
						"user_comment"=>$params['userComments'],
						"updated_by_user"=>$authNameSpace->dm_id,
						"updated_on_user"=>new Zend_Db_Expr('now()')
						);
			
			$noOfRowsAffected = $shipmentParticipantDb->updateShipment($data,$params['smid'],$params['hdLastDate']);
			
			$dbsResponseDb = new Application_Model_DbTable_ResponseDbs();
			$dbsResponseDb->updateResults($params);
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
		
		if(!$this->isShipmentEditable($params['shipmentId'],$params['participantId'])){
			return false;
		}
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$db->beginTransaction();
		try {
			$shipmentParticipantDb = new Application_Model_DbTable_ShipmentParticipantMap();
			$authNameSpace = new Zend_Session_Namespace('datamanagers');
			$attributes = array( "sample_rehydration_date"=>Pt_Commons_General::dateFormat($params['sampleRehydrationDate']),
						  "vl_assay"=>$params['vlAssay'],
						  "assay_lot_number"=>$params['assayLotNumber'],
						  "assay_expiration_date"=>Pt_Commons_General::dateFormat($params['assayExpirationDate']),
						  "specimen_volume"=>$params['specimenVolume']);
			$attributes = json_encode($attributes);
			$data = array(
						  "shipment_receipt_date"=>Pt_Commons_General::dateFormat($params['receiptDate']),
						  "shipment_test_date"=>Pt_Commons_General::dateFormat($params['testDate']),
						  "attributes" => $attributes,
						  "shipment_test_report_date"=>new Zend_Db_Expr('now()'),
						  "supervisor_approval"=>$params['supervisorApproval'],
						  "participant_supervisor"=>$params['participantSupervisor'],
						  "user_comment"=>$params['userComments'],
						  "updated_by_user"=>$authNameSpace->dm_id,
						  "updated_on_user"=>new Zend_Db_Expr('now()')
						  );
			
			$noOfRowsAffected = $shipmentParticipantDb->updateShipment($data,$params['smid'],$params['hdLastDate']);
			
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
		$authNameSpace = new Zend_Session_Namespace('administrators');
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
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
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
									'reference_result'=>$params['vlResult'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
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
									'reference_result'=>$params['possibleResults'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
									)
								);
				
				// <------ Insert reference_dts_eia table
				if(isset($params['eia'][$i+1]['eia'])){
					$eiaSize=sizeof($params['eia'][$i+1]['eia']);
					for($e=0;$e<$eiaSize;$e++){
						if(isset($params['eia'][$i+1]['eia'][$e]) && trim($params['eia'][$i+1]['eia'][$e])!=""){
							$expDate='';
							if(trim($params['eia'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['eia'][$i+1]['expiry'][$e]);
							}
							
							$dbAdapter->insert('reference_dts_eia',
								array('shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'eia'=>$params['eia'][$i+1]['eia'][$e],
									'lot'=>$params['eia'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'od'=>$params['eia'][$i+1]['od'][$e],
									'cutoff'=>$params['eia'][$i+1]['cutoff'][$e]
								)
							);
							
						}
					}
				}
				//------------->
				
				// <------ Insert reference_dts_wb table
				if(isset($params['wb'][$i+1]['wb'])){
					$wbSize=sizeof($params['wb'][$i+1]['wb']);
					for($e=0;$e<$wbSize;$e++){
						if(isset($params['wb'][$i+1]['wb'][$e]) && trim($params['wb'][$i+1]['wb'][$e])!=""){
							$expDate='';
							if(trim($params['wb'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['wb'][$i+1]['expiry'][$e]);
							}
							$dbAdapter->insert('reference_dts_wb',
								array('shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'wb'=>$params['wb'][$i+1]['wb'][$e],
									'lot'=>$params['wb'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'160'=>$params['wb'][$i+1]['160'][$e],
									'120'=>$params['wb'][$i+1]['120'][$e],
									'66'=>$params['wb'][$i+1]['66'][$e],
									'55'=>$params['wb'][$i+1]['55'][$e],
									'51'=>$params['wb'][$i+1]['51'][$e],
									'41'=>$params['wb'][$i+1]['41'][$e],
									'31'=>$params['wb'][$i+1]['31'][$e],
									'24'=>$params['wb'][$i+1]['24'][$e],
									'17'=>$params['wb'][$i+1]['17'][$e]
								)
							);
							
						}
						
					}
				}
				// ------------------>
			}

		}
		else if($params['schemeId'] == 'dbs'){
			
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_dbs',array(
									'shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['possibleResults'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
									)
								  );
				// <------ Insert reference_dbs_eia table
				if(isset($params['eia'][$i+1]['eia'])){
					$eiaSize=sizeof($params['eia'][$i+1]['eia']);
					for($e=0;$e<$eiaSize;$e++){
						if(isset($params['eia'][$i+1]['eia'][$e]) && trim($params['eia'][$i+1]['eia'][$e])!=""){
							$expDate='';
							if(trim($params['eia'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['eia'][$i+1]['expiry'][$e]);
							}
							
							$dbAdapter->insert('reference_dbs_eia',
								array('shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'eia'=>$params['eia'][$i+1]['eia'][$e],
									'lot'=>$params['eia'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'od'=>$params['eia'][$i+1]['od'][$e],
									'cutoff'=>$params['eia'][$i+1]['cutoff'][$e]
								)
							);
							
						}
					}
				}
				//------------->
				
				// <------ Insert reference_dbs_wb table
				if(isset($params['wb'][$i+1]['wb'])){
					$wbSize=sizeof($params['wb'][$i+1]['wb']);
					for($e=0;$e<$wbSize;$e++){
						if(isset($params['wb'][$i+1]['wb'][$e]) && trim($params['wb'][$i+1]['wb'][$e])!=""){
							$expDate='';
							if(trim($params['wb'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['wb'][$i+1]['expiry'][$e]);
							}
							$dbAdapter->insert('reference_dbs_wb',
								array('shipment_id'=>$lastId,
									'sample_id'=>($i+1),
									'wb'=>$params['wb'][$i+1]['wb'][$e],
									'lot'=>$params['wb'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'160'=>$params['wb'][$i+1]['160'][$e],
									'120'=>$params['wb'][$i+1]['120'][$e],
									'66'=>$params['wb'][$i+1]['66'][$e],
									'55'=>$params['wb'][$i+1]['55'][$e],
									'51'=>$params['wb'][$i+1]['51'][$e],
									'41'=>$params['wb'][$i+1]['41'][$e],
									'31'=>$params['wb'][$i+1]['31'][$e],
									'24'=>$params['wb'][$i+1]['24'][$e],
									'17'=>$params['wb'][$i+1]['17'][$e]
								)
							);
							
						}
						
					}
				}
				// ------------------>
			}

		}
		
		$distroService->updateDistributionStatus($params['distribution'],'pending');
	}
	
	public function getShipment($sid){
	    $db = new Application_Model_DbTable_Shipments();		
	    return $db->fetchRow($db->select()->where("shipment_id = ?",$sid));
	}
	
	public function shipItNow($params){
		$db = new Application_Model_DbTable_ShipmentParticipantMap();
		return $db->shipItNow($params);
	}
	
	public function removeShipment($sid){
		try{
			
			$shipmentDb = new Application_Model_DbTable_Shipments();
			$row = $shipmentDb->fetchRow('shipment_id='.$sid);
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			if($row['scheme_type'] == 'dts'){
				$db->delete('reference_dts_eia','shipment_id='.$sid);
				$db->delete('reference_dts_wb','shipment_id='.$sid);
				$db->delete("reference_result_dts",'shipment_id='.$sid);
			}else if($row['scheme_type'] == 'dbs'){
				$db->delete('reference_dbs_eia','shipment_id='.$sid);
				$db->delete('reference_dbs_wb','shipment_id='.$sid);
				$db->delete("reference_result_dbs",'shipment_id='.$sid);
			}else if($row['scheme_type'] == 'vl'){
				$db->delete("reference_result_vl",'shipment_id='.$sid);	
			}else if($row['scheme_type'] == 'eid'){
				$db->delete("reference_result_eid",'shipment_id='.$sid);	
			}
			
			$shipmentParticipantMap = new Application_Model_DbTable_ShipmentParticipantMap();			
			$shipmentParticipantMap->delete('shipment_id='.$sid);		
			
			
			
			$shipmentDb->delete('shipment_id='.$sid);
			
			return "Shipment deleted.";
		}catch(Exception $e){
			return($e->getMessage());
			return "c Unable to delete. Please try again later or contact system admin for help";
		}

	}
	
	public function isShipmentEditable($shipmentId,$participantId){
		$spMap = new Application_Model_DbTable_ShipmentParticipantMap();
		return $spMap->isShipmentEditable($shipmentId,$participantId);
	}
	
	
	public function getShipmentForEdit($sid){

		$db = Zend_Db_Table_Abstract::getDefaultAdapter();		
		$shipment = $db->fetchRow($db->select()->from(array('s'=>'shipment'))
									 ->join(array('d'=>'distributions'),'d.distribution_id = s.distribution_id',array('distribution_code','distribution_date'))
									 ->where("s.shipment_id = ?",$sid));
		
	
		$eia = '';
		$wb = '';
		if($shipment['scheme_type'] == 'dts'){			
			$reference = $db->fetchAll($db->select()->from(array('s'=>'shipment'))
						->join(array('ref'=>'reference_result_dts'),'ref.shipment_id=s.shipment_id')
						->where("s.shipment_id = ?",$sid));
			$schemeService = new Application_Service_Schemes();
			$possibleResults = $schemeService->getPossibleResults('dts');
			
			$eia = $db->fetchAll($db->select()->from('reference_dts_eia')->where("shipment_id = ?",$sid));
			$wb = $db->fetchAll($db->select()->from('reference_dts_wb')->where("shipment_id = ?",$sid));
			
		}else if($shipment['scheme_type'] == 'dbs'){
			
			$reference = $db->fetchAll($db->select()->from(array('s'=>'shipment'))
					->join(array('ref'=>'reference_result_dbs'),'ref.shipment_id=s.shipment_id')
					->where("s.shipment_id = ?",$sid));
			$schemeService = new Application_Service_Schemes();
			$possibleResults = $schemeService->getPossibleResults('dbs');
			
			$eia = $db->fetchAll($db->select()->from('reference_dbs_eia')->where("shipment_id = ?",$sid));			
			$wb = $db->fetchAll($db->select()->from('reference_dbs_wb')->where("shipment_id = ?",$sid));			
			
		}
		else if($shipment['scheme_type'] == 'eid'){			
			$reference = $db->fetchAll($db->select()->from(array('s'=>'shipment'))
					->join(array('ref'=>'reference_result_eid'),'ref.shipment_id=s.shipment_id')
					->where("s.shipment_id = ?",$sid));
			$schemeService = new Application_Service_Schemes();
			$possibleResults = $schemeService->getPossibleResults('eid');		
		}
		else if($shipment['scheme_type'] == 'vl'){			
			$reference = $db->fetchAll($db->select()->from(array('s'=>'shipment'))
					->join(array('ref'=>'reference_result_vl'),'ref.shipment_id=s.shipment_id')
					->where("s.shipment_id = ?",$sid));
			$possibleResults = "";		
		}else{
			return false;
		}
		
		return array('shipment'=>$shipment, 'reference'=>$reference,'possibleResults'=>$possibleResults , 'eia' => $eia , 'wb' => $wb);
		
	}
	
	
	public function updateShipment($params){
		//Zend_Debug::dump($params);die;
		
		
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
		$shipmentRow = $dbAdapter->fetchRow($dbAdapter->select()->from(array('s'=>'shipment'))->where('shipment_id = '.$params['shipmentId']));
		
		$scheme = $shipmentRow['scheme_type'];
		
		$size = count($params['sampleName']);
		if($scheme == 'eid'){
			$dbAdapter->delete('reference_result_eid','shipment_id = '.$params['shipmentId']);
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_eid',array(
									'shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['possibleResults'][$i],
									'reference_hiv_ct_od'=>$params['hivCtOd'][$i],
									'reference_ic_qs'=>$params['icQs'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
									)
								);
			}

		}
		else if($scheme == 'vl'){
			$dbAdapter->delete('reference_result_vl','shipment_id = '.$params['shipmentId']);
			for($i = 0;$i < $size;$i++){			
				$dbAdapter->insert('reference_result_vl',array(
									'shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['vlResult'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
									)
								  );
			}

		}
		else if($scheme == 'dts'){
			$dbAdapter->delete('reference_result_dts','shipment_id = '.$params['shipmentId']);
			$dbAdapter->delete('reference_dts_eia','shipment_id = '.$params['shipmentId']);
			$dbAdapter->delete('reference_dts_wb','shipment_id = '.$params['shipmentId']);
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_dts',array(
									'shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['possibleResults'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
									)
								);
				if(isset($params['eia'][$i+1]['eia'])){
					$eiaSize=sizeof($params['eia'][$i+1]['eia']);
					for($e=0;$e<$eiaSize;$e++){
						if(isset($params['eia'][$i+1]['eia'][$e]) && trim($params['eia'][$i+1]['eia'][$e])!=""){
							$expDate='';
							if(trim($params['eia'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['eia'][$i+1]['expiry'][$e]);
							}
							$dbAdapter->insert('reference_dts_eia',
								array('shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'eia'=>$params['eia'][$i+1]['eia'][$e],
									'lot'=>$params['eia'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'od'=>$params['eia'][$i+1]['od'][$e],
									'cutoff'=>$params['eia'][$i+1]['cutoff'][$e]
								)
							);
							
						}
						
					}
				}
				
				// <------ Insert reference_dbs_wb table
				if(isset($params['wb'][$i+1]['wb'])){
					$wbSize=sizeof($params['wb'][$i+1]['wb']);
					for($e=0;$e<$wbSize;$e++){
						if(isset($params['wb'][$i+1]['wb'][$e]) && trim($params['wb'][$i+1]['wb'][$e])!=""){
							$expDate='';
							if(trim($params['wb'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['wb'][$i+1]['expiry'][$e]);
							}
							$dbAdapter->insert('reference_dts_wb',
								array('shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'wb'=>$params['wb'][$i+1]['wb'][$e],
									'lot'=>$params['wb'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'160'=>$params['wb'][$i+1]['160'][$e],
									'120'=>$params['wb'][$i+1]['120'][$e],
									'66'=>$params['wb'][$i+1]['66'][$e],
									'55'=>$params['wb'][$i+1]['55'][$e],
									'51'=>$params['wb'][$i+1]['51'][$e],
									'41'=>$params['wb'][$i+1]['41'][$e],
									'31'=>$params['wb'][$i+1]['31'][$e],
									'24'=>$params['wb'][$i+1]['24'][$e],
									'17'=>$params['wb'][$i+1]['17'][$e]
								)
							);
							
						}
						
					}
				}
				// ------------------>
			}

		} else if($scheme == 'dbs'){
			$dbAdapter->delete('reference_result_dbs','shipment_id = '.$params['shipmentId']);
			$dbAdapter->delete('reference_dbs_eia','shipment_id = '.$params['shipmentId']);
			$dbAdapter->delete('reference_dbs_wb','shipment_id = '.$params['shipmentId']);
			for($i = 0;$i < $size;$i++){
				$dbAdapter->insert('reference_result_dbs',array(
									'shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'sample_label'=>$params['sampleName'][$i],
									'reference_result'=>$params['possibleResults'][$i],
									'control'=>$params['control'][$i],
									'mandatory'=>$params['mandatory'][$i],
									'sample_score'=>$params['score'][$i]
									)
								);
				if(isset($params['eia'][$i+1]['eia'])){
					$eiaSize=sizeof($params['eia'][$i+1]['eia']);
					for($e=0;$e<$eiaSize;$e++){
						if(isset($params['eia'][$i+1]['eia'][$e]) && trim($params['eia'][$i+1]['eia'][$e])!=""){
							$expDate='';
							if(trim($params['eia'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['eia'][$i+1]['expiry'][$e]);
							}
							$dbAdapter->insert('reference_dbs_eia',
								array('shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'eia'=>$params['eia'][$i+1]['eia'][$e],
									'lot'=>$params['eia'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'od'=>$params['eia'][$i+1]['od'][$e],
									'cutoff'=>$params['eia'][$i+1]['cutoff'][$e]
								)
							);
							
						}
						
					}
				}
				// <------ Insert reference_dbs_wb table
				if(isset($params['wb'][$i+1]['wb'])){
					$wbSize=sizeof($params['wb'][$i+1]['wb']);
					for($e=0;$e<$wbSize;$e++){
						if(isset($params['wb'][$i+1]['wb'][$e]) && trim($params['wb'][$i+1]['wb'][$e])!=""){
							$expDate='';
							if(trim($params['wb'][$i+1]['expiry'][$e])!=""){
								$expDate=Pt_Commons_General::dateFormat($params['wb'][$i+1]['expiry'][$e]);
							}
							$dbAdapter->insert('reference_dbs_wb',
								array('shipment_id'=>$params['shipmentId'],
									'sample_id'=>($i+1),
									'wb'=>$params['wb'][$i+1]['wb'][$e],
									'lot'=>$params['wb'][$i+1]['lot'][$e],
									'exp_date'=>$expDate,
									'160'=>$params['wb'][$i+1]['160'][$e],
									'120'=>$params['wb'][$i+1]['120'][$e],
									'66'=>$params['wb'][$i+1]['66'][$e],
									'55'=>$params['wb'][$i+1]['55'][$e],
									'51'=>$params['wb'][$i+1]['51'][$e],
									'41'=>$params['wb'][$i+1]['41'][$e],
									'31'=>$params['wb'][$i+1]['31'][$e],
									'24'=>$params['wb'][$i+1]['24'][$e],
									'17'=>$params['wb'][$i+1]['17'][$e]
								)
							);
							
						}
						
					}
				}
				// ------------------>
			
			}
		}
		
		$dbAdapter->update('shipment',array('number_of_samples' => $size),'shipment_id = '.$params['shipmentId']);
	}
	
	
	public function getShipmentOverview($parameters){
		$shipmentDb = new Application_Model_DbTable_Shipments();
		return $shipmentDb->getShipmentOverviewDetails($parameters);
	}
	
	public function getShipmentCurrent($parameters){
		$shipmentDb = new Application_Model_DbTable_Shipments();
		return $shipmentDb->getShipmentCurrentDetails($parameters);
	}
	
	public function getShipmentDefault($parameters){
		$shipmentDb = new Application_Model_DbTable_Shipments();
		return $shipmentDb->getShipmentDefaultDetails($parameters);
	}
	
	public function getShipmentAll($parameters){
		$shipmentDb = new Application_Model_DbTable_Shipments();
		return $shipmentDb->getShipmentAllDetails($parameters);
	}
	
	public function getShipmentInReports($distributionId){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('s'=>'shipment'))
					->join(array('d'=>'distributions'),'d.distribution_id=s.distribution_id')
					->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('participant_count' => new Zend_Db_Expr('count("participant_id")'), 'reported_count'=> new Zend_Db_Expr("SUM(shipment_test_date <> '')"), 'number_passed'=> new Zend_Db_Expr("SUM(final_result = 1)")))
					->join(array('sl'=>'scheme_list'),'sl.scheme_id=s.scheme_type')
					->joinLeft(array('rr'=>'r_results'),'sp.final_result=rr.result_id')
					->where("s.distribution_id = ?",$distributionId)
					->group('s.shipment_id');
			  
	    return $db->fetchAll($sql);
	}
	public function getParticipantCountBasedOnScheme() {
		$resultArray=array();
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery=$db->select()->from(array('s'=>'shipment'),array())
                            ->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('participantCount' => new Zend_Db_Expr("count(sp.participant_id)")))
                            ->join(array('sl'=>'scheme_list'),'sl.scheme_id=s.scheme_type',array('SCHEME'=>'sl.scheme_id'))
                            ->where("s.scheme_type = sl.scheme_id")
                            ->where("s.status!='pending'")
                            ->group('s.scheme_type')
                            ->order("sl.scheme_id");
         $resultArray=$db->fetchAll($sQuery);
        //Zend_Debug::dump($resultArray);die;
		return $resultArray;
	}

	public function getParticipantCountBasedOnShipment() {
        $resultArray=array();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $sQuery=$db->select()->from(array('s'=>'shipment'),array('s.shipment_code','s.scheme_type','s.lastdate_response'))
            ->join(array('sp'=>'shipment_participant_map'),'sp.shipment_id=s.shipment_id',array('participantCount' => new Zend_Db_Expr("count(sp.participant_id)"),'receivedCount' => new Zend_Db_Expr("SUM(sp.shipment_test_date <> '')")))
            ->where("s.status='shipped'")
            //->where("YEAR(s.shipment_date) = YEAR(CURDATE())")
            ->where("s.shipment_date > DATE_SUB(now(), INTERVAL 18 MONTH)")
            ->group('s.shipment_id')
            ->order("s.shipment_id");
        $resultArray=$db->fetchAll($sQuery);
        //Zend_Debug::dump($resultArray);die;
        return $resultArray;
    }
	
	public function removeShipmentParticipant($mapId){
		try{
			$shipmentParticipantMap = new Application_Model_DbTable_ShipmentParticipantMap();			
			return $shipmentParticipantMap->delete('map_id='.$mapId);
		}catch(Exception $e){
			return($e->getMessage());
			return "Unable to delete. Please try again later or contact system admin for help";
		}

	}
	
	public function addEnrollements($params){
		$db = new Application_Model_DbTable_ShipmentParticipantMap();
		return $db->addEnrollementDetails($params);
	}
}

