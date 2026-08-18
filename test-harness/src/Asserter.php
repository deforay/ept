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
 *
 * When an aberration also declares 'expected_notes', the participant feedback written to
 * shipment_participant_map.failure_reason is checked against it — the exact string the NIHE
 * workbook prints in its Feedback/NOTE column, with '' meaning the cell is blank and nothing
 * may be printed. Note mismatches are counted and reported SEPARATELY from verdict
 * mismatches: a wrong verdict means a lab is graded wrongly, a wrong note means a lab is
 * graded correctly but told the wrong thing. They need different responses.
 */
final class Asserter
{
    public function __construct(private Db $db) {}

    /**
     * @param array $provision Output of Provisioner::provision()
     * @return array{passes:int, fails:int, mismatches:array<int,array>}
     */
    public function assert(string $variantKey, array $provision, ?int $sampleCount = null): array
    {
        $variant = Variants::get($variantKey);
        $expectations = require $variant['expectations'];
        $expectedByAberration = $expectations['aberrations'];
        // Mirror Provisioner's sample cap so the asserter only checks IDs the harness
        // actually provisioned. Without this we'd flag every dropped sample as a fail
        // because actualPerSample[$sid] doesn't exist for IDs > $sampleCount.
        if ($sampleCount !== null && $sampleCount > 0) {
            foreach ($expectedByAberration as $k => $meta) {
                foreach (($meta['expected'] ?? []) as $tier => $perSample) {
                    $expectedByAberration[$k]['expected'][$tier] = array_filter(
                        $perSample,
                        static fn ($v, $sid) => $sid <= $sampleCount,
                        ARRAY_FILTER_USE_BOTH
                    );
                }
            }
        }

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

        $sampleLabels = $this->sampleLabels($shipmentId);
        $notesByMap   = $this->notesByMap($shipmentId, $sampleLabels);

        $passes = 0;
        $fails  = 0;
        $mismatches = [];
        $noteMismatches = [];
        $acceptedDivergences = [];

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

            $workbook = $expectedByAberration[$aberration]['workbook'] ?? null;
            if (!empty($workbook['divergence'])) {
                $acceptedDivergences[$aberration] = [
                    'sheet'      => $workbook['sheet'],
                    'row'        => $workbook['row'],
                    'divergence' => $workbook['divergence'],
                ];
            }

            $expectedNotes = $expectedByAberration[$aberration]['expected_notes'][$tier] ?? [];
            foreach ($expectedNotes as $sampleId => $expectedNote) {
                $actualNotes = $notesByMap[$mapId][$sampleId] ?? [];
                $actualNote  = implode(' | ', $actualNotes);
                if ($actualNote !== $expectedNote) {
                    $noteMismatches[] = [
                        'map_id'      => $mapId,
                        'participant' => $a['participant_id'],
                        'aberration'  => $aberration,
                        'tier'        => $tier,
                        'workbook'    => $workbook,
                        'sample'      => $sampleId,
                        'expected'    => $expectedNote === '' ? '(no feedback)' : $expectedNote,
                        'actual'      => $actualNote === '' ? '(no feedback)' : $actualNote,
                    ];
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

        return [
            'passes'               => $passes,
            'fails'                => $fails,
            'mismatches'           => $mismatches,
            'note_mismatches'      => $noteMismatches,
            'accepted_divergences' => array_values($acceptedDivergences),
        ];
    }

    /** sample_label => sample_id for the shipment's reference panel. */
    private function sampleLabels(int $shipmentId): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT sample_id, sample_label FROM reference_result_dts WHERE shipment_id = ?",
            [$shipmentId]
        ) as $r) {
            $out[(string) $r['sample_label']] = (int) $r['sample_id'];
        }
        return $out;
    }

    /**
     * Participant feedback, keyed [map_id][sample_id] => list of recommendation strings.
     *
     * The evaluator writes one JSON blob per participant on shipment_participant_map, with
     * each entry tagged by sample_label ('warning') and carrying the recommendation
     * ('correctiveAction'). Entries whose label isn't one of this shipment's samples are
     * participant-level notes, not per-sample feedback, so they're skipped.
     */
    private function notesByMap(int $shipmentId, array $sampleLabels): array
    {
        $out = [];
        foreach ($this->db->all(
            "SELECT map_id, failure_reason FROM shipment_participant_map WHERE shipment_id = ?",
            [$shipmentId]
        ) as $r) {
            $decoded = json_decode((string) ($r['failure_reason'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $label = (string) ($entry['warning'] ?? '');
                if (!isset($sampleLabels[$label])) {
                    continue;
                }
                $action = trim((string) ($entry['correctiveAction'] ?? ''));
                if ($action === '') {
                    continue;
                }
                $out[(int) $r['map_id']][$sampleLabels[$label]][] = $action;
            }
        }
        return $out;
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
