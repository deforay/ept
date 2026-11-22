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
    // Serial: a non-reactive Test 3 after two reactive tests has no path.
    ['serial', ['test1' => 'R', 'test2' => 'R', 'test3' => 'NR'], 'P', [['dts.result_not_accepted', 2]]],
    // Serial: Test 1 non-reactive ends the algorithm. The final result is not checked.
    ['serial', ['test1' => 'NR', 'test2' => 'R', 'test3' => '-'], 'P', [['dts.testing_should_have_stopped', 24]]],
    // Serial: a Test 3 that the instance does not record (X) is accepted.
    ['serial', ['test1' => 'R', 'test2' => 'NR', 'test3' => 'X'], 'N', []],
    // Parallel: Test 1 and Test 2 agree, so Test 3 should not have been done.
    ['parallel', ['test1' => 'R', 'test2' => 'R', 'test3' => 'R'], 'P', [['dts.testing_should_have_stopped', 24]]],
    // Parallel: Test 1 and Test 2 disagree and need Test 3.
    ['parallel', ['test1' => 'NR', 'test2' => 'R', 'test3' => '-'], 'P', [['dts.test_not_reported', 23]]],
    // Côte d'Ivoire: a reactive Repeat Test 1 allows Positive or Indeterminate, not Negative.
    ['cotedivoire', ['test1' => 'R', 'test2' => 'NR', 'test3' => '-', 'repeat1' => 'R'], 'N', [['dts.final_result_mismatch', 25]]],
    ['cotedivoire', ['test1' => 'R', 'test2' => 'NR', 'test3' => '-', 'repeat1' => 'R'], 'IND', []],
    // Côte d'Ivoire: two reactive tests need Test 3, and the repeat is not done.
    ['cotedivoire', ['test1' => 'R', 'test2' => 'R', 'test3' => 'R', 'repeat1' => 'R'], 'P', [['dts.testing_should_have_stopped', 24]]],
    // Malawi 2-test panel: R/R gives Positive without Test 3.
    ['malawi', ['test1' => 'R', 'test2' => 'R', 'repeat1' => '-'], 'P', []],
    // Malawi 3-test panel: R/R needs Test 3 to confirm.
    ['malawi:three-test-panel', ['test1' => 'R', 'test2' => 'R', 'test3' => '-', 'repeat1' => '-'], 'P', [['dts.test_not_reported', 23]]],
    // Malawi 3-test panel: a discordant result is settled by Repeat Test 1, not Test 3.
    ['malawi:three-test-panel', ['test1' => 'R', 'test2' => 'NR', 'test3' => 'R', 'repeat1' => 'NR'], 'N', [['dts.testing_should_have_stopped', 24]]],
    // Ghana: a reactive Repeat Test 1 needs Repeat Test 2.
    ['ghana', ['test1' => 'R', 'test2' => 'NR', 'test3' => '-', 'repeat1' => 'R', 'repeat2' => '-'], 'P', [['dts.test_not_reported', 23]]],
    // Ghana: repeats are not read after Test 1 is non-reactive.
    ['ghana', ['test1' => 'NR', 'test2' => '-', 'test3' => '-', 'repeat1' => 'R', 'repeat2' => 'R'], 'N', []],
    // Ghana: Repeat Test 1 non-reactive and Repeat Test 2 reactive has no path.
    ['ghana', ['test1' => 'R', 'test2' => 'NR', 'test3' => '-', 'repeat1' => 'NR', 'repeat2' => 'R'], 'IND', [['dts.result_not_accepted', 2]]],
];
