#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Rebuilds sql/init.sql from the migrations.
 *
 *   php bin/dev/regenerate-init-sql.php
 *   php bin/dev/regenerate-init-sql.php --output /tmp/init.sql --keep
 *
 * The script loads the current sql/init.sql into a throwaway database, runs
 * bin/migrate.php on it, and dumps the result back to sql/init.sql. It never
 * reads your own database, so local participants, settings and test data stay
 * out of the file.
 *
 * The dump keeps the phpMyAdmin layout that init.sql already uses: tables, then
 * data, then indexes, AUTO_INCREMENT and foreign keys. bin/heal-schema.php reads
 * that layout, and a git diff shows only what the migrations changed.
 *
 * Every row in the throwaway database is seed data, because it came from
 * init.sql or from a migration. The dump writes the rows of every table that has
 * any.
 *
 * --output <file>  writes the dump to that file instead of sql/init.sql.
 * --keep           leaves the throwaway database in place for inspection.
 *
 * Needs the mysql client and the database account from application.ini, which
 * must be allowed to create and drop databases.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require_once __DIR__ . '/../../cli-bootstrap.php';

$root = dirname(__DIR__, 2);
$output = "$root/sql/init.sql";
$keep = false;
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--keep') {
        $keep = true;
    } elseif ($arg === '--output' && isset($argv[$i + 1])) {
        $output = $argv[++$i];
    } elseif (str_starts_with($arg, '--output=')) {
        $output = substr($arg, 9);
    } else {
        fwrite(STDERR, "Usage: php bin/dev/regenerate-init-sql.php [--output <file>] [--keep]\n");
        exit(2);
    }
}

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$params = $conf->resources->db->params->toArray();
$host = (string) ($params['host'] ?? '127.0.0.1');
$port = (int) ($params['port'] ?? 3306);
$scratch = 'ept_initsql_' . getmypid();

// The mysql client reads the password from a file, so it never shows in the process list.
$defaults = tempnam(sys_get_temp_dir(), 'ept-my');
file_put_contents($defaults, "[client]\nhost=$host\nport=$port\nuser={$params['username']}\npassword=\"" . addcslashes((string) ($params['password'] ?? ''), '"\\') . "\"\n");
chmod($defaults, 0600);

$cleanup = static function () use ($scratch, $keep, $defaults): void {
    if ($keep) {
        echo "Kept the throwaway database `$scratch`.\n";
    } else {
        run(['mysql', "--defaults-extra-file=$defaults", '-e', "DROP DATABASE IF EXISTS `$scratch`"], null);
    }
    @unlink($defaults);
};

try {
    echo "1/3 Loading sql/init.sql into `$scratch`\n";
    $source = file_get_contents("$root/sql/init.sql");
    if ($source === false) {
        throw new RuntimeException('Cannot read sql/init.sql.');
    }
    // The dump creates and selects `ept`. Point both at the throwaway database.
    $source = preg_replace(
        ['/^CREATE DATABASE IF NOT EXISTS `[^`]+`/m', '/^USE `[^`]+`;/m'],
        ["CREATE DATABASE IF NOT EXISTS `$scratch`", "USE `$scratch`;"],
        $source
    );
    run(['mysql', "--defaults-extra-file=$defaults"], $source);

    echo "2/3 Running the migrations\n";
    run([PHP_BINARY, "$root/bin/migrate.php", '-y', '-q'], null, ['MIGRATE_DB_NAME' => $scratch]);

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$scratch;charset=utf8mb4", (string) $params['username'], (string) ($params['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $version = (string) $pdo->query("SELECT `value` FROM system_config WHERE config = 'app_version'")->fetchColumn();
    if ($version !== APP_VERSION) {
        throw new RuntimeException("The migrations stopped at $version, but APP_VERSION is " . APP_VERSION . '. Fix the failing migration first.');
    }

    echo "3/3 Writing $output\n";
    if (file_put_contents($output, buildDump($pdo, $scratch)) === false) {
        throw new RuntimeException("Cannot write $output.");
    }
    echo "Done. init.sql is at $version. Review it with: git diff --stat sql/init.sql\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    $cleanup();
    exit(1);
}
$cleanup();

/**
 * Runs a command, feeding it $stdin, and throws with its output if it fails.
 *
 * @param list<string> $command
 * @param array<string, string> $env
 */
function run(array $command, ?string $stdin, array $env = []): void
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + getenv());
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start ' . $command[0] . '.');
    }
    fwrite($pipes[0], (string) $stdin);
    fclose($pipes[0]);
    // Read both pipes before closing them, or the child can die on SIGPIPE.
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException(basename($command[1] ?? $command[0]) . " failed:\n" . trim($err . "\n" . $out));
    }
}

/** Builds the dump in the phpMyAdmin layout of sql/init.sql. */
function buildDump(PDO $pdo, string $schema): string
{
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    // phpMyAdmin lists tables in MySQL's case-insensitive order, where "_" sorts after letters.
    usort($tables, static fn (string $a, string $b): int => strcmp(strtoupper($a), strtoupper($b)));

    $blocks = [];
    $indexes = $autoIncrement = $constraints = '';
    foreach ($tables as $table) {
        $create = (string) $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
        $lines = explode("\n", $create);
        $close = array_pop($lines);
        array_shift($lines);
        $columns = $keys = $fks = [];
        $aiColumn = null;
        foreach ($lines as $line) {
            $line = rtrim(trim($line), ',');
            if (str_starts_with($line, '`')) {
                // phpMyAdmin writes these attributes in capitals.
                $line = (string) preg_replace_callback('/ (unsigned|zerofill)\b/', static fn (array $m): string => strtoupper($m[0]), $line);
                if (preg_match('/ AUTO_INCREMENT\b/', $line)) {
                    $aiColumn = $line;
                    $line = (string) preg_replace('/ AUTO_INCREMENT\b/', '', $line);
                }
                $columns[] = $line;
            } elseif (preg_match('/^CONSTRAINT `[^`]+` FOREIGN KEY/', $line)) {
                $fks[] = $line;
            } elseif (preg_match('/^(PRIMARY|UNIQUE|FULLTEXT|SPATIAL)? ?KEY\b/', $line)) {
                $keys[] = $line;
            } else {
                // CHECK constraints and anything else stay inside CREATE TABLE.
                $columns[] = $line;
            }
        }
        $counter = preg_match('/ AUTO_INCREMENT=(\d+)/', $close, $m) ? (int) $m[1] : null;
        $close = (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', $close);

        $blocks[] = "\n--\n-- Table structure for table `$table`\n--\n\n"
            . "CREATE TABLE `$table` (\n  " . implode(",\n  ", $columns) . "\n$close;\n"
            . tableRows($pdo, $schema, $table);
        if ($keys !== []) {
            $indexes .= "\n--\n-- Indexes for table `$table`\n--\nALTER TABLE `$table`\n  ADD "
                . implode(",\n  ADD ", $keys) . ";\n";
        }
        if ($aiColumn !== null) {
            $autoIncrement .= "\n--\n-- AUTO_INCREMENT for table `$table`\n--\nALTER TABLE `$table`\n  MODIFY $aiColumn"
                . ($counter !== null ? ", AUTO_INCREMENT=$counter" : '') . ";\n";
        }
        if ($fks !== []) {
            $constraints .= "\n--\n-- Constraints for table `$table`\n--\nALTER TABLE `$table`\n  ADD "
                . implode(",\n  ADD ", $fks) . ";\n";
        }
    }

    return "-- ePT database schema and seed data\n"
        . "-- Generated by bin/dev/regenerate-init-sql.php. Do not edit by hand.\n"
        . "-- Schema changes belong in database/migrations.\n\n"
        . "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n"
        . "--\n-- Database: `ept`\n--\n"
        . "CREATE DATABASE IF NOT EXISTS `ept` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;\nUSE `ept`;\n\n"
        . "-- --------------------------------------------------------\n"
        . implode("\n-- --------------------------------------------------------\n", $blocks)
        . "\n--\n-- Indexes for dumped tables\n--\n" . $indexes
        . "\n--\n-- AUTO_INCREMENT for dumped tables\n--\n" . $autoIncrement
        . "\n--\n-- Constraints for dumped tables\n--\n" . $constraints
        . "COMMIT;\n";
}

/** The INSERT for a table's rows, or '' when it has none. */
function tableRows(PDO $pdo, string $schema, string $table): string
{
    $types = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
    $types->execute([$schema, $table]);
    $columns = $types->fetchAll(PDO::FETCH_KEY_PAIR);
    $numeric = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double'];
    $binary = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];

    $values = [];
    foreach ($pdo->query("SELECT * FROM `$table`") as $row) {
        $cells = [];
        foreach ($columns as $name => $type) {
            $value = $row[$name];
            if ($value === null) {
                $cells[] = 'NULL';
            } elseif (in_array($type, $numeric, true)) {
                $cells[] = (string) $value;
            } elseif (in_array($type, $binary, true)) {
                $cells[] = $value === '' ? "''" : '0x' . strtoupper(bin2hex((string) $value));
            } else {
                $cells[] = $pdo->quote((string) $value);
            }
        }
        $values[] = '(' . implode(', ', $cells) . ')';
    }
    if ($values === []) {
        return '';
    }
    $names = implode(', ', array_map(static fn (string $c): string => "`$c`", array_keys($columns)));
    return "\n--\n-- Dumping data for table `$table`\n--\n\n"
        . "INSERT INTO `$table` ($names) VALUES\n" . implode(",\n", $values) . ";\n";
}
