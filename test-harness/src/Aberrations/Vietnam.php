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
            'consensus_group_passes' => [
                'label'         => 'S1 reported on a shared non-reference kit by 10+ labs (consensus passes)',
                'allowed_tiers' => ['screening', 'confirmatory'],
                'requires_min'  => 10, // need at least this many labs to clear MIN_PEER_LABS
            ],
        ];
    }

    /**
     * Generate response spec for one (aberration, tier) combination.
     * $seed is a per-participant deterministic seed used to pick among VALID
     * response variants — different participants get visibly different shapes
     * while every variant is still a correct (or aberrant) NIHE response.
     */
    public static function generate(string $aberration, string $tier, int $seed = 0): array
    {
        $method = 'apply_' . $aberration;
        if (!method_exists(self::class, $method)) {
            throw new \RuntimeException("Unknown Vietnam aberration: $aberration");
        }
        return self::$method($tier, $seed);
    }

    // ---------- Baseline correct-response builders, by tier ----------

    /**
     * Correct responses for a screening lab on the fixed 5-sample set.
     * Picks among VALID Vietnam screening patterns based on $seed so test shipments
     * don't all look identical. Every variant is Acceptable per algoVietnam.
     */
    private static function correctScreening(int $seed): array
    {
        // Variants for a positive sample (S1, S2): screening lab can do 1, 2 or 3 tests.
        // All are reactive (R or WR), final = INC with 'sent_for_confirmation' comment.
        $posVariants = [
            ['t1' => 'R',  't2' => '-',  't3' => '-', 'final' => 'INC'],
            ['t1' => 'R',  't2' => 'R',  't3' => '-', 'final' => 'INC'],
            ['t1' => 'R',  't2' => 'R',  't3' => 'R', 'final' => 'INC'],
            ['t1' => 'R',  't2' => 'WR', 't3' => '-', 'final' => 'INC'],
            ['t1' => 'WR', 't2' => 'R',  't3' => '-', 'final' => 'INC'],
        ];
        // Variants for a negative sample (S3-5): always NR final = N, may do 1 or 2 tests.
        $negVariants = [
            ['t1' => 'NR', 't2' => '-',  't3' => '-', 'final' => 'N'],
            ['t1' => 'NR', 't2' => 'NR', 't3' => '-', 'final' => 'N'],
        ];

        $pos = static fn (int $sid) => self::pick($posVariants, $seed, $sid);
        $neg = static fn (int $sid) => self::pick($negVariants, $seed, $sid);

        $kitsRef = ['kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return [
            1 => ['comment' => 'sent_for_confirmation'] + $pos(1) + $kitsRef,
            2 => ['comment' => 'sent_for_confirmation'] + $pos(2) + $kitsRef,
            3 => ['comment' => null]                   + $neg(3) + $kitsRef,
            4 => ['comment' => null]                   + $neg(4) + $kitsRef,
            5 => ['comment' => null]                   + $neg(5) + $kitsRef,
        ];
    }

    /**
     * Correct responses for a confirmatory lab on the fixed 5-sample set.
     * Confirmatory needs all 3 tests to conclude Positive; valid variants differ
     * only in the WR placement and the optional intermediate read.
     */
    private static function correctConfirmatory(int $seed): array
    {
        // S1 (diluted P): all variants have 3 tests, final = P. WR may sit anywhere.
        $dilutedPosVariants = [
            ['t1' => 'R',  't2' => 'WR', 't3' => 'R',  'final' => 'P'],
            ['t1' => 'WR', 't2' => 'R',  't3' => 'R',  'final' => 'P'],
            ['t1' => 'R',  't2' => 'R',  't3' => 'WR', 'final' => 'P'],
            ['t1' => 'R',  't2' => 'R',  't3' => 'R',  'final' => 'P'],
        ];
        // S2 (non-diluted P): all three R, final P.
        $strongPosVariants = [
            ['t1' => 'R',  't2' => 'R',  't3' => 'R',  'final' => 'P'],
        ];
        // S3-5 (N): confirmatory typically stops at T1=NR.
        $negVariants = [
            ['t1' => 'NR', 't2' => '-',  't3' => '-', 'final' => 'N'],
            ['t1' => 'NR', 't2' => 'NR', 't3' => '-', 'final' => 'N'],
        ];

        $kitsRef = ['kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return [
            1 => ['comment' => null] + self::pick($dilutedPosVariants, $seed, 1) + $kitsRef,
            2 => ['comment' => null] + self::pick($strongPosVariants,  $seed, 2) + $kitsRef,
            3 => ['comment' => null] + self::pick($negVariants,        $seed, 3) + $kitsRef,
            4 => ['comment' => null] + self::pick($negVariants,        $seed, 4) + $kitsRef,
            5 => ['comment' => null] + self::pick($negVariants,        $seed, 5) + $kitsRef,
        ];
    }

    /** Deterministic pick from a variant list using the given seed + bucket. */
    private static function pick(array $variants, int $seed, int $bucket): array
    {
        if (empty($variants)) {
            return [];
        }
        $idx = abs(crc32($seed . ':' . $bucket)) % count($variants);
        return $variants[$idx];
    }

    private static function baseline(string $tier, int $seed): array
    {
        return $tier === 'screening' ? self::correctScreening($seed) : self::correctConfirmatory($seed);
    }

    // ---------- Aberration handlers ----------

    private static function apply_fully_correct(string $tier, int $seed): array
    {
        return self::baseline($tier, $seed);
    }

    private static function apply_screening_concludes_positive(string $tier, int $seed): array
    {
        $r = self::baseline($tier, $seed);
        // S2 (positive, non-diluted): screening reports final = P instead of INC.
        $r[2]['final'] = 'P';
        return $r;
    }

    private static function apply_confirmatory_calls_pos_negative(string $tier, int $seed): array
    {
        $r = self::baseline($tier, $seed);
        // S2 (positive, non-diluted): confirmatory reports T1=NR, no further tests, final=N.
        $r[2] = ['comment' => null, 't1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }

    private static function apply_confirmatory_false_reactive_on_N(string $tier, int $seed): array
    {
        $r = self::baseline($tier, $seed);
        // S3 (negative): confirmatory reports R/R/R/P.
        $r[3] = ['comment' => null, 't1' => 'R', 't2' => 'R', 't3' => 'R', 'final' => 'P', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }

    private static function apply_consensus_minority_kit(string $tier, int $seed): array
    {
        $r = self::baseline($tier, $seed);
        // S1 reported on a non-reference primary kit, unique to this participant.
        // Consensus check is keyed by (sample_id, test_kit_name_1); peer count = 1 < 10.
        $r[1]['kit1'] = 'minority_unique';
        return $r;
    }

    private static function apply_consensus_group_passes(string $tier, int $seed): array
    {
        $r = self::baseline($tier, $seed);
        // S1 reported on a SHARED non-reference primary kit. Every participant with this
        // aberration uses the same kit on S1, so the (sample_id, kit) group hits
        // MIN_PEER_LABS=10. Baseline responses are all reactive on S1, satisfying the
        // ≥80% reactive threshold → consensus passes → algoVietnam evaluates normally.
        $r[1]['kit1'] = 'minority_shared';
        return $r;
    }
}
