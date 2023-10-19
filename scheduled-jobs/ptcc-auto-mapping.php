<?php
ini_set('memory_limit', '-1');

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');
$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$general = new Application_Service_Common();
try{
    $db = Zend_Db::factory($conf->resources->db);
	Zend_Db_Table::setDefaultAdapter($db);
    /* To get list of ptcc manager */
    $sQuery = $db->select()
        ->from(array('dm' => 'data_manager'), array('dm_id'))
        ->joinLeft(array('pcm' => 'ptcc_countries_map'), 'dm.dm_id=pcm.ptcc_id', array('country_id', 'state', 'district'))
        ->where("dm.ptcc like 'yes'");
    // echo($sQuery);die;
    $result = $db->fetchAll($sQuery);
    if(isset($result[0]) && sizeof($result) > 0){
        foreach($result as $key => $value){
            $mapResult = [];
            if(isset($value["district"]) && !empty($value["district"])){
                $mQuery = $db->select()->from(array("p"=> "participant"), array('participant_id'))->where("district", $value["district"]);
                $mapResult = $db->fetchAll($mQuery);
            } else if(isset($value["state"]) && !empty($value["state"])){
                $mQuery = $db->select()->from(array("p"=> "participant"), array('participant_id'))->where("state", $value["state"]);
                $mapResult = $db->fetchAll($mQuery);
            } else if(isset($value["country_id"]) && !empty($value["country_id"])){
                $mQuery = $db->select()->from(array("p"=> "participant"), array('participant_id'))->where("country", $value["country_id"]);
                $mapResult = $db->fetchAll($mQuery);
            }
            // print_r($mapResult);die;
            if(isset($mapResult[0]) && sizeof($mapResult) > 0){
                $db->delete('participant_manager_map', 'dm_id = ' . $value['dm_id']);
                foreach($mapResult as $pkey => $pvalue){
                    $multipleData[] = array('participant_id'=> $pvalue['participant_id'], 'dm_id' => $value['dm_id']);
                }
            }
            if(isset($multipleData[0]) && sizeof($multipleData) > 0){
                $common->insertMultiple('participant_manager_map', $multipleData);
            }
        }
    }
}catch (Exception $e) {
	error_log($e->getMessage());
	error_log($e->getTraceAsString());
	error_log('whoops! Something went wrong in scheduled-jobs/ptcc-auto-mapping.php');
}