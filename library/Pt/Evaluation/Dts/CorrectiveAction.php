<?php

use Pt_Commons_TranslateUtility as T;

/**
 * The corrective actions that DTS evaluation gives a participant.
 *
 * Each case value is the ID that dts_shipment_corrective_action_map stores for a
 * response, so a value never changes or gets reused. Add a new case with the next
 * free number. The wording belongs to the evaluation logic, so it lives here and not
 * in a table that admins edit.
 *
 * action() is the advice. problem() is the finding the advice answers. Both return
 * English for storage. Their translated forms are actionText() and problemText().
 */
enum Pt_Evaluation_Dts_CorrectiveAction: int
{
    case SubmitBeforeDeadline = 1;
    case FollowNationalAlgorithm = 2;
    case ReviewProceduresResultMismatch = 3;
    case TestAllSamples = 4;
    case DoNotUseExpiredKits = 5;
    case ReportKitExpiryDate = 6;
    case ReportKitName = 7;
    case UseApprovedKitsNotRepeatedForAll = 8;
    case UseApprovedKitsNotRepeated = 9;
    case ReportKitLotNumber = 10;
    case ReportSupervisorApproval = 11;
    case ReportRehydrationDate = 12;
    case ReportTestingDate = 13;
    case TestWithinRehydrationWindow = 14;
    case ReviewProceduresWithSupervisor = 15;
    case ReportReceiptDate = 16;
    case TestWithApprovedKits = 17;
    case ReportSampleCondition = 18;
    case ReportRefrigerator = 19;
    case ReportRoomTemperature = 20;
    case ReportStopWatch = 21;
    case ReportActualRehydrationDate = 22;

    /** The advice, in English. Store this. Translate it with actionText() when shown. */
    public function action(): string
    {
        return match ($this) {
            self::SubmitBeforeDeadline => T::markForTranslation('Submit your response before the response deadline.'),
            self::FollowNationalAlgorithm => T::markForTranslation('Review the SOP and test every sample as the National HIV Testing algorithm requires.'),
            self::ReviewProceduresResultMismatch => T::markForTranslation('Review all testing procedures before you test client samples again. Your result does not match the expected result.'),
            self::TestAllSamples => T::markForTranslation('Test every sample in the PT panel.'),
            self::DoNotUseExpiredKits => T::markForTranslation('Do not test with expired test kits. If no valid test kits are available, contact your supervisor.'),
            self::ReportKitExpiryDate => T::markForTranslation('Report the expiry date of every test kit you use.'),
            self::ReportKitName => T::markForTranslation('Report the name of every test kit you use.'),
            self::UseApprovedKitsNotRepeatedForAll,
            self::UseApprovedKitsNotRepeated => T::markForTranslation('Use a different approved test kit for each test, as the SOP and the National HIV Testing algorithm require.'),
            self::ReportKitLotNumber => T::markForTranslation('Report the lot number of every test kit you use.'),
            self::ReportSupervisorApproval => T::markForTranslation('Get supervisor approval and report the supervisor\'s name.'),
            self::ReportRehydrationDate => T::markForTranslation('Report the date you rehydrated the PT panel.'),
            self::ReportTestingDate => T::markForTranslation('Report the date you tested the PT panel.'),
            self::TestWithinRehydrationWindow => T::markForTranslation('Test the PT panel within the time after rehydration that the SOP sets.'),
            self::ReviewProceduresWithSupervisor => T::markForTranslation('Review all testing procedures before you test client samples again. Contact your supervisor to plan improvements.'),
            self::ReportReceiptDate => T::markForTranslation('Report the date you received the PT panel.'),
            self::TestWithApprovedKits => T::markForTranslation('Test with the country-approved test kits, as the National HIV Testing algorithm and the SOP require.'),
            self::ReportSampleCondition => T::markForTranslation('Report the condition of the PT samples.'),
            self::ReportRefrigerator => T::markForTranslation('Report whether a refrigerator is available.'),
            self::ReportRoomTemperature => T::markForTranslation('Report the room temperature.'),
            self::ReportStopWatch => T::markForTranslation('Report whether a stopwatch is available.'),
            self::ReportActualRehydrationDate => T::markForTranslation('Report the actual rehydration date. It cannot be earlier than the date you received the panel.'),
        };
    }

    /** The finding that the advice answers, in English. */
    public function problem(): string
    {
        return match ($this) {
            self::SubmitBeforeDeadline => T::markForTranslation('The laboratory submitted the response after the response deadline.'),
            self::FollowNationalAlgorithm => T::markForTranslation('The laboratory did not follow the National HIV Testing algorithm for a sample.'),
            self::ReviewProceduresResultMismatch => T::markForTranslation('The reported result for a sample does not match the expected result.'),
            self::TestAllSamples => T::markForTranslation('The laboratory did not report a result for every sample.'),
            self::DoNotUseExpiredKits => T::markForTranslation('The laboratory tested with an expired test kit.'),
            self::ReportKitExpiryDate => T::markForTranslation('The laboratory did not report a test kit expiry date.'),
            self::ReportKitName => T::markForTranslation('The laboratory did not report a test kit name.'),
            self::UseApprovedKitsNotRepeatedForAll => T::markForTranslation('The laboratory reported the same test kit for all three tests.'),
            self::UseApprovedKitsNotRepeated => T::markForTranslation('The laboratory reported the same test kit for two tests.'),
            self::ReportKitLotNumber => T::markForTranslation('The laboratory did not report a test kit lot number.'),
            self::ReportSupervisorApproval => T::markForTranslation('The laboratory did not report supervisor approval.'),
            self::ReportRehydrationDate => T::markForTranslation('The laboratory did not report the rehydration date.'),
            self::ReportTestingDate => T::markForTranslation('The laboratory did not report the testing date.'),
            self::TestWithinRehydrationWindow => T::markForTranslation('The laboratory did not test within the time after rehydration that the SOP sets.'),
            self::ReviewProceduresWithSupervisor => T::markForTranslation('The participant score is below the pass mark.'),
            self::ReportReceiptDate => T::markForTranslation('The laboratory did not report the panel receipt date.'),
            self::TestWithApprovedKits => T::markForTranslation('The laboratory tested with a test kit that is not country-approved.'),
            self::ReportSampleCondition => T::markForTranslation('The laboratory did not report the condition of the PT samples.'),
            self::ReportRefrigerator => T::markForTranslation('The laboratory did not report refrigerator availability.'),
            self::ReportRoomTemperature => T::markForTranslation('The laboratory did not report the room temperature.'),
            self::ReportStopWatch => T::markForTranslation('The laboratory did not report stopwatch availability.'),
            self::ReportActualRehydrationDate => T::markForTranslation('The reported rehydration date is earlier than the panel receipt date.'),
        };
    }

    public function actionText(): string
    {
        return T::safeTranslate($this->action());
    }

    public function problemText(): string
    {
        return T::safeTranslate($this->problem());
    }

    /**
     * Every action in English, keyed by ID. Evaluation stores this English in
     * failure_reason, and the display translates it.
     *
     * @return array<int, string>
     */
    public static function englishById(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->action();
        }
        return $map;
    }

    /**
     * Adds the translated action and problem to rows that carry an action ID, for
     * queries that read dts_shipment_corrective_action_map. It drops a row whose ID
     * has no case, as the inner join to the old r_dts_corrective_actions table did.
     *
     * @param array<int|string, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function fillRows(array $rows, string $idKey = 'action_id'): array
    {
        $filled = [];
        foreach ($rows as $row) {
            $case = self::tryFrom((int) ($row[$idKey] ?? 0));
            if ($case === null) {
                continue;
            }
            $row['corrective_action'] = $case->actionText();
            $row['description'] = $case->problemText();
            $filled[] = $row;
        }
        return $filled;
    }
}
