<?php

include_once 'CronInit.php';
include("DOCx.php");

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$common = new Application_Service_Common();
$participantsDb = new Application_Model_DbTable_Participants();
$dataManagerDb = new Application_Model_DbTable_DataManagers();
$schemesService = new Application_Service_Schemes();
$vlAssayArray = $schemesService->getVlAssay();



try {
    
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    $startDate="2016-01-01";
    $endDate="2017-01-31";
    $output = array();
    
    $query = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date',))
								->where("shipment_code='EID2016-I' OR shipment_code ='EID2016-II' OR shipment_code='VL2016-A' OR shipment_code='VL2016-B'")
								->order("s.scheme_type");
    
    
	$shipmentResult = $db->fetchAll($query);
    $shipmentIDArray=array();
    foreach($shipmentResult as $val){
        $shipmentIdArray[]=$val['shipment_id'];
        $shipmentCodeArray[$val['scheme_type']][]=$val['shipment_code'];
        $impShipmentId=implode(",",$shipmentIdArray);
    }
    
    $sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id','spm.attributes','spm.shipment_id','spm.participant_id','spm.shipment_score','spm.final_result'))
							->join(array('s' => 'shipment'),'s.shipment_id=spm.shipment_id',array('shipment_code','scheme_type'))
							->join(array('p' => 'participant'),'p.participant_id=spm.participant_id',array('unique_identifier','first_name','last_name','email','city','state','address','institute_name'))
                            ->order("scheme_type ASC");
                            
    $sQuery->where('spm.shipment_id IN ('.$impShipmentId.')');
    
    //Zend_Debug::dump($shipmentCodeArray);die;
    $shipmentParticipantResult = $db->fetchAll($sQuery);
	//Zend_Debug::dump($shipmentParticipantResult);die;
	$participants = array();
    foreach($shipmentParticipantResult as $shipment){

		//$assay = $vlAssayArray[$attribs]
		//Zend_Debug::dump($attribs);die;
        //count($participants);

		$participants[$shipment['unique_identifier']]['labName']=$shipment['first_name']." ".$shipment['last_name'];
		//$participants[$shipment['unique_identifier']]['finalResult']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['score']=$shipment['shipment_score'];
		$participants[$shipment['unique_identifier']][$shipment['scheme_type']][$shipment['shipment_code']]['result']=$shipment['final_result'];
		$participants[$shipment['unique_identifier']]['attribs']=json_decode($shipment['attributes'], true);
		//$participants[$shipment['unique_identifier']][$shipment['shipment_code']]=$shipment['shipment_score'];
        
    }
    //$this->generateAnnualReport($shipmentCodeArray,$participants,$startDate,$endDate);
    //Zend_Debug::dump($participants);die;
    foreach($participants as $participantUID=>$arrayVal){
			
			foreach($shipmentCodeArray as $schemeKey=>$scheme){
				if(isset($arrayVal[$schemeKey])){
					$certificate=true;
					$participated=false;
                
					$query = $db->select()->from('scheme_list',array('scheme_name'))->where("scheme_id=?", $schemeKey);
					$schemeResult = $db->fetchRow($query);
				
					foreach($scheme as $va){
						if(isset($arrayVal[$schemeKey][$va]['score'])){
							if($arrayVal[$schemeKey][$va]['result']!=1){
								$certificate=false;
							}
							if(trim($arrayVal[$schemeKey][$va]['score'])!=""){
								$participated=true;
							}
						}else{
							
							$certificate=false;
						}
					}
                
					if($certificate && $participated){
						$attribs = $arrayVal['attribs'];
						
						if($schemeKey == 'eid'){
							$doc = new DOCx("certificate-template/eid-e.docx");
							$doc->setValue("LABNAME",$arrayVal['labName']);
							$doc->setValue("DATE","24 January 2017");
							//$doc->save("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '_', $participantUID).".docx");					
							$doc->save("certificate/eid/".str_replace('/', '_', $participantUID)."-EID-2016.docx");
						}else if($schemeKey=='vl'){
							if($attribs["vl_assay"]==6){
								if(isset($attribs["other_assay"])){
									$assay=$attribs["other_assay"];	
								}else{
									$assay="Other";
								}
							}else{
								$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";	
							}
							$doc = new DOCx("certificate-template/vl-e.docx");
							$doc->setValue("LABNAME",$arrayVal['labName']);
							$doc->setValue("ASSAYNAME",$assay);
							$doc->setValue("DATE","24 January 2017");
							//$doc->save("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '_', $participantUID).".docx");	
							$doc->save("certificate/vl/".str_replace('/', '_', $participantUID)."-VL-2016.docx");
						}
						
					}else if($participated){
						$attribs = $arrayVal['attribs'];
						
						if($schemeKey == 'eid'){
							$doc = new DOCx("certificate-template/eid-p.docx");
							$doc->setValue("LABNAME",$arrayVal['labName']);
							$doc->setValue("DATE","24 January 2017");
							//$doc->save("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '-', $participantUID).".docx");	
							$doc->save("certificate/eid/".str_replace('/', '_', $participantUID)."-EID-2016.docx");
						}else if($schemeKey=='vl'){
							if($attribs["vl_assay"]==6){
								if(isset($attribs["other_assay"])){
									$assay=$attribs["other_assay"];	
								}else{
									$assay="Other";
								}
							}else{
								$assay = (isset($attribs["vl_assay"]) && isset($vlAssayArray[$attribs["vl_assay"]])) ? $vlAssayArray[$attribs["vl_assay"]] : " Other ";	
							}
							
							$doc = new DOCx("certificate-template/vl-p.docx");
							$doc->setValue("LABNAME",$arrayVal['labName']);
							$doc->setValue("ASSAYNAME",$assay);
							$doc->setValue("DATE","24 January 2017");
							//$doc->save("certificate/2017 Certificate - ".strtoupper($schemeKey)." for Lab ".str_replace('/', '-', $participantUID).".docx");
							$doc->save("certificate/vl/".str_replace('/', '_', $participantUID)."-VL-2016.docx");
						}
					}
                
				}
				
			}
			
	}
    
    
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in cron/GenerateCertificate.php');
}