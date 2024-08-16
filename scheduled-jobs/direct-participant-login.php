<?php

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

$options = getopt("sd");
if (isset($options['s'])) {
    $skipParticipantMapDelete = false;
} elseif (isset($options['d'])) {
    $skipParticipantMapDelete = true;
} else {
    $skipParticipantMapDelete = false;
}

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$generalModel = new Pt_Commons_General();
try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $output = [];

    $query = $db->select()
        ->from(array('p' => 'participant'), array('unique_identifier'));
    $pResult = $db->fetchAll($query);
    foreach ($pResult as $pRow) {
        $dmsql = $db->select()->from('data_manager')
            ->where("data_manager_type LIKE ?", 'participant')
            ->where("primary_email LIKE ?", $pRow['unique_identifier']);
        $dmresult = $db->fetchRow($dmsql);
        if (!$dmresult) {
            $dataManagerData = [
                'first_name'        => ($pRow['first_name']),
                'last_name'         => ($pRow['last_name']),
                'institute'         => ($pRow['institute_name']),
                'mobile'            => ($pRow['mobile']),
                'secondary_email'   => ($pRow['additional_email']),
                'primary_email'     => $pRow['unique_identifier'],
                'password'          => 'ept1@)(*&^',
                'force_password_reset' => 1,
                'data_manager_type' => 'participant',
                'created_on'        => new Zend_Db_Expr('now()'),
                'status'            => 'active'
            ];
            $db->insert('data_manager', $dataManagerData);
            $dmId = $db->lastInsertId();
            if ($dmId > 0) {
                if ($skipParticipantMapDelete) {
                    $db->delete('participant_manager_map', array(
                        'participant_id' => $pRow['participant_id']
                    ));
                }
                $db->insert('participant_manager_map', array(
                    'dm_id' => $dmId,
                    'participant_id' => $pRow['participant_id']
                ));
            }
        }
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
}
