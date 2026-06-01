<?php

declare(strict_types=1);

namespace EptTestHarness\Aberrations;

/**
 * Response-row generator for the Vietnam (NIHE) DTS variant.
 *
 * Each apply_* method returns the per-sample response spec for ONE participant.
 * The spec is declarative — codes ('R','WR','NR','-','P','N','INC') and a kit
 * strategy. The Provisioner resolves codes to r_possibleresult.id and kit
 * strategies to actual TestKitName_IDs.
 *
 * Spec shape per sample:
 *   [
 *     't1'    => 'R'|'WR'|'NR'|'-',
 *     't2'    => 'R'|'WR'|'NR'|'-',
 *     't3'    => 'R'|'WR'|'NR'|'-',
 *     'final' => 'P'|'N'|'INC'|null,           (null = leave blank)
 *     'kit1'  => 'reference'|'minority_unique', (test_kit_name_1)
 *     'kit2'  => 'reference',
 *     'kit3'  => 'reference',
 *   ]
 *
 * Note: 'minority_unique' tells the Provisioner to allocate a non-reference kit
 * that's unique to this participant — guaranteeing peer count = 1 < MIN_PEER_LABS,
 * so consensus excludes that (sample, kit) pair.
 */
final class Vietnam
{
    /** @return array<string, array{label:string, allowed_tiers: array<string>}> */
    public static function catalogue(): array
    {
        return [
            'fully_correct' => [
                'label'         => 'Fully correct responses for the tier',
                'allowed_tiers' => ['screening', 'confirmatory'],
            ],
            'screening_concludes_positive' => [
                'label'         => 'Screening: concludes Positive on S2 (must always refer)',
                'allowed_tiers' => ['screening'],
            ],
            'confirmatory_calls_pos_negative' => [
                'label'         => 'Confirmatory: reports S2 (positive) as Negative',
                'allowed_tiers' => ['confirmatory'],
            ],
            'confirmatory_false_reactive_on_N' => [
                'label'         => 'Confirmatory: reports S3 (negative) as Positive',
                'allowed_tiers' => ['confirmatory'],
            ],
            'consensus_minority_kit' => [
                'label'         => 'S1 reported on a minority non-reference kit (consensus fails)',
                'allowed_tiers' => ['screening', 'confirmatory'],
            ],
        ];
    }

    /** Generate response spec for one (aberration, tier) combination. */
    public static function generate(string $aberration, string $tier): array
    {
        $method = 'apply_' . $aberration;
        if (!method_exists(self::class, $method)) {
            throw new \RuntimeException("Unknown Vietnam aberration: $aberration");
        }
        return self::$method($tier);
    }

    // ---------- Baseline correct-response builders, by tier ----------

    /**
     * Correct responses for a screening lab on the fixed 5-sample set.
     * Inconclusive samples require lab_comment='sent_for_confirmation' (per the
     * Vietnam algorithm's screening criterion #3).
     */
    private static function correctScreening(): array
    {
        return [
            1 => ['t1' => 'R',  't2' => 'R', 't3' => '-', 'final' => 'INC', 'comment' => 'sent_for_confirmation', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            2 => ['t1' => 'R',  't2' => 'R', 't3' => '-', 'final' => 'INC', 'comment' => 'sent_for_confirmation', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            3 => ['t1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N',   'comment' => null,                    'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            4 => ['t1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N',   'comment' => null,                    'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            5 => ['t1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N',   'comment' => null,                    'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
        ];
    }

    /** Correct responses for a confirmatory lab on the fixed 5-sample set. */
    private static function correctConfirmatory(): array
    {
        return [
            1 => ['t1' => 'R',  't2' => 'WR', 't3' => 'R', 'final' => 'P', 'comment' => null, 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            2 => ['t1' => 'R',  't2' => 'R',  't3' => 'R', 'final' => 'P', 'comment' => null, 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            3 => ['t1' => 'NR', 't2' => '-',  't3' => '-', 'final' => 'N', 'comment' => null, 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            4 => ['t1' => 'NR', 't2' => '-',  't3' => '-', 'final' => 'N', 'comment' => null, 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
            5 => ['t1' => 'NR', 't2' => '-',  't3' => '-', 'final' => 'N', 'comment' => null, 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'],
        ];
    }

    private static function baseline(string $tier): array
    {
        return $tier === 'screening' ? self::correctScreening() : self::correctConfirmatory();
    }

    // ---------- Aberration handlers ----------

    private static function apply_fully_correct(string $tier): array
    {
        return self::baseline($tier);
    }

    private static function apply_screening_concludes_positive(string $tier): array
    {
        $r = self::baseline($tier);
        // S2 (positive, non-diluted): screening reports final = P instead of INC.
        $r[2]['final'] = 'P';
        return $r;
    }

    private static function apply_confirmatory_calls_pos_negative(string $tier): array
    {
        $r = self::baseline($tier);
        // S2 (positive, non-diluted): confirmatory reports T1=NR, no further tests, final=N.
        $r[2] = ['t1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }

    private static function apply_confirmatory_false_reactive_on_N(string $tier): array
    {
        $r = self::baseline($tier);
        // S3 (negative): confirmatory reports R/R/R/P.
        $r[3] = ['t1' => 'R', 't2' => 'R', 't3' => 'R', 'final' => 'P', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }

    private static function apply_consensus_minority_kit(string $tier): array
    {
        $r = self::baseline($tier);
        // S1 reported on a non-reference primary kit, unique to this participant.
        // Consensus check is keyed by (sample_id, test_kit_name_1); peer count = 1 < 10.
        $r[1]['kit1'] = 'minority_unique';
        return $r;
    }
}
