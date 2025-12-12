<?php

declare(strict_types=1);

/**
 * db-tools configuration for ePT
 * Reads database credentials from ZF1 application via bootstrap
 */

require_once __DIR__ . '/cli-bootstrap.php';

/** @var Zend_Db_Adapter_Abstract $db */
$db = $application->getBootstrap()->getResource('db');
$config = $db->getConfig();

return [
    'default' => [
        'database' => $config['dbname'] ?? null,
        'host' => $config['host'] ?? 'localhost',
        'port' => isset($config['port']) ? (int) $config['port'] : null,
        'user' => $config['username'] ?? null,
        'password' => $config['password'] ?? null,
        'label' => 'mtbept',
        'output_dir' => __DIR__ . '/backups',
        'retention' => 7,
    ],
];
