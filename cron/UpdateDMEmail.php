<?php

include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $sQuery = $db->select()->from(array('dm' => 'data_manager'),array('dm.dm_id'))
            ->join(array('p' => 'participant'), 'p.email=dm.primary_email',array('p.participant_id','unique_identifier'))
            ->order("dm_id ASC");
    $dmResult = $db->fetchAll($sQuery);
    
    //error_log('RUNNING CRON TO SEND MAIL PA');
    
    if (count($dmResult) > 0) {
        $oldDmId='';
        foreach ($dmResult as $result) {
            if($oldDmId!=""){
                if($result['dm_id']==$oldDmId){
                    $oldDmId='';
                    if($oldUniqIden>$result['unique_identifier']){
                        $result['unique_identifier']=$oldUniqIden;
                    }
                }else{
                    $oldDmId='';
                }
            }
            
            if($oldDmId==''){
                $oldDmId=$result['dm_id'];
                $oldUniqIden=$result['unique_identifier'];
            }
           
            $email=$result['unique_identifier']."_pt@vlsmartconnect.com";
            $db->update('data_manager',array('primary_email'=>$email), 'dm_id=' . $result['dm_id']);
            
            $db->insert('participant_manager_map',array('participant_id'=>$result['participant_id'],'dm_id'=>$result['dm_id']));

        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in cron/SendMailAlerts.php');
}
