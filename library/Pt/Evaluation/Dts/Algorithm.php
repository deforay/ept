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
 */
enum Pt_Evaluation_Dts_Algorithm: string
{
    /** The 3-test algorithm. Test 1 and Test 2 reactive is enough for Positive. */
    case ThreeTests = 'updated-3-tests';

    /** The 3-test algorithm with Test 3 required to confirm a positive (dtsRequireTest3). */
    case ThreeTestsRequireTest3 = 'updated-3-tests:require-test-3';

    /** Sierra Leone's 2-test algorithm, used before the move to 3 tests. */
    case SierraLeone = 'sierraLeone';

    /** The tests this algorithm reads, in order. Any other test is not part of it. */
    public function tests(): array
    {
        return match ($this) {
            self::ThreeTests, self::ThreeTestsRequireTest3 => ['test1', 'test2', 'test3', 'repeat1'],
            self::SierraLeone => ['test1', 'test2', 'test3'],
        };
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
        };
    }

    /**
     * A final result. $ignore lists the tests that the algorithm does not read once it
     * reaches this result, so a result in them is neither needed nor a mistake.
     *
     * @param list<string> $ignore
     * @return array{gives: string, ignore: list<string>}
     */
    private static function gives(string $final, array $ignore = []): array
    {
        return ['gives' => $final, 'ignore' => $ignore];
    }
}
