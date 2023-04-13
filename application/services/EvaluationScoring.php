<?php

class Application_Service_EvaluationScoring {
    const SAMPLE_MAX_SCORE = 20;

    const CONCERN_CT_MAX_VALUE = 42.00;
    const PASS_SCORE_PERCENT = 100.00;
    const CONCERN_SCORE_PERCENT = 100.00;
    const PARTIAL_SCORE_PERCENT = 50.00;
    const NO_RESULT_SCORE_PERCENT = 25.00;
    const FAIL_SCORE_PERCENT = 0.00;

    public function calculateTbSamplePassStatus($refMtbDetected, $resMtbDetected, $refRifResistance, $resRifResistance,
                                                $probe1, $probe2, $probe3, $probe4, $probe5, $probe6, $isExcluded,
                                                $isExempt) {
        $calculatedScore = "fail";
        if (!isset($resMtbDetected) || $resMtbDetected == "") {
            $calculatedScore = "fail";
        } else if ($isExcluded == 'yes') {
            $calculatedScore = "excluded";
            if ($isExempt == 'yes') {
                $calculatedScore = "exempt";
            }
        } else if ($resMtbDetected == "noResult" || $resMtbDetected == "error" || $resMtbDetected == "invalid") {
            $calculatedScore = "noresult";
        } else if ($this->resMtbDetectedEqualsRefMtbDetected($resMtbDetected, $refMtbDetected)) {
            if ($this->resRifResistanceEqualsRefRifResistance($resMtbDetected, $resRifResistance, $refRifResistance)) {
                $calculatedScore = "pass";
                $ctValues = array(
                    floatval($probe1),
                    floatval($probe2),
                    floatval($probe3),
                    floatval($probe4),
                    floatval($probe5),
                    floatval($probe6)
                );
                if (max($ctValues) > self::CONCERN_CT_MAX_VALUE) {
                    $calculatedScore = "concern";
                }
            } else if ($resRifResistance == "indeterminate") {
                $calculatedScore = "partial";
            }
        }
        return $calculatedScore;
    }

    public function resMtbDetectedEqualsRefMtbDetected ($refMtbDetected, $resMtbDetected) {
        $mtbDetectedValues = array("detected", "high", "medium", "low", "veryLow", "trace");
        if (in_array($refMtbDetected, $mtbDetectedValues) && in_array($resMtbDetected, $mtbDetectedValues)) {
            return true;
        }
        return $refMtbDetected == $resMtbDetected;
    }

    public function resRifResistanceEqualsRefRifResistance ($resMtbDetected, $refRifResistance, $resRifResistance) {
        $rifResistanceNotApplicableValues = array("notDetected", "na", "");
        if ($resMtbDetected == "notDetected") {
            if (in_array($refRifResistance, $rifResistanceNotApplicableValues) &&
                in_array($resRifResistance, $rifResistanceNotApplicableValues)) {
                return true;
            }
        }
        if (in_array($refRifResistance, $rifResistanceNotApplicableValues) &&
            $resRifResistance == null) {
            return true;
        }
        return $refRifResistance == $resRifResistance;
    }

    public function calculateTbSampleScore($passStatus, $sampleScore) {
        switch ($passStatus) {
            case "pass":
                return self::PASS_SCORE_PERCENT * ($sampleScore / 100.00);
            case "concern":
                return self::CONCERN_SCORE_PERCENT * ($sampleScore / 100.00);
            case "partial":
                return self::PARTIAL_SCORE_PERCENT * ($sampleScore / 100.00);
            case "noresult":
                return self::NO_RESULT_SCORE_PERCENT * ($sampleScore / 100.00);
            case "fail":
                return self::FAIL_SCORE_PERCENT * ($sampleScore / 100.00);
            case "excluded":
                return self::FAIL_SCORE_PERCENT * ($sampleScore / 100.00);
            case "exempt":
                return self::PASS_SCORE_PERCENT * ($sampleScore / 100.00);
            default:
                return self::FAIL_SCORE_PERCENT * ($sampleScore / 100.00);
        }
    }

    const FRIED_SAMPLE_HOURS = 336; // 14 Days
    const EXPIRY_FROM_DATE_OF_SHIPMENT_HOURS = 720; // 30 Days
    const MAX_DOCUMENTATION_SCORE = 0;
    const DEDUCTION_POINTS = 2;

    public function calculateTbDocumentationScore($shipmentDate, $expiryDate, $receiptDate,
                                                  $supervisorApproval, $supervisorName,
                                                  $responseDeadlineDate) {
        return self::MAX_DOCUMENTATION_SCORE;
    }

    private function isNullOrEmpty ($stringValue) {
        return !isset($stringValue) || $stringValue == '';
    }

    private function dateDiffInHours ($laterDate, $earlierDate) {
        $datLaterDate = is_string($laterDate) ? new DateTime($laterDate) : $laterDate;
        $datEarlierDate = is_string($earlierDate) ? new DateTime($earlierDate) : $earlierDate;
        $dateDiff = $datLaterDate->diff($datEarlierDate);
        $hoursBetweenDates = $dateDiff->h;
        return $hoursBetweenDates + ($dateDiff->days * 24);
    }

    const FAIL_IF_POINTS_DEDUCTED = 21;

    public function calculateSubmissionPassStatus($shipmentScore, $documentationScore, $maxShipmentScore, $samplePassStatuses) {
        if ((self::MAX_DOCUMENTATION_SCORE) + $maxShipmentScore - $shipmentScore - $documentationScore > self::FAIL_IF_POINTS_DEDUCTED) {
            return 'fail';
        }
        return 'pass';
    }
}
