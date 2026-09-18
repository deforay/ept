<?php

declare(strict_types=1);

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\TransactionStatement;

require_once __DIR__ . '/migration-sql.php';

/**
 * Read only schema definitions from the dump. Never execute its data, USE, or SET statements.
 *
 * @phpstan-type TableDefinition array{columns: array<string, CreateDefinition>, keys: list<CreateDefinition>, options: string}
 */
final class SchemaRepairPlan
{
    /** @var array<string, TableDefinition> */
    private array $tables = [];

    /** @var array<string, string> */
    private array $deferredTables = [];

    /** @var array<string, array<string, string>> */
    private array $deferredColumns = [];

    public function __construct(string $sql)
    {
        $parser = new Parser($sql);
        if ($parser->errors !== []) {
            throw new RuntimeException('Cannot parse init.sql: ' . $parser->errors[0]->getMessage());
        }
        foreach ($this->statements($parser->statements) as $statement) {
            if ($statement instanceof CreateStatement && $statement->options?->has('TABLE')) {
                $table = $statement->name?->table;
                if ($table === null || !is_array($statement->fields) || $statement->fields === []) {
                    throw new RuntimeException('Expected an explicit CREATE TABLE definition in init.sql.');
                }
                if ($statement->partitionBy !== null) {
                    throw new RuntimeException("Partitioned table {$table} needs explicit repair support.");
                }
                $this->tables[$table] = [
                    'columns' => [],
                    'keys' => [],
                    'options' => (string) $statement->entityOptions,
                ];
                foreach ($statement->fields as $field) {
                    $this->addDefinition($table, $field);
                }
            } elseif ($statement instanceof AlterStatement) {
                $this->readAlter($statement->build());
            }
        }
        if ($this->tables === []) {
            throw new RuntimeException('No table definitions found in init.sql.');
        }
    }

    /**
     * @param array<string, list<string>> $columns Existing tables and columns.
     * @param array<string, list<string>> $indexes Existing index names, including PRIMARY.
     * @return list<array{table: string, create: bool, sql: string}>
     */
    public function build(array $columns, array $indexes = []): array
    {
        $alter = [];
        $create = [];
        foreach ($this->tables as $table => $definition) {
            if (isset($this->deferredTables[$table])) {
                continue;
            }
            $quotedTable = self::quote($table);
            if (!array_key_exists($table, $columns)) {
                // Include keys, final AUTO_INCREMENT definitions and foreign keys atomically.
                // An interrupted run must never leave a half-created table that a rerun skips.
                $fields = array_merge(array_values($definition['columns']), $definition['keys']);
                $create[] = [
                    'table' => $table,
                    'create' => true,
                    'sql' => "CREATE TABLE {$quotedTable} (\n  "
                        . implode(",\n  ", array_map(static fn (CreateDefinition $field): string => $field->build(), $fields))
                        . "\n) " . $definition['options'],
                ];
                continue;
            }
            $actions = [];
            foreach ($definition['columns'] as $column => $field) {
                // PHP converts numeric column names (for example Western blot band `160`) to integer keys.
                $column = (string) $column;
                if (in_array($column, $columns[$table], true) || isset($this->deferredColumns[$table][$column])) {
                    continue;
                }
                $actions[] = 'ADD COLUMN ' . $field->build();
                if ($field->options?->has('AUTO_INCREMENT')) {
                    // MySQL requires an AUTO_INCREMENT column to be indexed in the same ALTER.
                    // Never replace an existing primary key to satisfy that requirement.
                    $key = $this->autoIncrementKey($table, $column, $indexes[$table] ?? []);
                    $actions[] = 'ADD ' . $key->build();
                }
            }
            if ($actions !== []) {
                $alter[] = ['table' => $table, 'create' => false, 'sql' => "ALTER TABLE {$quotedTable} " . implode(', ', $actions)];
            }
        }
        // Referenced columns on existing tables must exist before new child tables are created.
        return array_merge($alter, $create);
    }

    /**
     * Do not create empty destinations ahead of migrations that move existing data there.
     *
     * @param array<string, list<string>> $columns
     */
    public function deferMigrationTargets(string $sql, array $columns): void
    {
        $parser = new Parser(preg_replace('/^(\s*--)(?=\S)/m', '$1 ', $sql) ?? $sql);
        if ($parser->errors !== []) {
            throw new RuntimeException('Cannot inspect migration: ' . $parser->errors[0]->getMessage());
        }
        foreach ($this->statements($parser->statements) as $statement) {
            $query = $statement->build();
            $oldTable = $newTable = null;
            if (preg_match('/^RENAME\s+TABLE\s+`?([a-z0-9_]+)`?\s+TO\s+`?([a-z0-9_]+)`?$/i', $query, $m)
                || preg_match('/^ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+RENAME\s+(?:TO\s+)?`?([a-z0-9_]+)`?$/i', $query, $m)) {
                [, $oldTable, $newTable] = $m;
            } elseif ($statement instanceof CreateStatement && $statement->select !== null
                && preg_match('/\bFROM\s+`?([a-z0-9_]+)`?/i', $statement->select->build(), $m)) {
                $oldTable = $m[1];
                $newTable = $statement->name?->table;
            }
            if ($oldTable !== null && $newTable !== null && isset($columns[$oldTable]) && !isset($columns[$newTable])) {
                $this->deferredTables[$newTable] = $oldTable;
            }
            if (preg_match('/^ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+(.+)$/is', $query, $alter)) {
                foreach (migration_split_clauses($alter[2]) as $clause) {
                    if (preg_match('/^CHANGE\s+(?:COLUMN\s+)?`?([a-z0-9_]+)`?\s+`?([a-z0-9_]+)`?\s+/i', $clause, $m)
                        || preg_match('/^RENAME\s+COLUMN\s+`?([a-z0-9_]+)`?\s+TO\s+`?([a-z0-9_]+)`?$/i', $clause, $m)) {
                        [, $old, $new] = $m;
                        if ($old !== $new && in_array($old, $columns[$alter[1]] ?? [], true)
                            && !in_array($new, $columns[$alter[1]] ?? [], true)) {
                            $this->deferredColumns[$alter[1]][$new] = $old;
                        }
                    }
                }
            }
        }
    }

    /** @return list<string> */
    public function deferred(): array
    {
        $messages = [];
        foreach ($this->deferredTables as $new => $old) {
            if (isset($this->tables[$new])) {
                $messages[] = "{$new}: migration moves data from {$old}";
            }
        }
        foreach ($this->deferredColumns as $table => $columns) {
            foreach ($columns as $new => $old) {
                if (isset($this->tables[$table]['columns'][$new])) {
                    $messages[] = "{$table}.{$new}: migration renames {$old}";
                }
            }
        }
        return $messages;
    }

    /**
     * @param array<Statement> $statements
     * @return Generator<int, Statement>
     */
    private function statements(array $statements): Generator
    {
        foreach ($statements as $statement) {
            if ($statement instanceof TransactionStatement) {
                yield from $this->statements($statement->statements ?? []);
            } else {
                yield $statement;
            }
        }
    }

    private function readAlter(string $sql): void
    {
        if (!preg_match('/^ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+(.+)$/is', $sql, $match)) {
            throw new RuntimeException('Unsupported ALTER in init.sql: ' . $sql);
        }
        $table = $match[1];
        if (!isset($this->tables[$table])) {
            throw new RuntimeException("ALTER has no CREATE definition for {$table}.");
        }
        foreach (migration_split_clauses($match[2]) as $clause) {
            // Dump counters describe old rows, not schema. New empty tables start at their default counter.
            if (preg_match('/^AUTO_INCREMENT\s*=\s*\d+$/i', $clause)) {
                continue;
            }
            if (!preg_match('/^(ADD|MODIFY)\s+(?:COLUMN\s+)?(.+)$/is', $clause, $operation)) {
                throw new RuntimeException('Unsupported schema operation in init.sql: ' . $clause);
            }
            $parsed = new Parser('CREATE TABLE `_repair_definition` (' . $operation[2] . ')');
            $statement = $parsed->statements[0] ?? null;
            if ($parsed->errors !== [] || !$statement instanceof CreateStatement
                || !is_array($statement->fields) || count($statement->fields) !== 1) {
                throw new RuntimeException('Cannot read schema definition: ' . $clause);
            }
            $this->addDefinition($table, $statement->fields[0]);
        }
    }

    private function addDefinition(string $table, CreateDefinition $field): void
    {
        if ($field->type !== null && $field->name !== null) {
            $this->tables[$table]['columns'][$field->name] = $field;
        } elseif ($field->key !== null) {
            $this->tables[$table]['keys'][] = $field;
        } else {
            throw new RuntimeException("Unsupported definition in table {$table}: " . $field->build());
        }
    }

    /** @param list<string> $existing */
    private function autoIncrementKey(string $table, string $column, array $existing): CreateDefinition
    {
        foreach ($this->tables[$table]['keys'] as $field) {
            $key = $field->key;
            if ($key === null || $field->references !== null || ($key->columns[0]['name'] ?? null) !== $column) {
                continue;
            }
            $name = $key->type === 'PRIMARY KEY' ? 'PRIMARY' : $key->name;
            if ($name !== null && !in_array($name, $existing, true)) {
                return $field;
            }
        }
        throw new RuntimeException("Cannot add AUTO_INCREMENT column {$table}.{$column} without changing an existing key. Review its indexes first.");
    }

    private static function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
