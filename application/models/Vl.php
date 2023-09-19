<?php

error_reporting(E_ALL ^ E_NOTICE);

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




        $db->update('shipment_participant_map', array('is_excluded' => 'no'), "shipment_id = $shipmentId");
        $db->update('shipment_participant_map', array('is_excluded' => 'yes'), "shipment_id = $shipmentId and IFNULL(is_pt_test_not_performed, 'no') = 'yes'");



        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $passPercentage = $config->evaluation->vl->passPercentage;

        if ($reEvaluate) {
            $beforeSetVlRange = $db->fetchAll($db->select()->from('reference_vl_calculation', array('*'))->where('shipment_id = ' . $shipmentId)->where('use_range = "manual"'));
            // when re-evaluating we will set the reset the range
            $schemeService->setVlRange($shipmentId);
            $vlRange = $schemeService->getVlRange($shipmentId);
            if (isset($beforeSetVlRange) && !empty($beforeSetVlRange)) {
                foreach ($beforeSetVlRange as $row) {
                    $db->update('reference_vl_calculation', $row, "shipment_id = " . $shipmentId . " and sample_id = " . $row['sample_id'] . " and " . " vl_assay = " . $row['vl_assay']);
                }
            }
        } else {
            $vlRange = $schemeService->getVlRange($shipmentId);
        }


        foreach ($shipmentResult as $shipment) {

            $shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.
            $attributes = json_decode($shipment['attributes'], true);
            $shipmentAttributes = json_decode($shipment['shipment_attributes'], true);

            $methodOfEvaluation = isset($shipmentAttributes['methodOfEvaluation']) ? $shipmentAttributes['methodOfEvaluation'] : 'standard';

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = null;
            }

            $lastDate = new DateTime($shipment['lastdate_response']);

            //Zend_Debug::dump($createdOn->isEarlier($lastDate));die;
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

                    if (isset($vlRange[$responseAssay])) {
                        if ($methodOfEvaluation == 'standard') {
                            // matching reported and low/high limits
                            if (isset($result['reported_viral_load']) && $result['reported_viral_load'] != null) {
                                if (isset($vlRange[$responseAssay][$result['sample_id']]) && $vlRange[$responseAssay][$result['sample_id']]['low'] <= $result['reported_viral_load'] && $vlRange[$responseAssay][$result['sample_id']]['high'] >= $result['reported_viral_load']) {
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
                            } elseif (!empty($result['reported_viral_load'])) {
                                if (isset($vlRange[$responseAssay][$result['sample_id']])) {
                                    $zScore = 0;
                                    $sd = (float) $vlRange[$responseAssay][$result['sample_id']]['sd'];
                                    $median = (float) $vlRange[$responseAssay][$result['sample_id']]['median'];
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
                    $failureReason[] = array('warning' => 'Excluded from Evaluation');
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                } else {

                    // checking if total score and maximum scores are the same
                    if ($totalScore == 'N.A.') {
                        $failureReason[]['warning'] = "Could not determine score. Not enough responses found in the chosen VL Assay.";
                        $scoreResult = 'Not Evaluated';
                        $shipment['is_excluded'] = 'yes';
                    } else if ($totalScore != $maxScore) {
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
                    } else if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }

                    $shipmentResult[$counter]['shipment_score'] = $totalScore;
                    $shipmentResult[$counter]['max_score'] = $passPercentage; //$maxScore;



                    $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
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
                        // Zend_Debug::dump($shipmentResult);die;
                        $nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
                    }
                } else {

                    $nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'is_excluded' => $shipment['is_excluded'], 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
                }
            } else {
                $failureReason = array('warning' => "Response was submitted after the last response date.");
                $shipment['is_excluded'] = 'yes';

                $db->update('shipment_participant_map', array('is_excluded' => 'yes', 'failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }
            $counter++;
        }
        $db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);


        return $shipmentResult;
    }

    public function generateDtsViralLoadExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();


        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );

        $boldStyleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            )
        );

        $borderStyle = array(
            'font' => array(
                'bold' => true,
                'size'  => 12,
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );
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

        $firstSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Overall Results');
        $excel->addSheet($firstSheet, 0);

        $firstSheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode("Participant ID", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 1)->setValueExplicit(html_entity_decode("Participant Name", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(3, 1)->setValueExplicit(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(4, 1)->setValueExplicit(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(4, 1)->setValueExplicit(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(5, 1)->setValueExplicit(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(6, 1)->setValueExplicit(html_entity_decode("Assay Expiration Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(7, 1)->setValueExplicit(html_entity_decode("Assay Lot Number", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
        //$firstSheet->getCellByColumnAndRow(8, 1)->setValueExplicit(html_entity_decode("Specimen Volume", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

        $firstSheet->getStyleByColumnAndRow(1, 1, null, null)->applyFromArray($borderStyle, true);
        $firstSheet->getStyleByColumnAndRow(2, 1, null, null)->applyFromArray($borderStyle, true);
        $firstSheet->getStyleByColumnAndRow(3, 1, null, null)->applyFromArray($borderStyle, true);
        $firstSheet->getStyleByColumnAndRow(4, 1, null, null)->applyFromArray($borderStyle, true);
        //$firstSheet->getStyleByColumnAndRow(4, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(5, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(6, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(7, 1)->applyFromArray($borderStyle);
        //$firstSheet->getStyleByColumnAndRow(8, 1)->applyFromArray($borderStyle);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 5;
        foreach ($refResult as $refRow) {
            $colNamesArray[] = $refRow['sample_label'];
            if ($methodOfEvaluation == 'iso17043') {
                $colNamesArray[] = "z Score for " . $refRow['sample_label'];

                $colNamesArray[] = "Grade for " . $refRow['sample_label'];
            }
            $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
            $colNameCount++;
        }

        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Final Score", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Final Score";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Date Received";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Date Tested";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Assay";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Institute Name", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Institute Name";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Department Name", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Department Name";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Region", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Region";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Site Type", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Site Type";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Assay Expiration Date", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Assay Expiration Date";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Assay Lot Number", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Assay Lot Number";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Specimen Volume", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Specimen Volume";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Supervisor Name", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        $colNameCount++;

        $colNamesArray[] = "Supervisor Name";
        $firstSheet->getCellByColumnAndRow($colNameCount, 1)->setValueExplicit(html_entity_decode("Participant Comment", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getStyleByColumnAndRow($colNameCount, 1, null, null)->applyFromArray($borderStyle, true);
        // $colNameCount++;

        $firstSheet->setTitle('OVERALL', true);

        $queryOverAll = $db->select()->from(array('s' => 'shipment'))
            ->joinLeft(array('spm' => 'shipment_participant_map'), "spm.shipment_id = s.shipment_id")
            ->joinLeft(array('p' => 'participant'), "p.participant_id = spm.participant_id")
            ->joinLeft(array('c' => 'countries'), "c.id = p.country", array('country_name' => 'iso_name'))
            ->joinLeft(array('st' => 'r_site_type'), "st.r_stid=p.site_type")
            ->where("s.shipment_id = ?", $shipmentId);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
            $queryOverAll = $queryOverAll
                ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }
        $resultOverAll = $db->fetchAll($queryOverAll);

        $row = 1; // $row 0 is already the column headings

        $schemeService = new Application_Service_Schemes();
        $assayList = $schemeService->getVlAssay();

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
                $assayExpirationDate = Pt_Commons_General::humanReadableDateFormat($attributes['assay_expiration_date']);
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

            $firstSheet->getCellByColumnAndRow(1, $row)->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(2, $row)->setValueExplicit(utf8_encode($rowOverAll['lab_name']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode($rowOverAll['country_name'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            //$firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(5, $row)->setValueExplicit(html_entity_decode($assayName, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(6, $row)->setValueExplicit(html_entity_decode($assayExpirationDate, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(7, $row)->setValueExplicit(html_entity_decode($assayLotNumber, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
            //$firstSheet->getCellByColumnAndRow(8, $row)->setValueExplicit(html_entity_decode($specimenVolume, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

            // Zend_Debug::dump($resultResponse);die;
            $col = 4;
            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                $firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode("PT TEST NOT PERFORMED", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $col = 4 + count($refResult);
            } else if (count($resultResponse) > 0) {
                $firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode("Responded", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $col = 5;
                foreach ($resultResponse as $responseRow) {
                    $yrResult = '';
                    if (isset($responseRow['is_result_invalid']) && !empty($responseRow['is_result_invalid'])) {
                        $yrResult = (isset($responseRow['is_result_invalid']) && !empty($responseRow['is_result_invalid']) && !empty($responseRow['error_code'])) ? ucwords($responseRow['is_result_invalid']) . ', ' . $responseRow['error_code'] : ucwords($responseRow['is_result_invalid']);
                    } else {
                        $yrResult = round($responseRow['reported_viral_load'], 2) ?? null;
                    }
                    $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($yrResult, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    // we are also building the data required for other Assay Sheets
                    if ($attributes['vl_assay'] > 0) {
                        $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $yrResult;
                        if ($methodOfEvaluation == 'iso17043') {
                            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $responseRow['z_score'];
                            if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'pass') {
                                $grade = 'Acceptable';
                            } else if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'fail') {
                                $grade = 'Unacceptable';
                            } else if (isset($responseRow['calculated_score']) && $responseRow['calculated_score'] == 'warn') {
                                $grade = 'Warning';
                            } else {
                                $grade = 'N.A.';
                            }
                            $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $grade;
                        }
                    }
                }
            } else {
                $firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode("Not Responded", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $col = 4 + count($refResult);
            }


            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit($rowOverAll['shipment_score'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00") ? Pt_Commons_General::humanReadableDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00") ? Pt_Commons_General::humanReadableDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            // we are also building the data required for other Assay Sheets
            if ($attributes['vl_assay'] > 0) {
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $rowOverAll['shipment_score'];
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $receiptDate;
                $assayWiseData[$attributes['vl_assay']][$rowOverAll['unique_identifier']][] = $testDate;
            }


            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayName, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayExpirationDate, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($assayLotNumber, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($specimenVolume, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['participant_supervisor'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['user_comment'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

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
        //Zend_Debug::dump($assayWiseData);die;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $assayRes = $db->fetchAll($db->select()->from('r_vl_assay')->where("`status` like 'active'"));

        $countOfVlAssaySheet = 1;

        foreach ($assayRes as $assayRow) {
            $newsheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, '');
            $excel->addSheet($newsheet, $countOfVlAssaySheet);

            $newsheet->getDefaultRowDimension()->setRowHeight(15);

            $vlCalculation = [];
            $vlQuery = $db->select()->from(array('vlCal' => 'reference_vl_calculation'), array('mean', 'no_of_responses', 'median', 'low_limit', 'high_limit', 'sd', 'cv'))
                ->join(array('refVl' => 'reference_result_vl'), 'refVl.shipment_id=vlCal.shipment_id and vlCal.sample_id=refVl.sample_id', array('refVl.sample_label', 'refVl.mandatory'))
                ->join(array('sp' => 'shipment_participant_map'), 'vlCal.shipment_id=sp.shipment_id', array())
                ->join(array('res' => 'response_result_vl'), 'res.shipment_map_id = sp.map_id and res.sample_id = refVl.sample_id', array(
                    'NumberPassed' => new Zend_Db_Expr("SUM(CASE WHEN calculated_score = 'pass' OR calculated_score = 'warn' THEN 1 ELSE 0 END)"),
                ))

                ->where("vlCal.shipment_id=?", $shipmentId)
                ->where("vlCal.vl_assay=?", $assayRow['id'])
                ->where("refVl.control!=1")
                ->where('sp.attributes->>"$.vl_assay" = ' . $assayRow['id'])
                ->where("sp.is_excluded not like 'yes' OR sp.is_excluded like '' OR sp.is_excluded is null")
                ->where("sp.final_result = 1 OR sp.final_result = 2")
                ->group('refVl.sample_id');
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
                $vlQuery = $vlQuery
                    ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array('pmm.dm_id'))
                    ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
            }
            $vlCalRes = $db->fetchAll($vlQuery);
            if ($assayRow['id'] == 6) {
                $cQuery = $db->select()
                    ->from(array('sp' => 'shipment_participant_map'), array('sp.map_id', 'sp.attributes'))
                    ->where("sp.is_excluded not like 'yes'")
                    ->where('sp.attributes->>"$.vl_assay" = 6')
                    ->where('sp.shipment_id = ? ', $shipmentId);
                $authNameSpace = new Zend_Session_Namespace('datamanagers');
                if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants)) {
                    $vlQuery = $vlQuery
                        ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=sp.participant_id', array('pmm.dm_id'))
                        ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
                }
                $cResult = $db->fetchAll($cQuery);

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
                $vlCalculation[$assayRow['id']]['participant-count'] = $vlCalRes[0]['no_of_responses'];
                // $labResult[$vlCalRes[0]['no_of_responses']];
                if ($assayRow['id'] == 6) {
                    $vlCalculation[$assayRow['id']]['otherAssayName'] = $otherAssayCounter;
                }
            }
            $sample = [];
            $assayNameTxt = "";
            foreach ($vlCalculation as $vlCal) {
                $row = 10;
                if (isset($vlCal['participant-count']) && $vlCal['participant-count'] < 18 || $vlCal['vlAssay'] == "Other") {
                    $t = 0;

                    foreach ($vlCal as $k => $val) {

                        if (isset($val['median'])) {

                            $sample[$k]['response']       += $val['no_of_responses'];
                            $sample[$k]['median']         = $val['median'];
                            $sample[$k]['lowLimit']       = $val['low_limit'];
                            $sample[$k]['highLimit']      = $val['high_limit'];
                            $sample[$k]['sd']             = $val['sd'];
                            $sample[$k]['NumberPassed']   += !empty($val['NumberPassed']) ? $val['NumberPassed'] : 0;
                            $sample[$t]['label']          = $val['sample_label'];
                            $t++;
                        }
                    }
                    // $responseTxt = $val['no_of_responses'];
                    if ($vlCal['vlAssay'] == "Other") {
                        foreach ($vlCal['otherAssayName'] as $otherAssayName => $otherAssayCount) {
                            $assayNameTxt .= 'Other - ' . $otherAssayName . '(n=' . $otherAssayCount . '), ';
                        }
                    } else {
                        $assayNameTxt .= $vlCal['vlAssay'] . '(n=' . $vlCal[0]['no_of_responses'] . '), ';
                    }
                } else {
                    $newsheet->mergeCells('A10:H10');
                    $newsheet->getCellByColumnAndRow(1, 10)->setValueExplicit(html_entity_decode('Platform/Assay Name: ' . $vlCal['vlAssay'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 11)->setValueExplicit(html_entity_decode('Specimen ID', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(2, 11)->setValueExplicit(html_entity_decode('Number Of Participants', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(3, 11)->setValueExplicit(html_entity_decode('Assigned Value (log10 copies/mL)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(4, 11)->setValueExplicit(html_entity_decode('Lower limit (Q1)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(5, 11)->setValueExplicit(html_entity_decode('Upper limit (Q3)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(6, 11)->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->mergeCells('G11:H11');
                    $newsheet->getCellByColumnAndRow(7, 11)->setValueExplicit(html_entity_decode('Participants with Passing Results (|z| <3.0)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $newsheet->getStyleByColumnAndRow(1, 10, null, null)->applyFromArray($boldStyleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 11, null, null)->applyFromArray($borderStyle, true);
                    $newsheet->getStyleByColumnAndRow(2, 11, null, null)->applyFromArray($borderStyle, true);
                    $newsheet->getStyleByColumnAndRow(3, 11, null, null)->applyFromArray($borderStyle, true);
                    $newsheet->getStyleByColumnAndRow(4, 11, null, null)->applyFromArray($borderStyle, true);
                    $newsheet->getStyleByColumnAndRow(5, 11, null, null)->applyFromArray($borderStyle, true);
                    $newsheet->getStyleByColumnAndRow(6, 11, null, null)->applyFromArray($borderStyle, true);
                    $newsheet->getStyleByColumnAndRow(7, 11, null, null)->applyFromArray($borderStyle, true);
                    $row = 12;
                    foreach ($vlCal as $key => $val) {
                        $col = 1;
                        if (isset($val['median'])) {
                            $score = round((($val['NumberPassed'] / $val['no_of_responses']) * 100));
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode($val['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode($val['no_of_responses'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode(number_format(round($val['median'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode(number_format(round($val['low_limit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode(number_format(round($val['high_limit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode(number_format(round($val['sd'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode($val['NumberPassed'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $col++;
                            $newsheet->getCellByColumnAndRow($col, $row)->setValueExplicit(html_entity_decode($score . '%', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getStyleByColumnAndRow($col, $row, null, null)->applyFromArray($vlBorderStyle, true);
                            $row++;
                        }
                    }
                    // $assayName[] = $vlCal['vlAssay'];
                }
            }
            $row = (isset($row) && $row > 0) ? $row : 10;
            if (isset($sample) && count($sample) > 0) {
                $newsheet->mergeCells('A' . $row . ':H' . $row);
                $newsheet->getCellByColumnAndRow(1, $row)->setValueExplicit(html_entity_decode('Platform/Assay Name: VL platforms with < 18 participants', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getCellByColumnAndRow(1, ($row + 1))->setValueExplicit(html_entity_decode('Specimen ID', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getCellByColumnAndRow(2, ($row + 1))->setValueExplicit(html_entity_decode('Number Of Participants', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getCellByColumnAndRow(3, ($row + 1))->setValueExplicit(html_entity_decode('Assigned Value (log10 copies/mL)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getCellByColumnAndRow(4, ($row + 1))->setValueExplicit(html_entity_decode('Lower limit (Q1)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getCellByColumnAndRow(5, ($row + 1))->setValueExplicit(html_entity_decode('Upper limit (Q3)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getCellByColumnAndRow(6, ($row + 1))->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->mergeCells('G' . ($row + 1) . ':H' . ($row + 1));
                $newsheet->getCellByColumnAndRow(7, ($row + 1))->setValueExplicit(html_entity_decode('Participants with Passing Results (|z| <3.0)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                $newsheet->getStyleByColumnAndRow(1, $row, null, null)->applyFromArray($boldStyleArray, true);
                $newsheet->getStyleByColumnAndRow(1, ($row + 1), null, null)->applyFromArray($borderStyle, true);
                $newsheet->getStyleByColumnAndRow(2, ($row + 1), null, null)->applyFromArray($borderStyle, true);
                $newsheet->getStyleByColumnAndRow(3, ($row + 1), null, null)->applyFromArray($borderStyle, true);
                $newsheet->getStyleByColumnAndRow(4, ($row + 1), null, null)->applyFromArray($borderStyle, true);
                $newsheet->getStyleByColumnAndRow(5, ($row + 1), null, null)->applyFromArray($borderStyle, true);
                $newsheet->getStyleByColumnAndRow(6, ($row + 1), null, null)->applyFromArray($borderStyle, true);
                $newsheet->getStyleByColumnAndRow(7, ($row + 1), null, null)->applyFromArray($borderStyle, true);

                $row++;
                foreach ($sample as $point => $label) {
                    $col = 1;
                    $score = round((($label['NumberPassed'] / $label['response']) * 100));

                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode($label['label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode($label['response'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode(number_format(round($label['median'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode(number_format(round($label['lowLimit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode(number_format(round($label['highLimit'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode(number_format(round($label['sd'], 2), 2, '.', ''), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode($label['NumberPassed'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
                    $col++;
                    $newsheet->getCellByColumnAndRow($col, ($row + 1))->setValueExplicit(html_entity_decode($score . '%', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($col, ($row + 1), null, null)->applyFromArray($vlBorderStyle, true);
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
                $newsheet->getCellByColumnAndRow($i + 1, $startAt)->setValueExplicit(html_entity_decode($colName, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $newsheet->getStyleByColumnAndRow($i + 1, $startAt, null, null)->applyFromArray($borderStyle, true);
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
                    $newsheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('System Generated', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Q1', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode('Q3', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode('IQR', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode('Quartile Low', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 7)->setValueExplicit(html_entity_decode('Quartile High', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 8)->setValueExplicit(html_entity_decode('Mean', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 9)->setValueExplicit(html_entity_decode('SD', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 10)->setValueExplicit(html_entity_decode('CV', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 11)->setValueExplicit(html_entity_decode('Low Limit', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 12)->setValueExplicit(html_entity_decode('High Limit', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $newsheet->getStyleByColumnAndRow(1, 1, null, null)->applyFromArray($boldStyleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 2, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 3, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 4, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 5, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 6, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 7, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 8, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 9, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 10, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 11, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 12, null, null)->applyFromArray($styleArray, true);

                    $k = 1;
                    $manual = [];
                    foreach ($refVlCalci as $calculation) {
                        $newsheet->getCellByColumnAndRow($k + 1, 2)->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 3)->setValueExplicit(html_entity_decode(round($calculation['q1'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 4)->setValueExplicit(html_entity_decode(round($calculation['q3'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 5)->setValueExplicit(html_entity_decode(round($calculation['iqr'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 6)->setValueExplicit(html_entity_decode(round($calculation['quartile_low'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 7)->setValueExplicit(html_entity_decode(round($calculation['quartile_high'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 8)->setValueExplicit(html_entity_decode(round($calculation['mean'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 9)->setValueExplicit(html_entity_decode(round($calculation['sd'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 10)->setValueExplicit(html_entity_decode(round($calculation['cv'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 11)->setValueExplicit(html_entity_decode(round($calculation['low_limit'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 12)->setValueExplicit(html_entity_decode(round($calculation['high_limit'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                        $newsheet->getStyleByColumnAndRow($k + 1, 2, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 3, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 4, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 5, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 6, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 7, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 8, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 9, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 10, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 11, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 12, null, null)->applyFromArray($vlBorderStyle, true);
                        if ($calculation['manual_mean'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_sd'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_low_limit'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_high_limit'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_cv'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_q1'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_q3'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_iqr'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_quartile_low'] != 0) {
                            $manual[] = 'yes';
                        } elseif ($calculation['manual_quartile_high'] != 0) {
                            $manual[] = 'yes';
                        }
                        $k++;
                    }
                    if (count($manual) > 0) {
                        $newsheet->mergeCells('A15:F15');
                        $newsheet->getCellByColumnAndRow(1, 15)->setValueExplicit(html_entity_decode('Manual Generated', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 16)->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 17)->setValueExplicit(html_entity_decode('Manual Q1', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 18)->setValueExplicit(html_entity_decode('Manual Q3', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 19)->setValueExplicit(html_entity_decode('Manual IQR', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 20)->setValueExplicit(html_entity_decode('Manual Quartile Low', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 21)->setValueExplicit(html_entity_decode('Manual Quartile High', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 22)->setValueExplicit(html_entity_decode('Manual Mean', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 23)->setValueExplicit(html_entity_decode('Manual SD', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 24)->setValueExplicit(html_entity_decode('Manual CV', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 25)->setValueExplicit(html_entity_decode('Manual Low Limit', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow(1, 26)->setValueExplicit(html_entity_decode('Manual High Limit', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                        $newsheet->getStyleByColumnAndRow(1, 15, null, null)->applyFromArray($boldStyleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 16, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 17, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 18, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 19, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 20, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 21, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 22, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 23, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 24, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 25, null, null)->applyFromArray($styleArray, true);
                        $newsheet->getStyleByColumnAndRow(1, 26, null, null)->applyFromArray($styleArray, true);
                        $k = 1;
                        foreach ($refVlCalci as $calculation) {
                            $newsheet->getCellByColumnAndRow($k + 1, 16)->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 17)->setValueExplicit(html_entity_decode(round($calculation['manual_q1'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 18)->setValueExplicit(html_entity_decode(round($calculation['manual_q3'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 19)->setValueExplicit(html_entity_decode(round($calculation['manual_iqr'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 20)->setValueExplicit(html_entity_decode(round($calculation['manual_quartile_low'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 21)->setValueExplicit(html_entity_decode(round($calculation['manual_quartile_high'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 22)->setValueExplicit(html_entity_decode(round($calculation['manual_mean'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 23)->setValueExplicit(html_entity_decode(round($calculation['manual_sd'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 24)->setValueExplicit(html_entity_decode(round($calculation['manual_cv'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 25)->setValueExplicit(html_entity_decode(round($calculation['manual_low_limit'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                            $newsheet->getCellByColumnAndRow($k + 1, 26)->setValueExplicit(html_entity_decode(round($calculation['manual_high_limit'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                            $newsheet->getStyleByColumnAndRow($k + 1, 16, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 17, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 18, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 19, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 20, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 21, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 22, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 23, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 24, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 25, null, null)->applyFromArray($vlBorderStyle, true);
                            $newsheet->getStyleByColumnAndRow($k + 1, 26, null, null)->applyFromArray($vlBorderStyle, true);

                            $k++;
                        }
                    }
                } else if ($methodOfEvaluation == 'iso17043') {
                    $newsheet->mergeCells('A1:F1');
                    $newsheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('System Generated', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode('Sample', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode('Median', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode('Upper Limit (Q3)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode('Lower Limit (Q1)', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode('Robust SD', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 7)->setValueExplicit(html_entity_decode('Standard Uncertainty', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getCellByColumnAndRow(1, 8)->setValueExplicit(html_entity_decode('Is Uncertainty Acceptable?', ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $newsheet->getStyleByColumnAndRow(1, 1, null, null)->applyFromArray($boldStyleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 2, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 3, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 4, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 5, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 6, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 7, null, null)->applyFromArray($styleArray, true);
                    $newsheet->getStyleByColumnAndRow(1, 8, null, null)->applyFromArray($styleArray, true);

                    $k = 1;
                    $manual = [];
                    foreach ($refVlCalci as $calculation) {
                        $newsheet->getCellByColumnAndRow($k + 1, 2)->setValueExplicit(html_entity_decode($calculation['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 3)->setValueExplicit(html_entity_decode(round($calculation['median'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 4)->setValueExplicit(html_entity_decode(round($calculation['q3'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 5)->setValueExplicit(html_entity_decode(round($calculation['q1'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 6)->setValueExplicit(html_entity_decode(round($calculation['sd'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 7)->setValueExplicit(html_entity_decode(round($calculation['standard_uncertainty'], 4), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $newsheet->getCellByColumnAndRow($k + 1, 8)->setValueExplicit(html_entity_decode($calculation['is_uncertainty_acceptable'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);


                        $newsheet->getStyleByColumnAndRow($k + 1, 2, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 3, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 4, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 5, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 6, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 7, null, null)->applyFromArray($vlBorderStyle, true);
                        $newsheet->getStyleByColumnAndRow($k + 1, 8, null, null)->applyFromArray($vlBorderStyle, true);


                        $k++;
                    }
                }
            }
            //

            $assayData = isset($assayWiseData[$assayRow['id']]) ? $assayWiseData[$assayRow['id']] : array();
            //var_dump($assayData);die;
            $newsheet->setTitle(strtoupper($assayRow['short_name']), true);
            $row = $startAt; // $row 1-$startAt already occupied

            foreach ($assayData as $assayKey => $assayRow) {
                $row++;
                $noOfCols = count($assayRow);
                for ($c = 0; $c < $noOfCols; $c++) {
                    $newsheet->getCellByColumnAndRow($c + 1, $row)->setValueExplicit(html_entity_decode($assayRow[$c], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $newsheet->getStyleByColumnAndRow($c + 1, $row, null, null)->applyFromArray($vlBorderStyle, true);
                }
            }

            $countOfVlAssaySheet++;
        }

        $excel->setActiveSheetIndex(0);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . rand() . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }
}
