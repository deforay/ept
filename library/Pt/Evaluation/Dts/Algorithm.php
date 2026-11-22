<?php

/**
 * The national HIV testing algorithms that DTS evaluation checks, written as rules.
 *
 * Each algorithm is a set of steps. A step names the test it reads and maps each
 * result of that test to the next step, or to the final result that the algorithm
 * gives. '-' means the test was not done. A result that a step does not list is not
 * accepted at that step. Pt_Evaluation_Dts_AlgorithmCheck walks these rules and
 * explains any response that does not follow them.
 *
 * To change an algorithm, change its steps here. Run test-harness/bin/algorithms to
 * print every combination and to check the rules against
 * test-harness/expectations/dts-algorithms.php.
 *
 * Result codes: R reactive, NR non-reactive, I invalid. Final results: P positive,
 * N negative, IND indeterminate.
 *
 * Myanmar is not written as rules: it compares the response with the expected
 * result and gives half marks, so it stays in Application_Model_Dts::algoMyanmar().
 */
enum Pt_Evaluation_Dts_Algorithm: string
{
    /** The 3-test algorithm. Test 1 and Test 2 reactive is enough for Positive. */
    case ThreeTests = 'updated-3-tests';

    /** The 3-test algorithm with Test 3 required to confirm a positive (dtsRequireTest3). */
    case ThreeTestsRequireTest3 = 'updated-3-tests:require-test-3';

    /** Sierra Leone's 2-test algorithm, used before the move to 3 tests. */
    case SierraLeone = 'sierraLeone';

    /** The serial algorithm: each test runs only when the one before it is reactive. */
    case Serial = 'serial';

    /** The parallel algorithm: Test 1 and Test 2 run together, and Test 3 breaks a tie. */
    case Parallel = 'parallel';

    /** Côte d'Ivoire's algorithm. A repeat of Test 1 settles a disagreement. */
    case CoteDivoire = 'cotedivoire';

    /** Malawi's algorithm on a 2-test panel, which has no Test 3. */
    case Malawi = 'malawi';

    /** Malawi's algorithm on a 3-test panel, where Test 3 confirms a positive. */
    case MalawiThreeTestPanel = 'malawi:three-test-panel';

    /** Ghana's algorithm. Test 1 and Test 2 are both repeated when they disagree. */
    case Ghana = 'ghana';

    /** The tests this algorithm reads, in order. Any other test is not part of it. */
    public function tests(): array
    {
        return match ($this) {
            self::ThreeTests, self::ThreeTestsRequireTest3 => ['test1', 'test2', 'test3', 'repeat1'],
            self::SierraLeone, self::Serial, self::Parallel => ['test1', 'test2', 'test3'],
            self::CoteDivoire, self::MalawiThreeTestPanel => ['test1', 'test2', 'test3', 'repeat1'],
            self::Malawi => ['test1', 'test2', 'repeat1'],
            self::Ghana => ['test1', 'test2', 'test3', 'repeat1', 'repeat2'],
        };
    }

    /**
     * Whether a sample also fails when the reported final result differs from the
     * one the tests give. Serial and parallel never checked it: the final result is
     * scored on its own.
     */
    public function checksFinal(): bool
    {
        return !in_array($this, [self::Serial, self::Parallel], true);
    }

    /**
     * The results that mean a test was not done. 'X' marks a Test 3 that the
     * instance does not record (dtsOptionalTest3).
     *
     * @return list<string>
     */
    public function notDone(): array
    {
        return in_array($this, [self::Serial, self::Parallel], true) ? ['-', 'X'] : ['-'];
    }

    /**
     * The steps, keyed by step name. Evaluation starts at the first step.
     *
     * @return array<string, array<string, mixed>>
     */
    public function steps(): array
    {
        return match ($this) {
            self::ThreeTests => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                // Test 3 is not read once Test 1 and Test 2 are both reactive.
                'test2' => ['test' => 'test2', 'R' => self::gives('P', ignore: ['test3']), 'NR' => 'repeat1'],
                // Test 1 and Test 2 disagree: repeat Test 1 to settle it.
                'repeat1' => [
                    'test' => 'repeat1',
                    'NR' => self::gives('N', ignore: ['test3']),
                    'R' => self::gives('IND', ignore: ['test3']),
                ],
            ],
            self::ThreeTestsRequireTest3 => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => 'test3', 'NR' => 'repeat1'],
                'test3' => ['test' => 'test3', 'R' => self::gives('P'), 'NR' => self::gives('IND')],
                'repeat1' => [
                    'test' => 'repeat1',
                    'NR' => self::gives('N', ignore: ['test3']),
                    'R' => self::gives('IND', ignore: ['test3']),
                ],
            ],
            self::SierraLeone => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => 'afterTwoReactive', 'NR' => 'tieBreaker'],
                // Two reactive tests give Positive. A non-reactive or invalid Test 3
                // after them makes the result Indeterminate.
                'afterTwoReactive' => [
                    'test' => 'test3',
                    '-' => self::gives('P'),
                    'NR' => self::gives('IND'),
                    'I' => self::gives('IND'),
                ],
                // Test 1 and Test 2 disagree: Test 3 breaks the tie.
                'tieBreaker' => ['test' => 'test3', 'R' => self::gives('P'), 'NR' => self::gives('N')],
            ],
            // Serial and parallel do not check the final result, so a step that ends
            // on Test 3 gives no final result (null). X is a Test 3 that is not recorded.
            self::Serial => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => 'afterTwoReactive', 'NR' => 'tieBreaker'],
                'afterTwoReactive' => [
                    'test' => 'test3',
                    '-' => self::gives('P'),
                    'R' => self::gives('P'),
                    'X' => self::gives('P'),
                ],
                'tieBreaker' => ['test' => 'test3', 'R' => self::gives(null), 'NR' => self::gives(null), 'X' => self::gives(null)],
            ],
            self::Parallel => [
                'test1' => ['test' => 'test1', 'R' => 'afterTest1Reactive', 'NR' => 'afterTest1NonReactive'],
                'afterTest1Reactive' => ['test' => 'test2', 'R' => self::gives('P'), 'NR' => 'tieBreaker'],
                'afterTest1NonReactive' => ['test' => 'test2', 'NR' => self::gives('N'), 'R' => 'tieBreaker'],
                'tieBreaker' => ['test' => 'test3', 'R' => self::gives(null), 'NR' => self::gives(null), 'X' => self::gives(null)],
            ],
            self::CoteDivoire => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => 'test3', 'NR' => 'repeat1'],
                'test3' => ['test' => 'test3', 'R' => self::gives('P')],
                // Test 1 and Test 2 disagree: repeat Test 1. A reactive or invalid repeat
                // allows Positive or Indeterminate.
                'repeat1' => [
                    'test' => 'repeat1',
                    'NR' => self::gives('N', ignore: ['test3']),
                    'R' => self::gives(['P', 'IND'], ignore: ['test3']),
                    'I' => self::gives(['P', 'IND'], ignore: ['test3']),
                ],
            ],
            self::Malawi => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => self::gives('P'), 'NR' => 'repeat1'],
                'repeat1' => ['test' => 'repeat1', 'NR' => self::gives('N'), 'R' => self::gives('IND')],
            ],
            self::MalawiThreeTestPanel => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N'), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => 'test3', 'NR' => 'repeat1'],
                'test3' => ['test' => 'test3', 'R' => self::gives('P')],
                'repeat1' => ['test' => 'repeat1', 'NR' => self::gives('N'), 'R' => self::gives('IND')],
            ],
            self::Ghana => [
                'test1' => ['test' => 'test1', 'NR' => self::gives('N', ignore: ['repeat1', 'repeat2']), 'R' => 'test2'],
                'test2' => ['test' => 'test2', 'R' => 'test3', 'NR' => 'repeat1'],
                'test3' => ['test' => 'test3', 'R' => self::gives('P', ignore: ['repeat1', 'repeat2'])],
                // Test 1 and Test 2 disagree: repeat both.
                'repeat1' => ['test' => 'repeat1', 'NR' => 'repeat2AfterNonReactive', 'R' => 'repeat2AfterReactive'],
                'repeat2AfterNonReactive' => ['test' => 'repeat2', 'NR' => self::gives('N', ignore: ['test3'])],
                'repeat2AfterReactive' => [
                    'test' => 'repeat2',
                    'R' => self::gives('P', ignore: ['test3']),
                    'NR' => self::gives('IND', ignore: ['test3']),
                ],
            ],
        };
    }

    /**
     * A final result. $ignore lists the tests that the algorithm does not read once it
     * reaches this result, so a result in them is neither needed nor a mistake. A list
     * of finals accepts any of them. null ends the algorithm without a final result,
     * for an algorithm that does not check it.
     *
     * @param string|list<string>|null $final
     * @param list<string> $ignore
     * @return array{gives: string|list<string>|null, ignore: list<string>}
     */
    private static function gives(string|array|null $final, array $ignore = []): array
    {
        return ['gives' => $final, 'ignore' => $ignore];
    }
}
