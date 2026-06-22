<?php

declare(strict_types=1);

namespace EptTestHarness\Aberrations;

/**
 * Response-row generator for the "Updated 3-tests" DTS variant
 * (scheme_config.dts.dtsSchemeType = 'updated-3-tests', algorithm = 'dts-3-tests').
 *
 * Unlike Vietnam, this scheme is NOT tier-aware and NOT qualitative — it is the
 * serial 3-test confirmatory algorithm (Application_Model_Dts::algoUpdatedThreeTests):
 *
 *   Negative : T1 = NR, no further tests          → final N        (Pass)
 *   Positive : T1 = R, T2 = R (T3 optional)       → final P        (Pass)
 *   Discordant resolved: T1 = R, T2 = NR, repeatT1 = NR → final N  (Pass, not used here)
 *   Indeterminate      : T1 = R, T2 = NR, repeatT1 = R  → final I   (Pass, not used here)
 *   anything else                                  → algorithm Fail
 *
 * A per-sample verdict (response_result_dts.calculated_score) is Pass only when
 * BOTH the algorithm holds AND reference_result == reported_result. So there are
 * two independent ways to make a sample Unacceptable:
 *   - report the wrong final result (reference != reported), or
 *   - violate the algorithm (e.g. conclude Positive after a single reactive test).
 *
 * Spec shape per sample (resolved to r_possibleresult ids by the Provisioner):
 *   ['t1','t2','t3' => 'R'|'NR'|'-', 'final' => 'P'|'N'|null, 'kit1'|'kit2'|'kit3' => 'reference']
 *
 * Sample layout mirrors expectations/updated-3-tests.php:
 *   S1 Positive, S2 Negative, S3 Positive, S4 Negative, S5 Negative.
 */
final class UpdatedThreeTests
{
    /** @return array<string, array{label:string, allowed_tiers: array<string>}> */
    public static function catalogue(): array
    {
        return [
            'fully_correct' => [
                'label'         => 'Fully correct responses (algorithm + final result)',
                'allowed_tiers' => ['standard'],
            ],
            'positive_reported_negative' => [
                'label'         => 'S1 (positive) reported Negative (missed positive)',
                'allowed_tiers' => ['standard'],
            ],
            'negative_reported_positive' => [
                'label'         => 'S2 (negative) reported Positive (false reactive)',
                'allowed_tiers' => ['standard'],
            ],
            'algorithm_incomplete' => [
                'label'         => 'S1 (positive) concluded Positive after one reactive test (algorithm violation)',
                'allowed_tiers' => ['standard'],
            ],

            // ---------- Non-response states ----------
            'no_response' => [
                'label'          => 'Lab never submitted any response',
                'allowed_tiers'  => ['standard'],
                'response_state' => 'noresponse',
            ],
            'not_tested' => [
                'label'          => 'Lab submitted "PT test not performed" with a reason',
                'allowed_tiers'  => ['standard'],
                'response_state' => 'nottested',
            ],
        ];
    }

    /**
     * Generate the per-sample response spec for one (aberration, tier) participant.
     * $seed varies the VALID shape (e.g. 2-test vs 3-test positive) per participant
     * while keeping every fully-correct response algorithm-correct.
     */
    public static function generate(string $aberration, string $tier, int $seed = 0): array
    {
        $method = 'apply_' . $aberration;
        if (!method_exists(self::class, $method)) {
            throw new \RuntimeException("Unknown updated-3-tests aberration: $aberration");
        }
        return self::$method($tier, $seed);
    }

    // ---------- Baseline correct responses ----------

    /**
     * Correct response for the fixed 5-sample set.
     * Positives may be reported as 2-test (R,R) or 3-test (R,R,R) — both are
     * algorithm-correct. Negatives MUST be single-test (T1=NR, T2/T3 blank);
     * a second NR would make algoUpdatedThreeTests flag the algorithm.
     */
    private static function baseline(int $seed): array
    {
        $posVariants = [
            ['t1' => 'R', 't2' => 'R', 't3' => 'R', 'final' => 'P'],
            ['t1' => 'R', 't2' => 'R', 't3' => '-', 'final' => 'P'],
        ];
        $neg = ['t1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N'];

        $pos = static fn (int $sid) => self::pick($posVariants, $seed, $sid);
        $kitsRef = ['kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];

        return [
            1 => ['comment' => null] + $pos(1) + $kitsRef,
            2 => ['comment' => null] + $neg    + $kitsRef,
            3 => ['comment' => null] + $pos(3) + $kitsRef,
            4 => ['comment' => null] + $neg    + $kitsRef,
            5 => ['comment' => null] + $neg    + $kitsRef,
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

    // ---------- Aberration handlers ----------

    private static function apply_fully_correct(string $tier, int $seed): array
    {
        return self::baseline($seed);
    }

    private static function apply_positive_reported_negative(string $tier, int $seed): array
    {
        $r = self::baseline($seed);
        // S1 (positive): lab calls it Negative — algorithm-valid negative, but the
        // final result disagrees with the reference, so the sample is Unacceptable.
        $r[1] = ['comment' => null, 't1' => 'NR', 't2' => '-', 't3' => '-', 'final' => 'N', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }

    private static function apply_negative_reported_positive(string $tier, int $seed): array
    {
        $r = self::baseline($seed);
        // S2 (negative): lab reports R/R/R → Positive. Final disagrees with reference.
        $r[2] = ['comment' => null, 't1' => 'R', 't2' => 'R', 't3' => 'R', 'final' => 'P', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }

    private static function apply_algorithm_incomplete(string $tier, int $seed): array
    {
        $r = self::baseline($seed);
        // S1 (positive): final result is correct (P) but only ONE reactive test was
        // run — the 3-test algorithm requires T2 before concluding Positive, so
        // algoUpdatedThreeTests fails and the sample is Unacceptable despite the
        // correct final call. Isolates the algorithm check from the result check.
        $r[1] = ['comment' => null, 't1' => 'R', 't2' => '-', 't3' => '-', 'final' => 'P', 'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
        return $r;
    }
}
