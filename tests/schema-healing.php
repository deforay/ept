<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bin/lib/schema-repair-plan.php';
require_once __DIR__ . '/../bin/lib/migration-sql.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$source = file_get_contents(__DIR__ . '/../sql/init.sql');
$repair = new SchemaRepairPlan($source);
$plan = $repair->build([]);
check(count($plan) === preg_match_all('/^CREATE TABLE /m', $source), 'Read all tables inside the dump transaction.');
$schema = [];
$indexes = [];
foreach ($plan as $step) {
    $parser = new PhpMyAdmin\SqlParser\Parser($step['sql']);
    check($parser->errors === [], 'Repair SQL must parse: ' . $step['table']);
    check(count($parser->statements) === 1, 'Each repair must be one statement.');
    $statement = $parser->statements[0];
    check($statement instanceof PhpMyAdmin\SqlParser\Statements\CreateStatement, 'Never execute dump data or session commands.');
    $schema[$step['table']] = [];
    foreach ($statement->fields as $field) {
        if ($field->type !== null) {
            $schema[$step['table']][] = $field->name;
        } elseif ($field->key !== null && $field->references === null) {
            $indexes[$step['table']][] = $field->key->type === 'PRIMARY KEY' ? 'PRIMARY' : $field->key->name;
        }
    }
    if ($step['table'] === 'home_sections') {
        check(str_contains($step['sql'], 'AUTO_INCREMENT') && str_contains($step['sql'], 'PRIMARY KEY'), 'Fold dump ALTERs into atomic CREATE.');
    }
    if ($step['table'] === 'participant_feedback_answer') {
        check(str_contains($step['sql'], 'FOREIGN KEY'), 'Preserve foreign keys on newly created tables.');
    }
}
check($repair->build($schema, $indexes) === [], 'A second run must leave existing schema untouched.');
foreach ($schema as $table => $columns) {
    $missingTable = $schema;
    unset($missingTable[$table]);
    $missingPlan = $repair->build($missingTable, $indexes);
    check(count($missingPlan) === 1 && $missingPlan[0]['table'] === $table && $missingPlan[0]['create'], 'Repair any missing table: ' . $table);
    foreach ($columns as $column) {
        $missingColumn = $schema;
        $missingColumn[$table] = array_values(array_diff($columns, [$column]));
        // An index on a missing column cannot exist. Rebuild a required AUTO_INCREMENT key with its column.
        $columnPlan = $repair->build($missingColumn, array_replace($indexes, [$table => []]));
        check(count($columnPlan) === 1 && !$columnPlan[0]['create'], 'Repair any missing column: ' . $table . '.' . $column);
        check(str_contains($columnPlan[0]['sql'], '`' . $column . '`'), 'Use the missing column definition.');
    }
}
$legacySchema = $schema;
unset($legacySchema['r_testkitnames'], $legacySchema['queue_report_generation']);
$legacySchema['r_testkitname_dts'] = ['TestKitName_ID'];
$legacySchema['evaluation_queue'] = ['queue_id'];
$legacySchema['response_result_generic_test'] = ['result', 'repeat_result'];
$legacyRepair = new SchemaRepairPlan($source);
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $file) {
    $legacyRepair->deferMigrationTargets(file_get_contents($file), $legacySchema);
}
$legacyPlan = $legacyRepair->build($legacySchema, $indexes);
foreach ($legacyPlan as $step) {
    check(!in_array($step['table'], ['r_testkitnames', 'queue_report_generation'], true), 'Do not create empty destinations before table copies or renames.');
    if ($step['table'] === 'response_result_generic_test') {
        check(!str_contains($step['sql'], 'ADD COLUMN `result_1`') && !str_contains($step['sql'], 'ADD COLUMN `result_2`'),
            'Leave rename destinations to migrations so legacy result values survive.');
    }
}
check(count($legacyRepair->deferred()) === 4, 'Report every deferred destination.');
$fixture = new SchemaRepairPlan("START TRANSACTION;
CREATE TABLE arbitrary_table (id int NOT NULL, detail ENUM('x,y','z') DEFAULT 'x,y');
ALTER TABLE arbitrary_table ADD PRIMARY KEY (id);
ALTER TABLE arbitrary_table MODIFY id int NOT NULL AUTO_INCREMENT;
INSERT INTO arbitrary_table VALUES (9, 'z');
COMMIT;");
$fixturePlan = $fixture->build(['arbitrary_table' => ['detail']]);
check(str_contains($fixturePlan[0]['sql'], 'AUTO_INCREMENT') && str_contains($fixturePlan[0]['sql'], 'PRIMARY KEY'), 'Add an AUTO_INCREMENT column and key atomically.');
try {
    $fixture->build(['arbitrary_table' => ['detail']], ['arbitrary_table' => ['PRIMARY']]);
    throw new LogicException('Conflicting primary key must stop repair.');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'existing key'), 'Explain conflicting keys.');
}
try {
    $unsupported = new SchemaRepairPlan('CREATE TABLE example (id int); ALTER TABLE example DROP id;');
    throw new LogicException('Unsupported dump DDL must stop repair.');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(), 'Unsupported'), 'Explain unsupported definitions.');
}

check(count(migration_split_clauses("ADD a ENUM('yes','no'), ADD b DECIMAL(10,4), ADD c VARCHAR(30) COMMENT 'x, add y'")) === 3,
    'Do not split quoted strings or type parameters.');

// Load the real routing helpers without bootstrapping or touching the configured database.
require_once __DIR__ . '/../bin/lib/migration-helpers.php';
$DRY_RUN = false;

class HealingTestAdapter extends Zend_Db_Adapter_Pdo_Mysql
{
    public array $columns = ['sd_scaling_factor'];
    public array $queries = [];

    public function __construct() {}

    public function fetchOne($sql, $bind = [])
    {
        if ($sql === 'SELECT DATABASE()') {
            return 'test';
        }
        if (str_contains($sql, 'information_schema.COLUMNS')) {
            return in_array($bind[2], $this->columns, true);
        }
        throw new RuntimeException('Unexpected schema lookup: ' . $sql);
    }

    public function query($sql, $bind = [])
    {
        $this->queries[] = $sql;
        if (preg_match('/ADD\s+(?:COLUMN\s+)?`([^`]+)`/i', $sql, $m)) {
            $this->columns[] = $m[1];
        }
        return null;
    }
}

$db = new HealingTestAdapter();
$sql = "ALTER TABLE `r_possibleresult` ADD `sd_scaling_factor` VARCHAR(256) NULL AFTER `low_range`, ADD `uncertainy_scaling_factor` VARCHAR(256) NULL AFTER `sd_scaling_factor`, ADD `uncertainy_threshold` VARCHAR(256) NULL AFTER `uncertainy_scaling_factor`";
check(handle_idempotent_ddl($db, $sql) === MIG_EXECUTED, 'Repair the remaining ADDs when the first exists.');
check(count($db->queries) === 2, 'Only the missing columns should execute.');
check(handle_idempotent_ddl($db, $sql) === MIG_SKIPPED, 'A completed multi-ADD must be skipped.');
check(count($db->queries) === 2, 'A rerun must execute no DDL.');
$db->columns = [];
$db->queries = [];
$DRY_RUN = true;
ob_start();
handle_idempotent_ddl($db, $sql);
$preview = ob_get_clean();
check($db->queries === [], 'Dry run must never execute DDL.');
check(substr_count($preview, '[DRY-RUN]') === 3, 'Preview every missing column.');
$DRY_RUN = false;
check(handle_idempotent_ddl($db, "ALTER TABLE `t` ADD `a` INT, DROP `b`") === MIG_NOT_HANDLED,
    'Mixed ALTER actions must not use the single-column handler.');

$composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true, 512, JSON_THROW_ON_ERROR);
check($composer['scripts']['migrate'][0] === '@heal-schema', 'Composer must repair before migrating.');
check(in_array('@migrate', $composer['scripts']['post-update'], true), 'Post-update must include repair via migrate.');
echo "Schema healing regression checks passed.\n";
