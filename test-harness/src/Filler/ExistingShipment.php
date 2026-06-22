<?php

declare(strict_types=1);

namespace EptTestHarness\Filler;

use EptTestHarness\Config;
use EptTestHarness\Db;
use EptTestHarness\Evaluator;

/**
 * Interactive "--shipment <id|code>" flow shared by bin/dts and bin/custom-test:
 * attach to a shipment the admin already created, enroll participants if none, fill
 * responses against its own reference results, evaluate, and generate reports.
 *
 * Each bin restricts itself to the family it owns ('dts' or 'generic') and points the
 * user at the sibling bin if they pass the wrong kind of shipment.
 */
final class ExistingShipment
{
    /**
     * @param string $expectedFamily 'dts' or 'generic'
     * @param string $siblingBin     bin name to suggest when the family mismatches
     * @return int exit code
     */
    public static function run(Config $config, Db $db, string $idOrCode, string $expectedFamily, string $siblingBin): int
    {
        $filler = new ShipmentFiller($db);

        $shipment = $filler->resolveShipment($idOrCode);
        if ($shipment === null) {
            fwrite(STDERR, "No shipment found for '$idOrCode'.\n");
            return 2;
        }

        $class = $filler->classify($shipment);
        if ($class['family'] === 'unsupported') {
            fwrite(STDERR, "Cannot fill shipment {$shipment['shipment_code']}: {$class['reason']}.\n");
            return 2;
        }
        if ($class['family'] !== $expectedFamily) {
            fwrite(STDERR, "Shipment {$shipment['shipment_code']} is a '{$class['family']}' shipment — run it with `php test-harness/bin/$siblingBin --shipment $idOrCode` instead.\n");
            return 2;
        }

        $enrolled = (int) $db->scalar("SELECT COUNT(*) FROM shipment_participant_map WHERE shipment_id = ?", [(int) $shipment['shipment_id']]);

        fwrite(STDOUT, "Fill existing shipment — env={$config->env}, db={$config->dbName}\n\n");
        fwrite(STDOUT, "  shipment      : {$shipment['shipment_code']} (id={$shipment['shipment_id']})\n");
        fwrite(STDOUT, "  scheme        : {$shipment['scheme_name']} ({$shipment['scheme_type']})\n");
        fwrite(STDOUT, "  fill strategy : {$class['family']}\n");
        fwrite(STDOUT, "  enrolled      : $enrolled participant(s)\n\n");

        $enrollCount = 0;
        if ($enrolled === 0) {
            $enrollCount = max(1, (int) self::prompt("No participants enrolled — how many ATEST labs to enroll", '20'));
        } else {
            fwrite(STDOUT, "Using the {$enrolled} already-enrolled participant(s); only those without a response will be filled.\n");
        }
        $passPct = max(0, min(100, (int) self::prompt("Approx % that should PASS", '80'))) / 100;

        $proceed = strtolower(trim(self::prompt("\nProceed (fill → evaluate → generate reports)? [y/N]", 'n')));
        if ($proceed !== 'y' && $proceed !== 'yes') {
            fwrite(STDOUT, "Aborted.\n");
            return 0;
        }

        fwrite(STDOUT, "\n[1/3] Filling responses...\n");
        $r = $filler->fill($shipment, $class['family'], ['enrollCount' => $enrollCount, 'passPct' => $passPct]);
        fwrite(STDOUT, sprintf(
            "      enrolled now: %d (newly %d)  filled: %d  (intended pass %d / fail %d)  skipped(existing): %d\n",
            $r['already_enrolled'] + $r['newly_enrolled'],
            $r['newly_enrolled'],
            $r['filled'],
            $r['pass'],
            $r['fail'],
            $r['skipped']
        ));
        if ($r['late']) {
            fwrite(STDOUT, "      WARNING: response_deadline is in the past — evaluation may flag responses as late. Extend the deadline if you want them scored.\n");
        }
        if ($r['filled'] === 0) {
            fwrite(STDOUT, "\nNothing to fill (all enrolled participants already have responses). Re-evaluating anyway.\n");
        }

        $evaluator = new Evaluator($config);
        fwrite(STDOUT, "[2/3] Evaluating (subprocess)...\n");
        $evaluator->evaluate((int) $shipment['shipment_id']);

        fwrite(STDOUT, "[3/3] Generating participant + summary reports (subprocess)...\n");
        $evaluator->generateReports((int) $shipment['shipment_id']);

        $counts = $db->one(
            "SELECT SUM(response_status='responded') responded,
                    SUM(response_status='responded' AND final_result=1) pass,
                    SUM(response_status='responded' AND final_result=2) fail,
                    SUM(response_status='responded' AND final_result=3) excluded
               FROM shipment_participant_map WHERE shipment_id = ?",
            [(int) $shipment['shipment_id']]
        );
        $reportDir = $config->repoRoot . '/downloads/reports/' . $shipment['shipment_code'];
        $allPdfs = is_dir($reportDir) ? (glob($reportDir . '/*.pdf') ?: []) : [];
        $summaryPdfs = array_filter($allPdfs, static fn ($p) => str_contains(basename($p), '-summary.pdf'));

        fwrite(STDOUT, "\n─────────────────────────────────────────────\n");
        fwrite(STDOUT, "Done: {$shipment['shipment_code']} (id={$shipment['shipment_id']})\n");
        fwrite(STDOUT, sprintf(
            "Responders: %d   Pass: %d   Fail: %d   Excluded: %d\n",
            (int) $counts['responded'],
            (int) $counts['pass'],
            (int) $counts['fail'],
            (int) $counts['excluded']
        ));
        fwrite(STDOUT, sprintf("Reports: %d participant + %d summary PDFs in downloads/reports/%s/\n", count($allPdfs) - count($summaryPdfs), count($summaryPdfs), $shipment['shipment_code']));
        fwrite(STDOUT, "─────────────────────────────────────────────\n");

        return 0;
    }

    private static function prompt(string $question, string $default = ''): string
    {
        $suffix = $default !== '' ? " [$default]" : '';
        fwrite(STDOUT, "$question$suffix: ");
        $line = fgets(STDIN);
        if ($line === false) {
            return $default;
        }
        $line = trim($line);
        return $line === '' ? $default : $line;
    }
}
