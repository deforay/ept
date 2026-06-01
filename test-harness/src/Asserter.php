<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Reads response_result_dts.calculated_score per (map, sample) and compares
 * against the per-aberration-per-tier expected verdict from the variant's
 * expectations file.
 *
 * Verdict mapping:
 *   calculated_score 'Pass'           → 'Acc'
 *   calculated_score 'Fail'           → 'Unacc'
 *   calculated_score 'Not Evaluated'  → 'NotEval'
 *   anything else                     → 'Other' (treated as mismatch)
 */
final class Asserter
{
    public function __construct(private Db $db) {}

    /**
     * @param array $provision Output of Provisioner::provision()
     * @return array{passes:int, fails:int, mismatches:array<int,array>}
     */
    public function assert(string $variantKey, array $provision): array
    {
        $variant = Variants::get($variantKey);
        $expectations = require $variant['expectations'];
        $expectedByAberration = $expectations['aberrations'];

        $shipmentId = $provision['shipment_id'];
        $rows = $this->db->all(
            "SELECT shipment_map_id, sample_id, calculated_score
               FROM response_result_dts
              WHERE shipment_map_id IN (
                    SELECT map_id FROM shipment_participant_map WHERE shipment_id = ?)",
            [$shipmentId]
        );
        $byMap = [];
        foreach ($rows as $r) {
            $byMap[(int) $r['shipment_map_id']][(int) $r['sample_id']] = (string) ($r['calculated_score'] ?? '');
        }

        $passes = 0;
        $fails  = 0;
        $mismatches = [];

        foreach ($provision['assignments'] as $a) {
            $mapId = $a['map_id'];
            $aberration = $a['aberration'];
            $tier = $a['tier'];

            $expected = $expectedByAberration[$aberration]['expected'][$tier] ?? null;
            if ($expected === null) {
                $fails++;
                $mismatches[] = [
                    'map_id'      => $mapId,
                    'participant' => $a['participant_id'],
                    'aberration'  => $aberration,
                    'tier'        => $tier,
                    'reason'      => "No expectations declared for aberration='$aberration' tier='$tier'",
                ];
                continue;
            }

            $actualPerSample = $byMap[$mapId] ?? [];
            $sampleFailures = [];
            foreach ($expected as $sampleId => $expectedVerdict) {
                $rawScore = $actualPerSample[$sampleId] ?? '';
                $actualVerdict = self::verdictFromScore($rawScore);
                if ($actualVerdict !== $expectedVerdict) {
                    $sampleFailures[] = sprintf('S%d: expected %s, got %s (raw=%s)', $sampleId, $expectedVerdict, $actualVerdict, $rawScore === '' ? '(empty)' : $rawScore);
                }
            }

            if (empty($sampleFailures)) {
                $passes++;
            } else {
                $fails++;
                $mismatches[] = [
                    'map_id'      => $mapId,
                    'participant' => $a['participant_id'],
                    'aberration'  => $aberration,
                    'tier'        => $tier,
                    'samples'     => $sampleFailures,
                ];
            }
        }

        return ['passes' => $passes, 'fails' => $fails, 'mismatches' => $mismatches];
    }

    private static function verdictFromScore(string $score): string
    {
        return match ($score) {
            'Pass'          => 'Acc',
            'Fail'          => 'Unacc',
            'Not Evaluated' => 'NotEval',
            default         => 'Other(' . $score . ')',
        };
    }
}
