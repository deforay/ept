<?php

/**
 * Checks one sample's results against a national algorithm and says what is wrong.
 *
 * It follows the results through the algorithm's steps, the way a laboratory works
 * through the algorithm, and stops at the first problem on the path. It reads only
 * its arguments, so it needs no database and gives the same answer every time.
 */
final class Pt_Evaluation_Dts_AlgorithmCheck
{
    /**
     * @param array<string, string> $results result code by test (test1, test2, test3,
     *     repeat1, repeat2). '-' or a missing key means the test was not done.
     * @param string $final the reported final result as P, N or IND, or any other
     *     value for a result the algorithm never gives
     * @return array{
     *     pass: bool,
     *     expected: string|list<string>|null,
     *     problems: list<array{reason: Pt_Evaluation_Dts_Reason, test?: string, last?: string, extra?: list<string>, expected?: string|list<string>}>
     * }
     */
    public static function check(Pt_Evaluation_Dts_Algorithm $algorithm, array $results, string $final): array
    {
        $steps = $algorithm->steps();
        $result = static fn (string $test): string => ($results[$test] ?? '-') === '' ? '-' : ($results[$test] ?? '-');

        $visited = [];
        $step = $steps[array_key_first($steps)];
        while (true) {
            $test = $step['test'];
            $value = $result($test);
            $visited[] = $test;

            if (!array_key_exists($value, $step)) {
                $reason = $value === '-'
                    ? Pt_Evaluation_Dts_Reason::TestNotReported
                    : Pt_Evaluation_Dts_Reason::ResultNotAccepted;
                return ['pass' => false, 'expected' => null, 'problems' => [['reason' => $reason, 'test' => $test]]];
            }

            $next = $step[$value];
            if (is_string($next)) {
                $step = $steps[$next];
                continue;
            }
            break;
        }

        $problems = [];
        $ignored = array_merge($visited, $next['ignore']);
        $notDone = $algorithm->notDone();
        $extra = array_values(array_filter(
            $algorithm->tests(),
            static fn (string $t): bool => !in_array($t, $ignored, true) && !in_array($result($t), $notDone, true)
        ));
        // A step that gives no final result reads the last test, so nothing comes after it.
        if ($extra !== [] && $next['gives'] !== null) {
            $problems[] = [
                'reason' => Pt_Evaluation_Dts_Reason::TestingShouldHaveStopped,
                'last' => $test,
                'extra' => $extra,
                'expected' => $next['gives'],
            ];
        }
        if ($algorithm->checksFinal() && $next['gives'] !== null && !in_array($final, (array) $next['gives'], true)) {
            $problems[] = [
                'reason' => Pt_Evaluation_Dts_Reason::FinalResultMismatch,
                'expected' => $next['gives'],
            ];
        }
        return ['pass' => $problems === [], 'expected' => $next['gives'], 'problems' => $problems];
    }

    /**
     * Checks the results like check(), and turns each problem into a failure_reason
     * item with the corrective action that answers it.
     *
     * @param array<string, string> $results result code by test, as for check()
     * @return array{pass: bool, items: list<array<string, mixed>>, actions: list<int>}
     */
    public static function feedback(
        Pt_Evaluation_Dts_Algorithm $algorithm,
        array $results,
        string $final,
        string $sampleLabel
    ): array {
        $check = self::check($algorithm, $results, $final);
        $items = $actions = [];
        foreach ($check['problems'] as $problem) {
            $params = ['sample' => $sampleLabel];
            foreach (['test', 'last'] as $key) {
                if (isset($problem[$key])) {
                    $params[$key] = Pt_Evaluation_Dts_Reason::testName($problem[$key]);
                }
            }
            if (isset($problem['extra'])) {
                $params['extra'] = array_map(Pt_Evaluation_Dts_Reason::testName(...), $problem['extra']);
            }
            if (isset($problem['expected'])) {
                $params['expected'] = Pt_Evaluation_Dts_Reason::finalName($problem['expected']);
            }
            $action = match ($problem['reason']) {
                Pt_Evaluation_Dts_Reason::TestNotReported => Pt_Evaluation_Dts_CorrectiveAction::RunEveryNeededTest,
                Pt_Evaluation_Dts_Reason::TestingShouldHaveStopped => Pt_Evaluation_Dts_CorrectiveAction::StopWhereAlgorithmEnds,
                Pt_Evaluation_Dts_Reason::FinalResultMismatch => Pt_Evaluation_Dts_CorrectiveAction::ReportAlgorithmFinalResult,
                default => Pt_Evaluation_Dts_CorrectiveAction::FollowNationalAlgorithm,
            };
            $items[] = Pt_Evaluation_FailureReason::dts($problem['reason'], $params, $action);
            $actions[] = $action->value;
        }
        return ['pass' => $check['pass'], 'items' => $items, 'actions' => $actions];
    }
}
