#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--apply', '--help']) !== []) {
    fwrite(STDERR, "Usage: php bin/heal-schema.php [--apply]\n");
    exit(1);
}
if (in_array('--help', $arguments, true)) {
    echo "Usage: php bin/heal-schema.php [--apply]\n";
    echo "Preview missing tables and columns from sql/init.sql. Use --apply to repair.\n";
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';
require_once __DIR__ . '/lib/schema-repair-plan.php';

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    if (!$db instanceof Zend_Db_Adapter_Abstract) {
        throw new RuntimeException('No database adapter configured.');
    }
    $schema = (string) $db->fetchOne('SELECT DATABASE()');
    $source = file_get_contents(__DIR__ . '/../sql/init.sql');
    if ($source === false) {
        throw new RuntimeException('Cannot read sql/init.sql.');
    }
    $repair = new SchemaRepairPlan($source);
    $columns = [];
    $indexes = [];
    $rows = $db->fetchAll(
        'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?',
        [$schema]
    );
    if ($rows === null) {
        throw new RuntimeException('Could not inspect database columns.');
    }
    foreach ($rows as $row) {
        $columns[(string) $row['TABLE_NAME']][] = (string) $row['COLUMN_NAME'];
    }
    $indexRows = $db->fetchAll(
        'SELECT DISTINCT TABLE_NAME, INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ?',
        [$schema]
    );
    if ($indexRows === null) {
        throw new RuntimeException('Could not inspect database indexes.');
    }
    foreach ($indexRows as $row) {
        $indexes[(string) $row['TABLE_NAME']][] = (string) $row['INDEX_NAME'];
    }
    foreach (glob(__DIR__ . '/../database/migrations/*.sql') ?: [] as $file) {
        $migration = file_get_contents($file);
        if ($migration === false) {
            throw new RuntimeException('Cannot read migration: ' . basename($file));
        }
        $repair->deferMigrationTargets($migration, $columns);
    }
    $plan = $repair->build($columns, $indexes);
    $apply = in_array('--apply', $arguments, true);
    echo ($apply ? 'Repairing' : 'Previewing') . " database {$schema}\n";
    foreach ($repair->deferred() as $message) {
        echo 'Deferred to migrate.php: ' . $message . "\n";
    }
    foreach ($plan as $step) {
        $sql = $step['sql'];
        echo $sql . ";\n";
        if ($apply) {
            if ($step['create']) {
                // New tables are empty. Allow cycles and parents created later in the plan,
                // while including every constraint in the same atomic CREATE statement.
                $foreignKeyChecks = (int) $db->fetchOne('SELECT @@SESSION.FOREIGN_KEY_CHECKS');
                try {
                    $db->query('SET SESSION FOREIGN_KEY_CHECKS = 0');
                    $db->query($sql);
                } finally {
                    $db->query('SET SESSION FOREIGN_KEY_CHECKS = ' . $foreignKeyChecks);
                }
            } else {
                $db->query($sql);
            }
        }
    }
    echo count($plan) . ($apply ? ' repair(s) applied.' : ' repair(s) planned. No changes made.') . "\n";
    if ($apply) {
        echo "Run php bin/migrate.php -y to resume migrations.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Schema repair failed: ' . $e->getMessage() . "\n");
    exit(1);
}
