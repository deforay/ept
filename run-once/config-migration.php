#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

try {
    // Load configuration using Zend_Config_Ini
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);

    // Convert Zend_Config object to array
    $envConfig = $conf->toArray();

    $schemeConfigDb = new Application_Model_DbTable_SchemeConfig();
    $existingKeys = $schemeConfigDb->getAdapter()->fetchCol(
        $schemeConfigDb->select()->from($schemeConfigDb->info('name'), array('scheme_config_name'))
    );
    $existingLookup = array_flip($existingKeys);

    foreach ($envConfig['evaluation'] as $key => $value) {
        if (isset($existingLookup[$key])) {
            continue;
        }

        $schemeConfigDb->insert(array(
            'scheme_config_name' => $key,
            'scheme_config_value' => is_array($value) ? json_encode($value, true) : $value
        ));
    }

} catch (Exception $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
