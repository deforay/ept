#!/usr/bin/env php
<?php

// bin/dev/refresh-countries.php — refresh the `countries` table from ISO 3166-1 (dev-only).
//
// Source of truth: league/iso3166 (a dev dependency). Matches existing rows by the stable
// ISO3 (alpha-3) code and updates iso_name / iso2 / numeric_code in place — it NEVER touches
// `countries.id`, which is the foreign key behind `participant.country`. New ISO countries
// missing from the table are reported (and inserted only with --insert-missing).
//
// Modes:
//   (default)          dry-run: print the diff, change nothing
//   --apply            write the changes to this database
//   --sql[=FILE]       emit guarded, idempotent UPDATE SQL (for a migration) to FILE or stdout
//   --insert-missing   also add ISO countries absent from the table (new ids = MAX(id)+1)
//   --template[=FILE]  rebuild the import template's "Country List" sheet + dropdown from the DB

declare(strict_types=1);

use League\ISO3166\ISO3166;
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

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

// --- options ------------------------------------------------------------------
$argv = $_SERVER['argv'];
$apply = in_array('--apply', $argv, true);
$insertMissing = in_array('--insert-missing', $argv, true);
$emitSql = false;
$sqlFile = null;
$doTemplate = false;
$templateFile = ROOT_PATH . '/public/files/Participant-Bulk-Import-Excel-Format-v2.xlsx';
foreach ($argv as $a) {
    if ($a === '--sql') {
        $emitSql = true;
    } elseif (str_starts_with($a, '--sql=')) {
        $emitSql = true;
        $sqlFile = substr($a, 6);
    } elseif ($a === '--template') {
        $doTemplate = true;
    } elseif (str_starts_with($a, '--template=')) {
        $doTemplate = true;
        $templateFile = substr($a, 11);
    }
}

// Display overrides: keep these short instead of the verbose ISO official names.
$displayOverrides = [
    'USA' => 'United States',
    'GBR' => 'United Kingdom',
];

// --- load ISO + current DB rows -----------------------------------------------
$iso = [];
foreach (new ISO3166() as $c) {
    $iso[strtoupper($c['alpha3'])] = $c;
}

$dbRows = $db->fetchAll(
    $db->select()->from('countries', ['id', 'iso_name', 'iso2', 'iso3', 'numeric_code'])
);

$updates = [];   // [id, iso3, oldName, newName, oldIso2, newIso2, oldNum, newNum]
foreach ($dbRows as $row) {
    $a3 = strtoupper((string) $row['iso3']);
    if (!isset($iso[$a3])) {
        continue; // DB row with no ISO match (e.g. retired code) — leave untouched
    }
    $c = $iso[$a3];
    $newName = $displayOverrides[$a3] ?? $c['name'];
    $newIso2 = strtoupper($c['alpha2']);
    $newNum = (int) $c['numeric'];

    if ((string) $row['iso_name'] !== $newName
        || strtoupper((string) $row['iso2']) !== $newIso2
        || (int) $row['numeric_code'] !== $newNum) {
        $updates[] = [
            'id' => (int) $row['id'], 'iso3' => $a3,
            'oldName' => (string) $row['iso_name'], 'newName' => $newName,
            'oldIso2' => (string) $row['iso2'], 'newIso2' => $newIso2,
            'oldNum' => (int) $row['numeric_code'], 'newNum' => $newNum,
        ];
    }
}

$haveA3 = array_map(fn ($r) => strtoupper((string) $r['iso3']), $dbRows);
$missing = [];
foreach ($iso as $a3 => $c) {
    if (!in_array($a3, $haveA3, true)) {
        $missing[] = $c;
    }
}

// --- report -------------------------------------------------------------------
$io->title('Country refresh from ISO 3166-1');
if ($updates === []) {
    $io->success('No name/code changes needed — countries already match ISO 3166.');
} else {
    $rows = array_map(fn ($u) => [
        $u['iso3'],
        $u['oldName'] === $u['newName'] ? '—' : "{$u['oldName']}  →  {$u['newName']}",
        $u['oldIso2'] === $u['newIso2'] ? '—' : "{$u['oldIso2']}→{$u['newIso2']}",
        $u['oldNum'] === $u['newNum'] ? '—' : "{$u['oldNum']}→{$u['newNum']}",
    ], $updates);
    $io->table(['ISO3', 'Name', 'iso2', 'numeric'], $rows);
    $io->writeln(count($updates) . ' row(s) would change.');
}
if ($missing !== []) {
    $io->section('ISO countries NOT in this table');
    foreach ($missing as $c) {
        $io->writeln(sprintf('  %s / %s / %s  %s', $c['alpha2'], $c['alpha3'], $c['numeric'], $c['name']));
    }
    $io->writeln($insertMissing ? 'These will be inserted (--insert-missing).' : 'Run with --insert-missing to add them.');
}

// --- emit SQL (guarded, idempotent) -------------------------------------------
$q = fn ($s) => "'" . str_replace("'", "''", (string) $s) . "'";
if ($emitSql) {
    $lines = ['-- Generated by bin/dev/refresh-countries.php — ISO 3166-1 country refresh.'];
    foreach ($updates as $u) {
        // Guard on the old value so a re-run is a no-op and admin edits are never clobbered.
        $lines[] = sprintf(
            "UPDATE `countries` SET `iso_name` = %s, `iso2` = %s, `numeric_code` = %d WHERE `iso3` = %s AND `iso_name` = %s;",
            $q($u['newName']), $q($u['newIso2']), $u['newNum'], $q($u['iso3']), $q($u['oldName'])
        );
    }
    if ($insertMissing) {
        foreach ($missing as $c) {
            $lines[] = sprintf(
                "INSERT INTO `countries` (`id`, `iso_name`, `iso2`, `iso3`, `numeric_code`) " .
                "SELECT (SELECT MAX(id) + 1 FROM `countries`), %s, %s, %s, %d FROM DUAL " .
                "WHERE NOT EXISTS (SELECT 1 FROM `countries` WHERE `iso3` = %s);",
                $q($c['name']), $q(strtoupper($c['alpha2'])), $q(strtoupper($c['alpha3'])), (int) $c['numeric'], $q(strtoupper($c['alpha3']))
            );
        }
    }
    $sql = implode("\n", $lines) . "\n";
    if ($sqlFile !== null) {
        file_put_contents($sqlFile, $sql);
        $io->success("SQL written to {$sqlFile}");
    } else {
        $io->section('SQL');
        $io->writeln($sql);
    }
}

// --- apply --------------------------------------------------------------------
if ($apply && ($updates !== [] || ($insertMissing && $missing !== []))) {
    $db->beginTransaction();
    try {
        foreach ($updates as $u) {
            $db->update(
                'countries',
                ['iso_name' => $u['newName'], 'iso2' => $u['newIso2'], 'numeric_code' => $u['newNum']],
                $db->quoteInto('id = ?', $u['id'])
            );
        }
        if ($insertMissing) {
            foreach ($missing as $c) {
                $nextId = (int) $db->fetchOne($db->select()->from('countries', new Zend_Db_Expr('MAX(id) + 1')));
                $db->insert('countries', [
                    'id' => $nextId,
                    'iso_name' => $c['name'],
                    'iso2' => strtoupper($c['alpha2']),
                    'iso3' => strtoupper($c['alpha3']),
                    'numeric_code' => (int) $c['numeric'],
                ]);
            }
        }
        $db->commit();
        $io->success('Applied to the database.');
        try {
            (new Application_Model_DbTable_AuditLog())->addNewAuditLog(
                'Refreshed countries from ISO 3166 (' . count($updates) . ' updated' .
                ($insertMissing ? ', ' . count($missing) . ' inserted' : '') . ')',
                'config'
            );
        } catch (Throwable $ignore) {
        }
    } catch (Throwable $e) {
        $db->rollBack();
        $io->error('Apply failed, rolled back: ' . $e->getMessage());
        exit(1);
    }
} elseif (!$apply && $updates !== []) {
    $io->note('Dry run — re-run with --apply to write these changes.');
}

// --- rebuild the import template's Country List + dropdown ---------------------
if ($doTemplate) {
    $io->section('Import template');
    if (!is_file($templateFile)) {
        $io->error("Template not found: {$templateFile}");
        exit(1);
    }
    try {
        refreshImportTemplate($db, $templateFile, $io);
        $io->success("Template Country List + dropdown refreshed: {$templateFile}");
    } catch (Throwable $e) {
        $io->error('Template refresh failed: ' . $e->getMessage());
        exit(1);
    }
}

exit(0);

/**
 * Rewrite the hidden "Country List" sheet (a mirror of the countries table) from the DB
 * and point/extend the Country-column (M) dropdown at the full list. The sheet-0 header
 * row is never touched, so uploads still pass validateUploadedFile().
 */
function refreshImportTemplate($db, string $path, SymfonyStyle $io): void
{
    $rows = $db->fetchAll(
        $db->select()->from('countries', ['id', 'iso_name', 'iso2', 'iso3', 'numeric_code'])
            ->order('iso_name ASC')
    );

    $ss = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $list = $ss->getSheetByName('Country List');
    if ($list === null) {
        throw new RuntimeException('"Country List" sheet not found in template.');
    }

    // Clear old data rows (keep the header row 1) then write the fresh mirror.
    $oldLast = $list->getHighestRow();
    if ($oldLast >= 2) {
        $list->removeRow(2, $oldLast - 1);
    }
    $r = 2;
    foreach ($rows as $c) {
        $list->setCellValue("A{$r}", (int) $c['id']);
        $list->setCellValueExplicit("B{$r}", (string) $c['iso_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $list->setCellValueExplicit("C{$r}", (string) $c['iso2'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $list->setCellValueExplicit("D{$r}", (string) $c['iso3'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $list->setCellValue("E{$r}", (int) $c['numeric_code']);
        $r++;
    }
    $lastRow = $r - 1;

    // Re-point + widen the Country (M) dropdown. Clone the existing M validation so its
    // show/allow flags are preserved exactly, then swap the collection via reflection
    // (PhpSpreadsheet exposes no public remove for ranged validations).
    $sheet = $ss->getSheet(0);
    $prop = new ReflectionProperty(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::class, 'dataValidationCollection');
    $prop->setAccessible(true);
    /** @var array<string,\PhpOffice\PhpSpreadsheet\Cell\DataValidation> $coll */
    $coll = $prop->getValue($sheet);

    $template = null;
    foreach ($coll as $key => $dv) {
        if (preg_match('/^M\d/', $key)) {
            $template = $dv;
            unset($coll[$key]);
        }
    }
    $dv = $template ? clone $template : new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
    $dv->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
    $dv->setFormula1("'Country List'!\$B\$2:\$B\${$lastRow}");
    $dv->setSqref('M2:M1000');
    $coll['M2:M1000'] = $dv;
    $prop->setValue($sheet, $coll);

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($ss, 'Xlsx');
    $writer->save($path);
    $io->writeln('  wrote ' . count($rows) . " countries; dropdown M2:M1000 -> 'Country List'!\$B\$2:\$B\${$lastRow}");
}

/*
USAGE
  php bin/dev/refresh-countries.php                 # dry-run: show what would change
  php bin/dev/refresh-countries.php --apply         # write the changes to this DB
  php bin/dev/refresh-countries.php --sql=database/migrations/X.Y.Z.sql   # emit migration SQL
  php bin/dev/refresh-countries.php --apply --insert-missing              # also add missing ISO countries

  Matches by ISO3 code; never changes countries.id (FK target of participant.country).
  Requires the dev dependency league/iso3166.
*/
