<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Application_Service_QuantitativeCalculations as QuantitativeCalculations;


class Application_Model_GenericTest
{
    private $db = null;

    public function __construct()
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    public function evaluate($shipmentResult, $shipmentId, $reEvaluate = false)
    {
        $counter = 0;
        $maxScore = 0;
        $finalResult = null;
        $passingScore = 100;

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        foreach ($shipmentResult as $shipment) {
            $recommendedTestkits = $this->getRecommededGenericTestkits($shipment['scheme_type']);
            if (isset($shipment['attributes']) && !empty($shipment['attributes'])) {
                $attributes = Zend_Json_Decoder::decode($shipment['attributes'], true);
            } else {
                $attributes = null;
            }
            $testKitDb = new Application_Model_DbTable_Testkitnames();
            if (isset($attributes['kit_name']) && !empty($attributes['kit_name'])) {
                $updatedTestKitId = $testKitDb->getTestKitIdByName($attributes['kit_name']);
            } else {
                $updatedTestKitId = false;
            }

            $jsonConfig = Zend_Json_Decoder::decode($shipment['user_test_config'], true);
            $passingScore = $jsonConfig['passingScore'] ?? 100;

            $shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date'] ?? '');
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = new DateTime('1970-01-01');
            }

            $lastDate = new DateTime($shipment['lastdate_response']);
            $results = $this->getSamplesForParticipant($shipmentId, $shipment['participant_id']);
            $totalScore = 0;
            $calculatedScore = 0;
            $maxScore = 0;
            $failureReason = [];
            $mandatoryResult = "";
            $scoreResult = "";
            if (!empty($createdOn) && $createdOn <= $lastDate) {
                if (isset($jsonConfig['testType']) && !empty($jsonConfig['testType']) && $jsonConfig['testType'] == 'quantitative') {
                    $zScore = null;
                    if ($reEvaluate) {
                        // when re-evaluating we will set the reset the range
                        $this->setQuantRange($shipmentId);
                        $quantRange = $this->getQuantRange($shipmentId);
                    } else {
                        $quantRange = $this->getQuantRange($shipmentId);
                    }

                    foreach ($results as $result) {
                        if ($result['control'] == 1) {
                            continue;
                        }
                        $calcResult = "";

                        // matching reported and low/high limits
                        if (!empty($result['is_result_invalid']) && in_array($result['is_result_invalid'], ['invalid', 'error'])) {
                            error_log('error');
                            if ($result['sample_score'] > 0) {
                                $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                            }
                            $calcResult = "fail";
                            $zScore = null;
                        } elseif (!empty($result['reported_result'])) {
                            if (isset($quantRange[$result['sample_id']])) {
                                $zScore = 0;
                                $sd = (float) $quantRange[$result['sample_id']]['sd'];
                                $median = (float) $quantRange[$result['sample_id']]['median'];
                                if ($sd > 0) {
                                    $zScore = (float) (($result['reported_result'] - $median) / $sd);
                                }

                                if (0 == $sd) {
                                    // If SD is 0 and there is a detectable result reported, then it is treated as fail
                                    if (0 == $result['reported_result']) {
                                        $totalScore += $result['sample_score'];
                                        $calcResult = "pass";
                                    } elseif ($result['reported_result'] > 0) {
                                        //failed
                                        if ($result['sample_score'] > 0) {
                                            error_log('empty sample score');
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
                                            error_log('empty sample score 2');
                                            $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                        }
                                        $calcResult = "fail";
                                    }
                                }
                            } else {
                                if ($result['sample_score'] > 0) {
                                    error_log('empty sample score else part');
                                    $failureReason[]['warning'] = "Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                                $calcResult = "fail";
                            }
                        }
                        /* Zend_Debug::dump($quantRange);
                        Zend_Debug::dump($failureReason); */
                        $maxScore += $result['sample_score'];

                        $db->update('response_result_generic_test', array('z_score' => $zScore, 'calculated_score' => $calcResult), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                    }
                } else {
                    foreach ($results as $result) {
                        if (isset($result['reference_result']) && !empty($result['reference_result']) && isset($result['reported_result']) && !empty($result['reported_result'])) {
                            if ($result['reference_result'] == $result['reported_result']) {
                                if (0 == $result['control']) {
                                    $totalScore += $result['sample_score'];
                                    $calculatedScore = $result['sample_score'];
                                }
                            } else {
                                if ($result['sample_score'] > 0) {
                                    $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                            }
                        }
                        if (0 == $result['control']) {
                            $maxScore += $result['sample_score'];
                        }

                        $db->update('response_result_generic_test', ['calculated_score' => $calculatedScore], "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                    }
                }
                if (isset($updatedTestKitId) && !empty($updatedTestKitId['TestKitName_ID']) && isset($recommendedTestkits) && !empty($recommendedTestkits)) {
                    if (!in_array($updatedTestKitId['TestKitName_ID'], $recommendedTestkits)) {
                        $totalScore = 0;
                        $failureReason[] = [
                            'warning' => "Testing is not performed with country approved test kit.",
                            'correctiveAction' => "Please test " . $shipment['scheme_type'] . " sample as per National HIV Testing algorithm. Review and refer to SOP for testing"
                        ];
                    }
                }

                if ($maxScore > 100) {
                    $maxScore = 100;
                }
                if ($maxScore > 0 && $totalScore > 0) {
                    $totalScore = ($totalScore / $maxScore) * 100;
                }
                // if we are excluding this result, then let us not give pass/fail
                if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                    $finalResult = '';
                    $totalScore = 0;
                    $responseScore = 0;
                    $shipmentResult[$counter]['shipment_score'] = $responseScore;
                    $shipmentResult[$counter]['documentation_score'] = 0;
                    $shipmentResult[$counter]['display_result'] = '';
                    $shipmentResult[$counter]['is_followup'] = 'yes';
                    $shipmentResult[$counter]['is_excluded'] = 'yes';
                    $failureReason[] = ['warning' => 'Excluded from Evaluation'];
                    $finalResult = 3;
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                } else {
                    $shipment['is_excluded'] = 'no';

                    // checking if total score >= passing score
                    if ($totalScore >= $passingScore) {
                        $scoreResult = 'Pass';
                    } else {
                        $scoreResult = 'Fail';
                        $failureReason[] = [
                            'warning' => "Participant did not meet the score criteria (Participant Score is <strong>" . round($totalScore) . "</strong> and Required Score is <strong>" . round($passingScore) . "</strong>)",
                            'correctiveAction' => "Review all testing procedures prior to performing client testing and contact your supervisor for improvement"
                        ];
                    }

                    // if any of the results have failed, then the final result is fail
                    if ($scoreResult == 'Fail' || $mandatoryResult == 'Fail') {
                        $finalResult = 2;
                    } else {
                        $finalResult = 1;
                    }
                    $shipmentResult[$counter]['shipment_score'] = $totalScore = round($totalScore, 2);
                    $shipmentResult[$counter]['max_score'] = 100; //$maxScore;
                    $shipmentResult[$counter]['final_result'] = $finalResult;


                    $fRes = $db->fetchCol($db->select()->from('r_results', ['result_name'])->where("result_id = $finalResult"));

                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
                }
                /* Manual result override changes */
                if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
                    $sql = $db->select()->from('shipment_participant_map')->where("map_id = ?", $shipment['map_id']);
                    $shipmentOverall = $db->fetchRow($sql);
                    if (!empty($shipmentOverall)) {
                        $shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
                        $shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
                        if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
                            $shipmentOverall['final_result'] = 2;
                        }
                        $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $shipmentOverall['final_result']));
                        $shipmentResult[$counter]['display_result'] = $fRes[0];
                        $nofOfRowsUpdated = $db->update('shipment_participant_map', array('shipment_score' => $shipmentOverall['shipment_score'], 'documentation_score' => $shipmentOverall['documentation_score'], 'final_result' => $shipmentOverall['final_result']), "map_id = " . $shipment['map_id']);
                    }
                } else {
                    // let us update the total score in DB
                    $db->update('shipment_participant_map', array('shipment_score' => $totalScore, 'final_result' => $finalResult, 'failure_reason' => $failureReason), "map_id = " . $shipment['map_id']);
                }
            } else {
                $failureReason[] = [
                    'warning' => "Response was submitted after the last response date."
                ];
                $shipment['is_excluded'] = 'yes';
                $failureReason = ['warning' => "Response was submitted after the last response date."];
                $db->update('shipment_participant_map', ['failure_reason' => json_encode($failureReason)], "map_id = " . $shipment['map_id']);
            }

            $counter++;
        }
        $db->update('shipment', ['max_score' => $maxScore, 'status' => 'evaluated'], "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }

    public function getSamplesForParticipant($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['ref' => 'reference_result_generic_test'], ['shipment_id', 'sample_id', 'sample_label', 'reference_result', 'control', 'mandatory', 'sample_score'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id')
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['res' => 'response_result_generic_test'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['shipment_map_id', 'result', 'repeat_result', 'reported_result', 'is_result_invalid', 'error_code', 'additional_detail', 'comments'])
            ->where("sp.shipment_id = $sId AND sp.participant_id = $pId");
        return $db->fetchAll($sql);
    }

    public function generateGenericTestExcelReport($shipmentId, $schemeType = 'generic-test')
    {
        $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $excel = new Spreadsheet();
        //$sheet = $excel->getActiveSheet();
        $schemeService = new Application_Service_Schemes();
        $otherTestsPossibleResults =  $schemeService->getPossibleResults($schemeType);
        $otherTestPossibleResults = [];
        foreach ($otherTestsPossibleResults as $row) {
            $otherTestPossibleResults[$row['result_code']] = $row['response'];
        }
        $common = new Application_Service_Common();
        $feedbackOption = $common->getConfig('feed_back_option');
        $borderStyle = [
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'outline' => [
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ]
        ];

        $query = $db->select()->from('shipment', array('shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples', 'shipment_attributes'))
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        $refQuery = $db->select()->from(array('reference_result_generic_test'), array('sample_label', 'sample_id', 'sample_score', 'reference_result'))
            ->where("shipment_id = ?", $shipmentId);
        $refResult = $db->fetchAll($refQuery);


        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('sl.user_test_config'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.user_comment', 'sp.final_result'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(['sp.map_id']);
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
        }

        $shipmentResult = $db->fetchAll($sql);


        //<------------ Participant List Details Start -----

        $participantListHeadings = ['Participant Code', 'Participant Name', 'Institute Name', 'Department', 'Country', 'Address', 'Province', 'District', 'City', 'Telephone', 'Email'];

        $participantListSheet = new Worksheet($excel, 'Participant List');
        $excel->addSheet($participantListSheet, 0);
        $participantListSheet->setTitle('Participant List', true);

        $participantListSheet->getDefaultColumnDimension()->setWidth(24);
        $participantListSheet->getDefaultRowDimension()->setRowHeight(18);

        $participantListSheetData = [];
        if (isset($shipmentResult) && count($shipmentResult) > 0) {

            foreach ($shipmentResult as $key => $aRow) {
                $resQuery = $db->select()->from('response_result_generic_test')
                    ->where("shipment_map_id = ?", $aRow['map_id']);
                $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);

                $participantRow = [];
                $participantRow[] = $aRow['unique_identifier'];
                $participantRow[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
                $participantRow[] = $aRow['institute_name'];
                $participantRow[] = $aRow['department_name'];
                $participantRow[] = $aRow['iso_name'];
                $participantRow[] = $aRow['address'];
                $participantRow[] = $aRow['province'];
                $participantRow[] = $aRow['district'];
                $participantRow[] = $aRow['city'];
                $participantRow[] = $aRow['mobile'];
                $participantRow[] = $aRow['email'];

                $participantListSheetData[] = $participantRow;
                unset($participantRow);

                $shipmentCode = $aRow['shipment_code'];
            }
        }

        $participantListSheet->fromArray($participantListHeadings, null, "A1");
        $participantListSheet->getStyle('A1:' . $participantListSheet->getHighestColumn() . '1')->applyFromArray($borderStyle);
        $participantListSheet->fromArray($participantListSheetData, null, 'A2');

        //------------- Participant List Details End ------>

        //<-------- Second sheet start
        $reportHeadings = ['Participant Code', 'Participant Name', 'Region', 'Shipment Receipt Date', 'Testing Date'];
        $shipmentAttributes = Zend_Json_Decoder::decode($result['shipment_attributes'], true);
        // Zend_Debug::dump($shipmentAttributes);die;
        if (isset($shipmentAttributes['noOfTest']) && $shipmentAttributes['noOfTest'] == 2) {
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        } else {
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        }
        // For final Results
        $additionalDetails = false;
        $jsonConfig = Zend_Json_Decoder::decode($shipmentResult[0]['user_test_config'], true);
        $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        if (isset($jsonConfig['captureAdditionalDetails']) && !empty($jsonConfig['captureAdditionalDetails'])) {
            $additionalDetails = true;
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        }
        array_push($reportHeadings, 'Comments');
        $resultReportSheet = new Worksheet($excel, 'Results Reported');
        $excel->addSheet($resultReportSheet, 1);
        $resultReportSheet->setTitle('Results Reported', true);
        $resultReportSheet->getDefaultColumnDimension()->setWidth(24);
        $resultReportSheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        /* To get the first column for label */
        if ($additionalDetails) {
            $finalResColoumn = $n - (($result['number_of_samples'] * 2) + 1);
            $additionalColoumn = $n - ($result['number_of_samples'] + 1);
        } else {
            $finalResColoumn = $n - ($result['number_of_samples'] + 1);
        }

        $c = $additionRow = 1;
        /* To get the end colum cell */
        $endMergeCell = ($finalResColoumn + $result['number_of_samples']) - 1;
        if ($additionalDetails) {
            $endAdditionalMergeCell = ($additionalColoumn + $result['number_of_samples']) - 1;
        }
        /* Final Result Merge options */
        $firstCellName = $resultReportSheet->getCellByColumnAndRow($finalResColoumn + 1, 1)->getColumn();
        $secondCellName = $resultReportSheet->getCellByColumnAndRow($endMergeCell + 1, 1)->getColumn();
        if ($additionalDetails) {
            /* Additional Result Merge options */
            $additionalFirstCellName = $resultReportSheet->getCellByColumnAndRow($additionalColoumn + 1, 1)->getColumn();
            $additionalSecondCellName = $resultReportSheet->getCellByColumnAndRow($endAdditionalMergeCell + 1, 1)->getColumn();
        }
        /* Merge the final result lable cell */
        $resultReportSheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
        $resultReportSheet->getStyle($firstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $resultReportSheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle, true);
        $resultReportSheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle, true);
        if ($additionalDetails) {
            /* Merge the Additional lable cell */
            $resultReportSheet->mergeCells($additionalFirstCellName . "1:" . $additionalSecondCellName . "1");
            $resultReportSheet->getStyle($additionalFirstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            $resultReportSheet->getStyle($additionalFirstCellName . "1")->applyFromArray($borderStyle, true);
            $resultReportSheet->getStyle($additionalSecondCellName . "1")->applyFromArray($borderStyle, true);
        }

        foreach ($reportHeadings as $field => $value) {

            $resultReportSheet->getCellByColumnAndRow($colNo + 1, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $resultReportSheet->getStyleByColumnAndRow($colNo + 1, $currentRow, null, null)->getFont()->setBold(true);
            $cellName = $resultReportSheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
            $resultReportSheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);

            $cellName = $resultReportSheet->getCellByColumnAndRow($colNo + 1, 3)->getColumn();
            $resultReportSheet->getStyle($cellName . "3")->applyFromArray($borderStyle, true);
            if ($additionalDetails) {
                if ($colNo >= $additionalColoumn) {
                    if ($additionRow <= $result['number_of_samples']) {
                        $resultReportSheet->getCellByColumnAndRow($colNo + 1, 1)->setValueExplicit(html_entity_decode($jsonConfig['additionalDetailLabel'], ENT_QUOTES, 'UTF-8'));
                        $resultReportSheet->getStyleByColumnAndRow($colNo + 1, 1, null, null)->getFont()->setBold(true);
                        $cellName = $resultReportSheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
                        $resultReportSheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    }
                    $additionRow++;
                }
            }
            if ($colNo >= $finalResColoumn) {
                if ($c <= $result['number_of_samples']) {

                    $resultReportSheet->getCellByColumnAndRow($colNo + 1, 1)->setValueExplicit(html_entity_decode("Final Results", ENT_QUOTES, 'UTF-8'));
                    $resultReportSheet->getStyleByColumnAndRow($colNo + 1, 1, null, null)->getFont()->setBold(true);
                    $cellName = $resultReportSheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
                    $resultReportSheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $resultReportSheet->getCellByColumnAndRow($colNo + 1, 3)->setValueExplicit(html_entity_decode(str_replace("-", " ", ucwords($otherTestPossibleResults[$refResult[$l]['reference_result']])), ENT_QUOTES, 'UTF-8'));
                }
                $c++;
            }
            $resultReportSheet->getStyle($cellName . '3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $resultReportSheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $resultReportSheet->getStyle("A2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $resultReportSheet->getStyle("B2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $resultReportSheet->getStyle("C2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $resultReportSheet->getStyle("D2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $resultReportSheet->getStyle("E2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');


        //<-------- Sheet three heading -------
        $panelScoreSheet = new Worksheet($excel, 'Panel Score');
        $excel->addSheet($panelScoreSheet, 2);
        $panelScoreSheet->setTitle('Panel Score', true);
        $panelScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $panelScoreSheet->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $panelScoreSheet->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $panelScoreSheet->getStyleByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow, null, null)->getFont()->setBold(true);
            $cellName = $panelScoreSheet->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->getColumn();
            $panelScoreSheet->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $panelScoreSheet->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->getColumn();
                $panelScoreSheet->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->

        //<-------- Total Score Sheet Heading (Sheet Four)-------
        $totalScoreSheet = new Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 3);
        $totalScoreSheet->setTitle('Total Score', true);
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol + 1, $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol + 1, $totScoreRow, null, null)->getFont()->setBold(true);
            $cellName = $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol + 1, $totScoreRow)->getColumn();
            $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
            $totalScoreSheet->getStyleByColumnAndRow($totScoreSheetCol + 1, $totScoreRow, null, null)->getAlignment()->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->

        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && count($shipmentResult) > 0) {

            foreach ($shipmentResult as $aRow) {
                $r = 1;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 1;
                $totScoreCol = 1;
                $countCorrectResult = 0;

                $colCellObj = $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow);
                $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']));
                $cellName = $colCellObj->getColumn();
                $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
                // $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . ' ' . $aRow['dataManagerLastName']);
                $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['region']);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentReceiptDate);
                $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentTestDate);
                /* Panel score section */
                $panelScoreSheet->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $panelScoreSheet->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                $documentScore = (($aRow['documentation_score'] / 10) * 100);
                if ($config->evaluation->covid19->documentationScore > 0) {
                    $documentScore = (($aRow['documentation_score'] / $config->evaluation->covid19->documentationScore) * 100);
                }


                //<------------ Total score sheet ------------
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']));
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);
                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (count($aRow['response']) > 0) {
                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                        $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(str_replace("-", " ", ucwords($otherTestPossibleResults[$aRow['response'][$k]['result']])));
                    }
                    if (isset($shipmentAttributes['noOfTest']) && $shipmentAttributes['noOfTest'] == 2) {
                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(str_replace("-", " ", ucwords($otherTestPossibleResults[$aRow['response'][$k]['repeat_result']])));
                        }
                    }
                    for ($f = 0; $f < $aRow['number_of_samples']; $f++) {
                        $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(str_replace("-", " ", ucwords($otherTestPossibleResults[$aRow['response'][$f]['reported_result']])));

                        $panelScoreSheet->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['response'][$f]['calculated_score']);
                        if (isset($aRow['response'][$f]['calculated_score']) && $aRow['response'][$f]['calculated_score'] == 20 && $aRow['response'][$f]['sample_id'] == $refResult[$f]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }
                    if ($additionalDetails) {
                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['response'][$k]['additional_detail']);
                        }
                    }
                    $resultReportSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['user_comment']);

                    $panelScoreSheet->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult);

                    $totPer = round((($countCorrectResult / 5) * 100), 2);
                    if ($aRow['number_of_samples'] > 0) {
                        $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    }
                    $panelScoreSheet->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($countCorrectResult);
                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($totPer);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($totPer * 0.9));
                }
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($documentScore);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['documentation_score']);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']));
                $finalResultCell = ($aRow['final_result'] == 1) ? "Pass" : "Fail";
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($finalResultCell);


                $panelScoreSheet->getStyle('A1:' . $totalScoreSheet->getHighestColumn() . '1')->applyFromArray($borderStyle, true);
                $resultReportSheet->getStyle('A1:' . $totalScoreSheet->getHighestColumn() . '1')->applyFromArray($borderStyle, true);
                $totalScoreSheet->getStyle('A1:' . $totalScoreSheet->getHighestColumn() . '1')->applyFromArray($borderStyle, true);

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        //----------- Second Sheet End----->

        $excel->setActiveSheetIndex(0);

        $writer = IOFactory::createWriter($excel, 'Xlsx');
        $filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    public function addGenericTestSampleNameInArray($shipmentId, $headings)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_generic_test', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result = $db->fetchAll($query);
        foreach ($result as $res) {
            array_push($headings, $res['sample_label']);
        }
        return $headings;
    }

    public function getDataForSummaryPDF($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $pQuery = $db->select()->from(
            array('spm' => 'shipment_participant_map'),
            array(
                'spm.map_id',
                'spm.shipment_id',
                'spm.documentation_score',
                'participant_count' => new Zend_Db_Expr('count("participant_id")'),
                'reported_count' => new Zend_Db_Expr("SUM(response_status is not null AND response_status like 'responded')")
            )
        )
            ->joinLeft(
                array('res' => 'r_results'),
                'res.result_id=spm.final_result',
                array('result_name')
            )
            ->where("spm.shipment_id = ?", $shipmentId)
            ->group('spm.shipment_id');
        $totParticipantsRes = $db->fetchRow($pQuery);
        if ($totParticipantsRes != "") {
            $shipmentResult['participant_count'] = $totParticipantsRes['participant_count'];
        }

        $sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id', 'spm.shipment_id', 'spm.shipment_score', 'spm.documentation_score', 'spm.attributes'))
            //->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.first_name', 'p.last_name', 'p.status'))
            ->joinLeft(array('res' => 'r_results'), 'res.result_id=spm.final_result', array('result_name'))
            ->where("spm.shipment_id = ?", $shipmentId)
            //->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR IFNULL(spm.is_pt_test_not_performed, 'no') ='yes'")
            ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00'")
            //->where("IFNULL(spm.is_pt_test_not_performed, 'no') not like 'yes'")
            ->group('spm.map_id');

        $sQueryRes = $db->fetchAll($sQuery);
        //echo($sQuery);die;

        if (!empty($sQueryRes)) {
            $shipmentResult['summaryResult'][] = $sQueryRes;
        }

        $cQuery = $db->select()->from(array('refGenTest' => 'reference_result_generic_test'), array('refGenTest.sample_id', 'refGenTest.sample_label', 'refGenTest.reference_result', 'refGenTest.mandatory'))
            ->join(array('s' => 'shipment'), 's.shipment_id=refGenTest.shipment_id', array('s.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id', array('spm.map_id', 'spm.attributes', 'spm.shipment_score'))
            ->joinLeft(array('resGenTest' => 'response_result_generic_test'), 'resGenTest.shipment_map_id = spm.map_id and resGenTest.sample_id = refGenTest.sample_id', array('reported_result'))
            ->where('spm.shipment_id = ? ', $shipmentId)
            ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR IFNULL(spm.is_pt_test_not_performed, 'no') ='yes'")
            ->where("spm.is_excluded!='yes'")
            ->where("refGenTest.control = 0");
        // die($cQuery);
        $cResult = $db->fetchAll($cQuery);
        $correctResult = [];
        foreach ($cResult as $cVal) {
            //Formed correct result
            if (array_key_exists($cVal['sample_label'], $correctResult)) {
                if ($cVal['reported_result'] == $cVal['reference_result']) {
                    $correctResult[$cVal['sample_label']] += 1;
                }
            } else {
                $correctResult[$cVal['sample_label']] = [];
                if ($cVal['reported_result'] == $cVal['reference_result']) {
                    $correctResult[$cVal['sample_label']] = 1;
                } else {
                    $correctResult[$cVal['sample_label']] = 0;
                }
            }
        }


        $shipmentResult['correctRes'] = $correctResult;
        // Zend_Debug::dump($shipmentResult);die;


        foreach ($sQueryRes as $sVal) {
            $cQuery = $db->select()->from(array('refGenTest' => 'reference_result_generic_test'), array('refGenTest.sample_id', 'refGenTest.sample_label', 'refGenTest.reference_result', 'refGenTest.mandatory'))
                ->joinLeft(array('resGenTest' => 'response_result_generic_test'), 'resGenTest.sample_id = refGenTest.sample_id', array('reported_result'))
                ->where('refGenTest.shipment_id = ? ', $shipmentId)
                ->where("refGenTest.control = 0")
                ->where('resGenTest.shipment_map_id = ? ', $sVal['map_id']);

            $cResult = $db->fetchAll($cQuery);
        }

        return $shipmentResult;
    }

    public function getAllTestKitList($countryAdapted = false, $scheme = null)
    {
        $sql = $this->db->select()
            ->from(
                ['t' => 'r_testkitnames'],
                [
                    'TESTKITNAMEID' => 'TESTKITNAME_ID',
                    'TESTKITNAME' => 'TESTKIT_NAME',
                    'attributes'
                ]
            )
            ->joinLeft(['stm' => 'scheme_testkit_map'], 't.TestKitName_ID = stm.testkit_id', ['scheme_type', 'testkit_1', 'testkit_2', 'testkit_3'])
            ->order("TESTKITNAME ASC");
        if (isset($scheme) && !empty($scheme)) {
            $sql = $sql->where("scheme_type = '" . $scheme . "'");
        }
        if ($countryAdapted) {
            $sql = $sql->where('COUNTRYADAPTED = 1');
        }
        $stmt = $this->db->fetchAll($sql);

        return $stmt;
    }

    public function getRecommededGenericTestkits($testMode)
    {
        $sql = $this->db->select()->from(['generic_recommended_test_types']);

        if ($testMode != null) {
            $sql = $sql->where("scheme_id = '$testMode'");
        }
        $stmt = $this->db->fetchAll($sql);
        $retval = [];
        foreach ($stmt as $t) {
            $retval[] = $t['testkit'];
        }
        return $retval;
    }

    public function setQuantRange($shipmentId, $sdScalingFactor = 0.7413, $uncertaintyScalingFactor = 1.25, $uncertaintyThreshold = 0.3, $minimumRequiredSamples = 4)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();


        $beforeSetQuantRangeData = $db->fetchAll($db->select()->from('reference_generic_test_calculations', ['*'])
            ->where("shipment_id = $shipmentId"));
        $oldSetQuantRange = [];
        foreach ($beforeSetQuantRangeData as $beforeSetQuantRangeRow) {
            $oldSetQuantRange[$beforeSetQuantRangeRow['sample_id']] = $beforeSetQuantRangeRow;
        }

        $db->delete('reference_generic_test_calculations', "use_range IS NOT NULL and use_range not like 'manual' AND shipment_id=$shipmentId");

        $sql = $db->select()->from(['ref' => 'reference_result_generic_test'], ['shipment_id', 'sample_id'])
            ->join(['s' => 'shipment'], 's.shipment_id=ref.shipment_id', [])
            ->join(['sp' => 'shipment_participant_map'], 's.shipment_id=sp.shipment_id', ['participant_id'])
            ->joinLeft(['res' => 'response_result_generic_test'], 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', ['reported_result', 'z_score', 'is_result_invalid'])
            ->where('sp.shipment_id = ? ', $shipmentId)
            ->where('DATE(sp.shipment_test_report_date) <= s.lastdate_response')
            //->where("(sp.is_excluded LIKE 'yes') IS NOT TRUE")
            ->where("(sp.is_pt_test_not_performed LIKE 'yes') IS NOT TRUE");
        $response = $db->fetchAll($sql);

        $sampleWise = [];
        foreach ($response as $row) {
            $invalidValues = ['invalid', 'error'];

            if (!empty($row['is_result_invalid']) && in_array($row['is_result_invalid'], $invalidValues)) {
                $row['reported_result'] = null;
            }

            $sampleWise[$row['sample_id']][] = $row['reported_result'];
        }

        $responseCounter = [];
        foreach ($sampleWise as $sample => $reportedResult) {
            if (!empty($reportedResult)
                //  && (count($reportedResult) > $minimumRequiredSamples)
            ) {
                $responseCounter[$sample] = count($reportedResult);
                $inputArray = $reportedResult;

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

                sort($inputArray);
                $median = QuantitativeCalculations::calculateMedian($inputArray);
                $finalLow = $quartileLowLimit = $q1 = QuantitativeCalculations::calculateQuantile($inputArray, 0.25);
                $finalHigh = $quartileHighLimit = $q3 = QuantitativeCalculations::calculateQuantile($inputArray, 0.75);
                $iqr = $q3 - $q1;
                $sd = $sdScalingFactor * $iqr;
                if (!empty($inputArray)) {
                    $standardUncertainty = ($uncertaintyScalingFactor * $sd) / sqrt(count($inputArray));
                }
                if ($median == 0) {
                    $isUncertaintyAcceptable = 'NA';
                } elseif ($standardUncertainty < ($uncertaintyThreshold * $sd)) {
                    $isUncertaintyAcceptable = 'yes';
                } else {
                    $isUncertaintyAcceptable = 'no';
                }


                $data = [
                    'shipment_id' => $shipmentId,
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

                if (isset($oldSetQuantRange[$sample]) && !empty($oldSetQuantRange[$sample]) && $oldSetQuantRange[$sample]['use_range'] == 'manual') {
                    $data['manual_q1'] = $oldSetQuantRange[$sample]['manual_q1'] ?? null;
                    $data['manual_q3'] = $oldSetQuantRange[$sample]['manual_q3'] ?? null;
                    $data['manual_cv'] = $oldSetQuantRange[$sample]['manual_cv'] ?? null;
                    $data['manual_iqr'] = $oldSetQuantRange[$sample]['manual_iqr'] ?? null;
                    $data['manual_quartile_high'] = $oldSetQuantRange[$sample]['manual_quartile_high'] ?? null;
                    $data['manual_quartile_low'] = $oldSetQuantRange[$sample]['manual_quartile_low'] ?? null;
                    $data['manual_low_limit'] = $oldSetQuantRange[$sample]['manual_low_limit'] ?? null;
                    $data['manual_high_limit'] = $oldSetQuantRange[$sample]['manual_high_limit'] ?? null;
                    $data['manual_mean'] = $oldSetQuantRange[$sample]['manual_mean'] ?? null;
                    $data['manual_median'] = $oldSetQuantRange[$sample]['manual_median'] ?? null;
                    $data['manual_sd'] = $oldSetQuantRange[$sample]['manual_sd'] ?? null;
                    $data['manual_standard_uncertainty'] = $oldSetQuantRange[$sample]['manual_standard_uncertainty'] ?? null;
                    $data['manual_is_uncertainty_acceptable'] = $oldSetQuantRange[$sample]['manual_is_uncertainty_acceptable'] ?? null;
                    $data['updated_on'] = $oldSetQuantRange[$sample]['updated_on'] ?? null;
                    $data['use_range'] = $oldSetQuantRange[$sample]['use_range'] ?? 'calculated';
                }

                $db->delete('reference_generic_test_calculations', "sample_id=$sample AND shipment_id=$shipmentId");

                $db->insert('reference_generic_test_calculations', $data);
            } else {
                if (isset($oldSetQuantRange[$sample]) && !empty($oldSetQuantRange[$sample]) && $oldSetQuantRange[$sample]['use_range'] != 'manual') {
                    $db->delete('reference_generic_test_calculations', "shipment_id = $shipmentId");
                }
            }
        }
    }

    public function getQuantRange($shipmentId, $sampleId = null)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['calc' => 'reference_generic_test_calculations'])
            ->join(['ref' => 'reference_result_generic_test'], 'calc.sample_id = ref.sample_id', ['sample_label'])
            ->where('calc.shipment_id = ?', $shipmentId);

        if ($sampleId != null) {
            $sql = $sql->where('calc.sample_id = ?', $sampleId);
        }

        $res = $db->fetchAll($sql);
        $response = [];
        foreach ($res as $row) {

            $sampleId = $row['sample_id'];

            $response[$sampleId]['sample_id'] = $row['sample_id'];
            $response[$sampleId]['no_of_responses'] = $row['no_of_responses'];
            $response[$sampleId]['sample_label'] = $row['sample_label'];
            $response[$sampleId]['use_range'] = $row['use_range'] ?? 'calculated';

            if (!empty($row['use_range']) && $row['use_range'] == 'manual') {
                $response[$sampleId]['q1'] = $row['manual_q1'];
                $response[$sampleId]['q3'] = $row['manual_q3'];
                $response[$sampleId]['quartile_low'] = $row['manual_quartile_low'];
                $response[$sampleId]['quartile_high'] = $row['manual_quartile_high'];
                $response[$sampleId]['low'] = $row['manual_low_limit'];
                $response[$sampleId]['high'] = $row['manual_high_limit'];
                $response[$sampleId]['mean'] = $row['manual_mean'];
                $response[$sampleId]['median'] = $row['manual_median'];
                $response[$sampleId]['sd'] = $row['manual_sd'];
                $response[$sampleId]['standard_uncertainty'] = $row['manual_standard_uncertainty'];
                $response[$sampleId]['is_uncertainty_acceptable'] = $row['manual_is_uncertainty_acceptable'];
            } else {
                $response[$sampleId]['q1'] = $row['q1'];
                $response[$sampleId]['q3'] = $row['q3'];
                $response[$sampleId]['quartile_low'] = $row['quartile_low'];
                $response[$sampleId]['quartile_high'] = $row['quartile_high'];
                $response[$sampleId]['low'] = $row['low_limit'];
                $response[$sampleId]['high'] = $row['high_limit'];
                $response[$sampleId]['mean'] = $row['mean'];
                $response[$sampleId]['median'] = $row['median'];
                $response[$sampleId]['sd'] = $row['sd'];
                $response[$sampleId]['standard_uncertainty'] = $row['standard_uncertainty'];
                $response[$sampleId]['is_uncertainty_acceptable'] = $row['is_uncertainty_acceptable'];
            }
        }
        return $response;
    }
}
