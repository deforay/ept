<?php

include_once 'CronInit.php';
$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);
$limit = 10;
$sQuery = $db->select()
    ->from(array('pn' => 'push_notification'))
    ->where("pn.push_status=?", 'pending')
    ->limit($limit);
$pnResult = $db->fetchAll($sQuery);
echo "<pre>";
print_r($pnResult);
foreach($pnResult as $row){
    if($row['identify_type'] == 'shipment'){
        $subQuery = $db->select()
        ->from(array('s' => 'shipment'),array('shipment_code'))
        ->join(array('spm'=>'shipment_participant_map'),'spm.shipment_id=s.shipment_id',array('map_id'))
        ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=spm.participant_id',array('dm_id'))
        ->join(array('dm'=>'data_manager'),'pmm.dm_id=dm.dm_id',array('primary_email', 'push_notify_token'))
        ->where("s.shipment_id=?", $row['token_identify_id'])
        ->group('dm.dm_id')
        ->limit($limit);
        $subResult = $db->fetchAll($subQuery);
        Zend_Debug::dump($subResult);
        Zend_Debug::dump($pnResult);
        die;
    }

}