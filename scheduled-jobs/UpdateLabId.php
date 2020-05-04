<?php

include_once 'CronInit.php';
include_once "PHPExcel.php";

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$common = new Application_Service_Common();
$participantsDb = new Application_Model_DbTable_Participants();
$dataManagerDb = new Application_Model_DbTable_DataManagers();
try {
    
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    $fileName = UPLOAD_PATH . DIRECTORY_SEPARATOR . "All_enrolled_participants2016-II.xlsx";
    
    $missing=0;
    
    $objPHPExcel = PHPExcel_IOFactory::load($fileName);
    $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
    $count = count($sheetData);
    for ($i = 2; $i <= $count;  ++$i) {
        if (trim($sheetData[$i]['A']) == "" && $sheetData[$i]['B']=="" && $sheetData[$i]['C']=="" && $sheetData[$i]['B'] == null) {
            error_log('Looks like Mobile No is empty');
            continue;
        }
        
        $labId=trim($sheetData[$i]['A']);
        $labName=$sheetData[$i]['E'];
        $address=$sheetData[$i]['F'];
        $city=$sheetData[$i]['G'];
        $country=trim($sheetData[$i]['H']);
        $contact=trim($sheetData[$i]['I']);
        $email=$sheetData[$i]['J'];
        $additionalEmail=$sheetData[$i]['L'];
        $zip=trim($sheetData[$i]['M']);
        
        $userName=trim($sheetData[$i]['O']);
        $password=trim($sheetData[$i]['P']);
        
        $countryId="";
        if($country!=""){
            $query = $db->select()->from('countries')->where("iso_name LIKE '%".$country."%'");
            $cResult = $db->fetchRow($query);
            if($cResult!=""){
                $countryId=$cResult['id'];
            }
        }
        
        $data=array('unique_identifier'=>$labId);
        if(trim($address)!=""){
            $data['address']=$address;
        }
        if(trim($city)!=""){
            $data['city']=$city;
        }
        if(trim($countryId)!=""){
            $data['country']=$countryId;
        }
        if(trim($additionalEmail)!=""){
            $data['additional_email']=$additionalEmail;
        }
        if(trim($email)!=""){
            $data['email']=$email;
        }
        if(trim($zip)!=""){
            $data['zip']=$zip;
        }
        
        $sQuery = $db->select()->from(array('p' => 'participant'));
        if ($sheetData[$i]['C']!= null && trim($sheetData[$i]['C']) != "") {
            $vlId=trim($sheetData[$i]['C']);
            $sQuery=$sQuery->where("unique_identifier LIKE '%".$vlId."%'");
            $pResult = $db->fetchAll($sQuery);
            if(count($pResult)>0){
                foreach($pResult as $val){
                    $expStr=explode("/",$val['unique_identifier']);
                    if (strpos($expStr[0],'VL') !== false) {
                       
                    }
                    else if(isset($expStr[1]) && strpos($expStr[1],'VL') !== false) {
                        $expStr[0]=$expStr[1];
                    }
                    $str = substr($expStr[0], 2);
                    if($vlId==$str){
                        $query = $db->select()->from('participant')->where("unique_identifier = ?",$labId);
                        $cResult = $db->fetchAll($query);
                        if(count($cResult)==0){
                            $db->update('participant',$data,'participant_id='.$val['participant_id']);
                        }else{
                            //Delete repeat participants
                            $participantId=$cResult[0]['participant_id'];
                            
                            //Update participant id
                            $spQuery = $db->select()->from('shipment_participant_map')->where("participant_id=?",$val['participant_id']);
                            $spResult = $db->fetchAll($spQuery);
                            foreach($spResult as $res){
                                $db->update('shipment_participant_map',array('participant_id'=>$participantId),'map_id='.$res['map_id']);
                            }
                            
                            //Delete participants
                            $pmQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$val['participant_id']);
                            $pmResult = $db->fetchAll($pmQuery);
                            foreach($pmResult as $res){
                                $dmId=$res['dm_id'];
                                $pQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$participantId)->where("dm_id=?",$dmId);
                                $pResult = $db->fetchAll($pQuery);
                                if(count($pResult)==0){
                                    $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                                }
                                $db->delete('participant_manager_map', array('participant_id = ?' => $res['participant_id'],'dm_id = ?' => $res['dm_id']));
                            }
                            
                            //Update participant id in enrollments
                            $eQuery = $db->select()->from('enrollments')->where("participant_id=?",$val['participant_id']);
                            $eResult = $db->fetchAll($eQuery);
                            if(count($eResult)>0){
                                foreach($eResult as $res){
                                    $pQuery = $db->select()->from('enrollments')->where("participant_id=?",$participantId)->where("scheme_id=?",$res['scheme_id']);
                                    $pResult = $db->fetchAll($pQuery);
                                    if(count($pResult)==0){
                                        $db->insert('enrollments',array('participant_id'=>$participantId,'scheme_id'=>$res['scheme_id'],'enrolled_on'=>$res['enrolled_on'],'enrollment_ended_on'=>$res['enrollment_ended_on'],'status'=>$res['status']));
                                    }
                                    echo $db->delete('enrollments', array('participant_id = ?' => $res['participant_id'],'scheme_id = ?' => $res['scheme_id'],'enrolled_on = ?' => $res['enrolled_on']));
                                }
                            }
                            $db->delete('participant', 'participant_id='.$val['participant_id']);
                        }
                        
                    }else{
                        //echo $i." --->  $str <br/>";
                        $expStr=explode($vlId,$str);
                        if (strpos($str,$vlId) !== false) {
                            $query = $db->select()->from('participant')->where("unique_identifier = ?",$labId.$expStr[1]);
                            $cResult = $db->fetchAll($query);
                            if(count($cResult)==0){
                            $data=array('unique_identifier'=>$labId.$expStr[1]);
                            $db->update('participant',$data,'participant_id='.$val['participant_id']);
                            }else{
                                //Delete repeat participants
                                $participantId=$cResult[0]['participant_id'];
                                
                                //Update participant id
                                $spQuery = $db->select()->from('shipment_participant_map')->where("participant_id=?",$val['participant_id']);
                                $spResult = $db->fetchAll($spQuery);
                                foreach($spResult as $res){
                                    $db->update('shipment_participant_map',array('participant_id'=>$participantId),'map_id='.$res['map_id']);
                                }
                                
                                //Delete participants
                                $pmQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$val['participant_id']);
                                $pmResult = $db->fetchAll($pmQuery);
                                foreach($pmResult as $res){
                                    $dmId=$res['dm_id'];
                                    $pQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$participantId)->where("dm_id=?",$dmId);
                                    $pResult = $db->fetchAll($pQuery);
                                    if(count($pResult)==0){
                                        $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                                    }
                                    $db->delete('participant_manager_map', array('participant_id = ?' => $res['participant_id'],'dm_id = ?' => $res['dm_id']));
                                }
                                
                                //Update participant id in enrollments
                                $eQuery = $db->select()->from('enrollments')->where("participant_id=?",$val['participant_id']);
                                $eResult = $db->fetchAll($eQuery);
                                if(count($eResult)>0){
                                    foreach($eResult as $res){
                                        $pQuery = $db->select()->from('enrollments')->where("participant_id=?",$participantId)->where("scheme_id=?",$res['scheme_id']);
                                        $pResult = $db->fetchAll($pQuery);
                                        if(count($pResult)==0){
                                            $db->insert('enrollments',array('participant_id'=>$participantId,'scheme_id'=>$res['scheme_id'],'enrolled_on'=>$res['enrolled_on'],'enrollment_ended_on'=>$res['enrollment_ended_on'],'status'=>$res['status']));
                                        }
                                        echo $db->delete('enrollments', array('participant_id = ?' => $res['participant_id'],'scheme_id = ?' => $res['scheme_id'],'enrolled_on = ?' => $res['enrolled_on']));
                                    }
                                }
                                $db->delete('participant', 'participant_id='.$val['participant_id']);
                            }
                        }
                    }
                }
                
            }else{
                if ($sheetData[$i]['B']!= null && trim($sheetData[$i]['B']) != "") {
                    $vlId=trim($sheetData[$i]['B']);
                    $pQuery = $db->select()->from(array('p' => 'participant'))->where("unique_identifier LIKE '%".$vlId."%'");
                    $pResult = $db->fetchAll($pQuery);
                    if(count($pResult)>0){
                        foreach($pResult as $val){
                            $str = substr($val['unique_identifier'], 3);
                            if($vlId==$str){
                                $query = $db->select()->from('participant')->where("unique_identifier = ?",$labId);
                                $cResult = $db->fetchAll($query);
                                if(count($cResult)==0){
                                    $db->update('participant',$data,'participant_id='.$val['participant_id']);
                                }else{
                                    //Delete repeat participants
                                    $participantId=$cResult[0]['participant_id'];
                                    
                                    //Update participant id
                                    $spQuery = $db->select()->from('shipment_participant_map')->where("participant_id=?",$val['participant_id']);
                                    $spResult = $db->fetchAll($spQuery);
                                    foreach($spResult as $res){
                                        $db->update('shipment_participant_map',array('participant_id'=>$participantId),'map_id='.$res['map_id']);
                                    }
                                    
                                    //Delete participants
                                    $pmQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$val['participant_id']);
                                    $pmResult = $db->fetchAll($pmQuery);
                                    foreach($pmResult as $res){
                                        $dmId=$res['dm_id'];
                                        $pQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$participantId)->where("dm_id=?",$dmId);
                                        $pResult = $db->fetchAll($pQuery);
                                        if(count($pResult)==0){
                                            $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                                        }
                                        $db->delete('participant_manager_map', array('participant_id = ?' => $res['participant_id'],'dm_id = ?' => $res['dm_id']));
                                    }
                                    
                                    //Update participant id in enrollments
                                    $eQuery = $db->select()->from('enrollments')->where("participant_id=?",$val['participant_id']);
                                    $eResult = $db->fetchAll($eQuery);
                                    if(count($eResult)>0){
                                        foreach($eResult as $res){
                                            $pQuery = $db->select()->from('enrollments')->where("participant_id=?",$participantId)->where("scheme_id=?",$res['scheme_id']);
                                            $pResult = $db->fetchAll($pQuery);
                                            if(count($pResult)==0){
                                                $db->insert('enrollments',array('participant_id'=>$participantId,'scheme_id'=>$res['scheme_id'],'enrolled_on'=>$res['enrolled_on'],'enrollment_ended_on'=>$res['enrollment_ended_on'],'status'=>$res['status']));
                                            }
                                            echo $db->delete('enrollments', array('participant_id = ?' => $res['participant_id'],'scheme_id = ?' => $res['scheme_id'],'enrolled_on = ?' => $res['enrolled_on']));
                                        }
                                    }
                                    $db->delete('participant', 'participant_id='.$val['participant_id']);
                                }
                            }else{
                                //echo $i." --->  $str <br/>";
                                $expStr=explode($vlId,$str);
                                if (strpos($str,$vlId) !== false) {
                                    $query = $db->select()->from('participant')->where("unique_identifier = ?",$labId.$expStr[1]);
                                    $cResult = $db->fetchAll($query);
                                    if(count($cResult)==0){
                                        $data=array('unique_identifier'=>$labId.$expStr[1]);
                                        $db->update('participant',$data,'participant_id='.$val['participant_id']);
                                    }else{
                                        //Delete repeat participants
                                        $participantId=$cResult[0]['participant_id'];
                                        
                                        //Update participant id
                                        $spQuery = $db->select()->from('shipment_participant_map')->where("participant_id=?",$val['participant_id']);
                                        $spResult = $db->fetchAll($spQuery);
                                        foreach($spResult as $res){
                                            $db->update('shipment_participant_map',array('participant_id'=>$participantId),'map_id='.$res['map_id']);
                                        }
                                        
                                        //Delete participants
                                        $pmQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$val['participant_id']);
                                        $pmResult = $db->fetchAll($pmQuery);
                                        foreach($pmResult as $res){
                                            $dmId=$res['dm_id'];
                                            $pQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$participantId)->where("dm_id=?",$dmId);
                                            $pResult = $db->fetchAll($pQuery);
                                            if(count($pResult)==0){
                                                $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                                            }
                                            $db->delete('participant_manager_map', array('participant_id = ?' => $res['participant_id'],'dm_id = ?' => $res['dm_id']));
                                        }
                                        
                                        //Update participant id in enrollments
                                        $eQuery = $db->select()->from('enrollments')->where("participant_id=?",$val['participant_id']);
                                        $eResult = $db->fetchAll($eQuery);
                                        if(count($eResult)>0){
                                            foreach($eResult as $res){
                                                $pQuery = $db->select()->from('enrollments')->where("participant_id=?",$participantId)->where("scheme_id=?",$res['scheme_id']);
                                                $pResult = $db->fetchAll($pQuery);
                                                if(count($pResult)==0){
                                                    $db->insert('enrollments',array('participant_id'=>$participantId,'scheme_id'=>$res['scheme_id'],'enrolled_on'=>$res['enrolled_on'],'enrollment_ended_on'=>$res['enrollment_ended_on'],'status'=>$res['status']));
                                                }
                                                echo $db->delete('enrollments', array('participant_id = ?' => $res['participant_id'],'scheme_id = ?' => $res['scheme_id'],'enrolled_on = ?' => $res['enrolled_on']));
                                            }
                                        }
                                        $db->delete('participant', 'participant_id='.$val['participant_id']);
                                    }
                                }
                            }
                        }
                    }else{
                        //Insert Participants details
                        
                        $data['status']='active';
                        $data['created_on']=new Zend_Db_Expr('NOW()');
                        $participantId=$participantsDb->insert($data);
                        
                        if($userName=="" || $userName==NULL ){
                            $userName=$labId."_pt@vlsmartconnect.com";
                        }
                        
                        if($password=="" || $password==NULL){
                            $password=$common->getRandomString();
                        }
                        
                        $pdata=array(
                                        'primary_email'=>$userName,
                                        'password'=>$password,
                                        'status'=>'active',
                                        'force_password_reset'=>1,
                                        'created_on'=>new Zend_Db_Expr('NOW()')
                                    );
                        $dmId=$dataManagerDb->insert($pdata);
                        
                        $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                    }
                }
            }
        }else if ($sheetData[$i]['B']!= null && trim($sheetData[$i]['B']) != "") {
            $vlId=trim($sheetData[$i]['B']);
            $sQuery=$sQuery->where("unique_identifier LIKE '%".$vlId."%'");
            $pResult = $db->fetchAll($sQuery);
            
            if(count($pResult)>0){
                foreach($pResult as $val){
                    $str = substr($val['unique_identifier'], 3);
                    if($vlId==$str){
                        $query = $db->select()->from('participant')->where("unique_identifier = ?",$labId);
                        $cResult = $db->fetchAll($query);
                        if(count($cResult)==0){
                            $db->update('participant',$data,'participant_id='.$val['participant_id']);
                        }else{
                            //Delete repeat participants
                            $participantId=$cResult[0]['participant_id'];
                            
                            //Update participant id
                            $spQuery = $db->select()->from('shipment_participant_map')->where("participant_id=?",$val['participant_id']);
                            $spResult = $db->fetchAll($spQuery);
                            foreach($spResult as $res){
                                $db->update('shipment_participant_map',array('participant_id'=>$participantId),'map_id='.$res['map_id']);
                            }
                            
                            //Delete participants
                            $pmQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$val['participant_id']);
                            $pmResult = $db->fetchAll($pmQuery);
                            foreach($pmResult as $res){
                                $dmId=$res['dm_id'];
                                $pQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$participantId)->where("dm_id=?",$dmId);
                                $pResult = $db->fetchAll($pQuery);
                                if(count($pResult)==0){
                                    $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                                }
                                $db->delete('participant_manager_map', array('participant_id = ?' => $res['participant_id'],'dm_id = ?' => $res['dm_id']));
                            }
                            
                            //Update participant id in enrollments
                            $eQuery = $db->select()->from('enrollments')->where("participant_id=?",$val['participant_id']);
                            $eResult = $db->fetchAll($eQuery);
                            if(count($eResult)>0){
                                foreach($eResult as $res){
                                    $pQuery = $db->select()->from('enrollments')->where("participant_id=?",$participantId)->where("scheme_id=?",$res['scheme_id']);
                                    $pResult = $db->fetchAll($pQuery);
                                    if(count($pResult)==0){
                                        $db->insert('enrollments',array('participant_id'=>$participantId,'scheme_id'=>$res['scheme_id'],'enrolled_on'=>$res['enrolled_on'],'enrollment_ended_on'=>$res['enrollment_ended_on'],'status'=>$res['status']));
                                    }
                                    echo $db->delete('enrollments', array('participant_id = ?' => $res['participant_id'],'scheme_id = ?' => $res['scheme_id'],'enrolled_on = ?' => $res['enrolled_on']));
                                }
                            }
                            $db->delete('participant', 'participant_id='.$val['participant_id']);
                        }
                    }else{
                        //echo $i." --->  $str <br/>";
                        $expStr=explode($vlId,$str);
                        if (strpos($str,$vlId) !== false) {
                            $query = $db->select()->from('participant')->where("unique_identifier = ?",$labId.$expStr[1]);
                            $cResult = $db->fetchAll($query);
                            if(count($cResult)==0){
                            $data=array('unique_identifier'=>$labId.$expStr[1]);
                            $db->update('participant',$data,'participant_id='.$val['participant_id']);
                            }else{
                                //Delete repeat participants
                                $participantId=$cResult[0]['participant_id'];
                                
                                //Update participant id
                                $spQuery = $db->select()->from('shipment_participant_map')->where("participant_id=?",$val['participant_id']);
                                $spResult = $db->fetchAll($spQuery);
                                foreach($spResult as $res){
                                    $db->update('shipment_participant_map',array('participant_id'=>$participantId),'map_id='.$res['map_id']);
                                }
                                
                                //Delete participants
                                $pmQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$val['participant_id']);
                                $pmResult = $db->fetchAll($pmQuery);
                                foreach($pmResult as $res){
                                    $dmId=$res['dm_id'];
                                    $pQuery = $db->select()->from('participant_manager_map')->where("participant_id=?",$participantId)->where("dm_id=?",$dmId);
                                    $pResult = $db->fetchAll($pQuery);
                                    if(count($pResult)==0){
                                        $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
                                    }
                                    $db->delete('participant_manager_map', array('participant_id = ?' => $res['participant_id'],'dm_id = ?' => $res['dm_id']));
                                }
                                
                                //Update participant id in enrollments
                                $eQuery = $db->select()->from('enrollments')->where("participant_id=?",$val['participant_id']);
                                $eResult = $db->fetchAll($eQuery);
                                if(count($eResult)>0){
                                    foreach($eResult as $res){
                                        $pQuery = $db->select()->from('enrollments')->where("participant_id=?",$participantId)->where("scheme_id=?",$res['scheme_id']);
                                        $pResult = $db->fetchAll($pQuery);
                                        if(count($pResult)==0){
                                            $db->insert('enrollments',array('participant_id'=>$participantId,'scheme_id'=>$res['scheme_id'],'enrolled_on'=>$res['enrolled_on'],'enrollment_ended_on'=>$res['enrollment_ended_on'],'status'=>$res['status']));
                                        }
                                        echo $db->delete('enrollments', array('participant_id = ?' => $res['participant_id'],'scheme_id = ?' => $res['scheme_id'],'enrolled_on = ?' => $res['enrolled_on']));
                                    }
                                }
                                $db->delete('participant', 'participant_id='.$val['participant_id']);
                            }
                        }
                    }
                }
            }else{
                //echo $labId."  -->  "; 
                //Insert Participants details
                $data['status']='active';
                $data['created_on']=new Zend_Db_Expr('NOW()');
                $participantId=$participantsDb->insert($data);
                if($userName=="" || $userName==NULL ){
                    $userName=$labId."_pt@vlsmartconnect.com";
                }
                
                if($password=="" || $password==NULL){
                    $password=$common->getRandomString();
                }
                
                $pdata=array(
                                'primary_email'=>$userName,
                                'password'=>$password,
                                'status'=>'active',
                                'force_password_reset'=>1,
                                'created_on'=>new Zend_Db_Expr('NOW()')
                            );
                $dmId=$dataManagerDb->insert($pdata);
                $db->insert('participant_manager_map',array('participant_id'=>$participantId,'dm_id'=>$dmId));
            }
        }
    }
    
    
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/SendMailAlerts.php');
}
