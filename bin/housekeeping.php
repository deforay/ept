#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Omnibus housekeeping sweep — prunes grow-unbounded log/queue tables and
 * stale filesystem artifacts. Idempotent; safe to re-run.
 *
 * Retention policy lives in $targets / $fsTargets below; one place to tweak.
 *
 * DB targets covered:
 *   - track_api_requests       90 days  (API request log, by requested_on)
 *   - temp_mail                30 days  (terminal rows: sent/failed)
 *   - queue_report_generation  90 days  (finished/failed report jobs)
 *   - push_notification        90 days  (non-pending rows, by created_on)
 *   - audit_log               730 days  (statement-level audit trail)
 *   - user_login_history      730 days  (login audit; same window as audit_log
 *                                        so the audit modal's session join keeps
 *                                        browser/OS for any audit row it shows)
 *
 * The two audit tables anchor their 730-day cutoff to the table's NEWEST row,
 * not to NOW() — so an instance left idle for years keeps its final 2 years of
 * history instead of being wiped clean. Active instances behave identically.
 *
 * Filesystem targets:
 *   - logs/*.log               30 days  (mtime; Monolog already rotates the
 *                                        app/client logs — this catches orphans)
 *   - public/temporary/*         7 days  (mtime; transient upload scratch)
 *
 * Usage:
 *   php bin/housekeeping.php                    full sweep
 *   php bin/housekeeping.php --dry-run          count only, no DELETEs / no unlink
 *   php bin/housekeeping.php --only=temp_mail   one target by name
 *   php bin/housekeeping.php --quiet            machine-friendly; only the summary line
 *   php bin/housekeeping.php --help             print this docblock
 *
 * Schedule: scheduled-jobs/ScheduledTasks.php (suggested daily ~03:30, after
 *           the nightly DB backup at 00:45).
 * Writes:   DELETEs from the tables above; unlinks files/dirs in the FS targets.
 * Exit:     non-zero if any target failed, so cron/staleness checks can flag it.
 */

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

$cliMode = php_sapi_name() === 'cli';

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    $doc = file_get_contents(__FILE__) ?: '';
    if (preg_match('#/\*\*.*?\*/#s', $doc, $m)) {
        echo preg_replace('#^/\*\*?\n?|\n?\s*\*/$|^ \* ?#m', '', $m[0]) . PHP_EOL;
    }
    exit(0);
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

    $opts    = getopt('', ['only::', 'dry-run', 'quiet']);
    $dryRun  = array_key_exists('dry-run', $opts);
    $quiet   = array_key_exists('quiet', $opts);
    $only    = isset($opts['only']) ? (string) $opts['only'] : null;

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db   = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $rootDir   = ROOT_PATH;
    $batchSize = 5000;

    /**
     * DB targets. Each entry needs:
     *   name   — short slug for --only and the report line
     *   count  — SELECT COUNT(*) ... WHERE ... (for --dry-run + reporting)
     *   delete — DELETE ... WHERE ... (the LIMIT clause is appended per batch)
     *
     * Every delete is single-table so the batched LIMIT is legal — MySQL
     * forbids LIMIT on multi-table DELETE.
     */
    $targets = [
        [
            'name'   => 'track_api_requests',
            'count'  => 'SELECT COUNT(*) FROM track_api_requests WHERE requested_on < NOW() - INTERVAL 90 DAY',
            'delete' => 'DELETE FROM track_api_requests WHERE requested_on < NOW() - INTERVAL 90 DAY',
        ],
        [
            'name'   => 'temp_mail',
            'count'  => "SELECT COUNT(*) FROM temp_mail WHERE status IN ('sent','failed','failure','fail') "
                      . 'AND COALESCE(sent_at, updated_at, queued_on) < NOW() - INTERVAL 30 DAY',
            'delete' => "DELETE FROM temp_mail WHERE status IN ('sent','failed','failure','fail') "
                      . 'AND COALESCE(sent_at, updated_at, queued_on) < NOW() - INTERVAL 30 DAY',
        ],
        [
            // Only finished/failed jobs — never pending/processing rows the
            // worker may still pick up.
            'name'   => 'queue_report_generation',
            'count'  => 'SELECT COUNT(*) FROM queue_report_generation '
                      . "WHERE status NOT IN ('pending','processing') "
                      . 'AND last_updated_on < NOW() - INTERVAL 90 DAY',
            'delete' => 'DELETE FROM queue_report_generation '
                      . "WHERE status NOT IN ('pending','processing') "
                      . 'AND last_updated_on < NOW() - INTERVAL 90 DAY',
        ],
        [
            'name'   => 'push_notification',
            'count'  => 'SELECT COUNT(*) FROM push_notification '
                      . "WHERE (push_status IS NULL OR push_status <> 'pending') "
                      . 'AND created_on < NOW() - INTERVAL 90 DAY',
            'delete' => 'DELETE FROM push_notification '
                      . "WHERE (push_status IS NULL OR push_status <> 'pending') "
                      . 'AND created_on < NOW() - INTERVAL 90 DAY',
        ],
        // Audit-bearing tables — 2-year window anchored to the table's NEWEST
        // row, not NOW(). This keeps the last 2 years of *actual activity*: an
        // instance idle for 3 years still retains its final 2 years of audit
        // trail instead of having the whole table wiped. For an active instance
        // MAX(date) ~= NOW(), so the behaviour is identical. The derived-table
        // wrapper is what lets MySQL self-reference the table it's deleting from;
        // the cutoff is stable across batches since we only delete older rows.
        [
            'name'   => 'audit_log',
            'count'  => 'SELECT COUNT(*) FROM audit_log WHERE created_on < '
                      . '(SELECT cutoff FROM (SELECT DATE_SUB(MAX(created_on), INTERVAL 730 DAY) AS cutoff FROM audit_log) AS t)',
            'delete' => 'DELETE FROM audit_log WHERE created_on < '
                      . '(SELECT cutoff FROM (SELECT DATE_SUB(MAX(created_on), INTERVAL 730 DAY) AS cutoff FROM audit_log) AS t)',
        ],
        [
            'name'   => 'user_login_history',
            'count'  => 'SELECT COUNT(*) FROM user_login_history WHERE login_attempted_datetime < '
                      . '(SELECT cutoff FROM (SELECT DATE_SUB(MAX(login_attempted_datetime), INTERVAL 730 DAY) AS cutoff FROM user_login_history) AS t)',
            'delete' => 'DELETE FROM user_login_history WHERE login_attempted_datetime < '
                      . '(SELECT cutoff FROM (SELECT DATE_SUB(MAX(login_attempted_datetime), INTERVAL 730 DAY) AS cutoff FROM user_login_history) AS t)',
        ],
    ];

    $fsTargets = [
        [
            'name' => 'logs',
            'fn'   => fn (bool $dry) => pruneTopLevelByMtime("$rootDir/logs", days: 30, dryRun: $dry, pattern: '*.log'),
        ],
        [
            'name' => 'public/temporary',
            'fn'   => fn (bool $dry) => pruneTopLevelByMtime(TEMP_UPLOAD_PATH, days: 7, dryRun: $dry),
        ],
    ];

    $totalRows  = 0;
    $totalFiles = 0;
    $totalBytes = 0;
    $failed     = 0;
    $start      = microtime(true);

    if (!$quiet) {
        $io->title('Housekeeping sweep' . ($dryRun ? ' (dry run)' : '') . ($only ? " — only={$only}" : ''));
    }

    foreach ($targets as $t) {
        if ($only !== null && $only !== $t['name']) {
            continue;
        }

        // Isolate each target: a failure on one table (renamed column, locked
        // table, bad SQL) must not abort the rest of the unattended sweep.
        try {
            $eligible = (int) $db->fetchOne($t['count']);

            if ($dryRun) {
                if (!$quiet) {
                    $io->writeln(sprintf('  %-26s would prune %d rows', $t['name'], $eligible));
                }
                $totalRows += $eligible;
                continue;
            }

            if ($eligible === 0) {
                if (!$quiet) {
                    $io->writeln(sprintf('  %-26s pruned 0 rows', $t['name']));
                }
                continue;
            }

            // Batched delete to keep row locks short on the big tables
            // (track_api_requests is the obvious one).
            $deleted = 0;
            while (true) {
                $n = $db->query($t['delete'] . " LIMIT {$batchSize}")->rowCount();
                $deleted += $n;
                if ($n < $batchSize) {
                    break;
                }
            }
            $totalRows += $deleted;
            if (!$quiet) {
                $io->writeln(sprintf('  %-26s pruned %d rows', $t['name'], $deleted));
            }
        } catch (\Throwable $e) {
            $failed++;
            fwrite(STDERR, sprintf("  %-26s FAILED: %s\n", $t['name'], $e->getMessage()));
        }
    }

    foreach ($fsTargets as $t) {
        if ($only !== null && $only !== $t['name']) {
            continue;
        }

        try {
            [$files, $bytes] = ($t['fn'])($dryRun);
            $totalFiles += $files;
            $totalBytes += $bytes;
            if (!$quiet) {
                $verb = $dryRun ? 'would free' : 'freed';
                $io->writeln(sprintf('  %-26s %s %d entries / %s', $t['name'], $verb, $files, formatBytes($bytes)));
            }
        } catch (\Throwable $e) {
            $failed++;
            fwrite(STDERR, sprintf("  %-26s FAILED: %s\n", $t['name'], $e->getMessage()));
        }
    }

    $elapsed = microtime(true) - $start;
    $summary = sprintf(
        '%shousekeeping done in %.2fs — %d rows, %d entries, %s%s',
        $dryRun ? '[dry run] ' : '',
        $elapsed,
        $totalRows,
        $totalFiles,
        formatBytes($totalBytes),
        $failed > 0 ? " ({$failed} target(s) FAILED)" : ''
    );

    if ($quiet) {
        echo $summary . PHP_EOL;
    } elseif ($failed > 0) {
        $io->warning($summary);
    } else {
        $io->success($summary);
    }

    // Non-zero exit so cron/staleness alarms can flag a partial sweep.
    exit($failed > 0 ? 1 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'housekeeping FATAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// ─── helpers ───────────────────────────────────────────────────────────

/**
 * Remove top-level entries (files and/or directories) older than $days by
 * mtime. Directories are removed recursively. Returns [entriesRemoved, bytes].
 *
 * @return array{0:int,1:int}
 */
function pruneTopLevelByMtime(string $dir, int $days, bool $dryRun, string $pattern = '*'): array
{
    if (!is_dir($dir)) {
        return [0, 0];
    }

    $cutoff  = time() - ($days * 86400);
    $entries = 0;
    $bytes   = 0;

    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..' || $name === 'index.php' || $name === '.gitkeep') {
            continue;
        }
        if ($pattern !== '*' && !fnmatch($pattern, $name)) {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (@filemtime($path) >= $cutoff) {
            continue;
        }

        if (is_dir($path) && !is_link($path)) {
            $bytes += removeDirRecursive($path, $dryRun);
            $entries++;
        } elseif (is_file($path) || is_link($path)) {
            $bytes += (int) @filesize($path);
            $entries++;
            if (!$dryRun) {
                @unlink($path);
            }
        }
    }

    return [$entries, $bytes];
}

/**
 * Recursively delete a directory's contents and the directory itself.
 * Returns the byte total of files seen. No-op deletes when $dryRun.
 */
function removeDirRecursive(string $dir, bool $dryRun): int
{
    $bytes = 0;
    $iter  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    /** @var SplFileInfo $info */
    foreach ($iter as $info) {
        if ($info->isFile() || $info->isLink()) {
            $bytes += (int) @$info->getSize();
            if (!$dryRun) {
                @unlink($info->getPathname());
            }
        } elseif ($info->isDir()) {
            if (!$dryRun) {
                @rmdir($info->getPathname());
            }
        }
    }

    if (!$dryRun) {
        @rmdir($dir);
    }

    return $bytes;
}

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $u = -1;
    $n = (float) $bytes;
    do {
        $n /= 1024;
        $u++;
    } while ($n >= 1024 && $u < count($units) - 1);
    return sprintf('%.1f %s', $n, $units[$u]);
}
