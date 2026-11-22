<?php

/**
 * The warnings and corrective actions that key DTS result combinations must get,
 * as reason codes and corrective action IDs. Checked by test-harness/bin/algorithms.
 *
 * Codes, not English text, so a wording change does not break the check. Each row:
 * algorithm, results by test, final, then the expected [reason code, action ID]
 * pairs in the order the warnings appear. Declared from the algorithm rules, not
 * computed from the code under test.
 */

return [
    // Sierra Leone 2-test: R/R/R → P. A reactive Test 3 after two reactive tests has no path.
    ['sierraLeone', ['test1' => 'R', 'test2' => 'R', 'test3' => 'R'], 'P', [['dts.result_not_accepted', 2]]],
    // 3-test: Test 1 non-reactive ends at Negative, so Tests 2 and 3 should not have been done.
    ['updated-3-tests', ['test1' => 'NR', 'test2' => 'NR', 'test3' => 'NR', 'repeat1' => '-'], 'N', [['dts.testing_should_have_stopped', 24]]],
    // 3-test: Test 1 reactive needs Test 2.
    ['updated-3-tests', ['test1' => 'R', 'test2' => '-', 'test3' => '-', 'repeat1' => '-'], 'P', [['dts.test_not_reported', 23]]],
    // 3-test: R/R gives Positive, reported Negative.
    ['updated-3-tests', ['test1' => 'R', 'test2' => 'R', 'test3' => '-', 'repeat1' => '-'], 'N', [['dts.final_result_mismatch', 25]]],
    // 3-test: R/NR needs Repeat Test 1.
    ['updated-3-tests', ['test1' => 'R', 'test2' => 'NR', 'test3' => '-', 'repeat1' => '-'], 'P', [['dts.test_not_reported', 23]]],
    // 3-test: an invalid Test 2 has no path.
    ['updated-3-tests', ['test1' => 'R', 'test2' => 'I', 'test3' => '-', 'repeat1' => '-'], 'P', [['dts.result_not_accepted', 2]]],
    // 3-test: two problems on one sample, both shown.
    ['updated-3-tests', ['test1' => 'NR', 'test2' => '-', 'test3' => 'R', 'repeat1' => '-'], 'P', [['dts.testing_should_have_stopped', 24], ['dts.final_result_mismatch', 25]]],
    // 3-test with Test 3 required: R/R needs Test 3.
    ['updated-3-tests:require-test-3', ['test1' => 'R', 'test2' => 'R', 'test3' => '-', 'repeat1' => '-'], 'P', [['dts.test_not_reported', 23]]],
    // Passing combinations get no warnings.
    ['updated-3-tests', ['test1' => 'R', 'test2' => 'R', 'test3' => '-', 'repeat1' => '-'], 'P', []],
    ['sierraLeone', ['test1' => 'R', 'test2' => 'NR', 'test3' => 'NR'], 'N', []],
];
