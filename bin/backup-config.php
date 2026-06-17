#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Config backup — snapshots application/configs/application.ini into
 * backups/config/ so a fat-fingered edit or a bad upgrade can be rolled back.
 * Idempotent; safe to re-run.
 *
 * application.ini is not under version control (it carries per-instance DB
 * creds, mail DSNs, domains), so nothing else captures it. The nightly
 * db-tools backup covers the database; this covers the one config file that
 * the database can't be brought back up without.
 *
 * Each run writes backups/config/application.ini.<YYYY-MM-DD_His>.bak — but
 * only if the file changed since the most recent snapshot, so weekly runs of
 * an untouched config don't pile up identical copies. Snapshots older than
 * --keep are pruned, with at least --min-keep most-recent copies always kept.
 *
 * Usage:
 *   php bin/backup-config.php                 snapshot if changed, then prune
 *   php bin/backup-config.php --dry-run       report only, no writes / no prune
 *   php bin/backup-config.php --force         snapshot even if unchanged
 *   php bin/backup-config.php --keep=26       prune snapshots beyond the newest 26 (default 26)
 *   php bin/backup-config.php --min-keep=4    never prune below this many (default 4)
 *   php bin/backup-config.php --quiet         machine-friendly; only the summary line
 *   php bin/backup-config.php --help          print this docblock
 *
 * Schedule: scheduled-jobs/ScheduledTasks.php (suggested weekly).
 * Writes:   backups/config/application.ini.*.bak; unlinks pruned snapshots.
 * Exit:     non-zero on failure, so cron/staleness checks can flag it.
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

    if (!$cliMode) {
        echo 'This script can only be run from the command line.' . PHP_EOL;
        exit(1);
    }

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    $opts    = getopt('', ['keep::', 'min-keep::', 'dry-run', 'force', 'quiet']);
    $dryRun  = array_key_exists('dry-run', $opts);
    $force   = array_key_exists('force', $opts);
    $quiet   = array_key_exists('quiet', $opts);
    $keep    = max(1, (int) ($opts['keep']     ?? 26));
    $minKeep = max(1, (int) ($opts['min-keep'] ?? 4));

    $source  = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'configs' . DIRECTORY_SEPARATOR . 'application.ini';
    $destDir = BACKUP_PATH . DIRECTORY_SEPARATOR . 'config';

    if (!$quiet) {
        $io->title('Config backup' . ($dryRun ? ' (dry run)' : ''));
    }

    if (!is_file($source)) {
        throw new RuntimeException("source config not found: {$source}");
    }

    if (!is_dir($destDir) && !$dryRun) {
        if (!mkdir($destDir, 0750, true) && !is_dir($destDir)) {
            throw new RuntimeException("could not create backup dir: {$destDir}");
        }
    }

    // Existing snapshots, newest last (timestamped names sort chronologically).
    $existing = glob($destDir . DIRECTORY_SEPARATOR . 'application.ini.*.bak') ?: [];
    sort($existing);
    $latest = end($existing) ?: null;

    // Skip when the live config is byte-identical to the most recent snapshot.
    $unchanged = $latest !== null && is_file($latest) && @md5_file($latest) === @md5_file($source);

    $wrote = null;
    if ($unchanged && !$force) {
        if (!$quiet) {
            $io->writeln('  unchanged since last snapshot — skipping (use --force to override)');
        }
    } else {
        // date() honours the timezone cli-bootstrap set from application.ini.
        $dest = $destDir . DIRECTORY_SEPARATOR . 'application.ini.' . date('Y-m-d_His') . '.bak';
        if ($dryRun) {
            if (!$quiet) {
                $io->writeln('  would write ' . basename($dest));
            }
        } else {
            if (!@copy($source, $dest)) {
                throw new RuntimeException("copy failed: {$source} -> {$dest}");
            }
            @chmod($dest, 0640);
            $wrote = $dest;
            if (!in_array($dest, $existing, true)) {
                $existing[] = $dest;
            }
            sort($existing);
            if (!$quiet) {
                $io->writeln('  wrote ' . basename($dest) . ' (' . formatBytes((int) @filesize($dest)) . ')');
            }
        }
    }

    // Prune oldest beyond --keep, but never below --min-keep newest copies.
    $pruned = 0;
    $floor  = max($keep, $minKeep);
    if (count($existing) > $floor) {
        $toPrune = array_slice($existing, 0, count($existing) - $floor);
        foreach ($toPrune as $old) {
            if ($dryRun) {
                if (!$quiet) {
                    $io->writeln('  would prune ' . basename($old));
                }
                $pruned++;
            } elseif (@unlink($old)) {
                $pruned++;
            }
        }
    }

    $summary = sprintf(
        '%sconfig backup done — %s, %d pruned, %d kept',
        $dryRun ? '[dry run] ' : '',
        $wrote ? 'snapshot written' : ($dryRun && !$unchanged ? 'snapshot pending' : 'no new snapshot'),
        $pruned,
        max(0, count($existing) - ($dryRun ? 0 : $pruned))
    );

    if ($quiet) {
        echo $summary . PHP_EOL;
    } else {
        $io->success($summary);
    }

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'backup-config FATAL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// ─── helpers ───────────────────────────────────────────────────────────

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
