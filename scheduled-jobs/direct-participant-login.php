<?php
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

use Symfony\Component\Uid\Ulid;

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
$common = new Application_Service_Common();
try {

    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    $error = "";
    $output = [];

    $query = $db->select()->from(['p' => 'participant'])->where('ulid is null');
    $pResult = $db->fetchAll($query);
    foreach ($pResult as $pRow) {
        echo '...For participant (' . $pRow['unique_identifier'] . ')...' . PHP_EOL;

        $error = "PARTICIPANT UNIQUE ID => " . $pRow['unique_identifier'];
        $ulid = (new Ulid())->toRfc4122();
        $db->update('participant', ['ulid' => $ulid, 'updated_on' => new Zend_Db_Expr('now()')], 'participant_id = ' . $pRow['participant_id']);

        $dmsql = $db->select()->from('data_manager')
            ->where("data_manager_type LIKE ?", 'participant')
            ->where("primary_email LIKE '" . $pRow['unique_identifier'] . "'");
        $dmresult = $db->fetchRow($dmsql);
        $dataManagerData = [
            'participant_ulid'  => $ulid,
            'first_name'        => $pRow['first_name'],
            'last_name'         => $pRow['last_name'],
            'institute'         => $pRow['institute_name'],
            'mobile'            => $pRow['mobile'],
            'secondary_email'   => $pRow['additional_email'],
            'password'          => 'ept1@)(*&^',
            'force_password_reset' => 1,
            'data_manager_type' => 'participant',
            'created_on'        => new Zend_Db_Expr('now()'),
            'status'            => 'active'
        ];
        $dmId = 0;
        if (isset($dmresult) && !empty($dmresult)) {
            echo 'updating...' . PHP_EOL;

            $where = 'primary_email like "' . $pRow['unique_identifier'] . '"';
            if ($pRow['unique_identifier'] != $dmresult['primary_email']) {
                $dataManagerData['primary_email'] = $pRow['unique_identifier'];
            }
            $db->update('data_manager', $dataManagerData, $where);
            $dmId = $dmresult['dm_id'];
        } else {
            echo 'inserting...' . PHP_EOL;

            $dataManagerData['primary_email'] = $pRow['unique_identifier'];
            $db->insert('data_manager', $dataManagerData);
            $dmId = $db->lastInsertId();
        }
        if ($dmId > 0) {
            echo "data manager saved..." . PHP_EOL;
            if ($skipParticipantMapDelete === true) {
                $deleted = $db->delete(
                    'participant_manager_map',
                    'participant_id = ' . $pRow['participant_id'] . ' AND 
                    dm_id NOT IN ( SELECT dm_id FROM data_manager WHERE IFNULL(ptcc, "no") like "yes")'
                );
                if ($deleted) {
                    echo " participant and login previous mapping removed..." . PHP_EOL;
                }
                $inserted = $db->insert('participant_manager_map', [
                    'dm_id' => $dmId,
                    'participant_id' => $pRow['participant_id']
                ]);
            } else {
                $inserted = $common->insertIgnore('participant_manager_map', [
                    'dm_id' => $dmId,
                    'participant_id' => $pRow['participant_id']
                ]);
            }
            if ($inserted) {
                echo "participant manager created for... participant / DM => " . $pRow['participant_id'] . '/' . $dmId . PHP_EOL;
            }
        }
        echo '_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*' . PHP_EOL;
    }
} catch (Exception $e) {
    error_log('_*__*_*_*_*_*_*_*_*_*_*_*_*_*_*_*_*');
    error_log('ERROR ON direct-participant-login.php CRON FILE (' . $error . ')');
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
}
