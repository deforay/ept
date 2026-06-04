<?php

/**
 * Independent expected verdicts for the Vietnam (NIHE) DTS algorithm.
 *
 * Sourced from the NIHE workbook (~/Downloads/VIETNAM/Assesment_1.1_Amit_21_May_2026.xlsx)
 * and the spec at ~/Downloads/VIETNAM/vietnam-dts-algorithm.md. This is the WHOLE POINT
 * of the harness: these expectations are NOT computed from algoVietnam — they are
 * declared from the spec. A bug in algoVietnam that drifts from the spec fails the test.
 *
 * Sample set is FIXED in Phase 2 (10 samples — matches the NIHE template panel size):
 *   S1:  Positive, diluted     (weak positive)
 *   S2:  Positive, not diluted
 *   S3:  Negative
 *   S4:  Negative
 *   S5:  Negative
 *   S6:  Positive, not diluted
 *   S7:  Positive, diluted     (weak positive)
 *   S8:  Negative
 *   S9:  Positive, not diluted
 *   S10: Negative
 *
 * Verdict vocabulary (mapped from response_result_dts.calculated_score):
 *   'Acc'     — 'Pass'          (NIHE "Acceptable")
 *   'Unacc'   — 'Fail'          (NIHE "Unacceptable")
 *   'NotEval' — 'Not Evaluated' (consensus short-circuit)
 */

return [
    'samples' => [
        1  => ['ref' => 'P', 'diluted' => true,  'label' => 'Sample 1'],
        2  => ['ref' => 'P', 'diluted' => false, 'label' => 'Sample 2'],
        3  => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 3'],
        4  => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 4'],
        5  => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 5'],
        6  => ['ref' => 'P', 'diluted' => false, 'label' => 'Sample 6'],
        7  => ['ref' => 'P', 'diluted' => true,  'label' => 'Sample 7'],
        8  => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 8'],
        9  => ['ref' => 'P', 'diluted' => false, 'label' => 'Sample 9'],
        10 => ['ref' => 'N', 'diluted' => false, 'label' => 'Sample 10'],
    ],

    'aberrations' => [
        'fully_correct' => [
            'label'         => 'Fully correct responses for the tier',
            'allowed_tiers' => ['screening', 'confirmatory'],
            'expected'      => [
                'screening'    => [1 => 'Acc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
                'confirmatory' => [1 => 'Acc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
            ],
        ],

        'screening_concludes_positive' => [
            'label'         => 'Screening lab concludes Positive on S2 (must always refer)',
            'allowed_tiers' => ['screening'],
            'expected'      => [
                'screening' => [1 => 'Acc', 2 => 'Unacc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
            ],
        ],

        'confirmatory_calls_pos_negative' => [
            'label'         => 'Confirmatory lab reports S2 (positive) as Negative',
            'allowed_tiers' => ['confirmatory'],
            'expected'      => [
                'confirmatory' => [1 => 'Acc', 2 => 'Unacc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
            ],
        ],

        'confirmatory_false_reactive_on_N' => [
            'label'         => 'Confirmatory lab reports S3 (negative) as Positive',
            'allowed_tiers' => ['confirmatory'],
            'expected'      => [
                'confirmatory' => [1 => 'Acc', 2 => 'Acc', 3 => 'Unacc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
            ],
        ],

        'consensus_minority_kit' => [
            'label'         => 'S1 reported on a non-reference kit with no peer group (consensus fails)',
            'allowed_tiers' => ['screening', 'confirmatory'],
            'expected'      => [
                'screening'    => [1 => 'NotEval', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
                'confirmatory' => [1 => 'NotEval', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
            ],
        ],

        'consensus_group_passes' => [
            'label'         => 'S1 reported on a shared non-reference kit by 10+ labs (consensus passes)',
            'allowed_tiers' => ['screening', 'confirmatory'],
            'expected'      => [
                // Consensus passes → algoVietnam evaluates normally on the baseline-reactive
                // S1 responses, which are Acceptable for both tiers.
                'screening'    => [1 => 'Acc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
                'confirmatory' => [1 => 'Acc', 2 => 'Acc', 3 => 'Acc', 4 => 'Acc', 5 => 'Acc', 6 => 'Acc', 7 => 'Acc', 8 => 'Acc', 9 => 'Acc', 10 => 'Acc'],
            ],
        ],

        // No response_result_dts rows exist for these labs, so no per-sample
        // calculated_score is produced. Expectations are empty — the asserter's
        // per-sample comparison loop iterates over zero entries and the lab
        // counts as a Pass (nothing to verify against). The harness still wants
        // these in the catalogue so allocations are tracked and the report
        // sections that handle "did not respond" can be exercised.
        'no_response' => [
            'label'         => 'Lab never submitted any response',
            'allowed_tiers' => ['screening', 'confirmatory'],
            'expected'      => [
                'screening'    => [],
                'confirmatory' => [],
            ],
        ],
        'not_tested' => [
            'label'         => 'Lab submitted "PT test not performed" with a reason',
            'allowed_tiers' => ['screening', 'confirmatory'],
            'expected'      => [
                'screening'    => [],
                'confirmatory' => [],
            ],
        ],
    ],
];
