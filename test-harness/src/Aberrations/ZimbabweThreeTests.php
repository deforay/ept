<?php

declare(strict_types=1);

namespace EptTestHarness\Aberrations;

/**
 * Response-row generator for "Updated 3-tests" with Test 3 required
 * (scheme_config.dts.dtsRequireTest3 = 'yes', as configured for Zimbabwe).
 *
 *   Negative      : T1 = NR, no further tests         → final N    (Pass)
 *   Positive      : T1 = R, T2 = R, T3 = R            → final P    (Pass)
 *   Inconclusive  : T1 = R, T2 = R, T3 = NR           → final IND/INC (algorithm Pass)
 *   T1 = R, T2 = R, T3 blank                          → algorithm Fail, whatever the final
 *   T1 = R, T2 = R, T3 = NR, final P                  → algorithm Fail
 *
 * The reference panel is only Positive/Negative, so the Inconclusive path can never be
 * Acceptable here (the final result disagrees with the reference); it is not exercised.
 *
 * Sample layout mirrors expectations/zimbabwe-3-tests.php:
 *   S1 Positive, S2 Negative, S3 Positive, S4 Negative, S5 Negative.
 */
final class ZimbabweThreeTests
{
    /** @return array<string, array{label:string, allowed_tiers: array<string>}> */
    public static function catalogue(): array
    {
        return [
            'fully_correct' => [
                'label'         => 'Fully correct responses (all three tests on positives)',
                'allowed_tiers' => ['standard'],
            ],
            'test3_missing' => [
                'label'         => 'S1 (positive) concluded Positive with Test 3 blank',
                'allowed_tiers' => ['standard'],
            ],
            'test3_nonreactive_reported_positive' => [
                'label'         => 'S1 (positive) reported Positive although Test 3 was non-reactive',
                'allowed_tiers' => ['standard'],
            ],
            'positive_reported_negative' => [
                'label'         => 'S1 (positive) reported Negative (missed positive)',
                'allowed_tiers' => ['standard'],
            ],
            'no_response' => [
                'label'          => 'Lab never submitted any response',
                'allowed_tiers'  => ['standard'],
                'response_state' => 'noresponse',
            ],
        ];
    }

    public static function generate(string $aberration, string $tier, int $seed = 0): array
    {
        $method = 'apply_' . $aberration;
        if (!method_exists(self::class, $method)) {
            throw new \RuntimeException("Unknown zimbabwe-3-tests aberration: $aberration");
        }
        return self::$method();
    }

    private static function sample(string $t1, string $t2, string $t3, ?string $final): array
    {
        return ['comment' => null, 't1' => $t1, 't2' => $t2, 't3' => $t3, 'final' => $final,
            'kit1' => 'reference', 'kit2' => 'reference', 'kit3' => 'reference'];
    }

    private static function baseline(): array
    {
        $pos = self::sample('R', 'R', 'R', 'P');
        $neg = self::sample('NR', '-', '-', 'N');
        return [1 => $pos, 2 => $neg, 3 => $pos, 4 => $neg, 5 => $neg];
    }

    private static function apply_fully_correct(): array
    {
        return self::baseline();
    }

    private static function apply_test3_missing(): array
    {
        $r = self::baseline();
        // NMRL-PT ePT-01: the final call is right but the algorithm was not completed.
        $r[1] = self::sample('R', 'R', '-', 'P');
        return $r;
    }

    private static function apply_test3_nonreactive_reported_positive(): array
    {
        $r = self::baseline();
        $r[1] = self::sample('R', 'R', 'NR', 'P');
        return $r;
    }

    private static function apply_positive_reported_negative(): array
    {
        $r = self::baseline();
        $r[1] = self::sample('NR', '-', '-', 'N');
        return $r;
    }
}
