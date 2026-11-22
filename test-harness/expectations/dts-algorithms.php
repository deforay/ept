<?php

/**
 * The result combinations that pass each DTS algorithm written as rules in
 * Pt_Evaluation_Dts_Algorithm. Every other combination fails.
 *
 * Recorded on Nov 22 2026 from the hand-written algorithm methods that the rules
 * replaced, so it pins the verdicts those methods gave. Edit a row here only when a
 * country changes its algorithm, and change the rules in the same commit.
 *
 * Columns: the tests that Pt_Evaluation_Dts_Algorithm::tests() lists for the
 * algorithm, in that order, then the final. Tests: - not done, R reactive,
 * NR non-reactive, I invalid, IND indeterminate, WR weak reactive, X a Test 3 that
 * the instance does not record. Finals: P positive, N negative, IND indeterminate.
 * '*' stands for any value: in a test column, any result of that test, and in the
 * final column, any final, for an algorithm that does not check it.
 */

return [
    'updated-3-tests' => [
        ['R', 'R', '-', '-', 'P'],
        ['R', 'R', 'R', '-', 'P'],
        ['R', 'R', 'NR', '-', 'P'],
        ['R', 'R', 'I', '-', 'P'],
        ['R', 'R', 'IND', '-', 'P'],
        ['R', 'R', 'WR', '-', 'P'],
        ['R', 'NR', '-', 'R', 'IND'],
        ['R', 'NR', '-', 'NR', 'N'],
        ['R', 'NR', 'R', 'R', 'IND'],
        ['R', 'NR', 'R', 'NR', 'N'],
        ['R', 'NR', 'NR', 'R', 'IND'],
        ['R', 'NR', 'NR', 'NR', 'N'],
        ['R', 'NR', 'I', 'R', 'IND'],
        ['R', 'NR', 'I', 'NR', 'N'],
        ['R', 'NR', 'IND', 'R', 'IND'],
        ['R', 'NR', 'IND', 'NR', 'N'],
        ['R', 'NR', 'WR', 'R', 'IND'],
        ['R', 'NR', 'WR', 'NR', 'N'],
        ['NR', '-', '-', '-', 'N'],
    ],
    'updated-3-tests:require-test-3' => [
        ['R', 'R', 'R', '-', 'P'],
        ['R', 'R', 'NR', '-', 'IND'],
        ['R', 'NR', '-', 'R', 'IND'],
        ['R', 'NR', '-', 'NR', 'N'],
        ['R', 'NR', 'R', 'R', 'IND'],
        ['R', 'NR', 'R', 'NR', 'N'],
        ['R', 'NR', 'NR', 'R', 'IND'],
        ['R', 'NR', 'NR', 'NR', 'N'],
        ['R', 'NR', 'I', 'R', 'IND'],
        ['R', 'NR', 'I', 'NR', 'N'],
        ['R', 'NR', 'IND', 'R', 'IND'],
        ['R', 'NR', 'IND', 'NR', 'N'],
        ['R', 'NR', 'WR', 'R', 'IND'],
        ['R', 'NR', 'WR', 'NR', 'N'],
        ['NR', '-', '-', '-', 'N'],
    ],
    'sierraLeone' => [
        ['R', 'R', '-', 'P'],
        ['R', 'R', 'NR', 'IND'],
        ['R', 'R', 'I', 'IND'],
        ['R', 'NR', 'R', 'P'],
        ['R', 'NR', 'NR', 'N'],
        ['NR', '-', '-', 'N'],
    ],
    // Columns: Test 1, Test 2, Test 3. The final is not checked.
    'serial' => [
        ['R', 'R', '-', '*'],
        ['R', 'R', 'R', '*'],
        ['R', 'R', 'X', '*'],
        ['R', 'NR', 'R', '*'],
        ['R', 'NR', 'NR', '*'],
        ['R', 'NR', 'X', '*'],
        ['NR', '-', '-', '*'],
        ['NR', '-', 'X', '*'],
    ],
    // Columns: Test 1, Test 2, Test 3. The final is not checked.
    'parallel' => [
        ['R', 'R', '-', '*'],
        ['R', 'R', 'X', '*'],
        ['R', 'NR', 'R', '*'],
        ['R', 'NR', 'NR', '*'],
        ['R', 'NR', 'X', '*'],
        ['NR', 'R', 'R', '*'],
        ['NR', 'R', 'NR', '*'],
        ['NR', 'R', 'X', '*'],
        ['NR', 'NR', '-', '*'],
        ['NR', 'NR', 'X', '*'],
    ],
    // Columns: Test 1, Test 2, Test 3, Repeat Test 1, final.
    'cotedivoire' => [
        ['R', 'R', 'R', '-', 'P'],
        ['R', 'NR', '*', 'R', 'P'],
        ['R', 'NR', '*', 'R', 'IND'],
        ['R', 'NR', '*', 'NR', 'N'],
        ['R', 'NR', '*', 'I', 'P'],
        ['R', 'NR', '*', 'I', 'IND'],
        ['NR', '-', '-', '-', 'N'],
    ],
    // Columns: Test 1, Test 2, Repeat Test 1, final. A 2-test panel has no Test 3.
    'malawi' => [
        ['R', 'R', '-', 'P'],
        ['R', 'NR', 'R', 'IND'],
        ['R', 'NR', 'NR', 'N'],
        ['NR', '-', '-', 'N'],
    ],
    // Columns: Test 1, Test 2, Test 3, Repeat Test 1, final.
    'malawi:three-test-panel' => [
        ['R', 'R', 'R', '-', 'P'],
        ['R', 'NR', '-', 'R', 'IND'],
        ['R', 'NR', '-', 'NR', 'N'],
        ['NR', '-', '-', '-', 'N'],
    ],
    // Columns: Test 1, Test 2, Test 3, Repeat Test 1, Repeat Test 2, final.
    'ghana' => [
        ['R', 'R', 'R', '*', '*', 'P'],
        ['R', 'NR', '*', 'R', 'R', 'P'],
        ['R', 'NR', '*', 'R', 'NR', 'IND'],
        ['R', 'NR', '*', 'NR', 'NR', 'N'],
        ['NR', '-', '-', '*', '*', 'N'],
    ],
];
