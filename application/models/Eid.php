<?php

error_reporting(E_ALL ^ E_NOTICE);

class Application_Model_Eid
{

    public function __construct()
    {
    }

    public function evaluate($shipmentResult, $shipmentId)
    {
        $counter = 0;
        $maxScore = 0;

        $passingScore = 100;

        $scoreHolder = [];
        $finalResult = null;
        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($shipmentResult as $shipment) {


            $shipment['is_excluded'] = 'no'; // setting it as no by default. It will become 'yes' if some condition matches.

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date']);
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {

                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = new DateTime('1970-01-01');
            }

            $lastDate = new DateTime($shipment['lastdate_response']);

            $results = $schemeService->getEidSamples($shipmentId, $shipment['participant_id']);
            $totalScore = 0;
            $maxScore = 0;
            $failureReason = [];
            $mandatoryResult = "";
            $scoreResult = "";

            if ($createdOn > $lastDate) {
                $failureReason[] = array(
                    'warning' => "Response was submitted after the last response date."
                );
                $shipment['is_excluded'] = 'yes';
                $failureReason = array('warning' => "Response was submitted after the last response date.");
                $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }

            foreach ($results as $result) {
                // matching reported and reference results
                if (isset($result['reported_result']) && $result['reported_result'] != null) {
                    if ($result['reference_result'] == $result['reported_result']) {
                        if (0 == $result['control']) {
                            $totalScore += $result['sample_score'];
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

                // checking if mandatory fields were entered and were entered right
                //if ($result['mandatory'] == 1) {
                //    if ((!isset($result['reported_result']) || $result['reported_result'] == "" || $result['reported_result'] == null)) {
                //        $mandatoryResult = 'Fail';
                //        $failureReason[]['warning'] = "Mandatory Control/Sample <strong>" . $result['sample_label'] . "</strong> was not reported";
                //    } else if (($result['reference_result'] != $result['reported_result'])) {
                //        $mandatoryResult = 'Fail';
                //        $failureReason[]['warning'] = "Mandatory Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                //    }
                //}
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
                $failureReason[] = array('warning' => 'Excluded from Evaluation');
                $finalResult = 3;
                $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
            } else {
                $shipment['is_excluded'] = 'no';


                // checking if total score >= passing score
                if ($totalScore >= $passingScore) {
                    $scoreResult = 'Pass';
                } else {
                    $scoreResult = 'Fail';
                    $failureReason[]['warning'] = "Participant did not meet the score criteria (Participant Score - <strong>$totalScore</strong> and Required Score - <strong>$passingScore</strong>)";
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


                $fRes = $db->fetchCol($db->select()->from('r_results', array('result_name'))->where('result_id = ' . $finalResult));

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
            //$counter++;
            $counter++;
        }
        $db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);

        //Zend_Debug::dump($shipmentResult);die;

        return $shipmentResult;
    }

    public function generateDbsEidExcelReport($shipmentId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();


        $styleArray = array(
            'font' => array(
                'bold' => true,
            ),
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
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
        $patientResponseColor = array(
            'fill' => array(
                'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => array('rgb' => '18bc9c')
            )
        );
        $referenceColor = array(
            'fill' => array(
                'type' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => array('rgb' => 'F0E68C')
            )
        );

        $query = $db->select()->from('shipment')
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);


        $refQuery = $db->select()->from(array('refRes' => 'reference_result_eid'))
            ->where("refRes.shipment_id = ?", $shipmentId);
        $refResult = $db->fetchAll($refQuery);

        $firstSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'EID PT Results');
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

        /* $firstSheet->mergeCells('G1:G2');
        $firstSheet->getCell('G1')->setValue(html_entity_decode("Sample Rehydration Date", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('G1:G2')->applyFromArray($borderStyle, true); */

        /*  $firstSheet->mergeCells('H1:H2');
        $firstSheet->getCell('H1')->setValue(html_entity_decode("Extraction", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('H1:H2')->applyFromArray($borderStyle, true); */

        $firstSheet->mergeCells('G1:G2');
        $firstSheet->getCell('G1')->setValue(html_entity_decode("Assay", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('G1:G2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('H1:H2');
        $firstSheet->getCell('H1')->setValue(html_entity_decode("Date Received", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('H1:H2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('I1:I2');
        $firstSheet->getCell('I1')->setValue(html_entity_decode("Date Tested", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('I1:I2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('J1:J2');
        $firstSheet->getCell('J1')->setValue(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('J1:J2')->applyFromArray($borderStyle, true);

        $firstSheet->mergeCells('K1:K2');
        $firstSheet->getCell('K1')->setValue(html_entity_decode("Final Score", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle('K1:K2')->applyFromArray($borderStyle, true);

        $firstSheet->getDefaultRowDimension()->setRowHeight(15);

        $colNameCount = 11;
        $cellName1 = $firstSheet->getCellByColumnAndRow($colNameCount + 1, '1')->getColumn();

        foreach ($refResult as $refRow) {
            $firstSheet->getCellByColumnAndRow($colNameCount + 1, 2)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount + 1, 2, null, null)->applyFromArray($borderStyle, true);
            $colNameCount++;
        }

        $cellName2 = $firstSheet->getCellByColumnAndRow($colNameCount, '1')->getColumn();
        $firstSheet->mergeCells($cellName1 . '1:' . $cellName2 . '1');
        $firstSheet->getCell($cellName1 . '1')->setValue(html_entity_decode("PARTICIPANT RESPONSE", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '1')->applyFromArray($borderStyle, true);
        $firstSheet->getStyle($cellName1 . '1:' . $cellName2 . '2')->applyFromArray($patientResponseColor, true);

        $cellName3 = $firstSheet->getCellByColumnAndRow($colNameCount + 1, '1')->getColumn();
        $colNumberforReference = $colNameCount + 1;
        foreach ($refResult as $refRow) {
            $firstSheet->getCellByColumnAndRow($colNameCount + 1, 2)->setValueExplicit(html_entity_decode($refRow['sample_label'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($colNameCount + 1, 2, null, null)->applyFromArray($borderStyle, true);
            $colNameCount++;
        }
        $cellName4 = $firstSheet->getCellByColumnAndRow($colNameCount, '1')->getColumn();
        $firstSheet->mergeCells($cellName3 . '1:' . $cellName4 . '1');
        $firstSheet->getCell($cellName3 . '1')->setValue(html_entity_decode("REFERENCE RESULTS", ENT_QUOTES, 'UTF-8'));
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '1')->applyFromArray($borderStyle, true);
        $firstSheet->getStyle($cellName3 . '1:' . $cellName4 . '2')->applyFromArray($referenceColor, true);


        $firstSheet->setTitle('EID PT Results', true);

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
        $extractionAssayList = $schemeService->getEidExtractionAssay();
        $detectionAssayList = $schemeService->getEidDetectionAssay();

        //Zend_Debug::dump($extractionAssayList);die;

        foreach ($resultOverAll as $rowOverAll) {
            //Zend_Debug::dump($rowOverAll);
            $row++;

            $queryResponse = $db->select()->from(array('res' => 'response_result_eid'))
                ->joinLeft(array('pr' => 'r_possibleresult'), "res.reported_result=pr.id")
                ->where("res.shipment_map_id = ?", $rowOverAll['map_id']);
            $resultResponse = $db->fetchAll($queryResponse);

            $attributes = json_decode($rowOverAll['attributes'], true);
            $extraction = (array_key_exists($attributes['extraction_assay'], $extractionAssayList)) ? $extractionAssayList[$attributes['extraction_assay']] : "";
            // $detection = (array_key_exists($attributes['detection_assay'], $detectionAssayList)) ? $detectionAssayList[$attributes['detection_assay']] : "";
            // $sampleRehydrationDate = (isset($attributes['sample_rehydration_date'])) ? Pt_Commons_General::humanReadableDateFormat($attributes['sample_rehydration_date']) : "";


            $firstSheet->getCellByColumnAndRow(1, $row)->setValueExplicit(html_entity_decode($rowOverAll['unique_identifier'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(2, $row)->setValueExplicit(html_entity_decode($rowOverAll['first_name'] . " " . $rowOverAll['last_name'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(3, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['institute_name']), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(4, $row)->setValueExplicit(html_entity_decode(ucwords($rowOverAll['department_name']), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(5, $row)->setValueExplicit(html_entity_decode($rowOverAll['region'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow(6, $row)->setValueExplicit(html_entity_decode($rowOverAll['site_type'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // $firstSheet->getCellByColumnAndRow(7, $row)->setValueExplicit(html_entity_decode($sampleRehydrationDate, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $col = 7;

            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($extraction, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            // $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($detection, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            $receiptDate = ($rowOverAll['shipment_receipt_date'] != "" && $rowOverAll['shipment_receipt_date'] != "0000-00-00" && $rowOverAll['shipment_receipt_date'] != "1970-01-01") ? Pt_Commons_General::humanReadableDateFormat($rowOverAll['shipment_receipt_date']) : "";
            $testDate = ($rowOverAll['shipment_test_date'] != "" && $rowOverAll['shipment_test_date'] != "0000-00-00" && $rowOverAll['shipment_test_date'] != "1970-01-01") ? Pt_Commons_General::humanReadableDateFormat($rowOverAll['shipment_test_date']) : "";
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($receiptDate, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($testDate, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if ($rowOverAll['is_pt_test_not_performed'] == 'yes') {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode("PT Test Not Performed", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else if ((isset($rowOverAll['shipment_test_date']) && $rowOverAll['shipment_test_date'] != "0000-00-00" && $rowOverAll['shipment_test_date'] != "")) {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode("Responded", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            } else {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode("Not Responded", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }

            $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($rowOverAll['shipment_score'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

            foreach ($resultResponse as $responseRow) {
                $firstSheet->getCellByColumnAndRow($col++, $row)->setValueExplicit(html_entity_decode($responseRow['response'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }

        $queryReference = $db->select()->from(array('res' => 'reference_result_eid'))
            ->joinLeft(array('pr' => 'r_possibleresult'), "res.reference_result=pr.id")
            ->where("res.shipment_id = ?", $shipmentId);
        $referenceresult = $db->fetchAll($queryReference);
        $nRow = 3;
        for ($i = 3; $i < $row; $i++) {
            $col = $colNumberforReference;
            foreach ($referenceresult as $referenceRow) {
                $firstSheet->getCellByColumnAndRow($col++, $nRow)->setValueExplicit(html_entity_decode($referenceRow['response'], ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $nRow++;
        }

        foreach (range('A', 'Z') as $columnID) {
            $firstSheet->getColumnDimension($columnID, true)
                ->setAutoSize(true);
        }

        $excel->setActiveSheetIndex(0);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
        $filename = $result['shipment_code'] . '-' . date('d-M-Y-H-i-s') . rand() . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }
}
