<?php

/**
 * Independent expected verdicts for the "Updated 3-tests" DTS algorithm
 * (scheme_config.dts.dtsSchemeType = 'updated-3-tests', algorithm = 'dts-3-tests').
 *
 * These are declared from the algorithm spec, NOT computed from
 * Application_Model_Dts::algoUpdatedThreeTests — that's the whole point of the
 * harness. If the algorithm drifts from this, the test fails.
 *
 * Sample set (FIXED, 5 samples — mirrors a real per-shipment panel):
 *   S1: Positive
 *   S2: Negative
 *   S3: Positive
 *   S4: Negative
 *   S5: Negative
 *
 * Per-sample verdict (mapped from response_result_dts.calculated_score):
 *   'Acc'     — 'Pass'          (correct algorithm AND reference == reported)
 *   'Unacc'   — 'Fail'          (wrong final result OR algorithm violation)
 *   'NotEval' — 'Not Evaluated'
 *
 * This scheme is single-tier ('standard'): not screening/confirmatory-aware.
 */

return [
    'samples' => [
        1 => ['ref' => 'P', 'diluted' => false, 'label' => 'Sample 1'],
        2 => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 2'],
        3 => ['ref' => 'P', 'diluted' => false, 'label' => 'Sample 3'],
        4 => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 4'],
        5 => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 5'],
    ],

    'aberrations' => [
        'fully_correct' => [
            'label'         => 'Fully correct responses (algorithm + final result)',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                'standard' => [1 => 'Acc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'positive_reported_negative' => [
            'label'         => 'S1 (positive) reported Negative (missed positive)',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                'standard' => [1 => 'Unacc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'negative_reported_positive' => [
            'label'         => 'S2 (negative) reported Positive (false reactive)',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                'standard' => [1 => 'Acc', 2 => 'Unacc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'algorithm_incomplete' => [
            'label'         => 'S1 (positive) concluded Positive after one reactive test (algorithm violation)',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                // Final result (P) is correct, but the 3-test algorithm was not satisfied
                // (only T1 reactive) → algoUpdatedThreeTests fails → sample Unacceptable.
                'standard' => [1 => 'Unacc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        // No response_result_dts rows are written for these labs, so there are no
        // per-sample calculated_score values to verify. Empty expectations → the
        // asserter iterates zero entries and the lab counts as a Pass. Kept in the
        // catalogue so the "did not respond" / "PT test not performed" report code
        // paths are still exercised on every harness run.
        'no_response' => [
            'label'         => 'Lab never submitted any response',
            'allowed_tiers' => ['standard'],
            'expected'      => ['standard' => []],
        ],
        'not_tested' => [
            'label'         => 'Lab submitted "PT test not performed" with a reason',
            'allowed_tiers' => ['standard'],
            'expected'      => ['standard' => []],
        ],
    ],
];
