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
    case RunEveryNeededTest = 23;
    case StopWhereAlgorithmEnds = 24;
    case ReportAlgorithmFinalResult = 25;

    /** The advice, in English. Store this. Translate it with actionText() when shown. */
    public function action(): string
    {
        return $this->texts()[0];
    }

    /** The finding that the advice answers, in English. */
    public function problem(): string
    {
        return $this->texts()[1];
    }

    /**
     * The advice and the finding it answers, in English, kept side by side.
     *
     * @return array{0: string, 1: string}
     */
    private function texts(): array
    {
        return match ($this) {
            self::SubmitBeforeDeadline => [
                T::markForTranslation('Please submit your response before the last date.'),
                T::markForTranslation('The laboratory submitted the response after the response deadline.'),
            ],
            self::FollowNationalAlgorithm => [
                T::markForTranslation('Review and refer to the SOP for testing. Ensure each sample is tested as per the National HIV Testing algorithm.'),
                T::markForTranslation('The laboratory did not follow the National HIV Testing algorithm for a sample.'),
            ],
            self::ReviewProceduresResultMismatch => [
                T::markForTranslation('Review all testing procedures before client testing, as the reported result does not match the expected result.'),
                T::markForTranslation('The reported result for a sample does not match the expected result.'),
            ],
            self::TestAllSamples => [
                T::markForTranslation('You are required to test all samples in the PT panel.'),
                T::markForTranslation('The laboratory did not report a result for every sample.'),
            ],
            self::DoNotUseExpiredKits => [
                T::markForTranslation('Ensure expired test kits are not used for testing. If no valid test kits are available, please contact your supervisor.'),
                T::markForTranslation('The laboratory tested with an expired test kit.'),
            ],
            self::ReportKitExpiryDate => [
                T::markForTranslation('Ensure the expiry date is reported for all performed tests.'),
                T::markForTranslation('The laboratory did not report a test kit expiry date.'),
            ],
            self::ReportKitName => [
                T::markForTranslation('Ensure test kit name is reported for all performed tests.'),
                T::markForTranslation('The laboratory did not report a test kit name.'),
            ],
            self::UseApprovedKitsNotRepeatedForAll => [
                T::markForTranslation('Please use a different approved test kit for each test, as per the SOP and the National HIV Testing algorithm.'),
                T::markForTranslation('The laboratory reported the same test kit for all three tests.'),
            ],
            self::UseApprovedKitsNotRepeated => [
                T::markForTranslation('Please use a different approved test kit for each test, as per the SOP and the National HIV Testing algorithm.'),
                T::markForTranslation('The laboratory reported the same test kit for two tests.'),
            ],
            self::ReportKitLotNumber => [
                T::markForTranslation('Ensure the lot number is reported for all performed tests.'),
                T::markForTranslation('The laboratory did not report a test kit lot number.'),
            ],
            self::ReportSupervisorApproval => [
                T::markForTranslation('Ensure the supervisor\'s approval and name are provided.'),
                T::markForTranslation('The laboratory did not report supervisor approval.'),
            ],
            self::ReportRehydrationDate => [
                T::markForTranslation('Ensure the panel rehydration date is provided.'),
                T::markForTranslation('The laboratory did not report the rehydration date.'),
            ],
            self::ReportTestingDate => [
                T::markForTranslation('Ensure the panel testing date is provided.'),
                T::markForTranslation('The laboratory did not report the testing date.'),
            ],
            self::TestWithinRehydrationWindow => [
                T::markForTranslation('Ensure DTS testing is done within the specified hours of rehydration, as per the SOP.'),
                T::markForTranslation('The laboratory did not test within the time after rehydration that the SOP sets.'),
            ],
            self::ReviewProceduresWithSupervisor => [
                T::markForTranslation('Review all testing procedures before client testing, and contact your supervisor for improvement.'),
                T::markForTranslation('The participant score is below the pass mark.'),
            ],
            self::ReportReceiptDate => [
                T::markForTranslation('Ensure the panel receipt date is provided.'),
                T::markForTranslation('The laboratory did not report the panel receipt date.'),
            ],
            self::TestWithApprovedKits => [
                T::markForTranslation('Please use country-approved test kits as per the National HIV Testing algorithm. Review and refer to the SOP.'),
                T::markForTranslation('The laboratory tested with a test kit that is not country-approved.'),
            ],
            self::ReportSampleCondition => [
                T::markForTranslation('Please ensure the condition of the PT samples is reported.'),
                T::markForTranslation('The laboratory did not report the condition of the PT samples.'),
            ],
            self::ReportRefrigerator => [
                T::markForTranslation('Please ensure refrigerator availability is reported.'),
                T::markForTranslation('The laboratory did not report refrigerator availability.'),
            ],
            self::ReportRoomTemperature => [
                T::markForTranslation('Please ensure the room temperature is reported.'),
                T::markForTranslation('The laboratory did not report the room temperature.'),
            ],
            self::ReportStopWatch => [
                T::markForTranslation('Please ensure stopwatch availability is reported.'),
                T::markForTranslation('The laboratory did not report stopwatch availability.'),
            ],
            self::ReportActualRehydrationDate => [
                T::markForTranslation('Record the date the panel was actually rehydrated. A panel can only be rehydrated after it has been received.'),
                T::markForTranslation('The reported rehydration date is earlier than the panel receipt date.'),
            ],
            self::RunEveryNeededTest => [
                T::markForTranslation('Review and refer to the SOP. Ensure every test required by the National HIV Testing algorithm is performed.'),
                T::markForTranslation('The laboratory did not report a test that the algorithm needs.'),
            ],
            self::StopWhereAlgorithmEnds => [
                T::markForTranslation('Review and refer to the SOP. Ensure testing stops when the National HIV Testing algorithm gives a final result.'),
                T::markForTranslation('The laboratory ran tests after the algorithm gave a final result.'),
            ],
            self::ReportAlgorithmFinalResult => [
                T::markForTranslation('Review and refer to the SOP. Ensure the final result reported is the one the National HIV Testing algorithm gives.'),
                T::markForTranslation('The reported final result is not the one the test results give.'),
            ],
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
