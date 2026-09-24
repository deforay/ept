<?php

/**
 * Independent expected verdicts for "Updated 3-tests" with Test 3 required
 * (scheme_config.dts.dtsRequireTest3 = 'yes', Zimbabwe national algorithm).
 *
 * Declared from the algorithm spec, NOT computed from algoUpdatedThreeTests.
 * A Positive needs Test 1, Test 2 and Test 3 all reactive; see
 * src/Aberrations/ZimbabweThreeTests.php for the full rule table.
 *
 * Sample set (FIXED, 5 samples):
 *   S1: Positive, S2: Negative, S3: Positive, S4: Negative, S5: Negative
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
            'label'         => 'Fully correct responses (all three tests on positives)',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                'standard' => [1 => 'Acc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'test3_missing' => [
            'label'         => 'S1 (positive) concluded Positive with Test 3 blank',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                // Final result is right, but Test 3 was never run (NMRL-PT ePT-01).
                'standard' => [1 => 'Unacc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'test3_nonreactive_reported_positive' => [
            'label'         => 'S1 (positive) reported Positive although Test 3 was non-reactive',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                // R, R, NR is Inconclusive under the 3-test algorithm, not Positive.
                'standard' => [1 => 'Unacc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'positive_reported_negative' => [
            'label'         => 'S1 (positive) reported Negative (missed positive)',
            'allowed_tiers' => ['standard'],
            'expected'      => [
                'standard' => [1 => 'Unacc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc'],
            ],
        ],

        'no_response' => [
            'label'         => 'Lab never submitted any response',
            'allowed_tiers' => ['standard'],
            'expected'      => ['standard' => []],
        ],
    ],
];
