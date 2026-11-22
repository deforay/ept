<?php

use Pt_Commons_TranslateUtility as T;

/**
 * Why DTS evaluation flagged a response, as a stable code.
 *
 * failure_reason stores the code and its values, so the message is built in the
 * reader's language when it is shown, and its wording can change without a
 * re-evaluation. A case value is stored, so it never changes. Add a new case for a
 * new reason. Pt_Evaluation_FailureReason builds and shows the items.
 *
 * The messages name tests and the final result the algorithm gives, not the
 * results the laboratory reported: the response shows those right above.
 */
enum Pt_Evaluation_Dts_Reason: string
{
    /** The results do not follow an algorithm that is not written as rules yet. */
    case AlgorithmNotFollowed = 'dts.algorithm_not_followed';

    /** A test that the algorithm needs has no result. */
    case TestNotReported = 'dts.test_not_reported';

    /** A test result that the algorithm does not accept at that point. */
    case ResultNotAccepted = 'dts.result_not_accepted';

    /** The algorithm reached a final result, but later tests have results. */
    case TestingShouldHaveStopped = 'dts.testing_should_have_stopped';

    /** The test results follow the algorithm, but the reported final result is different. */
    case FinalResultMismatch = 'dts.final_result_mismatch';

    /** The message in English, with a %name% placeholder for each value. */
    public function template(): string
    {
        return match ($this) {
            self::AlgorithmNotFollowed => T::markForTranslation('For %sample%, the reported results do not follow the National HIV Testing algorithm.'),
            self::TestNotReported => T::markForTranslation('For %sample%, the algorithm needs %test%, but it was not reported.'),
            self::ResultNotAccepted => T::markForTranslation('For %sample%, the %test% result does not fit the algorithm.'),
            self::TestingShouldHaveStopped => T::markForTranslation('For %sample%, testing stops after %last%, which gives %expected%. %extra% should not have been done.'),
            self::FinalResultMismatch => T::markForTranslation('For %sample%, the test results give %expected%, which differs from the reported final result.'),
        };
    }

    /**
     * The placeholders that hold a label to translate, such as a test name. The
     * others, such as a sample label, are shown as entered.
     *
     * @return list<string>
     */
    public function translatedParams(): array
    {
        return ['test', 'last', 'extra', 'expected'];
    }

    /** The English name of a test that an algorithm reads. */
    public static function testName(string $test): string
    {
        return match ($test) {
            'test1' => T::markForTranslation('Test 1'),
            'test2' => T::markForTranslation('Test 2'),
            'test3' => T::markForTranslation('Test 3'),
            'repeat1' => T::markForTranslation('Repeat Test 1'),
            'repeat2' => T::markForTranslation('Repeat Test 2'),
            default => $test,
        };
    }

    /**
     * The English name of a final result that an algorithm gives. A list names a
     * choice of finals.
     *
     * @param string|list<string> $final
     */
    public static function finalName(string|array $final): string
    {
        return match (is_array($final) ? implode('|', $final) : $final) {
            'P|IND' => T::markForTranslation('Positive or Indeterminate'),
            'P' => T::markForTranslation('Positive'),
            'N' => T::markForTranslation('Negative'),
            'IND' => T::markForTranslation('Indeterminate'),
            default => is_array($final) ? implode(', ', $final) : $final,
        };
    }
}
