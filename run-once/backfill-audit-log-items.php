<?php

// Links audit log entries written before 7.7.3 to the item they are about.
//
// From 7.7.3 every entry records its item (entity_type, entity_id, entity_label),
// so the Audit Log page can show an item's full history. Older entries only have
// a statement. This script reads those statements and fills in the item where
// the statement names it without doubt:
//
//   shipment      the shipment code field of known statements, such as
//                 "Evaluated shipment DTS0826-1",
//                 "Submitted DTS response - DTS0826-1 / DTS-53",
//                 "Finalized shipment - DTS0826-1" and
//                 "Downloaded ... report - DTS0826-1 (390 rows)".
//                 Only the code field is read, so a Lab ID is never taken
//                 for a shipment code. Unknown statements are left alone.
//   distribution  "Added a new", "Updated", "Deleted" or "Shipped" PT Survey - CODE.
//   testkit       "Added a new test kit - NAME", "Updated test kit - NAME",
//                 when exactly one test kit has that name.
//   scheme        "Updated HIV serology settings", "Updated VL settings" and the
//                 other scheme settings pages.
//
// Participants are not linked. Old statements name them by lab name, which is
// not unique. Before and after values and the important flag cannot be
// recovered, because older entries never recorded them.
//
// Only rows with no entity_type are read, so running it again changes nothing.
// Exits non-zero on failure so bin/run-once.php retries it on the next upgrade.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

const BATCH_SIZE = 5000;

$db = Zend_Db_Table_Abstract::getDefaultAdapter();

try {
    $columns = $db->describeTable('audit_log');
    if (!isset($columns['entity_type'])) {
        fwrite(STDERR, "backfill-audit-log-items: audit_log has no entity_type column. Run the 7.7.3 migration first.\n");
        exit(1);
    }

    $shipments = [];
    foreach ($db->fetchAll('SELECT shipment_id, shipment_code FROM shipment WHERE shipment_code IS NOT NULL AND shipment_code <> \'\'') as $row) {
        $shipments[trim((string) $row['shipment_code'])] = (string) $row['shipment_id'];
    }

    $distributions = [];
    foreach ($db->fetchAll('SELECT distribution_id, distribution_code FROM distributions WHERE distribution_code IS NOT NULL AND distribution_code <> \'\'') as $row) {
        $distributions[(string) $row['distribution_code']] = (string) $row['distribution_id'];
    }

    // Only names that belong to one kit.
    $testkits = [];
    foreach ($db->fetchAll('SELECT TestKit_Name, MIN(TestKitName_ID) AS id, COUNT(*) AS n FROM r_testkitnames GROUP BY TestKit_Name') as $row) {
        if ((int) $row['n'] === 1 && trim((string) $row['TestKit_Name']) !== '') {
            $testkits[trim((string) $row['TestKit_Name'])] = (string) $row['id'];
        }
    }

    $schemeStatements = [
        'Updated HIV serology settings' => ['dts', 'HIV Serology'],
        'Updated SARS-CoV-2 settings' => ['covid19', 'SARS-CoV-2'],
        'Updated EID settings' => ['eid', 'EID'],
        'Updated VL settings' => ['vl', 'VL'],
        'Updated TB settings' => ['tb', 'TB'],
        'Updated RECENCY settings' => ['recency', 'HIV Recency (RTRI)'],
    ];

    // Each pattern captures the field that holds the code, so a Lab ID that
    // looks like a shipment code ("... response - DTS2026 / DTS2026-B") is
    // never mistaken for one. Codes are then matched exactly.
    $shipmentPatterns = [
        '/^Evaluated shipment (.+)$/',
        '/^Created new shipment (.+)$/',
        '/ response - (.+?) \/ .+$/',
        '/^(?:Added a new|Updated|Finalized|Unfinalized|Cancelled|Deleted) shipment - (.+?)(?: \(.*\))?$/',
        '/^Downloaded .+? report - (.+?)(?: \(\d+ rows\))?$/',
    ];
    $distributionPatterns = [
        '/^(?:Added a new|Updated|Deleted|Shipped) PT Survey - (.+?)(?: \(.*\))?$/',
    ];

    $resolve = static function (string $statement) use ($shipments, $distributions, $testkits, $schemeStatements, $shipmentPatterns, $distributionPatterns): ?array {
        if (isset($schemeStatements[$statement])) {
            return ['scheme', $schemeStatements[$statement][0], $schemeStatements[$statement][1]];
        }
        foreach ($distributionPatterns as $pattern) {
            if (preg_match($pattern, $statement, $m)) {
                $code = trim($m[1]);
                return isset($distributions[$code]) ? ['distribution', $distributions[$code], $code] : null;
            }
        }
        if (preg_match('/^(?:Added a new|Updated) test kit - (.+)$/', $statement, $m)) {
            $name = trim($m[1]);
            return isset($testkits[$name]) ? ['testkit', $testkits[$name], $name] : null;
        }
        foreach ($shipmentPatterns as $pattern) {
            if (preg_match($pattern, $statement, $m)) {
                $code = trim($m[1]);
                return isset($shipments[$code]) ? ['shipment', $shipments[$code], $code] : null;
            }
        }
        return null;
    };

    $lastId = 0;
    $linked = ['shipment' => 0, 'distribution' => 0, 'testkit' => 0, 'scheme' => 0];
    $read = 0;
    while (true) {
        $rows = $db->fetchAll(
            'SELECT audit_log_id, statement FROM audit_log
             WHERE audit_log_id > ? AND entity_type IS NULL
             ORDER BY audit_log_id LIMIT ' . BATCH_SIZE,
            [$lastId]
        );
        if ($rows === []) {
            break;
        }
        $byEntity = [];
        foreach ($rows as $row) {
            $lastId = (int) $row['audit_log_id'];
            $read++;
            $match = $resolve(trim((string) $row['statement']));
            if ($match !== null) {
                $byEntity[implode("\x1f", $match)][] = $lastId;
            }
        }
        $db->beginTransaction();
        try {
            foreach ($byEntity as $key => $ids) {
                [$type, $id, $label] = explode("\x1f", $key);
                $db->update(
                    'audit_log',
                    ['entity_type' => $type, 'entity_id' => $id, 'entity_label' => mb_substr($label, 0, 255)],
                    ['audit_log_id IN (?)' => $ids, 'entity_type IS NULL']
                );
                $linked[$type] += count($ids);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    printf(
        "backfill-audit-log-items: read %d entries. Linked %d to shipments, %d to PT surveys, %d to test kits and %d to scheme settings.\n",
        $read,
        $linked['shipment'],
        $linked['distribution'],
        $linked['testkit'],
        $linked['scheme']
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'backfill-audit-log-items failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
