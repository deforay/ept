#!/usr/bin/env php
<?php

// bin/check-participant-emails.php
//
// Walks a batch of participant + data_manager rows, validates each address
// (syntax + MX), and stamps {*_status, email_status_checked_at}. Runs on a
// schedule (see ScheduledTasks.php) so the bulk-mail path can filter on the
// stamped status instead of doing DNS in the send loop.
//
// Rows are picked oldest-checked first; rows where checked_at is older than
// --recheck-days (default 30) are revisited, so dead domains that come back
// online and stale 'invalid_*' rows are retried automatically.

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

$cliMode = php_sapi_name() === 'cli';

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . 'Cancelled by user.' . PHP_EOL;
        exit(130);
    });
}

try {
    require_once __DIR__ . '/../cli-bootstrap.php';
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    if (!$cliMode) {
        echo 'This script can only be run from the command line.' . PHP_EOL;
        exit(1);
    }

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    $opts = getopt('', ['batch::', 'recheck-days::', 'table::', 'dry-run', 'quiet']);
    $batchSize    = max(1, (int) ($opts['batch']         ?? 500));
    $recheckDays  = max(1, (int) ($opts['recheck-days']  ?? 30));
    $tableFilter  = strtolower((string) ($opts['table']  ?? 'all'));
    $dryRun       = array_key_exists('dry-run', $opts);
    $quiet        = array_key_exists('quiet', $opts);

    if (!in_array($tableFilter, ['all', 'participant', 'data_manager'], true)) {
        $io->error("Invalid --table value '$tableFilter'. Use participant | data_manager | all.");
        exit(1);
    }

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    if (!$quiet) {
        $io->title('Participant email validity check');
        $io->writeln(sprintf(
            ' batch=%d  recheck-days=%d  table=%s  dry-run=%s',
            $batchSize,
            $recheckDays,
            $tableFilter,
            $dryRun ? 'yes' : 'no'
        ));
    }

    // Shared MX cache across both tables for the run — same domain checked once.
    $mxCache = [];

    $totals = [
        'participant'  => ['scanned' => 0, 'valid' => 0, 'invalid_syntax' => 0, 'invalid_domain' => 0, 'unknown' => 0],
        'data_manager' => ['scanned' => 0, 'valid' => 0, 'invalid_syntax' => 0, 'invalid_domain' => 0, 'unknown' => 0],
    ];

    /**
     * Classify a single address. Returns one of unknown|valid|invalid_syntax|invalid_domain.
     * Empty/null → 'unknown' (no email to check; not an error).
     */
    $classify = function (?string $raw) use (&$mxCache): string {
        $addr = trim((string) $raw);
        if ($addr === '') {
            return 'unknown';
        }
        $normalized = Application_Service_Common::validateEmail($addr);
        if ($normalized === null) {
            return 'invalid_syntax';
        }
        $domain = strtolower(substr(strrchr($normalized, '@'), 1));
        if (!isset($mxCache[$domain])) {
            $mxCache[$domain] = checkdnsrr($domain, 'MX');
        }
        return $mxCache[$domain] ? 'valid' : 'invalid_domain';
    };

    $processTable = function (string $table, string $pk, string $primaryCol, string $secondaryCol, string $primaryStatusCol, string $secondaryStatusCol) use ($db, $batchSize, $recheckDays, $dryRun, $quiet, $io, $classify, &$totals): void {
        $sql = sprintf(
            'SELECT %s AS pk, %s AS primary_addr, %s AS secondary_addr
               FROM %s
              WHERE (
                       (%s IS NOT NULL AND %s <> "")
                    OR (%s IS NOT NULL AND %s <> "")
                    )
                AND (email_status_checked_at IS NULL
                     OR email_status_checked_at < DATE_SUB(NOW(), INTERVAL %d DAY))
              ORDER BY email_status_checked_at ASC
              LIMIT %d',
            $db->quoteIdentifier($pk),
            $db->quoteIdentifier($primaryCol),
            $db->quoteIdentifier($secondaryCol),
            $db->quoteIdentifier($table),
            $db->quoteIdentifier($primaryCol),
            $db->quoteIdentifier($primaryCol),
            $db->quoteIdentifier($secondaryCol),
            $db->quoteIdentifier($secondaryCol),
            $recheckDays,
            $batchSize
        );

        $rows = $db->fetchAll($sql);
        if (!$rows) {
            if (!$quiet) {
                $io->writeln("  $table: nothing to process");
            }
            return;
        }

        if (!$quiet) {
            $io->section($table . ' (' . count($rows) . ' rows)');
        }

        foreach ($rows as $row) {
            $primaryStatus   = $classify($row['primary_addr']);
            $secondaryStatus = $classify($row['secondary_addr']);

            $totals[$table]['scanned']++;
            // Bookkeeping: count the "worst" outcome of the two; "valid" wins
            // over "unknown" wins over "invalid_*"; this is just for the summary.
            $bookkeep = (in_array('valid', [$primaryStatus, $secondaryStatus], true))
                ? 'valid'
                : (($primaryStatus === 'unknown' && $secondaryStatus === 'unknown')
                    ? 'unknown'
                    : (in_array('invalid_domain', [$primaryStatus, $secondaryStatus], true)
                        ? 'invalid_domain'
                        : 'invalid_syntax'));
            $totals[$table][$bookkeep]++;

            if ($dryRun) {
                continue;
            }

            $db->update(
                $table,
                [
                    $primaryStatusCol         => $primaryStatus,
                    $secondaryStatusCol       => $secondaryStatus,
                    'email_status_checked_at' => new Zend_Db_Expr('NOW()'),
                ],
                [$db->quoteIdentifier($pk) . ' = ?' => $row['pk']]
            );
        }
    };

    if ($tableFilter === 'all' || $tableFilter === 'participant') {
        $processTable('participant', 'participant_id', 'email', 'additional_email', 'email_status', 'additional_email_status');
    }
    if ($tableFilter === 'all' || $tableFilter === 'data_manager') {
        $processTable('data_manager', 'dm_id', 'primary_email', 'secondary_email', 'primary_email_status', 'secondary_email_status');
    }

    if (!$quiet) {
        $io->section('Summary');
        foreach ($totals as $t => $counts) {
            if ($counts['scanned'] === 0) {
                continue;
            }
            $io->writeln(sprintf(
                '  %-13s scanned=%d  valid=%d  invalid_domain=%d  invalid_syntax=%d  unknown=%d',
                $t,
                $counts['scanned'],
                $counts['valid'],
                $counts['invalid_domain'],
                $counts['invalid_syntax'],
                $counts['unknown']
            ));
        }
        $io->writeln('  unique domains MX-checked: ' . count($mxCache));
        if ($dryRun) {
            $io->note('Dry-run: no rows were written.');
        }
    }

    exit(0);
} catch (Throwable $e) {
    if (class_exists('Pt_Commons_LoggerUtility')) {
        Pt_Commons_LoggerUtility::logError($e->getMessage(), [
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    fwrite(STDERR, 'check-participant-emails failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
