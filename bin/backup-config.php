#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Config backup — snapshots the per-instance files that are not under
 * version control into backups/config/, so a fat-fingered edit, a bad upgrade
 * or a lost server can be recovered. Idempotent; safe to re-run.
 *
 * The nightly db-tools backup covers the database. This covers the files the
 * database does not hold:
 *   - application/configs/application.ini (DB creds, mail DSNs, domains)
 *   - application/configs/config.ini (the instance's scheme settings)
 *   - the uploaded templates: certificate designs and forms, report
 *     letterhead PDFs and logos. Certificate layouts (where the details sit
 *     on a design) are in the database.
 *
 * Each run writes, only for what changed since its most recent snapshot:
 *   backups/config/application.ini.<YYYY-MM-DD_His>.bak
 *   backups/config/config.ini.<YYYY-MM-DD_His>.bak
 *   backups/config/templates.<YYYY-MM-DD_His>.zip  (paths relative to the
 *     ePT folder, so unzip it there to restore)
 * so weekly runs of untouched files don't pile up identical copies. Each kind
 * is pruned on its own: snapshots older than --keep go, with at least
 * --min-keep most-recent copies always kept.
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
 * Writes:   backups/config/{application,config}.ini.*.bak and templates.*.zip;
 *           unlinks pruned snapshots.
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

    $destDir = BACKUP_PATH . DIRECTORY_SEPARATOR . 'config';
    $configDir = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'configs';

    if (!$quiet) {
        $io->title('Config backup' . ($dryRun ? ' (dry run)' : ''));
    }

    $appIni = $configDir . DIRECTORY_SEPARATOR . 'application.ini';
    if (!is_file($appIni)) {
        throw new RuntimeException("source config not found: {$appIni}");
    }

    if (!is_dir($destDir) && !$dryRun) {
        if (!mkdir($destDir, 0750, true) && !is_dir($destDir)) {
            throw new RuntimeException("could not create backup dir: {$destDir}");
        }
    }

    $log = function (string $line) use ($io, $quiet): void {
        if (!$quiet) {
            $io->writeln('  ' . $line);
        }
    };

    $wrote = [];
    $pending = 0;
    $pruned = 0;
    $kept = 0;
    $floor = max($keep, $minKeep);

    // The two ini files: a plain copy each. config.ini is optional.
    foreach ([$appIni, $configDir . DIRECTORY_SEPARATOR . 'config.ini'] as $source) {
        if (!is_file($source)) {
            continue;
        }
        $name = basename($source);
        $existing = snapshots($destDir, $name . '.*.bak');
        $latest = end($existing) ?: null;
        if ($latest !== null && @md5_file($latest) === @md5_file($source) && !$force) {
            $log("{$name}: unchanged since last snapshot — skipping (use --force to override)");
        } else {
            // date() honours the timezone cli-bootstrap set from application.ini.
            $dest = $destDir . DIRECTORY_SEPARATOR . $name . '.' . date('Y-m-d_His') . '.bak';
            if ($dryRun) {
                $pending++;
                $log('would write ' . basename($dest));
            } else {
                if (!@copy($source, $dest)) {
                    throw new RuntimeException("copy failed: {$source} -> {$dest}");
                }
                @chmod($dest, 0640);
                $wrote[] = basename($dest);
                $existing[] = $dest;
                $log('wrote ' . basename($dest) . ' (' . formatBytes((int) @filesize($dest)) . ')');
            }
        }
        [$p, $k] = prune($existing, $floor, $dryRun, $log);
        $pruned += $p;
        $kept += $k;
    }

    // The uploaded templates: one zip. The zip comment holds a fingerprint
    // of the files, so an unchanged set is not zipped again.
    $files = templateFiles();
    $existing = snapshots($destDir, 'templates.*.zip');
    if (!empty($files)) {
        $fingerprint = fingerprint($files);
        $latest = end($existing) ?: null;
        $previous = null;
        if ($latest !== null) {
            $zip = new ZipArchive();
            if ($zip->open($latest) === true) {
                $previous = $zip->getArchiveComment() ?: null;
                $zip->close();
            }
        }
        if ($previous === $fingerprint && !$force) {
            $log('templates: unchanged since last snapshot — skipping (use --force to override)');
        } else {
            $dest = $destDir . DIRECTORY_SEPARATOR . 'templates.' . date('Y-m-d_His') . '.zip';
            if ($dryRun) {
                $pending++;
                $log('would write ' . basename($dest) . ' (' . count($files) . ' files)');
            } else {
                $zip = new ZipArchive();
                if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException("could not create {$dest}");
                }
                foreach ($files as $relative => $absolute) {
                    $zip->addFile($absolute, $relative);
                }
                $zip->setArchiveComment($fingerprint);
                if (!$zip->close()) {
                    @unlink($dest);
                    throw new RuntimeException("could not write {$dest}");
                }
                @chmod($dest, 0640);
                $wrote[] = basename($dest);
                $existing[] = $dest;
                $log('wrote ' . basename($dest) . ' (' . count($files) . ' files, ' . formatBytes((int) @filesize($dest)) . ')');
            }
        }
    } else {
        $log('templates: none uploaded — skipping');
    }
    [$p, $k] = prune($existing, $floor, $dryRun, $log);
    $pruned += $p;
    $kept += $k;

    $summary = sprintf(
        '%sconfig backup done — %s, %d pruned, %d kept',
        $dryRun ? '[dry run] ' : '',
        $wrote ? count($wrote) . ' snapshot(s) written' : ($pending ? $pending . ' snapshot(s) pending' : 'no new snapshot'),
        $pruned,
        $kept
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

/** Snapshots matching $pattern in $dir, oldest first (timestamped names sort chronologically). */
function snapshots(string $dir, string $pattern): array
{
    $files = glob($dir . DIRECTORY_SEPARATOR . $pattern) ?: [];
    sort($files);
    return $files;
}

/**
 * Remove the oldest snapshots beyond $floor. Returns [pruned, kept].
 */
function prune(array $existing, int $floor, bool $dryRun, callable $log): array
{
    sort($existing);
    $pruned = 0;
    if (count($existing) > $floor) {
        foreach (array_slice($existing, 0, count($existing) - $floor) as $old) {
            if ($dryRun) {
                $log('would prune ' . basename($old));
                $pruned++;
            } elseif (@unlink($old)) {
                $pruned++;
            }
        }
    }
    return [$pruned, count($existing) - $pruned];
}

/**
 * The uploaded template files, as path relative to ROOT_PATH => absolute
 * path: certificate templates, report letterhead PDFs and logos. The
 * index.php placeholders that keep the folders in the release are skipped.
 */
function templateFiles(): array
{
    $dirs = [
        ROOT_PATH . '/scheduled-jobs/certificate-templates',
        UPLOAD_PATH . '/report-formats',
        UPLOAD_PATH . '/logo',
        UPLOAD_PATH . '/logos',
    ];
    $root = rtrim(str_replace('\\', '/', (string) realpath(ROOT_PATH)), '/') . '/';
    $files = [];
    foreach ($dirs as $dir) {
        $dir = realpath($dir);
        if ($dir === false || !is_dir($dir)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getFilename() === 'index.php' || $file->getFilename() === '.DS_Store') {
                continue;
            }
            $absolute = str_replace('\\', '/', $file->getPathname());
            if (str_starts_with($absolute, $root)) {
                $files[substr($absolute, strlen($root))] = $file->getPathname();
            }
        }
    }
    ksort($files);
    return $files;
}

/** A fingerprint of the files' names and contents. */
function fingerprint(array $files): string
{
    $hash = hash_init('sha256');
    foreach ($files as $relative => $absolute) {
        hash_update($hash, $relative . "\0" . (string) @md5_file($absolute) . "\n");
    }
    return 'ept-templates sha256:' . hash_final($hash);
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
