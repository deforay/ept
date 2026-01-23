<?php


use PhpOffice\PhpSpreadsheet\IOFactory;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Application_Service_QuantitativeCalculations as QuantitativeCalculations;

class Application_Model_Vl
{

    public function __construct()
    {
    }

    public function evaluate($shipmentResult, $shipmentId, $reEvaluate)
    {
        $counter = 0;
        $maxScore = 0;
        $scoreHolder = [];
        $finalResult = null;
        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();


        $db->update('shipment_participant_map', ['is_excluded' => 'no'], "shipment_id = $shipmentId");
        $db->update('shipment_participant_map', ['is_excluded' => 'yes'], "shipment_id = $shipmentId and IFNULL(is_pt_test_not_performed, 'no') = 'yes'");



        //$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        //$config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $passPercentage = Pt_Commons_SchemeConfig::get('vl.passPercentage') ?? 100;

        if ($reEvaluate) {
            //$beforeSetVlRange = $db->fetchAll($db->select()->from('reference_vl_calculation', array('*'))->where('shipment_id = ' . $shipmentId)->where('use_range = "manual"'));
            // when re-evaluating we will set the reset the range
            $this->setVlRange($shipmentId);
            $quantRange = $this->getVlRange($shipmentId);
            // if (isset($beforeSetVlRange) && !empty($beforeSetVlRange)) {
            //     foreach ($beforeSetVlRange as $row) {
            //         $db->update('reference_vl_calculation', $row, "shipment_id = " . $shipmentId . " and sample_id = " . $row['sample_id'] . " and " . " vl_assay = " . $row['vl_assay']);
            //     }
            // }
        } else {
            $quantRange = $this->getVlRange($shipmentId);
        }


        foreach ($shipmentResult as $shipment) {
            Pt_Commons_MiscUtility::updateHeartbeat('shipment', 'shipment_id', $shipmentId);
            $shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.
            $attributes = json_decode($shipment['attributes'], true);
            $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);

            $methodOfEvaluation = $shipmentAttributes['methodOfEvaluation'] ?? 'standard';

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date'] ?? '');
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = null;
            }

            $lastDate = new DateTime($shipment['lastdate_response']);

            if (!empty($createdOn) && $createdOn <= $lastDate) {

                $results = $schemeService->getVlSamples($shipmentId, $shipment['participant_id']);

                $totalScore = 0;
                $maxScore = 0;
                $zScore = null;
                $mandatoryResult = "";
                $scoreResult = "";
                $failureReason = [];

                foreach ($results as $result) {
                    if ($result['control'] == 1) {
                        continue;
                    }
                    $calcResult = "";
                    $responseAssay = json_decode($result['attributes'], true);
                    $responseAssay = isset($responseAssay['vl_assay']) ? $responseAssay['vl_assay'] : "";
                    // if (!in_array($result['unique_identifier'], $meganda[$responseAssay]) && $shipment['is_pt_test_not_performed'] != 'yes') {
                    //     $meganda[$responseAssay][] = $result['unique_identifier'];
                    //     sort($meganda[$responseAssay]);
                    // }

                    if (isset($quantRange[$responseAssay])) {
                        if ($methodOfEvaluation == 'standard') {
                            // matching reported and low/high limits
                            if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
                                if (isset($quantRange[$responseAssay][$result['sample_id']]) && $quantRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $quantRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
                                    $totalScore += $result['sample_score'];
                                    $calcResult = "pass";
                                } else {
                                    if ($result['sample_score'] > 0) {
                                        $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                    }
                                    $calcResult = "fail";
                                }
                            }
                        } elseif ($methodOfEvaluation == 'iso17043') {
                            // matching reported and low/high limits
                            if (!empty($result['is_result_invalid']) && in_array($result['is_result_invalid'], ['invalid', 'error'])) {
                                if ($result['sample_score'] > 0) {
                                    $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                                $calcResult = "fail";
                                $zScore = null;
                            } elseif (!empty($result['reported_viral_load'])) {
                                if (isset($quantRange[$responseAssay][$result['sample_id']])) {
                                    $zScore = 0;
                                    $sd = (float) $quantRange[$responseAssay][$result['sample_id']]['sd'];
                                    $median = (float) $quantRange[$responseAssay][$result['sample_id']]['median'];
                                    if ($sd > 0) {
                                        $zScore = (float) (($result['reported_viral_load'] - $median) / $sd);
                                    }

                                    if (0 == $sd) {
                                        // If SD is 0 and there is a detectable result reported, then it is treated as fail
                                        if (0 == $result['reported_viral_load']) {
                                            $totalScore += $result['sample_score'];
                                            $calcResult = "pass";
                                        } elseif ($result['reported_viral_load'] > 0) {
                                            //failed
                                            if ($result['sample_score'] > 0) {
                                                $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                            }
                                            $calcResult = "fail";
                                        }
                                    } else {
                                        $absZScore = abs($zScore);
                                        if ($absZScore <= 2) {
                                            //passed
                                            $totalScore += $result['sample_score'];
                                            $calcResult = "pass";
                                        } elseif ($absZScore > 2 && $absZScore <= 3) {
                                            //passed but with a warning
                                            $totalScore += $result['sample_score'];
                                            $calcResult = "warn";
                                        } elseif ($absZScore > 3) {
                                            //failed
                                            if ($result['sample_score'] > 0) {
                                                $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                            }
                                            $calcResult = "fail";
                                        }
                                    }
                                } else {
                                    if ($result['sample_score'] > 0) {
                                        $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                    }
                                    $calcResult = "fail";
                                }
                            }
                        }
                    } else {
                        $totalScore = "N.A.";
                        $calcResult = "excluded";
                        $shipment['is_excluded'] = 'yes';
                        $failureReason[]['warning'] = "Excluded from Shipment.";
                    }

                    $maxScore += $result['sample_score'];

                    $db->update('response_result_vl', array('z_score' => $zScore, 'calculated_score' => $calcResult), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);

                    //// checking if mandatory fields were entered and were entered right
                    //if ($result['mandatory'] == 1) {
                    //	if ((!isset($result['reported_viral_load']) || $result['reported_viral_load'] == "" || $result['reported_viral_load'] == null)) {
                    //		$mandatoryResult = 'Fail';
                    //		$failureReason[]['warning'] = "Mandatory Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
                    //	}
                    //	//else if(($result['reported_viral_load'] != $result['reported_viral_load'])){
                    //	//	$mandatoryResult = 'Fail';
                    //	//	$failureReason[]= "Mandatory Sample <strong>".$result['sample_label']."</strong> was reported wrongly";
                    //	//}
                    //}
                }



                // if we are excluding this result, then let us not give pass/fail
                if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                    $finalResult = '';
                    $totalScore = 0;
                    $failureReason = [];
                    $shipmentResult[$counter]['shipment_score'] = $responseScore = 0;
                    $shipmentResult[$counter]['documentation_score'] = 0;
                    $shipmentResult[$counter]['display_result'] = 'Excluded';
                    $shipmentResult[$counter]['is_followup'] = 'yes';
                    $shipmentResult[$counter]['is_excluded'] = 'yes';
                    $failureReason[] = ['warning' => 'Excluded from Evaluation'];
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $failureReason;
                } else {

                    // checking if total score and maximum scores are the same
                    if ($totalScore == 'N.A.') {
                        $failureReason[] = [
                            'warning' => "Could not determine score. Not enough responses found in the chosen VL Assay."
                        ];
                        $scoreResult = 'Not Evaluated';
                        $shipment['is_excluded'] = 'yes';
                    } elseif ($totalScore != $maxScore) {
                        $scoreResult = 'Fail';
                        if ($maxScore != 0) {
                            $totalScore = ($totalScore / $maxScore) * 100;
                        }
                        $failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$passPercentage</strong>)";
                    } else {
                        if ($maxScore != 0) {
                            $totalScore = ($totalScore / $maxScore) * 100;
                        }
                        $scoreResult = 'Pass';
                    }


                    // if $finalResult == 3 , then  excluded

                    if ($scoreResult == 'Not Evaluated') {
                        $finalResult = 4;
                    } elseif ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }

                    $shipmentResult[$counter]['shipment_score'] = $totalScore;
                    $shipmentResult[$counter]['max_score'] = $passPercentage; //$maxScore;



                    $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $failureReason;
                    //Zend_Debug::dump($shipmentResult[$counter]);
                    // let us update the total score in DB
                    if ($totalScore == 'N/A') {
                        $totalScore = 0;
                    }
                }
                /* Manual result override changes */
                if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
                    $sql = $db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
                    $shipmentOverall = $db->fetchRow($sql);
                    if (!empty($shipmentOverall)) {
                        $shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
                        if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
                            $shipmentOverall['final_result'] = 2;
                        }
                        $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $shipmentOverall['final_result']));
                        $shipmentResult[$counter]['display_result'] = $fRes[0];
                        $db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
                    }
                } else {
                    $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'is_excluded' => $shipment['is_excluded'], 'failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
                }
            } else {

                $failureReason[] = ['warning' => "Response was submitted after the last response date."];
                $shipment['is_excluded'] = 'yes';

                $db->update('shipment_participant_map', [
                    'is_excluded' => 'yes',
                    'failure_reason' => json_encode($failureReason)
                ], "map_id = " . $shipment['map_id']);
            }
            $counter++;
        }
        $db->update('shipment', ['max_score' => $maxScore, 'status' => 'evaluated'], "shipment_id = " . $shipmentId);


        return $shipmentResult;
    }

    public function generateDtsViralLoadExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $excel = new Spreadsheet();


        $styleArray = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ]
        ];

        $boldStyleArray = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ]
        ];

        $borderStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ]
        ];
        $vlBorderStyle = array(
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );


        $query = $db->select()->from('shipment')
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        $shipmentAttributes = json_decode($result['shipment_attributes'], true);
        $methodOfEvaluation = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';

        $refQuery = $db->select()->from(array('refRes' => 'reference_result_vl'))->where("refRes.shipment_id = ?", $shipmentId)->where("refRes.control!=1");
        $refResult = $db->fetchAll($refQuery);

        $colNamesArray = [];
        $colNamesArray[] = "Participant ID";
        //$colNamesArray[] = "Lab Name";
        //$colNamesArray[] = "Department Name";
        //$colNamesArray[] = "Region";
        //$colNamesArray[] = "Site Type";
        //$colNamesArray[] = "Assay";
        //$colNamesArray[] = "Assay Expiration Date";
        //$colNamesArray[] = "Assay Lot Number";
        //$colNamesArray[] = "Specimen Volume";

        $firstSheet = new Worksheet($excel, 'Overall Results');
        $excel->addSheet($firstSheet, 0);

        $firstSheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
            ->setValueExplicit(html_entity_decode("Participant ID", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(2) . 1)
            ->setValueExplicit(html_entity_decode("Participant Name", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(3) . 1)
            ->setValueExplicit(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . 1)
            ->setValueExplicit(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'));
        //$firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . 1)
        //->setValueExplicit(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCell(Coordinate::stringFromColumnIndex(5) . 1)
        //->setValueExplicit(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCell(Coordinate::stringFromColumnIndex(6) . 1)
        //->setValueExplicit(html_entity_decode("Assay Expiration Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCell(Coordinate::stringFromColumnIndex(7) . 1)
        //->setValueExplicit(html_entity_decode("Assay Lot Number", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCell(Coordinate::stringFromColumnIndex(8) . 1)
        //->setValueExplicit(html_entity_decode("Specimen Volume", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1)->applyFromArray($borderStyle, true);
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex(2) . 1)->applyFromArray($borderStyle, true);
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex(3) . 1)->applyFromArray($borderStyle, true);
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex(4) . 1)->applyFromArray($borderStyle, true);
        //$firstSheet->getStyle(Coordinate::stringFromColumnIndex(4) . 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyle(Coordinate::stringFromColumnIndex(5) . 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyle(Coordinate::stringFromColumnIndex(6) . 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyle(Coordinate::stringFromColumnIndex(7) . 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyle(Coordinate::stringFromColumnIndex(8) . 1)->applyFromArray($borderStyle);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 5;
        foreach ($refResult as $refRow) {
            $colNamesArray[] = $refRow['sample_label'];
            if ($methodOfEvaluation == 'iso17043') {
                $colNamesArray[] = "z Score for " . $refRow['sample_label'];

                $colNamesArray[] = "Grade for " . $refRow['sample_label'];
            }
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
                ->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
            $colNameCount++;
        }

        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Final Score", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Final Score";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Date Received";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Date Tested";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Assay";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Institute Name", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Institute Name";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Department Name", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Department Name";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Region", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Region";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Site Type";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Assay Expiration Date", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Assay Expiration Date";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Assay Lot Number", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Assay Lot Number";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Specimen Volume", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Specimen Volume";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Supervisor Name", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Supervisor Name";
        $firstSheet->getCell(Coordinate::stringFromColumnIndex($colNameCount) . 1)
            ->setValueExplicit(html_entity_decode("Participant Comment", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle(Coordinate::stringFromColumnIndex($colNameCount) . 1)->applyFromArray($borderStyle, true);
        // $colNameCount++;

        $firstSheet->setTitle('OVERALL', true);

        $queryOverAll = $db->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), "spm.shipment_id = s.shipment_id")
            ->joinLeft(array('p' => 'participant'), "p.participant_id = spm.participant_id")
            ->joinLeft(array('c' => 'countries'), "c.id = p.country", array('country_name' => 'iso_name'))
            ->joinLeft(array('st' => 'r_site_type'), "st.r_stid=p.site_type")
            ->where("s.shipment_id = ?", $shipmentId);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $queryOverAll = $queryOverAll
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $resultOverAll = $db->fetchAll($queryOverAll);

        $row = 1; // $row 0 is already the column headings

        $assayList = $this->getVlAssay();

        $assayWiseData = [];

        foreach ($resultOverAll as $rowOverAll) {
            $row++;

            $queryResponse = $db->select()
                ->from(array('res' => 'response_result_vl'))
                ->joinLeft(array('refRes' => 'reference_result_vl'), "refRes.sample_id = res.sample_id")
                ->where("refRes.control!=1")
                ->where("refRes.shipment_id = ?", $shipmentId)
                ->where("res.shipment_map_id = ?", $rowOverAll['map_id']);
            //echo $queryResponse;
            $resultResponse = $db->fetchAll($queryResponse);

            $attributes = json_decode($rowOverAll['attributes'], true);

            if (isset($attributes['other_assay']) && $attributes['other_assay'] != "") {
                $assayName = "Other - " . $attributes['other_assay'];
            } else {
                $assayName = (array_key_exists($attributes['vl_assay'], $assayList)) ? $assayList[$attributes['vl_assay']] : "";
            }

            $assayExpirationDate = "";
            if (isset($attributes['assay_expiration_date']) && $attributes['assay_expiration_date'] != "") {
                $assayExpirationDate = Pt_Commons_DateUtility::humanReadableDateFormat($attributes['assay_expiration_date']);
            }

            $assayLotNumber = "";
            if (isset($attributes['assay_lot_number']) && $attributes['assay_lot_number'] != "") {
                $assayLotNumber = ($attributes['assay_lot_number']);
            }

            $specimenVolume = "";
            if (isset($attributes['specimen_volume']) && $attributes['specimen_volume'] != "") {
                $specimenVolume = ($attributes['specimen_volume']);
            }
            // we are also building the data required for other Assay Sheets
            if ($attributes['vl_assay'] > 0) {
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['unique_identifier'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['first_name']." ".$rowOverAll['last_name'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = ucwords($rowOverAll['institute_name']);
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = ucwords($rowOverAll['department_name']);
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['region'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['site_type'];
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayName;
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayExpirationDate;
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayLotNumber;
                //$assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $specimenVolume;
            }

            $firstSheet->getCell(Coordinate::stringFromColumnIndex(1) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(2) . $row)
                ->setValueExplicit(mb_convert_encoding($rowOverAll['lab_name'], 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex(3) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['country_name'], ENT_QUOTES, 'UTF-8'));

            //$firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . $row)
            //->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCell(Coordinate::stringFromColumnIndex(5) . $row)
            //->setValueExplicit(html_entity_decode($assayName, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCell(Coordinate::stringFromColumnIndex(6) . $row)
            //->setValueExplicit(html_entity_decode($assayExpirationDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCell(Coordinate::stringFromColumnIndex(7) . $row)
            //->setValueExplicit(html_entity_decode($assayLotNumber, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCell(Coordinate::stringFromColumnIndex(8) . $row)
            //->setValueExplicit(html_entity_decode($specimenVolume, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            $col = 4;
            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                $firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . $row)
                    ->setValueExplicit(html_entity_decode("PT TEST NOT PERFORMED", ENT_QUOTES, 'UTF-8'));
                $col = 4 + count($refResult);
            } elseif (count($resultResponse) > 0) {
                $firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . $row)
                    ->setValueExplicit(html_entity_decode("Responded", ENT_QUOTES, 'UTF-8'));
                $col = 5;
                foreach ($resultResponse as $responseRow) {
                    $yrResult = '';
                    if (isset($responseRow['is_result_invalid']) && !empty($responseRow['is_result_invalid'])) {
                        $yrResult = (isset($responseRow['is_result_invalid']) && !empty($responseRow['is_result_invalid']) && !empty($responseRow['error_code'])) ? ucwords($responseRow['is_result_invalid']) . ', ' . $responseRow['error_code'] : ucwords($responseRow['is_result_invalid']);
                    } else {
                        $yrResult = round($responseRow['reported_viral_load'], 2) ?? null;
                    }
                    $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                        ->setValueExplicit(html_entity_decode($yrResult, ENT_QUOTES, 'UTF-8'));
                    // we are also building the data required for other Assay Sheets
                    if ($attributes['vl_assay'] > 0) {
                        $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $yrResult;
                        if ($methodOfEvaluation == 'iso17043') {
                            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $responseRow['z_score'];
                            if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'pass') {
                                $grade = 'Acceptable';
                            } elseif (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'fail') {
                                $grade = 'Unacceptable';
                            } elseif (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'warn') {
                                $grade = 'Warning';
                            } else {
                                $grade = 'N.A.';
                            }
                            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $grade;
                        }
                    }
                }
            } else {
                $firstSheet->getCell(Coordinate::stringFromColumnIndex(4) . $row)
                    ->setValueExplicit(html_entity_decode("Not Responded", ENT_QUOTES, 'UTF-8'));
                $col = 4 + count($refResult);
            }


            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit($rowOverAll['shipment_score']);

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00") ? Pt_Commons_DateUtility::humanReadableDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00") ? Pt_Commons_DateUtility::humanReadableDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'));

            // we are also building the data required for other Assay Sheets
            if ($attributes['vl_assay'] > 0) {
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['shipment_score'];
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $receiptDate;
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $testDate;
            }


            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($assayName, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($assayExpirationDate, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($assayLotNumber, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($specimenVolume, ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['participant_supervisor'], ENT_QUOTES, 'UTF-8'));
            $firstSheet->getCell(Coordinate::stringFromColumnIndex($col++) . $row)
                ->setValueExplicit(html_entity_decode($rowOverAll['user_comment'], ENT_QUOTES, 'UTF-8'));

            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayName;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['institute_name'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['department_name'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['region'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['site_type'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayExpirationDate;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $assayLotNumber;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $specimenVolume;
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['participant_supervisor'];
            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['user_comment'];


            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                unset($assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']]);
            }
        }


        foreach (range('A', 'Z') as $columnID) {
            $firstSheet->getColumnDimension($columnID, true)
                ->setAutoSize(true);
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $assayRes = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));

        $countOfVlAssaySheet = 1;

        foreach ($assayRes as $assayRow) {
            $newsheet = new Worksheet($excel, '');
            $excel->addSheet($newsheet, $countOfVlAssaySheet);

            $newsheet->getDefaultRowDimension()->setRowHeight(15);

            $vlCalculation = [];
            $vlQuery = $db->select()->from(['vlCal' => 'reference_vl_calculation'], ['*'])
                ->join(['refVl' => 'reference_result_vl'], 'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id', ['refVl.sample_label', 'refVl.mandatory'])
                ->join(['sp' => 'shipment_participant_map'], 'vlCal.shipment_id=sp.shipment_id', [])
                ->join(['res' => 'response_result_vl'], 'res.shipment_map_id = sp.map_id and res.sample_id = refVl.sample_id', [
                    'NumberPassed' => new Zend_Db_Expr("SUM(CASE WHEN calculated_score = 'pass' OR calculated_score = 'warn' THEN 1 ELSE 0 END)"),
                ])

                ->where("vlCal.shipment_id = ?", $shipmentId)
                ->where("vlCal.vl_assay = ?", $assayRow['id'])
                ->where("refVl.control != 1")
                ->where('sp.attributes->>"$.vl_assay" = ' . $assayRow['id'])
                ->where("sp.is_excluded not like 'yes' OR sp.is_excluded like '' OR sp.is_excluded is null")
                ->where("sp.final_result = 1 OR sp.final_result = 2")
                ->group('refVl.sample_id');
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (!empty($authNameSpace->dm_id)) {
                $vlQuery = $vlQuery
                    ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=sp.participant_id', ['pmm.dm_id'])
                    ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
            }
            $vlCalRes = $db->fetchAll($vlQuery);
            if ($assayRow['id'] == 6) {
                $cQuery = $db->select()
                    ->from(['sp' => 'shipment_participant_map'], ['sp.map_id', 'sp.attributes'])
                    ->where("sp.is_excluded not like 'yes'")
                    ->where('sp.attributes->>"$.vl_assay" = 6')
                    ->where('sp.shipment_id = ? ', $shipmentId);
                $authNameSpace = new Zend_Session_Namespace('datamanagers');
                if (!empty($authNameSpace->dm_id)) {
                    $vlQuery = $vlQuery
                        ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=sp.participant_id', ['pmm.dm_id'])
                        ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
                }
                $cResult = $db->fetchAll($cQuery);

                $otherAssayCounter = [];
                foreach ($cResult as $val) {
                    $valAttributes = json_decode($val['attributes'], true);
                    if (isset($valAttributes['other_assay'])) {
                        if (!empty($otherAssayCounter[$valAttributes['other_assay']])) {
                            $otherAssayCounter[$valAttributes['other_assay']]++;
                        } else {
                            $otherAssayCounter[$valAttributes['other_assay']] = 1;
                        }
                    }
                }
            }

            if (!empty($vlCalRes)) {
                $vlCalculation[$assayRow['id']] = $vlCalRes;
                $vlCalculation[$assayRow['id']]['vlAssay'] = $assayRow['name'];
                $vlCalculation[$assayRow['id']]['shortName'] = $assayRow['short_name'];
                $vlCalculation[$assayRow['id']]['participant_response_count'] = $vlCalRes[0]['no_of_responses'];
                if ($assayRow['id'] == 6) {
                    $vlCalculation[$assayRow['id']]['otherAssayName'] = $otherAssayCounter;
                }
            }
            $sample = [];
            $assayNameTxt = "";
            foreach ($vlCalculation as $vlCal) {
                $row = 10;
                if (isset($vlCal['participant_response_count']) && $vlCal['participant_response_count'] < 18 || $vlCal['vlAssay'] == "Other") {
                    $t = 0;

                    foreach ($vlCal as $k => $val) {

                        if (isset($val['median'])) {

                            $sample[$k]['response'] += $val['no_of_responses'];
                            $sample[$k]['median'] = $val['median'];
                            $sample[$k]['lowLimit'] = $val['low_limit'];
                            $sample[$k]['highLimit'] = $val['high_limit'];
                            $sample[$k]['sd'] = $val['sd'];
                            $sample[$k]['NumberPassed'] += !empty($val['NumberPassed']) ? $val['NumberPassed'] : 0;
                            $sample[$t]['label'] = $val['sample_label'];
                            $t++;
                        }
                    }
                    // $responseTxt = $val['no_of_responses'];
                    if ($vlCal['vlAssay'] == "Other") {
                        foreach ($vlCal['otherAssayName'] as $otherAssayName => $otherAssayCount) {
                            $assayNameTxt .= "Other - $otherAssayName(n=$otherAssayCount), ";
                        }
                    } else {
                        $assayNameTxt .= $vlCal['vlAssay'] . '(n=' . $vlCal[0]['no_of_responses'] . '), ';
                    }
                } else {
                    $newsheet->mergeCells('A10:H10');
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 10)
                        ->setValueExplicit(html_entity_decode('Platform/Assay Name: ' . $vlCal['vlAssay'], ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 11)
                        ->setValueExplicit(html_entity_decode('Specimen ID', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(2) . 11)
                        ->setValueExplicit(html_entity_decode('Number Of Participants', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(3) . 11)
                        ->setValueExplicit(html_entity_decode('Assigned Value (log10 copies/mL)', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(4) . 11)
                        ->setValueExplicit(html_entity_decode('Lower limit (Q1)', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(5) . 11)
                        ->setValueExplicit(html_entity_decode('Upper limit (Q3)', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(6) . 11)
                        ->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'));
                    $newsheet->mergeCells('G11:H11');
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(7) . 11)
                        ->setValueExplicit(html_entity_decode('Participants with Passing Results (|z| <3.0)', ENT_QUOTES, 'UTF-8'));

                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 10)->applyFromArray($boldStyleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 11)->applyFromArray($borderStyle, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(2) . 11)->applyFromArray($borderStyle, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(3) . 11)->applyFromArray($borderStyle, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(4) . 11)->applyFromArray($borderStyle, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(5) . 11)->applyFromArray($borderStyle, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(6) . 11)->applyFromArray($borderStyle, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(7) . 11)->applyFromArray($borderStyle, true);
                    $row = 12;
                    foreach ($vlCal as $key => $val) {
                        $col = 1;

                        if (!empty($val['useRange']) && $val['useRange'] == 'manual') {
                            $val['low'] = $val['manual_low'];
                            $val['high'] = $val['manual_high'];
                            $val['median'] = $val['manual_median'];
                            $val['sd'] = $val['manual_sd'];
                        }
                        if (isset($val['median'])) {
                            $score = round((($val['NumberPassed'] / $val['no_of_responses']) * 100));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode($val['sample_label'], ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode($val['no_of_responses'], ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode(number_format(round($val['median'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode(number_format(round($val['low_limit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode(number_format(round($val['high_limit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode(number_format(round($val['sd'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode($val['NumberPassed'], ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)
                                ->setValueExplicit(html_entity_decode($score . '%', ENT_QUOTES, 'UTF-8'));
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . $row)->applyFromArray($vlBorderStyle, true);
                            $row++;
                        }
                    }
                    // $assayName[] = $vlCal['vlAssay'];
                }
            }
            $row = (isset($row) && $row > 0) ? $row : 10;
            if (isset($sample) && count($sample) > 0) {
                $newsheet->mergeCells('A' . $row . ':H' . $row);
                $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . $row)
                    ->setValueExplicit(html_entity_decode('Platform/Assay Name: VL platforms with < 18 participants', ENT_QUOTES, 'UTF-8'));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(1) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Specimen ID', ENT_QUOTES, 'UTF-8'));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(2) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Number Of Participants', ENT_QUOTES, 'UTF-8'));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(3) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Assigned Value (log10 copies/mL)', ENT_QUOTES, 'UTF-8'));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(4) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Lower limit (Q1)', ENT_QUOTES, 'UTF-8'));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(5) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Upper limit (Q3)', ENT_QUOTES, 'UTF-8'));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(6) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'));
                $newsheet->mergeCells('G' . ($row + 1) . ':H' . ($row + 1));
                $newsheet->getCell(
                    Coordinate::stringFromColumnIndex(7) . ($row + 1)
                )->setValueExplicit(html_entity_decode('Participants with Passing Results (|z| <3.0)', ENT_QUOTES, 'UTF-8'));

                $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . $row)->applyFromArray($boldStyleArray, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . ($row + 1))->applyFromArray($borderStyle, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(2) . ($row + 1))->applyFromArray($borderStyle, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(3) . ($row + 1))->applyFromArray($borderStyle, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(4) . ($row + 1))->applyFromArray($borderStyle, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(5) . ($row + 1))->applyFromArray($borderStyle, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(6) . ($row + 1))->applyFromArray($borderStyle, true);
                $newsheet->getStyle(Coordinate::stringFromColumnIndex(7) . ($row + 1))->applyFromArray($borderStyle, true);

                $row++;
                foreach ($sample as $point => $label) {
                    $col = 1;
                    $score = round((($label['NumberPassed'] / $label['response']) * 100));

                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode($label['label'], ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode($label['response'], ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode(number_format(round($label['median'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode(number_format(round($label['lowLimit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode(number_format(round($label['highLimit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode(number_format(round($label['sd'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode($label['NumberPassed'], ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . ($row + 1)
                    )->setValueExplicit(html_entity_decode($score . '%', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($col) . ($row + 1))->applyFromArray($vlBorderStyle, true);
                    $row++;
                }
                // $assayName[] = 'VL platforms with < 18 participants';
            }


            foreach (range('A', 'Z') as $columnID) {
                $newsheet->getColumnDimension($columnID, true)->setAutoSize(true);
            }

            $i = 0;
            $startAt = 28;
            foreach ($colNamesArray as $colName) {
                $newsheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $startAt)
                    ->setValueExplicit(html_entity_decode($colName, ENT_QUOTES, 'UTF-8'));
                $newsheet->getStyle(Coordinate::stringFromColumnIndex($i + 1) . $startAt)->applyFromArray($borderStyle, true);
                $i++;
            }
            //get vl_assay wise low high limit
            $refVlCalci = $db->fetchAll($db->select()->from(array('rvc' => 'reference_vl_calculation'))
                ->join(array('rrv' => 'reference_result_vl'), 'rrv.sample_id=rvc.sample_id AND rrv.shipment_id=' . $result['shipment_id'], array('sample_label'))
                ->where('rvc.shipment_id=' . $result['shipment_id'])->where('rvc.vl_assay=' . $assayRow['id'])
                ->where('rrv.control!=1'));
            if (count($refVlCalci) > 0) {

                if ($methodOfEvaluation == 'standard') {


                    //write in excel low and high limit title
                    $newsheet->mergeCells('A1:F1');
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                        ->setValueExplicit(html_entity_decode('System Generated', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 2)
                        ->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 3)
                        ->setValueExplicit(html_entity_decode('Q1', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 4)
                        ->setValueExplicit(html_entity_decode('Q3', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 5)
                        ->setValueExplicit(html_entity_decode('IQR', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 6)
                        ->setValueExplicit(html_entity_decode('Quartile Low', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 7)
                        ->setValueExplicit(html_entity_decode('Quartile High', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 8)
                        ->setValueExplicit(html_entity_decode('Mean', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 9)
                        ->setValueExplicit(html_entity_decode('SD', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 10)
                        ->setValueExplicit(html_entity_decode('CV', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 11)
                        ->setValueExplicit(html_entity_decode('Low Limit', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 12)
                        ->setValueExplicit(html_entity_decode('High Limit', ENT_QUOTES, 'UTF-8'));

                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1)->applyFromArray($boldStyleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 2)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 3)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 4)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 5)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 6)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 7)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 8)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 9)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 10)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 11)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 12)->applyFromArray($styleArray, true);

                    $k = 1;
                    $manual = [];
                    foreach ($refVlCalci as $calculation) {
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 2)
                            ->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 3)
                            ->setValueExplicit(html_entity_decode(round($calculation['q1'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 4)
                            ->setValueExplicit(html_entity_decode(round($calculation['q3'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 5)
                            ->setValueExplicit(html_entity_decode(round($calculation['iqr'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 6)
                            ->setValueExplicit(html_entity_decode(round($calculation['quartile_low'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 7)
                            ->setValueExplicit(html_entity_decode(round($calculation['quartile_high'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 8)
                            ->setValueExplicit(html_entity_decode(round($calculation['mean'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 9)
                            ->setValueExplicit(html_entity_decode(round($calculation['sd'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 10)
                            ->setValueExplicit(html_entity_decode(round($calculation['cv'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 11)
                            ->setValueExplicit(html_entity_decode(round($calculation['low_limit'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 12)
                            ->setValueExplicit(html_entity_decode(round($calculation['high_limit'], 4), ENT_QUOTES, 'UTF-8'));

                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 2)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 3)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 4)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 5)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 6)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 7)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 8)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 9)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 10)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 11)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 12)->applyFromArray($vlBorderStyle, true);
                        $keys = ['manual_mean', 'manual_sd', 'manual_low_limit', 'manual_high_limit', 'manual_cv', 'manual_q1', 'manual_q3', 'manual_iqr', 'manual_quartile_low', 'manual_quartile_high'];

                        foreach ($keys as $key) {
                            if ($calculation[$key] != 0) {
                                $manual[] = 'yes';
                                break;
                            }
                        }
                        $k++;
                    }
                    if (count($manual) > 0) {
                        $newsheet->mergeCells('A15:F15');
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 15)
                            ->setValueExplicit(html_entity_decode('Manual Generated', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 16)
                            ->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 17)
                            ->setValueExplicit(html_entity_decode('Manual Q1', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 18)
                            ->setValueExplicit(html_entity_decode('Manual Q3', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 19)
                            ->setValueExplicit(html_entity_decode('Manual IQR', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 20)
                            ->setValueExplicit(html_entity_decode('Manual Quartile Low', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 21)
                            ->setValueExplicit(html_entity_decode('Manual Quartile High', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 22)
                            ->setValueExplicit(html_entity_decode('Manual Mean', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 23)
                            ->setValueExplicit(html_entity_decode('Manual SD', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 24)
                            ->setValueExplicit(html_entity_decode('Manual CV', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 25)
                            ->setValueExplicit(html_entity_decode('Manual Low Limit', ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 26)
                            ->setValueExplicit(html_entity_decode('Manual High Limit', ENT_QUOTES, 'UTF-8'));

                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 15)->applyFromArray($boldStyleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 16)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 17)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 18)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 19)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 20)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 21)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 22)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 23)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 24)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 25)->applyFromArray($styleArray, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 26)->applyFromArray($styleArray, true);
                        $k = 1;
                        foreach ($refVlCalci as $calculation) {
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 16)
                                ->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 17)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_q1'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 18)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_q3'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 19)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_iqr'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 20)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_quartile_low'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 21)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_quartile_high'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 22)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_mean'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 23)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_sd'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 24)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_cv'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 25)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_low_limit'], 4), ENT_QUOTES, 'UTF-8'));
                            $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 26)
                                ->setValueExplicit(html_entity_decode(round($calculation['manual_high_limit'], 4), ENT_QUOTES, 'UTF-8'));

                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 16)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 17)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 18)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 19)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 20)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 21)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 22)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 23)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 24)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 25)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 26)->applyFromArray($vlBorderStyle, true);

                            $k++;
                        }
                    }
                } elseif ($methodOfEvaluation == 'iso17043') {
                    $newsheet->mergeCells('A1:F1');
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 1)
                        ->setValueExplicit(html_entity_decode('System Generated', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 2)
                        ->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 3)
                        ->setValueExplicit(html_entity_decode('Median', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 4)
                        ->setValueExplicit(html_entity_decode('Upper Limit (Q3)', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 5)
                        ->setValueExplicit(html_entity_decode('Lower Limit (Q1)', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 6)
                        ->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 7)
                        ->setValueExplicit(html_entity_decode('Standard Uncertainty', ENT_QUOTES, 'UTF-8'));
                    $newsheet->getCell(Coordinate::stringFromColumnIndex(1) . 8)
                        ->setValueExplicit(html_entity_decode('Is Uncertainty Acceptable?', ENT_QUOTES, 'UTF-8'));

                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1)->applyFromArray($boldStyleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 2)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 3)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 4)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 5)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 6)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 7)->applyFromArray($styleArray, true);
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex(1) . 8)->applyFromArray($styleArray, true);

                    $k = 1;
                    $manual = [];
                    foreach ($refVlCalci as $calculation) {
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 2)
                            ->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 3)
                            ->setValueExplicit(html_entity_decode(round($calculation['median'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 4)
                            ->setValueExplicit(html_entity_decode(round($calculation['q3'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 5)
                            ->setValueExplicit(html_entity_decode(round($calculation['q1'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 6)
                            ->setValueExplicit(html_entity_decode(round($calculation['sd'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 7)
                            ->setValueExplicit(html_entity_decode(round($calculation['standard_uncertainty'], 4), ENT_QUOTES, 'UTF-8'));
                        $newsheet->getCell(Coordinate::stringFromColumnIndex($k + 1) . 8)
                            ->setValueExplicit(html_entity_decode($calculation['is_uncertainty_acceptable'], ENT_QUOTES, 'UTF-8'));


                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 2)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 3)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 4)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 5)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 6)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 7)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyle(Coordinate::stringFromColumnIndex($k + 1) . 8)->applyFromArray($vlBorderStyle, true);


                        $k++;
                    }
                }
            }
            //

            $assayData = isset($assayWiseData[$assayRow['id']]) ? $assayWiseData[$assayRow['id']] : array();
            $newsheet->setTitle(strtoupper($assayRow['short_name']), true);
            $row = $startAt; // $row 1-$startAt already occupied

            foreach ($assayData as $assayKey => $assayRow) {
                $row++;
                $noOfCols = count($assayRow);
                for ($c = 0; $c < $noOfCols; $c++) {
                    $newsheet->getCell(Coordinate::stringFromColumnIndex($c + 1) . $row)
                        ->setValueExplicit(html_entity_decode($assayRow[$c], ENT_QUOTES, 'UTF-8'));
                    $newsheet->getStyle(Coordinate::stringFromColumnIndex($c + 1) . $row)->applyFromArray($vlBorderStyle, true);
                }
            }

            $countOfVlAssaySheet++;
        }

        $firstName = $authNameSpace->first_name;
        $lastName = $authNameSpace->last_name;
        $name = $firstName . " " . $lastName;
        $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("DTS Viral Load report downloaded by $userName", "shipment");

        $excel->setActiveSheetIndex(0);

        // $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function getDataForIndividualPDF($shipmentId, $participantId, $participantAttributes, $shipmentAttributes)
    {
        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $vlAssayResultSet = $this->getVlAssay();
        $vlRange = $this->getVlRange($shipmentId);
        $results = $schemeService->getVlSamples($shipmentId, $participantId);
        $attributes = json_decode($participantAttributes, true);
        $shipmentAttributes = json_decode($shipmentAttributes, true);

        $methodOfEvaluation = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';
        if ($vlRange == null || $vlRange == "" || count($vlRange) == 0) {
            $this->setVlRange($shipmentId);
            $vlRange = $this->getVlRange($shipmentId);
        }

        $sql = $db->select()->from(array('ref' => 'reference_result_vl'), array('sample_id', 'ref.sample_label', 'control', 'mandatory'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id', array('*'))
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id', array('sp.map_id', 'sp.attributes', 'sp.shipment_receipt_date', 'sp.shipment_test_date', 'sp.is_pt_test_not_performed', 'sp.is_excluded', 'sp.shipment_test_report_date', 'sp.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier'))
            ->joinLeft(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('reported_viral_load', 'is_result_invalid', 'error_code', 'z_score'))
            //->where("sp.is_pt_test_not_performed is NULL")
            ->where("sp.is_excluded not like 'yes'")
            ->where("sp.shipment_test_date IS NOT NULL AND sp.shipment_test_date not like '' AND sp.shipment_test_date not like '0000-00-00' AND sp.shipment_test_date not like '0000-00-00'")
            ->where('sp.shipment_id = ? ', $shipmentId);

        $spmResult = $db->fetchAll($sql);

        $vlGraphResult = [];
        foreach ($spmResult as $val) {
            $valAttributes = json_decode($val['attributes'], true);
            if ((isset($attributes['id']) && isset($valAttributes['vl_assay'])) && ($attributes['vl_assay'] == $valAttributes['vl_assay'])) {
                if (array_key_exists($val['sample_label'], $vlGraphResult)) {
                    if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'] <= $val['reported_viral_load'] && isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'] >= $val['reported_viral_load']) {
                        $vlGraphResult[$val['sample_label']]['vl'][] = $val['reported_viral_load'];
                    } else {
                        $vlGraphResult[$val['sample_label']]['NA'][] = $val['reported_viral_load'];
                    }
                    //$vlGraphResult[$val['sample_label']]['pId'][]="lab ".$val['unique_identifier'];
                } else {
                    $vlGraphResult[$val['sample_label']] = [];
                    if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'] <= $val['reported_viral_load'] && isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high']) && $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'] >= $val['reported_viral_load']) {
                        $vlGraphResult[$val['sample_label']]['vl'][] = $val['reported_viral_load'];
                    } else {
                        $vlGraphResult[$val['sample_label']]['NA'][] = $val['reported_viral_load'];
                    }
                    if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'])) {
                        $vlGraphResult[$val['sample_label']]['low'] = $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['low'];
                    }
                    if (isset($vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'])) {
                        $vlGraphResult[$val['sample_label']]['high'] = $vlRange[$valAttributes['vl_assay']][$val['sample_id']]['high'];
                    }
                }
            }
        }

        $counter = 0;
        $zScore = null;
        $toReturn = [];
        foreach ($results as $result) {
            //$toReturn = [];
            $responseAssay = json_decode($result['attributes'], true);
            if ($responseAssay['vl_assay'] == 6) {
                $assayName = $responseAssay['other_assay'];
            } else {
                $assayName = $vlAssayResultSet[$responseAssay['vl_assay']];
            }
            $toReturn[$counter]['vl_assay'] = isset($vlAssayResultSet[$responseAssay['vl_assay']]) ? $vlAssayResultSet[$responseAssay['vl_assay']] : "";
            $responseAssay = $responseAssay['vl_assay'];

            $vlGraphResult[$result['sample_label']]['pVal'] = $result['reported_viral_load'];

            $toReturn[$counter]['sample_label'] = $result['sample_label'];
            $toReturn[$counter]['shipment_map_id'] = $result['map_id'];
            $toReturn[$counter]['shipment_id'] = $result['shipment_id'];
            $toReturn[$counter]['responseDate'] = $result['responseDate'];
            $toReturn[$counter]['shipment_score'] = $result['shipment_score'];
            $toReturn[$counter]['shipment_test_date'] = $result['shipment_test_date'];
            $toReturn[$counter]['shipment_test_report_date'] = $result['shipment_test_report_date'];
            $toReturn[$counter]['user_comment'] = $result['user_comment'];
            $toReturn[$counter]['is_excluded'] = $result['is_excluded'];
            $toReturn[$counter]['is_pt_test_not_performed'] = $result['is_pt_test_not_performed'];
            $toReturn[$counter]['shipment_receipt_date'] = $result['shipment_receipt_date'];
            $toReturn[$counter]['max_score'] = $result['max_score'];
            $toReturn[$counter]['reported_viral_load'] = $result['reported_viral_load'];
            $toReturn[$counter]['is_result_invalid'] = $result['is_result_invalid'];
            $toReturn[$counter]['error_code'] = $result['error_code'];

            if (isset($vlRange[$responseAssay])) {


                $toReturn[$counter]['no_of_participants'] = $vlRange[$responseAssay][$result['sample_id']]['no_of_responses'];

                if ($methodOfEvaluation == 'standard') {
                    // matching reported and low/high limits
                    if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
                        if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
                            $grade = 'Acceptable';
                        } else {
                            $grade = 'Unacceptable';
                        }
                    }

                    if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null && trim($result['reported_viral_load']) != null) {
                        if ($vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
                            $grade = 'Acceptable';
                        } else {
                            if ($result['sample_score'] > 0) {
                                $grade = 'Unacceptable';
                            } else {
                                $grade = '-';
                            }
                        }
                    } else {
                        $grade = 'Unacceptable';
                    }
                    $toReturn[$counter]['low'] = $vlRange[$responseAssay][$result['sample_id']]['low'];
                    $toReturn[$counter]['high'] = $vlRange[$responseAssay][$result['sample_id']]['high'];
                    $toReturn[$counter]['sd'] = $vlRange[$responseAssay][$result['sample_id']]['sd'];
                    $toReturn[$counter]['mean'] = $vlRange[$responseAssay][$result['sample_id']]['mean'];
                    $toReturn[$counter]['median'] = $vlRange[$responseAssay][$result['sample_id']]['median'];
                    $toReturn[$counter]['manualMean'] = $vlRange[$responseAssay][$result['sample_id']]['manual_mean'];
                    $toReturn[$counter]['manualSd'] = $vlRange[$responseAssay][$result['sample_id']]['manual_sd'];
                    $toReturn[$counter]['manualMedian'] = $vlRange[$responseAssay][$result['sample_id']]['manual_median'];
                    $toReturn[$counter]['useRange'] = $vlRange[$responseAssay][$result['sample_id']]['use_range'] ?? 'calculated';
                    $toReturn[$counter]['zscore'] = $result['z_score'];
                } elseif ($methodOfEvaluation == 'iso17043') {
                    // matching reported and low/high limits
                    if (isset($result['calculated_score']) && $result['calculated_score'] == 'pass') {
                        $grade = 'Acceptable';
                    } elseif (isset($result['calculated_score']) && $result['calculated_score'] == 'fail') {
                        $grade = 'Unacceptable';
                    } elseif (isset($result['calculated_score']) && $result['calculated_score'] == 'warn') {
                        $grade = 'Warning';
                    }

                    $toReturn[$counter]['low'] = $vlRange[$responseAssay][$result['sample_id']]['q1'];
                    $toReturn[$counter]['high'] = $vlRange[$responseAssay][$result['sample_id']]['q3'];
                    $toReturn[$counter]['sd'] = $vlRange[$responseAssay][$result['sample_id']]['sd'];
                    $toReturn[$counter]['median'] = $vlRange[$responseAssay][$result['sample_id']]['median'];
                    $toReturn[$counter]['zscore'] = $result['z_score'];
                }
            } else {
                $toReturn[$counter]['low'] = 'Not Applicable';
                $toReturn[$counter]['high'] = 'Not Applicable';
                $toReturn[$counter]['sd'] = 'Not Applicable';
                $toReturn[$counter]['mean'] = 'Not Applicable';
                $toReturn[$counter]['median'] = 'Not Applicable';
                $grade = 'Not Applicable';
                $toReturn[$counter]['zscore'] = 0;
            }
            $toReturn[$counter]['grade'] = $grade;

            $counter++;
        }

        return $toReturn;
    }


    public function getVlAssay($option = true)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $res = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));
        $response = [];
        if ($option) {
            foreach ($res as $row) {
                $response[$row['id']] = $row['name'];
            }
            return $response;
        } else {
            return $res;
        }
    }

    public function setVlRange($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'])
            ->where('shipment_id = ? ', $shipmentId);
        $shipment = $db->fetchRow($sql);


        $beforeSetVlRangeData = $db->fetchAll($db->select()->from('reference_vl_calculation', ['*'])
            ->where("shipment_id = ?", $shipmentId));
        $oldQuantRange = [];
        foreach ($beforeSetVlRangeData as $beforeSetVlRangeRow) {
            $oldQuantRange[$beforeSetVlRangeRow['vl_assay']][$beforeSetVlRangeRow['sample_id']] = $beforeSetVlRangeRow;
        }


        $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);

        $method = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';

        $db->delete('reference_vl_calculation', "use_range IS NOT NULL and use_range not like 'manual' AND " . $db->quoteInto('shipment_id = ?', $shipmentId));

        $sql = $db->select()->from(['ref' => 'reference_result_vl'], ['shipment_id', 'sample_id'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id', [])
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id', ['participant_id', 'assay' => new Zend_Db_Expr('sp.attributes->>"$.vl_assay"')])
            ->joinLeft(['res' => 'response_result_vl'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_viral_load', 'z_score', 'is_result_invalid'])
            ->where('sp.shipment_id = ? ', $shipmentId)
            ->where('DATE(sp.shipment_test_report_date) <= s.lastdate_response')
            //->where("(sp.is_excluded LIKE 'yes') IS NOT TRUE")
            ->where("(sp.is_pt_test_not_performed LIKE 'yes') IS NOT TRUE");

        $response = $db->fetchAll($sql);

        $sampleWise = [];
        foreach ($response as $row) {
            $invalidValues = ['invalid', 'error'];

            if (!empty($row['is_result_invalid']) && in_array($row['is_result_invalid'], $invalidValues)) {
                $row['reported_viral_load'] = null;
            }

            $sampleWise[$row['assay']][$row['sample_id']][] = $row['reported_viral_load'];
        }


        $vlAssayArray = $this->getVlAssay();

        $skippedAssays = [];
        $skippedAssays[] = 6; // adding "Others" to skippedAssays as it will always be skipped

        $responseCounter = [];

        if ('standard' == $method) {
            $minimumRequiredResponses = 6;
        } elseif ('iso17043' == $method) {
            $minimumRequiredResponses = 18;
        }

        foreach ($vlAssayArray as $vlAssayId => $vlAssayName) {


            if (!isset($sampleWise[$vlAssayId])) {
                continue;
            }

            // IMPORTANT: If the reported samples for an Assay are < $minimumRequiredResponses
            // then we use the ranges of the Assay with maximum responses

            foreach ($sampleWise[$vlAssayId] as $sample => $reportedVl) {

                if ($vlAssayId != 6 && !empty($reportedVl) && count($reportedVl) > $minimumRequiredResponses) {
                    $responseCounter[$vlAssayId] = count($reportedVl);

                    $inputArray = $reportedVl;

                    $finalHigh = null;
                    $finalLow = null;
                    $quartileHighLimit = null;
                    $quartileLowLimit = null;
                    $iqr = null;
                    $cv = null;
                    $finalLow = null;
                    $finalHigh = null;
                    $avg = null;
                    $median = null;
                    $standardUncertainty = null;
                    $isUncertaintyAcceptable = null;
                    $q1 = $q3 = 0;

                    // removing all null values
                    $inputArray = array_filter(
                        $inputArray,
                        function ($value) {
                            return !is_null($value);
                        }
                    );

                    if ('standard' == $method) {
                        sort($inputArray);
                        $q1 = QuantitativeCalculations::calculateQuantile($inputArray, 0.25);
                        $q3 = QuantitativeCalculations::calculateQuantile($inputArray, 0.75);
                        $iqr = $q3 - $q1;
                        $iqrMultiplier = $iqr * 1.5;
                        $quartileLowLimit = $q1 - $iqrMultiplier;
                        $quartileHighLimit = $q3 + $iqrMultiplier;

                        $newDataSet = [];
                        $removeArray = [];
                        foreach ($inputArray as $a) {
                            if ($a >= round($quartileLowLimit, 2) && $a <= round($quartileHighLimit, 2)) {
                                $newDataSet[] = $a;
                            } else {
                                $removeArray[] = $a;
                            }
                        }

                        //Zend_Debug::dump("Under Assay $vlAssayId-Sample $sample - COUNT AFTER REMOVING OUTLIERS: ".count($newArray) . " FOLLOWING ARE OUTLIERS");
                        //Zend_Debug::dump($removeArray);

                        $avg = QuantitativeCalculations::calculateMean($newDataSet);
                        $sd = QuantitativeCalculations::calculateStandardDeviation($newDataSet);

                        $cv = QuantitativeCalculations::calculateCoefficientOfVariation($newDataSet, $avg, $sd);
                        $threeTimesSd = $sd * 3;
                        $finalLow = $avg - $threeTimesSd;
                        $finalHigh = $avg + $threeTimesSd;
                    } elseif ('iso17043' == $method) {
                        sort($inputArray);
                        $median = QuantitativeCalculations::calculateMedian($inputArray);
                        $finalLow = $quartileLowLimit = $q1 = QuantitativeCalculations::calculateQuantile($inputArray, 0.25);
                        $finalHigh = $quartileHighLimit = $q3 = QuantitativeCalculations::calculateQuantile($inputArray, 0.75);
                        $iqr = $q3 - $q1;
                        $sd = 0.7413 * $iqr;
                        if (!empty($inputArray)) {
                            $standardUncertainty = (1.25 * $sd) / sqrt(count($inputArray));
                        }
                        if ($median == 0) {
                            $isUncertaintyAcceptable = 'NA';
                        } elseif ($standardUncertainty < (0.3 * $sd)) {
                            $isUncertaintyAcceptable = 'yes';
                        } else {
                            $isUncertaintyAcceptable = 'no';
                        }
                    }


                    $data = [
                        'shipment_id' => $shipmentId,
                        'vl_assay' => $vlAssayId,
                        'no_of_responses' => count($inputArray),
                        'sample_id' => $sample,
                        'q1' => $q1,
                        'q3' => $q3,
                        'iqr' => $iqr ?? 0,
                        'quartile_low' => $quartileLowLimit,
                        'quartile_high' => $quartileHighLimit,
                        'mean' => $avg ?? 0,
                        'median' => $median ?? 0,
                        'sd' => $sd ?? 0,
                        'standard_uncertainty' => $standardUncertainty ?? 0,
                        'is_uncertainty_acceptable' => $isUncertaintyAcceptable ?? 'NA',
                        'cv' => $cv ?? 0,
                        'low_limit' => $finalLow,
                        'high_limit' => $finalHigh,
                        'calculated_on' => new Zend_Db_Expr('now()'),
                    ];

                    if (isset($oldQuantRange[$vlAssayId][$sample]) && !empty($oldQuantRange[$vlAssayId][$sample]) && $oldQuantRange[$vlAssayId][$sample]['use_range'] == 'manual') {
                        $data['manual_q1'] = $oldQuantRange[$vlAssayId][$sample]['manual_q1'] ?? null;
                        $data['manual_q3'] = $oldQuantRange[$vlAssayId][$sample]['manual_q3'] ?? null;
                        $data['manual_cv'] = $oldQuantRange[$vlAssayId][$sample]['manual_cv'] ?? null;
                        $data['manual_iqr'] = $oldQuantRange[$vlAssayId][$sample]['manual_iqr'] ?? null;
                        $data['manual_quartile_high'] = $oldQuantRange[$vlAssayId][$sample]['manual_quartile_high'] ?? null;
                        $data['manual_quartile_low'] = $oldQuantRange[$vlAssayId][$sample]['manual_quartile_low'] ?? null;
                        $data['manual_low_limit'] = $oldQuantRange[$vlAssayId][$sample]['manual_low_limit'] ?? null;
                        $data['manual_high_limit'] = $oldQuantRange[$vlAssayId][$sample]['manual_high_limit'] ?? null;
                        $data['manual_mean'] = $oldQuantRange[$vlAssayId][$sample]['manual_mean'] ?? null;
                        $data['manual_median'] = $oldQuantRange[$vlAssayId][$sample]['manual_median'] ?? null;
                        $data['manual_sd'] = $oldQuantRange[$vlAssayId][$sample]['manual_sd'] ?? null;
                        $data['manual_standard_uncertainty'] = $oldQuantRange[$vlAssayId][$sample]['manual_standard_uncertainty'] ?? null;
                        $data['manual_is_uncertainty_acceptable'] = $oldQuantRange[$vlAssayId][$sample]['manual_is_uncertainty_acceptable'] ?? null;
                        $data['updated_on'] = $oldQuantRange[$vlAssayId][$sample]['updated_on'] ?? null;
                        $data['use_range'] = $oldQuantRange[$vlAssayId][$sample]['use_range'] ?? 'calculated';
                    }

                    $db->delete('reference_vl_calculation', $db->quoteInto('vl_assay = ?', $vlAssayId) . ' AND ' . $db->quoteInto('sample_id = ?', $sample) . ' AND ' . $db->quoteInto('shipment_id = ?', $shipmentId));

                    $db->insert('reference_vl_calculation', $data);
                } else {

                    if (isset($oldQuantRange[$vlAssayId][$sample]) && !empty($oldQuantRange[$vlAssayId][$sample]) && $oldQuantRange[$vlAssayId][$sample]['use_range'] != 'manual') {
                        $db->delete('reference_vl_calculation', $db->quoteInto('vl_assay = ?', $vlAssayId) . ' AND ' . $db->quoteInto('shipment_id = ?', $shipmentId));
                    }

                    $skippedAssays[] = $vlAssayId;
                    $skippedResponseCounter[$vlAssayId] = count($reportedVl);
                }
            }
        }

        // Okay now we are going to take the assay with maximum responses and use its range for assays having < $minimumRequiredResponses

        $skippedAssays = array_unique($skippedAssays);
        arsort($responseCounter);
        reset($responseCounter);
        $maxResponsesAssay = key($responseCounter);

        $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'])
            // ->where('rvc.vl_assay = ?', $maxAssay)
            ->where('rvc.shipment_id = ?', $shipmentId);

        if (isset($maxResponsesAssay) && $maxResponsesAssay != "") {
            $sql->where('rvc.vl_assay = ?', $maxResponsesAssay);
        }
        $res = $db->fetchAll($sql);

        foreach ($skippedAssays as $vlAssayId) {
            foreach ($res as $row) {

                $sample = $row['sample_id'];
                $row['vl_assay'] = $vlAssayId;
                $row['no_of_responses'] = $skippedResponseCounter[$vlAssayId];

                // if there are no responses then continue
                // (this is especially put to check and remove vl assay = 6 if no one used "Others")
                // Why? because we manually inserted "6" into skippedAssays at the top of this function
                if (empty($row['no_of_responses'])) {
                    continue;
                }

                if (isset($oldQuantRange[$vlAssayId][$sample]) && !empty($oldQuantRange[$vlAssayId][$sample]) && $oldQuantRange[$vlAssayId][$sample]['use_range'] == 'manual') {
                    $row['manual_q1'] = $oldQuantRange[$vlAssayId][$sample]['manual_q1'] ?? null;
                    $row['manual_q3'] = $oldQuantRange[$vlAssayId][$sample]['manual_q3'] ?? null;
                    $row['manual_cv'] = $oldQuantRange[$vlAssayId][$sample]['manual_cv'] ?? null;
                    $row['manual_iqr'] = $oldQuantRange[$vlAssayId][$sample]['manual_iqr'] ?? null;
                    $row['manual_quartile_high'] = $oldQuantRange[$vlAssayId][$sample]['manual_quartile_high'] ?? null;
                    $row['manual_quartile_low'] = $oldQuantRange[$vlAssayId][$sample]['manual_quartile_low'] ?? null;
                    $row['manual_low_limit'] = $oldQuantRange[$vlAssayId][$sample]['manual_low_limit'] ?? null;
                    $row['manual_high_limit'] = $oldQuantRange[$vlAssayId][$sample]['manual_high_limit'] ?? null;
                    $row['manual_mean'] = $oldQuantRange[$vlAssayId][$sample]['manual_mean'] ?? null;
                    $row['manual_median'] = $oldQuantRange[$vlAssayId][$sample]['manual_median'] ?? null;
                    $row['manual_sd'] = $oldQuantRange[$vlAssayId][$sample]['manual_sd'] ?? null;
                    $row['manual_standard_uncertainty'] = $oldQuantRange[$vlAssayId][$sample]['manual_standard_uncertainty'] ?? null;
                    $row['manual_is_uncertainty_acceptable'] = $oldQuantRange[$vlAssayId][$sample]['manual_is_uncertainty_acceptable'] ?? null;
                    $row['updated_on'] = $oldQuantRange[$vlAssayId][$sample]['updated_on'] ?? null;
                    $row['use_range'] = $oldQuantRange[$vlAssayId][$sample]['use_range'] ?? 'calculated';
                }

                $db->delete('reference_vl_calculation', "vl_assay = " . $row['vl_assay'] . " AND sample_id= " . $row['sample_id'] . " AND shipment_id=  " . $row['shipment_id']);
                $db->insert('reference_vl_calculation', $row);
            }
        }
    }


    public function getVlRange($sId, $sampleId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'])
            ->join(['ref' => 'reference_result_vl'], 'rvc.sample_id = ref.sample_id', ['sample_label'])
            ->join(['a' => 'r_vl_assay'], 'a.id = rvc.vl_assay', ['assay_name' => 'name'])
            ->where('rvc.shipment_id = ?', $sId);

        if ($sampleId != null) {
            $sql = $sql->where('rvc.sample_id = ?', $sampleId);
        }

        $res = $db->fetchAll($sql);
        $response = [];
        foreach ($res as $row) {

            $assay = $row['vl_assay'];
            $sampleId = $row['sample_id'];

            $response[$assay][$sampleId]['sample_id'] = $row['sample_id'];
            $response[$assay][$sampleId]['vl_assay'] = $row['vl_assay'];
            $response[$assay][$sampleId]['no_of_responses'] = $row['no_of_responses'];
            $response[$assay][$sampleId]['assay_name'] = $row['assay_name'];
            $response[$assay][$sampleId]['sample_label'] = $row['sample_label'];
            $response[$assay][$sampleId]['use_range'] = $row['use_range'] ?? 'calculated';

            if (!empty($row['use_range']) && $row['use_range'] == 'manual') {
                $response[$assay][$sampleId]['q1'] = $row['manual_q1'];
                $response[$assay][$sampleId]['q3'] = $row['manual_q3'];
                $response[$assay][$sampleId]['quartile_low'] = $row['manual_quartile_low'];
                $response[$assay][$sampleId]['quartile_high'] = $row['manual_quartile_high'];
                $response[$assay][$sampleId]['low'] = $row['manual_low_limit'];
                $response[$assay][$sampleId]['high'] = $row['manual_high_limit'];
                $response[$assay][$sampleId]['mean'] = $row['manual_mean'];
                $response[$assay][$sampleId]['median'] = $row['manual_median'];
                $response[$assay][$sampleId]['sd'] = $row['manual_sd'];
                $response[$assay][$sampleId]['standard_uncertainty'] = $row['manual_standard_uncertainty'];
                $response[$assay][$sampleId]['is_uncertainty_acceptable'] = $row['manual_is_uncertainty_acceptable'];
            } else {
                $response[$assay][$sampleId]['q1'] = $row['q1'];
                $response[$assay][$sampleId]['q3'] = $row['q3'];
                $response[$assay][$sampleId]['quartile_low'] = $row['quartile_low'];
                $response[$assay][$sampleId]['quartile_high'] = $row['quartile_high'];
                $response[$assay][$sampleId]['low'] = $row['low_limit'];
                $response[$assay][$sampleId]['high'] = $row['high_limit'];
                $response[$assay][$sampleId]['mean'] = $row['mean'];
                $response[$assay][$sampleId]['median'] = $row['median'];
                $response[$assay][$sampleId]['sd'] = $row['sd'];
                $response[$assay][$sampleId]['standard_uncertainty'] = $row['standard_uncertainty'];
                $response[$assay][$sampleId]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'];
            }
        }
        return $response;
    }


    public function getVlRangeInformation($sId, $sampleId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'], ['*'])
            ->join(['ref' => 'reference_result_vl'], 'rvc.sample_id = ref.sample_id AND ref.shipment_id=' . $sId, ['sample_label'])
            ->joinLeft(['a' => 'r_vl_assay'], 'a.id = rvc.vl_assay', ['assay_name' => 'name'])
            ->join(['s' => 'shipment'], 'rvc.shipment_id = s.shipment_id')
            ->where('rvc.shipment_id = ?', $sId)
            ->order(['sample_label', 'assay_name']);

        if (!empty($sampleId)) {
            $sql = $sql->where('rvc.sample_id = ?', $sampleId);
        }

        $res = $db->fetchAll($sql);

        // if no data found, then it means we do not have enough responses to calculate
        // get the data from r_vl_assay table and show blank or 0 values for all fields
        if (empty($res)) {
            $sql = $db->select()->from(['a' => 'r_vl_assay'], ['assay_name' => 'name', 'vl_assay' => 'id'])
                ->joinLeft(['s' => 'shipment'], "s.shipment_id = $sId")
                ->join(['ref' => 'reference_result_vl'], "ref.shipment_id= $sId", ['sample_label', 'sample_id'])
                ->order(['sample_label', 'assay_name']);

            $res = $db->fetchAll($sql);
        }

        $shipmentAttributes = !empty($res[0]['shipment_attributes']) ? json_decode($res[0]['shipment_attributes'], true) : null;
        $methodOfEvaluation = $shipmentAttributes['methodOfEvaluation'] ?? 'standard';


        $response = [];

        $response['method_of_evaluation'] = $methodOfEvaluation;

        foreach ($res as $row) {

            $response[$row['sample_id']][$row['vl_assay']]['shipment_id'] = $row['shipment_id'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['sample_label'] = $row['sample_label'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['sample_id'] = $row['sample_id'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['vl_assay'] = $row['vl_assay'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['assay_name'] = $row['assay_name'] ?? null;
            $response[$row['sample_id']][$row['vl_assay']]['low'] = $row['low_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['high'] = $row['high_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['mean'] = $row['mean'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['median'] = $row['median'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['sd'] = $row['sd'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['standard_uncertainty'] = $row['standard_uncertainty'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_mean'] = $row['manual_mean'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_median'] = $row['manual_median'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_sd'] = $row['manual_sd'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_low_limit'] = $row['manual_low_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['manual_high_limit'] = $row['manual_high_limit'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['use_range'] = $row['use_range'] ?? 0;
            $response[$row['sample_id']][$row['vl_assay']]['method_of_evaluation'] = $methodOfEvaluation;

            if (!isset($response['updated_on'])) {
                $response['updated_on'] = $row['updated_on'] ?? null;
            }
            if (!isset($response['calculated_on'])) {
                $response['calculated_on'] = $row['calculated_on'] ?? null;
            }
        }

        return $response;
    }


    public function updateVlInformation($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        foreach ($params['sampleId'] as $assayId => $samples) {
            foreach ($samples as $sampid) {
                //$data['manual_low_limit'] = $params['manualLow'][$assayId][$sampid];
                //$data['manual_high_limit'] = $params['manualHigh'][$assayId][$sampid];
                $data['use_range'] = $params['useRange'][$assayId][$sampid];
                $data['updated_on'] = new Zend_Db_Expr('now()');
                //echo "shipment_id = ".base64_decode($params['sid'])." and sample_id = " . $sampid . " and "." vl_assay = " . $assayId ;
                $db->update('reference_vl_calculation', $data, "shipment_id = " . base64_decode($params['sid']) . " and sample_id = " . $sampid . " and " . " vl_assay = " . $assayId);
            }
        }
    }


    public function updateVlManualValue($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->beginTransaction();
        try {
            $shipmentId = base64_decode($params['shipmentId']);
            $sampleId = base64_decode($params['sampleId']);
            $vlAssay = base64_decode($params['vlAssay']);
            if (trim($shipmentId) != "" && trim($sampleId) != "" && trim($vlAssay) != "") {
                $data['manual_q1'] = !empty($params['manualQ1']) ? $params['manualQ1'] : null;
                $data['manual_q3'] = !empty($params['manualQ3']) ? $params['manualQ3'] : null;
                $data['manual_iqr'] = !empty($params['manualIqr']) ? $params['manualIqr'] : null;
                $data['manual_quartile_low'] = !empty($params['manualQuartileLow']) ? $params['manualQuartileLow'] : null;
                $data['manual_quartile_high'] = !empty($params['manualQuartileHigh']) ? $params['manualQuartileHigh'] : null;
                $data['low_limit'] = !empty($params['lowLimit']) ? $params['lowLimit'] : null;
                $data['high_limit'] = !empty($params['highLimit']) ? $params['highLimit'] : null;
                $data['manual_mean'] = !empty($params['manualMean']) ? $params['manualMean'] : null;
                $data['manual_median'] = !empty($params['manualMedian']) ? $params['manualMedian'] : null;
                $data['manual_sd'] = !empty($params['manualSd']) ? $params['manualSd'] : null;
                $data['manual_standard_uncertainty'] = !empty($params['manualStandardUncertainty']) ? $params['manualStandardUncertainty'] : null;
                $data['manual_is_uncertainty_acceptable'] = !empty($params['manualIsUncertaintyAcceptable']) ? $params['manualIsUncertaintyAcceptable'] : null;
                $data['manual_cv'] = !empty($params['manualCv']) ? $params['manualCv'] : null;
                $data['manual_low_limit'] = !empty($params['manualLowLimit']) ? $params['manualLowLimit'] : null;
                $data['manual_high_limit'] = !empty($params['manualHighLimit']) ? $params['manualHighLimit'] : null;
                $db->update('reference_vl_calculation', $data, "shipment_id = " . $shipmentId . " and sample_id = " . $sampleId . " and " . " vl_assay = " . $vlAssay);
                $db->commit();
                return $params['shipmentId'];
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }


    public function getVlManualValue($shipmentId, $sampleId, $vlAssay)
    {
        if (trim($shipmentId) != "" && trim($sampleId) != "" && trim($vlAssay) != "") {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sql = $db->select()->from(['rvc' => 'reference_vl_calculation'], ['shipment_id', 'sample_id', 'low_limit', 'high_limit', 'vl_assay', 'manual_q1', 'manual_q3', 'manual_iqr', 'manual_quartile_low', 'manual_quartile_high', 'manual_mean', 'manual_sd', 'manual_cv', 'manual_high_limit', 'manual_low_limit', 'manual_standard_uncertainty', 'manual_is_uncertainty_acceptable', 'manual_median', 'use_range'])
                ->join(['ref' => 'reference_result_vl'], 'rvc.sample_id = ref.sample_id AND ref.shipment_id=' . $shipmentId, ['sample_label'])
                ->join(['a' => 'r_vl_assay'], 'a.id = rvc.vl_assay', ['assay_name' => 'name'])
                ->join(['s' => 'shipment'], 'rvc.shipment_id = s.shipment_id')
                ->where('rvc.shipment_id = ?', $shipmentId)
                ->where('rvc.sample_id = ?', $sampleId)
                ->where('rvc.vl_assay = ?', $vlAssay);
            return $db->fetchRow($sql);
        }
    }
}
