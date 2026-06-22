<?php

declare(strict_types=1);

namespace EptTestHarness\CustomTest;

use EptTestHarness\Db;

/**
 * Reads response_result_generic_test.calculated_score per (map, sample) and compares
 * to the expectation derived from each participant's flip-set: samples the harness
 * flipped must score 0 (Unacc), all others must score > 0 (Acc).
 *
 * CustomTest::evaluate writes a numeric calculated_score per sample: the sample's
 * score when reported == reference, else 0. Verdict mapping:
 *   calculated_score > 0   → 'Acc'
 *   calculated_score == 0  → 'Unacc'
 *   no row / null          → 'None' (only valid for non-response labs, whose flip-set is empty)
 */
final class Asserter
{
    public function __construct(private Db $db) {}

    /**
     * @return array{passes:int, fails:int, mismatches:array<int,array>}
     */
    public function assert(array $provision): array
    {
        $shipmentId = $provision['shipment_id'];
        $sampleIds = array_keys($provision['samples']);

        $rows = $this->db->all(
            "SELECT shipment_map_id, sample_id, calculated_score
               FROM response_result_generic_test
              WHERE shipment_map_id IN (
                    SELECT map_id FROM shipment_participant_map WHERE shipment_id = ?)",
            [$shipmentId]
        );
        $byMap = [];
        foreach ($rows as $r) {
            $byMap[(int) $r['shipment_map_id']][(int) $r['sample_id']] = $r['calculated_score'];
        }

        $passes = 0;
        $fails  = 0;
        $mismatches = [];

        foreach ($provision['assignments'] as $a) {
            // Non-response labs have no response rows to verify.
            if (($a['response_state'] ?? null) !== null) {
                $passes++;
                continue;
            }

            $flip = $a['flip'];
            $actual = $byMap[$a['map_id']] ?? [];
            $sampleFailures = [];
            foreach ($sampleIds as $sid) {
                $expected = in_array($sid, $flip, true) ? 'Unacc' : 'Acc';
                $got = self::verdictFromScore($actual[$sid] ?? null);
                if ($got !== $expected) {
                    $sampleFailures[] = sprintf(
                        'S%d: expected %s, got %s (raw=%s)',
                        $sid,
                        $expected,
                        $got,
                        ($actual[$sid] ?? null) === null ? '(none)' : $actual[$sid]
                    );
                }
            }

            if (empty($sampleFailures)) {
                $passes++;
            } else {
                $fails++;
                $mismatches[] = [
                    'map_id'      => $a['map_id'],
                    'participant' => $a['participant_id'],
                    'aberration'  => $a['aberration'],
                    'samples'     => $sampleFailures,
                ];
            }
        }

        return ['passes' => $passes, 'fails' => $fails, 'mismatches' => $mismatches];
    }

    private static function verdictFromScore(mixed $score): string
    {
        if ($score === null || $score === '') {
            return 'None';
        }
        return ((float) $score) > 0 ? 'Acc' : 'Unacc';
    }
}
