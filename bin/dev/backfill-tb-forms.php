#!/usr/bin/env php
<?php

// bin/dev/backfill-tb-forms.php — place already-generated TB forms into the
// participants' durable download folders (one-time, dev-only).
//
// generateFormPDF() now writes each participant's form to
// downloads/<unique_identifier>/ as it generates it. Forms generated before that
// only survive inside the shipment bundles under downloads/tb-forms/, because the
// loose copies were written to public/temporary/<code>/, which housekeeping prunes
// at 7 days. This script explodes those bundles into the durable folders so the
// participant's "Download Form" button resolves for past shipments too.
//
// Modes:
//   (default)          dry-run: report what would be placed, write nothing
//   --apply            actually write the files
//   --shipment=CODE    only this shipment code (default: every bundle found)
//   --overwrite        replace a form already present in the durable folder

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

if (php_sapi_name() !== 'cli') {
    echo 'This script can only be run from the command line.' . PHP_EOL;
    exit(1);
}

require_once __DIR__ . '/../../cli-bootstrap.php';
ini_set('memory_limit', '-1');
set_time_limit(0);

$io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

$argv = $_SERVER['argv'];
$apply = in_array('--apply', $argv, true);
$overwrite = in_array('--overwrite', $argv, true);
$onlyShipment = null;
foreach ($argv as $a) {
    if (str_starts_with($a, '--shipment=')) {
        $onlyShipment = substr($a, 11);
    }
}

if (!class_exists('ZipArchive')) {
    $io->error('ext-zip is not available — cannot read the TB form bundles.');
    exit(1);
}

$bundleDir = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'tb-forms';
$bundles = glob($bundleDir . DIRECTORY_SEPARATOR . '*-TB-FORMS.zip') ?: [];
if ($bundles === []) {
    $io->warning("No TB form bundles found in $bundleDir");
    exit(0);
}

$io->title($apply ? 'Backfilling TB forms' : 'Backfilling TB forms (dry run)');

$totalPlaced = 0;
$totalSkipped = 0;
$totalFailed = 0;

foreach ($bundles as $bundle) {
    $shipmentCode = preg_replace('/-TB-FORMS\.zip$/', '', basename($bundle));
    if ($onlyShipment !== null && $shipmentCode !== $onlyShipment) {
        continue;
    }

    $zip = new ZipArchive();
    if ($zip->open($bundle) !== true) {
        $io->error("Could not open $bundle");
        $totalFailed++;
        continue;
    }

    $placed = 0;
    $skipped = 0;
    $failed = 0;

    // Entries are named TB-FORM-<shipment code>-<unique identifier>.pdf; the
    // identifier is whatever follows the shipment code, so derive it from the
    // known prefix rather than splitting on '-' (identifiers can contain them).
    $prefix = 'TB-FORM-' . $shipmentCode . '-';

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if ($entry === false || !str_starts_with($entry, $prefix) || !str_ends_with($entry, '.pdf')) {
            continue;
        }
        $uniqueIdentifier = substr($entry, strlen($prefix), -4);
        if ($uniqueIdentifier === '' || str_contains($uniqueIdentifier, '/')) {
            continue;
        }

        $target = Application_Service_Shipments::tbFormParticipantPath($shipmentCode, $uniqueIdentifier);
        if (is_file($target) && !$overwrite) {
            $skipped++;
            continue;
        }

        if (!$apply) {
            $placed++;
            continue;
        }

        if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0777, true) && !is_dir(dirname($target))) {
            $io->error('Could not create ' . dirname($target));
            $failed++;
            continue;
        }

        $stream = $zip->getStream($entry);
        $out = ($stream !== false) ? @fopen($target, 'wb') : false;
        if ($stream === false || $out === false) {
            if ($stream !== false) {
                fclose($stream);
            }
            $io->error("Could not write $target");
            $failed++;
            continue;
        }
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);
        $placed++;
    }

    $zip->close();

    $io->writeln(sprintf(
        '%s: %d %s, %d already present, %d failed',
        $shipmentCode,
        $placed,
        $apply ? 'placed' : 'to place',
        $skipped,
        $failed
    ));

    $totalPlaced += $placed;
    $totalSkipped += $skipped;
    $totalFailed += $failed;
}

$io->newLine();
$summary = sprintf(
    '%d %s, %d already present, %d failed',
    $totalPlaced,
    $apply ? 'placed' : 'to place',
    $totalSkipped,
    $totalFailed
);
if ($totalFailed > 0) {
    $io->warning($summary);
} else {
    $io->success($summary);
}
if (!$apply) {
    $io->note('Dry run — re-run with --apply to write the files.');
}

exit($totalFailed > 0 ? 1 : 0);
