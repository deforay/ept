<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');
$config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

$globalConfig = $db->fetchRow($db->select()->from('global_config')->where("name like 'disable_push_notification'"));
if ($globalConfig['value'] == 'yes' && $globalConfig['name'] == 'disable_push_notification') {
    exit;
}
$limit = 10;
$sQuery = $db->select()
    ->from(array('pn' => 'push_notification'))
    ->where("pn.push_status=?", 'pending')
    ->limit($limit);
$pnResult = $db->fetchAll($sQuery);

foreach ($pnResult as $row) {
    if ($row['notification_type'] == 'announcement') {

        $subQuery = $db->select()
            ->from(array('s' => 'shipment'), array('shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('map_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array('dm_id'))
            ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email', 'push_notify_token'))
            ->where("dm.dm_id IN (" . $row['token_identify_id'] . ")")
            ->group('dm.dm_id');
    } else {

        $subQuery = $db->select()
            ->from(array('s' => 'shipment'), array('shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('map_id'))
            ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array('dm_id'))
            ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email', 'push_notify_token'))
            ->where("s.shipment_id=?", $row['token_identify_id'])
            ->group('dm.dm_id');
    }
    $subResult = $db->fetchAll($subQuery);

    $notify = (array)json_decode($row['notification_json']);
    $status = false;
    foreach ($subResult as $subRow) {

        $json_data = array(
            "to"            => $subRow['push_notify_token'],
            "notification"  => array(
                "title"             => $notify['title'],
                "body"              => $notify['body'],
                "icon"              => (isset($notify['icon']) && $notify['icon'] != '') ? $notify['icon'] : "fcm_push_icon"
            ),
            "data"  => array(
                "title"             => $notify['title'],
                "body"              => $notify['body'],
                "notifyType"        => $row['notification_type']
            ),
            /* "data"          =>  array(
                "notifyType"        => $row['notification_type']
            ), */
            "priority"      => 10
        );

        $data = json_encode($json_data);
        //FCM API end-point
        $url = $config->fcm->url;
        //api_key in Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key
        $server_key = $config->fcm->serverkey;

        //header with content_type api key
        $headers = array(
            'Content-Type:application/json',
            'Authorization:key=' . $server_key
        );
        //CURL request to route notification to FCM connection server (provided by Google)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        if ($result === FALSE) {
            error_log('Oops! FCM Send Error: ' . curl_error($ch));
            $pushStatus = "not-sent";
            $status = false;
        } else {
            $response = json_decode($result);
            if (isset($response) && $response != '' && $response != NULL) {
                if ($response->success > 0) {
                    $pushStatus = "send";
                    $status = true;
                } else {
                    $pushStatus = "not-sent";
                }
            } else {
                $pushStatus = "not-sent";
            }
        }
        curl_close($ch);
        $db->update('data_manager', array('push_status' => $pushStatus), 'dm_id = ' . $subRow['dm_id']);
    }
    if ($status) {
        $pushStatus = "send";
    } else {
        $pushStatus = "not-sent";
    }
    $db->update('push_notification', array('push_status' => $pushStatus), 'id = ' . $row['id']);
}
