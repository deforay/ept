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

    unset($envConfig['evaluation']);

    $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
    $existingGlobalKeys = $globalConfigDb->getAdapter()->fetchCol(
        $globalConfigDb->select()->from($globalConfigDb->info('name'), array('name'))
    );
    $existingGlobalLookup = array_flip($existingGlobalKeys);

    $toSnakeCase = static function ($value) {
        $value = str_replace('.', '_', $value);
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value);
        $value = preg_replace('/_+/', '_', $value);
        return strtolower($value);
    };

    foreach ($envConfig as $key => $value) {
        $queue = array(array($key, $value));

        while (!empty($queue)) {
            [$currentKey, $currentValue] = array_pop($queue);

            if (is_array($currentValue)) {
                foreach ($currentValue as $childKey => $childValue) {
                    $queue[] = array($currentKey . '_' . $childKey, $childValue);
                }
                continue;
            }

            $snakeKey = $toSnakeCase($currentKey);
            if (isset($existingGlobalLookup[$snakeKey])) {
                continue;
            }

            $globalConfigDb->insert(array(
                'name' => $snakeKey,
                'value' => is_array($currentValue) ? json_encode($currentValue, true) : $currentValue
            ));
            $existingGlobalLookup[$snakeKey] = true;
        }
    }
} catch (Exception $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}
