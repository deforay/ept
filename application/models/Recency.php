<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Application_Model_Recency
{

    private $db = null;
    public $failureReason = [];
    public function __construct($db = null)
    {
        $this->db = $db;
    }

    public function evaluate($shipmentResult, $shipmentId)
    {

        $counter = 0;
        $maxScore = 0;
        $scoreHolder = [];
        $finalResult = null;
        $schemeService = new Application_Service_Schemes();

        $possibleResultsArray = $schemeService->getPossibleResults('recency');
        $possibleResults = [];
        foreach ($possibleResultsArray as $possibleResults) {
            $possibleResults['result_code'] = $possibleResults['id'];
        }


        $recencyDocumentationScore = Pt_Commons_SchemeConfig::get('recency.documentationScore') ?? 10;
        $recencyPassPercentage = Pt_Commons_SchemeConfig::get('recency.passPercentage') ?? 100;


        foreach ($shipmentResult as $shipment) {
            Pt_Commons_MiscUtility::updateHeartbeat('shipment', 'shipment_id', $shipmentId);
            $shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date'] ?? '');
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = new DateTime('1970-01-01');
            }

            $lastDate = Pt_Commons_DateUtility::endOfDay($shipment['lastdate_response']);

            if ($createdOn <= $lastDate) {

                $attributes = json_decode($shipment['attributes'], true);
                $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);
                $results = $schemeService->getRecencySamples($shipmentId, $shipment['participant_id']);


                $documentationScoreArray = $this->getDocumentationScore($results, $attributes, $shipmentAttributes);
                $documentationScore = $documentationScoreArray['documentationScore'];

                $totalScore = 0;
                $maxScore = 0;
                $mandatoryResult = "";
                $scoreResult = "";
                $this->failureReason = [];

                foreach ($results as $result) {

                    $controlLine = strtolower($result['control_line']);
                    $verificationLine = strtolower($result['diagnosis_line']);
                    $longtermLine = strtolower($result['longterm_line']);

                    // CHECK 1: RTRI Final Interpretation Correctness
                    // matching reported and reference results
                    if (isset($result['reported_result']) && $result['reported_result'] != null) {
                        if ($result['reference_result'] == $result['reported_result']) {
                            $score = "Pass";
                        } else {
                            if ($result['sample_score'] > 0) {
                                $this->failureReason[] = array(
                                    'warning' => "Final interpretation for sample <strong>" . $result['sample_label'] . "</strong> reported wrongly",
                                    'correctiveAction' => "Final interpretation not matching with the expected results. Please review the RTRI SOP and/or job aide to ensure test procedures are followed and  interpretation of results are reported accurately."
                                );
                            }
                            $score = "Fail";
                        }
                    } else {
                        $score = "Fail";
                    }
                    if (0 == $result['control']) {
                        $maxScore += $result['sample_score'];
                    }


                    // CHECK 2: RTRI Algorithm Correctness
                    $isAlgoWrong = false;

                    if (empty($controlLine) && empty($verificationLine) && empty($longtermLine)) {
                        $isAlgoWrong = true;
                    } elseif (empty($controlLine) || $controlLine == 'absent') {
                        $isAlgoWrong = true;
                    }
                    // elseif ($verificationLine == 'absent') {
                    //     $isAlgoWrong = true;
                    // }

                    // if final result was expected as Negative
                    if ($result['reference_result'] == $possibleResults['N']) {
                        if ($controlLine == 'present' && $verificationLine == 'absent' && $longtermLine == 'absent') {
                        } else {
                            $isAlgoWrong = true;
                        }
                    }

                    // if final result was expected as Recent
                    if ($result['reference_result'] == $possibleResults['R']) {
                        if ($controlLine == 'present' && $verificationLine == 'present' && $longtermLine == 'absent') {
                        } else {
                            $isAlgoWrong = true;
                        }
                    }

                    // if final result was expected as Long term
                    if ($result['reference_result'] == $possibleResults['LT']) {
                        if ($controlLine == 'present' && $verificationLine == 'present' && $longtermLine == 'present') {
                        } else {
                            $isAlgoWrong = true;
                        }
                    }
                    if ($isAlgoWrong) {
                        $score = "Fail";
                        $this->failureReason[] = array(
                            'warning' => "Algorithm reported wrongly for sample <strong>" . $result['sample_label'] . "</strong>",
                            'correctiveAction' => "Identification of the presence or absence of RTRI lines/bands do not match the Final Interpretation reported. Please follow the RTRI SOP and/or job aide to report the presence or absence of RTRI lines/bands correctly."
                        );
                    }

                    if (0 == $result['control'] && $score == "Pass") {
                        $totalScore += $result['sample_score'];
                    }


                    if ($score == 'Fail' || (!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null) || ($result['reference_result'] != $result['reported_result'])) {
                        $this->db->update('response_result_recency', array('calculated_score' => "Fail"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                    } else {
                        $this->db->update('response_result_recency', array('calculated_score' => "Pass"), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                    }
                }


                $configuredDocScore = ((isset($recencyDocumentationScore) && $recencyDocumentationScore != "" && $recencyDocumentationScore != null) ? $recencyDocumentationScore : 10);

                // Response Score
                if ($maxScore == 0 || $totalScore == 0) {
                    $responseScore = 0;
                } else {
                    $responseScore = round(($totalScore / $maxScore) * 100 * (100 - $configuredDocScore) / 100, 2);
                }

                $grandTotal = ($responseScore + $documentationScore);
                if ($grandTotal < $recencyPassPercentage) {
                    $scoreResult = 'Fail';
                    $this->failureReason[] = array(
                        'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $recencyPassPercentage . "</strong>)",
                        'correctiveAction' => "Participant did not meet the score criteria (Participant Score is <strong>" . $grandTotal . "</strong> and Required Score is <strong>" . $recencyPassPercentage . "</strong>)",
                    );
                    $correctiveActionList[] = 15;
                } else {
                    $scoreResult = 'Pass';
                }

                if (!empty($documentationScoreArray['failureReasons'])) {
                    $this->failureReason = array_merge($this->failureReason, $documentationScoreArray['failureReasons']);
                }


                // echo "<pre>";
                // var_dump($this->failureReason);
                // echo "</pre>";

                // if we are excluding this result, then let us not give pass/fail
                if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                    $finalResult = '';
                    $totalScore = 0;
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
                    $shipmentResult[$counter]['documentation_score'] = 0;
                    $shipmentResult[$counter]['display_result'] = '';
                    //$shipmentResult[$counter]['is_followup'] = 'yes';
                    $shipmentResult[$counter]['is_excluded'] = 'yes';
                    $this->failureReason[] = array(
                        'warning' => "Excluded from Evaluation"
                    );
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $this->failureReason = json_encode($this->failureReason);
                } else {
                    $shipment['is_excluded'] = 'no';

                    // if any of the results have failed, then the final result is fail
                    if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = round($responseScore, 2);
                    $shipmentResult[$counter]['documentation_score'] = $documentationScore;
                    $scoreHolder[$shipment['map_id']] = ($responseScore + $documentationScore);
                    $shipmentResult[$counter]['max_score'] = 100; //$maxScore;
                    $shipmentResult[$counter]['final_result'] = $finalResult;


                    $fRes = $this->db->fetchCol($this->db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $this->failureReason = json_encode($this->failureReason);
                }
                /* Manual result override changes */
                if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
                    $sql = $this->db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
                    $shipmentOverall = $this->db->fetchRow($sql);
                    if (!empty($shipmentOverall)) {
                        $shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
                        $shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
                        if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
                            $shipmentOverall['final_result'] = 2;
                        }
                        $fRes = $this->db->fetchCol($this->db->select()->from('r_results', array('result_name'))->where('result_id = ' . $shipmentOverall['final_result']));
                        $shipmentResult[$counter]['display_result'] = $fRes[0];
                        $nofOfRowsUpdated = $this->db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'documentation_score' => $shipmentOverall['documentation_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
                    }
                } else {
                    // let us update the total score in DB
                    $this->db->update('shipment_participant_map', array('shipment_score' => $responseScore, 'documentation_score' => $documentationScore, 'final_result' => $finalResult, 'failure_reason' => $this->failureReason), "map_id = " . $shipment['map_id']);
                }
                //$counter++;
            } else {
                $failureReason = array(
                    'warning' => "Response was submitted after the last response date."
                );
                $this->db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }
            $counter++;
        }

        if (count($scoreHolder) > 0) {
            $averageScore = round(array_sum($scoreHolder) / count($scoreHolder), 2);
        } else {
            $averageScore = 0;
        }


        $this->db->update('shipment', array('max_score' => $maxScore, 'average_score' => $averageScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);

        return $shipmentResult;
    }


    // public function getSampleScore()
    // {
    //     $sampleScore = 0;
    //     return $sampleScore;
    // }

    public function getDocumentationScore($results, $attributes, $shipmentAttributes)
    {

        $failureReasonsArray = [];

        $recencyDocumentationScore = Pt_Commons_SchemeConfig::get('recency.documentationScore') ?? 10;

        //Let us now calculate documentation score
        $documentationScore = 0;
        $documentationPercentage = !empty($recencyDocumentationScore) ? $recencyDocumentationScore : 10;


        if (empty($shipmentAttributes['sampleType'])) {
            $shipmentAttributes['sampleType'] = 'dried'; // in case sampleType is not set, we will treat it as dried
        }


        if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
            // for Dried Samples, we will have rehydration as one of the documentation scores
            $documentationScorePerItem = ($documentationPercentage / 5);
        } else {
            // for Non Dried Samples, we will NOT have rehydration documentation scores
            // there are 2 conditions for rehydration so 5 - 2 = 3
            $documentationScorePerItem = ($documentationPercentage / 3);
        }

        // D.1
        if (isset($results[0]['shipment_receipt_date']) && strtolower($results[0]['shipment_receipt_date']) != '') {
            $documentationScore += $documentationScorePerItem;
        } else {
            $failureReasonsArray[] = array(
                'warning' => "Panel Receipt date not provided",
                'correctiveAction' => "Review and refer to SOP for testing. Panel Receipt Date needs to be provided."
            );
        }

        //D.3
        if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {
            // Only for Dried Samples we will check Sample Rehydration
            if (isset($attributes['sample_rehydration_date']) && trim($attributes['sample_rehydration_date']) != "") {
                $documentationScore += $documentationScorePerItem;
            } else {
                $failureReasonsArray[] = array(
                    'warning' => "Specimen Rehydration date not provided",
                    'correctiveAction' => "Review and refer to National SOP for testing.  Specimen Rehydration date needs to be provided."
                );
            }
        }

        //D.5
        if (isset($results[0]['shipment_test_date']) && trim($results[0]['shipment_test_date']) != "") {
            $documentationScore += $documentationScorePerItem;
        } else {
            $failureReasonsArray[] = array(
                'warning' => "Panel test date not provided",
                'correctiveAction' => "Review and refer to National SOP for testing. Panel test date needs to be provided."
            );
        }

        //D.7
        if (isset($shipmentAttributes['sampleType']) && $shipmentAttributes['sampleType'] == 'dried') {

            // Only for Dried samples we will do this check

            // Testing should be done within 24*($sampleRehydrateDays) hours of rehydration.
            $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
            $testedOnDate = new DateTime($results[0]['shipment_test_date']);
            $interval = $sampleRehydrationDate->diff($testedOnDate);

            $recencySampleRehydrateDays = Pt_Commons_SchemeConfig::get('recency.sampleRehydrateDays') ?? 1;

            // we can allow testers to test upto sampleRehydrateDays or sampleRehydrateDays + 1
            if (empty($attributes['sample_rehydration_date']) || $interval->days < $recencySampleRehydrateDays || $interval->days > ($recencySampleRehydrateDays + 1)) {
                $failureReason[] = array(
                    'warning' => "Testing not done within specified time of rehydration as per SOP.",
                    'correctiveAction' => "Review and refer to National SOP for testing. Testing should be done within specified time of rehydration."
                );
            } else {
                $documentationScore += $documentationScorePerItem;
            }
        }

        //D.8
        if (isset($results[0]['supervisor_approval']) && strtolower($results[0]['supervisor_approval']) == 'yes' && trim($results[0]['participant_supervisor']) != "") {
            $documentationScore += $documentationScorePerItem;
        } else {
            $failureReasonsArray[] = array(
                'warning' => "Supervisor approval not recorded",
                'correctiveAction' => "Review and refer to National SOP for testing. Supervisor approval is mandatory",
            );
        }
        return array('documentationScore' => $documentationScore, 'failureReasons' => $failureReasonsArray);
    }

    public function generateRecencyExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $excel = new Spreadsheet();

        $borderStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'style' => Border::BORDER_THIN,
                ],
            ]
        ];
        $patientResponseColor = [
            'fill' => [
                'type' => Fill::FILL_SOLID,
                'color' => ['rgb' => '18bc9c']
            ]
        ];
        $referenceColor = [
            'fill' => [
                'type' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'F0E68C']
            ]
        ];

        $query = $db->select()->from('shipment')
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);


        $refQuery = $db->select()->from(array('refRes' => 'reference_result_recency'))
            ->joinLeft(array('pr' => 'r_possibleresult'), "refRes.reference_result=pr.id")
            ->where("refRes.shipment_id = ?", $shipmentId);
        $refResult = $db->fetchAll($refQuery);


        $firstSheet = new Worksheet($excel, 'Recency PT Results');
        $excel->addSheet($firstSheet, 0);

        $firstSheet->mergeCells('A1:A2');
        $firstSheet->getCell('A1')->setValue(html_entity_decode("Lab ID", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('A1:A2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('B1:B2');
        $firstSheet->getCell('B1')->setValue(html_entity_decode("Lab Name", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('B1:B2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('C1:C2');
        $firstSheet->getCell('C1')->setValue(html_entity_decode("Institute", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('C1:C2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('D1:D2');
        $firstSheet->getCell('D1')->setValue(html_entity_decode("Department", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('D1:D2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('E1:E2');
        $firstSheet->getCell('E1')->setValue(html_entity_decode("Region", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('E1:E2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('F1:F2');
        $firstSheet->getCell('F1')->setValue(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('F1:F2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('G1:G2');
        $firstSheet->getCell('G1')->setValue(html_entity_decode("Sample Rehydration Date", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('G1:G2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('H1:H2');
        $firstSheet->getCell('H1')->setValue(html_entity_decode("Recency Assay", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('H1:H2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('I1:I2');
        $firstSheet->getCell('I1')->setValue(html_entity_decode("Recency Assay Lot No", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('I1:I2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('J1:J2');
        $firstSheet->getCell('J1')->setValue(html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('J1:J2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('K1:K2');
        $firstSheet->getCell('K1')->setValue(html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('K1:K2')->applyFromArray($borderStyle, true);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 11;
        $cellName1 = $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount + 1) . '1')
            ->getColumn();

        foreach ($refResult as $refRow) {
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount + 1) . 2)
                ->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount + 1) . 2)->applyFromArray($borderStyle, true);
            $colNameCount++;
        }

        $cellName2 = $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount - 2) . '1')
            ->getColumn();
        $firstSheet->mergeCells($cellName1 . '1:' . $cellName2 . '1');
        $firstSheet->getCell($cellName1 . '1')->setValue(html_entity_decode("PARTICIPANT RESPONSE", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '1')->applyFromArray($borderStyle, true);
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '2')->applyFromArray($patientResponseColor, true);

        $cellName3 = $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount + 1) . '1')
            ->getColumn();
        $colNumberforReference = $colNameCount;
        foreach ($refResult as $refRow) {
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount + 1) . 2)
                ->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount + 1) . 2)->applyFromArray($borderStyle, true);
            $colNameCount++;
        }
        $cellName4 = $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount - 2) . '1')
            ->getColumn();
        $firstSheet->mergeCells($cellName3 . '1:' . $cellName4 . '1');
        $firstSheet->getCell($cellName3 . '1')->setValue(html_entity_decode("REFERENCE RESULTS", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '1')->applyFromArray($borderStyle, true);
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '2')->applyFromArray($referenceColor, true);

        $firstSheet->setTitle('Recency PT Results', true);

        $queryOverAll = $db->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), "spm.shipment_id = s.shipment_id")
            ->joinLeft(array('p' => 'participant'), "p.participant_id = spm.participant_id")
            ->joinLeft(array('st' => 'r_site_type'), "st.r_stid=p.site_type")
            ->where("s.shipment_id = ?", $shipmentId);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $queryOverAll = $queryOverAll
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $resultOverAll = $db->fetchAll($queryOverAll);

        $row = 2; // $row 0 is already the column headings

        $schemeService = new Application_Service_Schemes();
        $assayList = $schemeService->getRecencyAssay();

        foreach ($resultOverAll as $rowOverAll) {
            //Zend_Debug::dump($rowOverAll);
            $row++;

            $queryResponse = $db->select()->from(array('res' => 'response_result_recency'))
                ->joinLeft(array('pr' => 'r_possibleresult'), "res.reported_result=pr.id")
                ->where("res.shipment_map_id = ?", $rowOverAll['map_id']);
            $resultResponse = $db->fetchAll($queryResponse);

            $rqResponse = $db->select()->from(array('ref' => 'reference_result_recency'))
                ->joinLeft(array('pr' => 'r_possibleresult'), "ref.reference_result=pr.id")
                ->where("ref.shipment_id = ?", $shipmentId);
            $refResponse = $db->fetchAll($rqResponse);

            $attributes = json_decode($rowOverAll['attributes'], true);
            $extraction = (array_key_exists($attributes['recency_assay'], $assayList)) ? $assayList[$attributes['recency_assay']] : "";
            $assayLot = $attributes['recency_assay_lot_no'];
            $sampleRehydrationDate = (isset($attributes['sample_rehydration_date'])) ? Pt_Commons_DateUtility::humanReadableDateFormat($attributes['sample_rehydration_date']) : "";

            $firstSheet->getCell(Coordinate::stringFromColumnIndex(1) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(2) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['first_name'] . " " . $rowOverAll['last_name'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(3) . $row)
                ->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . $row)
                ->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(5) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(6) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(7) . $row)
                ->setValueExplicit(html_entity_decode($sampleRehydrationDate, ENT_QUOTES, 'UTF-8'));

            $col = 7;

            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($extraction, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($assayLot, ENT_QUOTES, 'UTF-8'));

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00" && $rowOverAll['shipment_receipt_date'] != "1970-01-01") ? Pt_Commons_DateUtility::humanReadableDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00" && $rowOverAll['shipment_test_date'] != "1970-01-01") ? Pt_Commons_DateUtility::humanReadableDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'));

            foreach ($resultResponse as $responseRow) {
                $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                    ->setValueExplicit(html_entity_decode($responseRow['response'], ENT_QUOTES, 'UTF-8'));
            }
            foreach ($refResponse as $responseRow) {
                $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                    ->setValueExplicit(html_entity_decode($responseRow['response'], ENT_QUOTES, 'UTF-8'));
            }
        }

        //<------------ Participant List Details Start -----

        $headings = array('Participant Code', 'Participant Name', 'Institute Name', 'Department', 'Country', 'Address', 'Province', 'District', 'City', 'Facility Telephone', 'Email');

        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Participant List', true);

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('sp.map_id'));
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $shipmentResult = $db->fetchAll($sql);
        $colNo = 0;
        $currentRow = 1;
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            // $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo) . $currentRow)->getFont()->setBold(true);
            $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
            $colNo++;
        }

        if (isset($shipmentResult) && count($shipmentResult) > 0) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                if ($result['scheme_type'] == 'recency') {
                    $resQuery = $db->select()->from(array('rrr' => 'response_result_recency'))
                        ->joinLeft(array('r' => 'r_possibleresult'), 'r.id=rrr.reported_result', array('finalResult' => 'r.response'))
                        ->where("rrr.shipment_map_id = ?", $aRow['map_id']);
                    $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                }


                $sheet->getCell(Coordinate::stringFromColumnIndex(1) . $currentRow)
                    ->setValueExplicit(ucwords($aRow['unique_identifier']));
                $sheet->getCell(Coordinate::stringFromColumnIndex(2) . $currentRow)
                    ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(3) . $currentRow)
                    ->setValueExplicit($aRow['institute_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(4) . $currentRow)
                    ->setValueExplicit($aRow['department_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(5) . $currentRow)
                    ->setValueExplicit($aRow['iso_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(6) . $currentRow)
                    ->setValueExplicit($aRow['address']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(7) . $currentRow)
                    ->setValueExplicit($aRow['province']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(8) . $currentRow)
                    ->setValueExplicit($aRow['district']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(9) . $currentRow)
                    ->setValueExplicit($aRow['city']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(10) . $currentRow)
                    ->setValueExplicit($aRow['mobile']);
                $sheet->getCell(Coordinate::stringFromColumnIndex(11) . $currentRow)
                    ->setValueExplicit(strtolower($aRow['email']));

                for ($i = 0; $i <= 11; $i++) {
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $currentRow)
                        ->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>
        //<-------- Second sheet start
        $reportHeadings = array('Participant Code', 'Participant Name', 'Point of Contact', 'Region', 'Shipment Receipt Date', 'Sample Rehydration Date', 'Testing Date');

        if ($result['scheme_type'] == 'recency') {
            foreach (range(0, $result['number_of_samples']) as $dummy) {
                array_push($reportHeadings, 'Control Line', 'Verification Line', 'Long Term Line');
            }
            array_push($reportHeadings, 'Comments');
        }

        $sheet = new Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 2);
        $sheet->setTitle('Results Reported', true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        $finalResColoumn = $n - (($result['number_of_samples'] + 1) * 3);
        $finalResColoumn--;
        $c = 0;

        // To get the sample list
        $samples = $this->addRecencySampleNameInArray($shipmentId);
        foreach ($reportHeadings as $value) {

            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow, null, null)->getFont()->setBold(true);

            $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);

            $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 3)
                ->getColumn();
            $sheet->getStyle($cellName . "3")->applyFromArray($borderStyle, true);

            if ($colNo >= $finalResColoumn) {
                if ($c <= $result['number_of_samples']) {
                    $col = 7;
                    foreach ($samples as $sample) {
                        $firstCellName = $sheet->getCell(Coordinate::stringFromColumnIndex($col + 1) . 1)
                            ->getColumn();
                        $secondCellName = $sheet->getCell(Coordinate::stringFromColumnIndex(($col + 3)) . 1)
                            ->getColumn();

                        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
                        $sheet->getStyle($firstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                        $sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle, true);
                        $sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle, true);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($col + 1) . 1)
                            ->setValueExplicit(html_entity_decode($sample, ENT_QUOTES, 'UTF-8'));

                        $colorCol = $col;
                        $cellNameBar = $sheet->getCell(Coordinate::stringFromColumnIndex($colorCol + 1) . 1)
                            ->getColumn();
                        $sheet->getStyle($cellNameBar . 2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                        $colorCol = $colorCol + 1;

                        $cellNameBar = $sheet->getCell(Coordinate::stringFromColumnIndex($colorCol + 1) . 1)
                            ->getColumn();
                        $sheet->getStyle($cellNameBar . 2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                        $colorCol = $colorCol + 1;

                        $cellNameBar = $sheet->getCell(Coordinate::stringFromColumnIndex($colorCol + 1) . 1)
                            ->getColumn();
                        $sheet->getStyle($cellNameBar . 2)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

                        $col = $col + 3;
                    }
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                        ->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $c++;
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . 3)
                        ->setValueExplicit(html_entity_decode($refResult[$l]['reference_result'], ENT_QUOTES, 'UTF-8'));
                }
            }
            $sheet->getStyle($cellName . '3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        //$sheet->getStyle("D2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("E2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');
        //$sheet->getStyle("F2")->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#A7A7A7');

        $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($n + 1) . 3)
            ->getColumn();
        //$sheet->getStyle('A3:'.$cellName.'3')->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('#969696');
        //$sheet->getStyle('A3:'.$cellName.'3')->applyFromArray($borderStyle);

        //<-------- Sheet three heading -------
        $sheetThree = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 3);
        $sheetThree->setTitle('Panel Score', true);
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addRecencySampleNameInArray($shipmentId);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $sheetThree->getStyle(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow, null, null)->getFont()->setBold(true);
            $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow)
                ->getColumn();
            $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow)
                    ->getColumn();
                $sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->
        //<-------- Document Score Sheet Heading (Sheet Four)-------

        if ($result['scheme_type'] == 'recency') {
            $recencyDocumentationScore = Pt_Commons_SchemeConfig::get('recency.documentationScore') ?? 10;
            $documentationScorePerItem = ($recencyDocumentationScore > 0) ? ($recencyDocumentationScore / 5) : 0;
        }

        $docScoreSheet = new Worksheet($excel, 'Documentation Score');
        $excel->addSheet($docScoreSheet, 4);
        $docScoreSheet->setTitle('Documentation Score', true);
        $docScoreSheet->getDefaultColumnDimension()->setWidth(20);
        //$docScoreSheet->getDefaultRowDimension()->setRowHeight(20);
        $docScoreSheet->getDefaultRowDimension()->setRowHeight(25);

        $docScoreHeadings = array('Participant Code', 'Participant Name', 'Supervisor signature', 'Panel Receipt Date', 'Rehydration Date', 'Tested Date', 'Rehydration Test In Specified Time', 'Documentation Score %');

        $docScoreSheetCol = 0;
        $docScoreRow = 1;
        $docScoreHeadingsCount = count($docScoreHeadings);
        foreach ($docScoreHeadings as $sheetThreeHK => $value) {
            $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreSheetCol + 1) . $docScoreRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $docScoreSheet->getStyle(Coordinate::stringFromColumnIndex($docScoreSheetCol + 1) . $docScoreRow, null, null)->getFont()->setBold(false);
            $cellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreSheetCol + 1) . $docScoreRow)
                ->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);
            $docScoreSheet->getStyle(Coordinate::stringFromColumnIndex($docScoreSheetCol + 1) . $docScoreRow, null, null)->getAlignment()->setWrapText(true);
            $docScoreSheetCol++;
        }
        $docScoreRow = 2;
        $secondRowcellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex(2) . $docScoreRow);
        $secondRowcellName->setValueExplicit(html_entity_decode("Points Breakdown", ENT_QUOTES, 'UTF-8'));
        $docScoreSheet->getStyle(Coordinate::stringFromColumnIndex(2) . $docScoreRow, null, null)->getFont()->setBold(true);
        $cellName = $secondRowcellName->getColumn();
        $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);

        for ($r = 2; $r <= 7; $r++) {
            $secondRowcellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($r + 1) . $docScoreRow);
            if ($r != 7) {
                $secondRowcellName->setValueExplicit(html_entity_decode($documentationScorePerItem, ENT_QUOTES, 'UTF-8'));
            }
            $docScoreSheet->getStyle(Coordinate::stringFromColumnIndex($r + 1) . $docScoreRow, null, null)->getFont()->setBold(false);
            $cellName = $secondRowcellName->getColumn();
            $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->
        //<-------- Total Score Sheet Heading (Sheet Four)-------


        $totalScoreSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 5);
        $totalScoreSheet->setTitle('Total Score', true);
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $totalScoreSheet->getStyle(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)->getFont()->setBold(true);
            $cellName = $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)->getColumn();
            $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
            $totalScoreSheet->getStyle(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)->getAlignment()->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->
        $ktr = 9;
        $kitId = 7; //Test Kit coloumn count
        if (isset($refResult) && count($refResult) > 0) {
            foreach ($refResult as $keyv => $row) {
                $keyv = $keyv + 1;
                $ktr = $ktr + $keyv;
                //In Excel Third row added the Test kit name1,kit lot,exp date
                $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++) . 3)->setValueExplicit($row['reference_control_line']);
                $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++) . 3)->setValueExplicit($row['reference_diagnosis_line']);
                $sheet->getCell(Coordinate::stringFromColumnIndex($kitId++) . 3)->setValueExplicit($row['reference_longterm_line']);

                $sheet->getCell(Coordinate::stringFromColumnIndex($ktr + 1) . 3)->setValueExplicit($row['response']);
                $ktr = 5;
            }
        }
        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && !empty($shipmentResult)) {

            foreach ($shipmentResult as $aRow) {
                $r = 0;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 0;
                $docScoreCol = 0;
                $totScoreCol = 0;
                $countCorrectResult = 0;

                $colCellObj = $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow);
                $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']));
                $cellName = $colCellObj->getColumn();
                //$sheet->getStyle($cellName.$currentRow)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                //$sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . ' ' . $aRow['dataManagerLastName']);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['region']);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                if (trim($aRow['attributes']) != "") {
                    $attributes = json_decode($aRow['attributes'], true);
                    $sampleRehydrationDate = new Zend_Date($attributes['sample_rehydration_date']);
                    $rehydrationDate = Pt_Commons_General::excelDateFormat($attributes["sample_rehydration_date"]);
                }

                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['shipment_receipt_date']);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($rehydrationDate);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentTestDate);



                $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++) . $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++) . $sheetThreeRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                //<-------------Document score sheet------------

                $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                if (isset($shipmentReceiptDate) && trim($shipmentReceiptDate) != "") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($documentationScorePerItem);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(0);
                }

                if (isset($aRow['supervisor_approval']) && strtolower($aRow['supervisor_approval']) == 'yes' && isset($aRow['participant_supervisor']) && trim($aRow['participant_supervisor']) != "") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($documentationScorePerItem);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(0);
                }

                if (isset($rehydrationDate) && trim($rehydrationDate) != "") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($documentationScorePerItem);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(0);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($documentationScorePerItem);
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(0);
                }

                if (isset($sampleRehydrationDate) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {


                    $sampleRehydrationDate = new DateTime($attributes['sample_rehydration_date']);
                    $testedOnDate = new DateTime($aRow['shipment_test_date']);
                    $interval = $sampleRehydrationDate->diff($testedOnDate);

                    // Testing should be done within 24*($sampleRehydrateDays) hours of rehydration.
                    //$sampleRehydrateDays = $sampleRehydrateDays;
                    $dtsSampleRehydrateDays = Pt_Commons_SchemeConfig::get('dts.sampleRehydrateDays') ?? 1;
                    $dtsDocumentationScore = Pt_Commons_SchemeConfig::get('dts.documentationScore') ?? 10;

                    if ($interval->days < $dtsSampleRehydrateDays || $interval->days > ($dtsSampleRehydrateDays + 1)) {

                        $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(0);
                    } else {
                        $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($documentationScorePerItem);
                    }
                } else {
                    $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit(0);
                }

                $documentScore = !empty($dtsDocumentationScore) && (int) $dtsDocumentationScore > 0 ? (($aRow['documentation_score'] / $dtsDocumentationScore) * 100) : 0;
                $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($docScoreCol++) . $docScoreRow)->setValueExplicit($documentScore);

                //-------------Document score sheet------------>
                //<------------ Total score sheet ------------

                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (count($aRow['response']) > 0) {

                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                        //$row[] = $aRow[$k]['testResult1'];
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['control_line']);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['diagnosis_line']);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['longterm_line']);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['finalResult']);
                        if (isset($aRow['response'][$k]['calculated_score']) && $aRow['response'][$k]['calculated_score'] == 'Pass' && $aRow['response'][$k]['sample_id'] == $refResult[$k]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }

                    $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['user_comment']);

                    $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++) . $sheetThreeRow)
                        ->setValueExplicit($countCorrectResult);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++) . $sheetThreeRow)
                        ->setValueExplicit($totPer);

                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($countCorrectResult);
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($totPer);

                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit(($totPer * 0.9));
                }
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit($documentScore);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit($aRow['documentation_score']);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']));

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($i + 1) . $sheetThreeRow)
                        ->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $currentRow)
                        ->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
                    $cellName = $docScoreSheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $docScoreRow)
                        ->getColumn();
                    $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $cellName = $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $totScoreRow)
                        ->getColumn();
                    $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        $firstName = $authNameSpace->first_name;
        $lastName = $authNameSpace->last_name;
        $name = $firstName . " " . $lastName;
        $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Recency excel report downloaded by $userName", "shipment");

        $excel->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . rand() . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function addRecencySampleNameInArray($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_recency', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result = $db->fetchAll($query);
        $samples = [];
        foreach ($result as $row) {
            $samples[] = $row['sample_label'];
        }
        return $samples;
    }
}
