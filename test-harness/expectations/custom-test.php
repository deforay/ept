<?php

/**
 * Scheme-agnostic spec for the qualitative custom-test harness.
 *
 * The harness runs against an EXISTING user-configured scheme (scheme_list.
 * is_user_configured='yes') chosen at runtime — HBV, HCV, SYP, etc. Because each
 * scheme has its own result vocabulary, this file does NOT hardcode result codes.
 * Instead it declares:
 *   - the panel size and its Positive/Negative reference pattern, and
 *   - the aberrations as flip-sets (which samples are reported incorrectly).
 *
 * The harness resolves 'P'/'N' to the selected scheme's actual FINAL result codes
 * (e.g. HBV-F-P / HBV-F-N), writes correct/flipped responses, then asserts that the
 * evaluator scored exactly the flipped samples as incorrect. The flip-set is the
 * independent expectation: "I flipped S1, so S1 must score 0" — and the real
 * CustomTest evaluator must agree.
 *
 * Per-sample verdict (mapped from response_result_generic_test.calculated_score):
 *   'Acc'   — calculated_score > 0  (reported == reference)
 *   'Unacc' — calculated_score == 0 (reported != reference)
 */

return [
    'sample_count' => 5,
    // Reference pattern across the panel; 'P' -> scheme's positive FINAL code,
    // 'N' -> scheme's negative FINAL code.
    'pattern' => ['P', 'N', 'P', 'N', 'N'],

    'aberrations' => [
        'fully_correct' => ['label' => 'All samples reported correctly', 'flip' => []],
        'one_incorrect' => ['label' => 'S1 reported incorrectly (participant fails)', 'flip' => [1]],
        'two_incorrect' => ['label' => 'S1 and S2 reported incorrectly', 'flip' => [1, 2]],
        'all_incorrect' => ['label' => 'Every sample reported incorrectly', 'flip' => 'all'],

        // Non-response states: no response rows written → no calculated_score → empty
        // expectation → counts as Pass (nothing to verify). Exercises the report's
        // "did not respond" / "PT test not performed" code paths.
        'no_response' => ['label' => 'Lab never submitted any response', 'flip' => [], 'response_state' => 'noresponse'],
        'not_tested' => ['label' => 'Lab submitted "PT test not performed" with a reason', 'flip' => [], 'response_state' => 'nottested'],
    ],
];
