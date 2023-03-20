<?php

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;



class Application_Model_Tb
{

    private $db = null;

    public function __construct()
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
    }

    public function evaluate($shipmentResult, $shipmentId)
    {
        $counter = 0;
        $maxScore = 0;
        $finalResult = null;
        $passingScore = 100;

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

            $attributes = json_decode($shipment['attributes'], true);

            $lastDate = new DateTime($shipment['lastdate_response']);

            $results = [];

            $results = $this->getTbSamplesForParticipant($shipmentId, $shipment['participant_id']);

            $totalScore = 0;
            $calculatedScore = 0;
            $maxScore = 0;
            $failureReason = array();
            $mandatoryResult = "";
            $scoreResult = "";
            if ($createdOn >= $lastDate) {
                $failureReason[] = array(
                    'warning' => "Response was submitted after the last response date."
                );
                $shipment['is_excluded'] = 'yes';
                $failureReason = array('warning' => "Response was submitted after the last response date.");
                $db->update(
                    'shipment_participant_map',
                    array('failure_reason' => json_encode($failureReason)),
                    "map_id = " . $shipment['map_id']
                );
            }
            foreach ($results as $result) {

                if (isset($result['drug_resistance_test']) && !empty($result['drug_resistance_test']) && $result['drug_resistance_test'] != "yes") {

                    // matching reported and reference results without Rif
                    if (isset($result['mtb_detected']) && $result['mtb_detected'] != null) {
                        if ($result['mtb_detected'] == $result['refMtbDetected']) {
                            if (0 == $result['control']) {
                                $totalScore += $result['sample_score'];
                                $calculatedScore = $result['sample_score'];
                            }
                        } else {
                            if ($result['sample_score'] > 0) {
                                $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                            }
                        }
                    } else {
                        if ($result['sample_score'] > 0) {
                            $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                        }
                    }
                } else {

                    // matching reported and reference results with rif
                    if (
                        isset($result['mtb_detected']) &&
                        $result['mtb_detected'] != null &&
                        isset($result['rif_resistance']) &&
                        $result['rif_resistance'] != null
                    ) {
                        if (
                            $result['mtb_detected'] == $result['refMtbDetected'] &&
                            $result['rif_resistance'] == 'indeterminate'  &&
                            0 == $result['control']
                        ) {
                            $totalScore += ($result['sample_score'] * 0.5);
                            $calculatedScore = ($result['sample_score'] * 0.5);
                        } elseif (
                            in_array($result['mtb_detected'], ['invalid', 'error']) &&
                            0 == $result['control']
                        ) {
                            $totalScore += ($result['sample_score'] * 0.25);
                            $calculatedScore = ($result['sample_score'] * 0.25);
                        } elseif (
                            $result['mtb_detected'] == $result['refMtbDetected'] &&
                            $result['rif_resistance'] == $result['refRifResistance']  &&
                            0 == $result['control']
                        ) {
                            $totalScore += $result['sample_score'];
                            $calculatedScore = $result['sample_score'];
                        } else {
                            $calculatedScore = 0;
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

                $db->update(
                    'response_result_tb',
                    array('calculated_score' => $calculatedScore),
                    "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']
                );
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


                $fRes = $db->fetchCol($db->select()
                    ->from('r_results', array('result_name'))
                    ->where('result_id = ' . $finalResult));

                $shipmentResult[$counter]['display_result'] = $fRes[0];
                $shipmentResult[$counter]['failure_reason'] = $failureReason = json_encode($failureReason);
            }
            /* Manual result override changes */
            if (isset($shipment['manual_override']) && $shipment['manual_override'] == 'yes') {
                $sql = $db->select()
                    ->from('shipment_participant_map')
                    ->where("map_id = ?", $shipment['map_id']);
                $shipmentOverall = $db->fetchRow($sql);
                if (!empty($shipmentOverall)) {
                    $shipmentResult[$counter]['shipment_score'] = $shipmentOverall['shipment_score'];
                    $shipmentResult[$counter]['documentation_score'] = $shipmentOverall['documentation_score'];
                    if (!isset($shipmentOverall['final_result']) || $shipmentOverall['final_result'] == "") {
                        $shipmentOverall['final_result'] = 2;
                    }
                    $fRes = $db->fetchCol($db->select()
                        ->from('r_results', array('result_name'))
                        ->where('result_id =  ?', $shipmentOverall['final_result']));
                    $shipmentResult[$counter]['display_result'] = $fRes[0];
                    $nofOfRowsUpdated = $db->update(
                        'shipment_participant_map',
                        array(
                            'shipment_score' => $shipmentOverall['shipment_score'],
                            'documentation_score' => $shipmentOverall['documentation_score'],
                            'final_result' => $shipmentOverall['final_result']
                        ),
                        "map_id = " . $shipment['map_id']
                    );
                }
            } else {
                // let us update the total score in DB
                $db->update(
                    'shipment_participant_map',
                    array(
                        'shipment_score' => $totalScore,
                        'final_result' => $finalResult,
                        'failure_reason' => $failureReason
                    ),
                    "map_id = " . $shipment['map_id']
                );
            }
            $counter++;
        }

        $db->update('shipment', array(
            'max_score' => $maxScore,
            'status' => 'evaluated'
        ), "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }

    public function getTbSamplesForParticipant($sId, $pId, $type)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(
                array('ref' => 'reference_result_tb'),
                array(
                    'sample_id',
                    'sample_label',
                    'refMtbDetected' => 'mtb_detected',
                    'refRifResistance' => 'rif_resistance',
                    'control',
                    'mandatory',
                    'sample_score',
                    'request_attributes'
                )
            )
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id')
            ->joinLeft(
                array('res' => 'response_result_tb'),
                'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id',
                array(
                    'mtb_detected',
                    'rif_resistance',
                    'probe_d',
                    'probe_c',
                    'probe_e',
                    'probe_b',
                    'spc',
                    'probe_a',
                    'is1081_is6110',
                    'rpo_b1',
                    'rpo_b2',
                    'rpo_b2',
                    'rpo_b3',
                    'rpo_b4',
                    'test_date',
                    'tester_name',
                    'error_code',
                    'responseDate' => 'res.created_on',
                    'response_attributes'
                )
            )
            ->joinLeft(array('rtb' => 'r_tb_assay'), 'spm.attributes->>"$.assay_name" =rtb.id')
            ->where("spm.shipment_id = ?", $sId)
            // ->where("spm.participant_id = ?", $pId)
            ->order(array('ref.sample_id'));
        if(!empty($pId)){
            $sql = $sql->where("spm.participant_id = ?", $pId);
        }
        if(isset($type) && $type == "shipment"){
            $sql = $sql->group("ref.sample_id");
        }
        // die($sql);
        return ($db->fetchAll($sql));
    }

    public function getAllTbAssays()
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchAllTbAssay();
    }

    public function getTbAssayName($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->getTbAssayName($assayId);
    }

    public function getTbAssayDrugResistanceStatus($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchTbAssayDrugResistanceStatus($assayId);
    }

    public function generateTbExcelReport($shipmentId)
    {
        $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $borderStyle = array(
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );

        $query = $db->select()->from('shipment', array('shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples'))
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        if ($result['scheme_type'] == 'tb') {

            $refQuery = $db->select()->from(array('refRes' => 'reference_result_tb'))
                ->where("refRes.shipment_id = ?", $shipmentId);
            $refResult = $db->fetchAll($refQuery);
        }


        //<------------ Participant List Details Start -----

        $headings = array(
            'Participant Code',
            'Participant Name',
            'Institute Name',
            'Department',
            'Address',
            'Province',
            'District',
            'City',
            'Facility Telephone',
            'Email'
        );

        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 0);
        $sheet->setTitle('Participant List', true);

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', 'spm.participant_id', 'spm.attributes', 'spm.shipment_test_date', 'spm.shipment_receipt_date', 'spm.shipment_test_report_date', 'spm.supervisor_approval', 'spm.participant_supervisor', 'spm.shipment_score', 'spm.documentation_score', 'spm.user_comment'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('spm.map_id'));

        $shipmentResult = $db->fetchAll($sql);
        $colNo = 0;
        $currentRow = 1;
        //$sheet->getCellByColumnAndRow(0, 1)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), $type);
        //$sheet->getStyleByColumnAndRow(0,1)->getFont()->setBold(true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) .  $currentRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->applyFromArray($borderStyle, true);
            $colNo++;
        }

        if (isset($shipmentResult) && !empty($shipmentResult)) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                if ($result['scheme_type'] == 'tb') {
                    $resQuery = $db->select()->from(array('rrtb' => 'response_result_tb'))
                        ->where("rrtb.shipment_map_id = ?", $aRow['map_id']);
                    $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                }


                $sheet->getCell(Coordinate::stringFromColumnIndex(1) . $currentRow)->setValueExplicit(($aRow['unique_identifier']), DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(2) . $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(3) . $currentRow)->setValueExplicit($aRow['institute_name'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(4) . $currentRow)->setValueExplicit($aRow['department_name'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(5) . $currentRow)->setValueExplicit($aRow['address'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(6) . $currentRow)->setValueExplicit($aRow['province'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(7) . $currentRow)->setValueExplicit($aRow['district'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(8) . $currentRow)->setValueExplicit($aRow['city'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(9) . $currentRow)->setValueExplicit($aRow['mobile'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex(10) . $currentRow)->setValueExplicit(strtolower($aRow['email']), DataType::TYPE_STRING);

                for ($i = 1; $i <= 10; $i++) {
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($i) . $currentRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>

        //<-------- Second sheet start
        $reportHeadings = array(
            'Participant Code',
            'Participant Name',
            'Region',
            'Shipment Receipt Date',
            'Testing Date',
            'Assay Name',
            'Assay Lot',
            'Assay Expiration'
        );

        $reportHeadings = $this->addTbSampleNameInArray($shipmentId, $reportHeadings, true);

        array_push($reportHeadings, 'Comments');
        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Results Reported', true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        $finalResColoumn = $n - ($result['number_of_samples'] + 1);
        $c = 1;
        $endMergeCell = ($finalResColoumn + $result['number_of_samples']) - 1;

        $firstCellName = Coordinate::stringFromColumnIndex($finalResColoumn + 1);
        $secondCellName = Coordinate::stringFromColumnIndex($endMergeCell + 1);
        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
        $sheet->getStyle($firstCellName . "1")
            ->applyFromArray($borderStyle, true);
        $sheet->getStyle($secondCellName . "1")
            ->applyFromArray($borderStyle, true);

        foreach ($reportHeadings as $field => $value) {

            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->getFont()
                ->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $currentRow)
                ->applyFromArray($borderStyle, true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . "3")
                ->applyFromArray($borderStyle, true);

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("E2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("F2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("G2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("H2")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        //$cellName = $sheet->getCellByColumnAndRow($n + 1, 3)->getColumn();

        //<-------- Sheet three heading -------
        $sheetThree = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 2);
        $sheetThree->setTitle('Panel Score', true);
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addTbSampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) .  $sheetThreeRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $sheetThree->getStyle(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow)
                ->getFont()
                ->setBold(true);
            $sheetThree->getStyle(Coordinate::stringFromColumnIndex($sheetThreeColNo + 1) . $sheetThreeRow)
                ->applyFromArray($borderStyle, true);

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->

        $totalScoreSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 3);
        $totalScoreSheet->setTitle('Total Score', true);
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
        $totalScoreHeadings = array(
            'Participant Code',
            'Participant Name',
            'No. of Panels Correct (N=' . $result['number_of_samples'] . ')',
            'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)',
            'Documentation Score(100% Conv.)',
            'Documentation Score(10% Conv.)',
            'Total Score', 'Overall Performance'
        );

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)
                ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $totalScoreSheet->getStyle(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)
                ->getFont()
                ->setBold(true);

            $totalScoreSheet->getStyle(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)
                ->applyFromArray($borderStyle, true);
            $totalScoreSheet->getstyle(Coordinate::stringFromColumnIndex($totScoreSheetCol + 1) . $totScoreRow)
                ->getAlignment()
                ->setWrapText(true);
            $totScoreSheetCol++;
        }

        //---------- Document Score Sheet Heading (Sheet Four)------->
        $currentRow = 4;
        $sheetThreeRow = 2;
        $docScoreRow = 3;
        $totScoreRow = 2;
        if (isset($shipmentResult) && !empty($shipmentResult)) {

            foreach ($shipmentResult as $aRow) {
                $r = 1;
                $k = 1;
                $shipmentTestDate = "";
                $sheetThreeCol = 1;
                $totScoreCol = 1;

                $attributes = json_decode($aRow['attributes'], true);

                $colCellObj = $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow);
                $colCellObj->setValueExplicit(($aRow['unique_identifier']), DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit($aRow['region'], DataType::TYPE_STRING);
                if (
                    isset($aRow['shipment_receipt_date']) &&
                    trim($aRow['shipment_receipt_date']) != ""
                ) {
                    $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (
                    isset($aRow['shipment_test_date']) &&
                    trim($aRow['shipment_test_date']) != "" &&
                    trim($aRow['shipment_test_date']) != "0000-00-00"
                ) {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit($aRow['shipment_receipt_date'], DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit($shipmentTestDate, DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit((isset($attributes['assay_name']) && !empty($attributes['assay_name'])) ? $attributes['assay_name'] : '', DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit((isset($attributes['assay_lot_number']) && !empty($attributes['assay_lot_number'])) ? $attributes['assay_lot_number'] : '', DataType::TYPE_STRING);
                $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                    ->setValueExplicit((isset($attributes['expiry_date']) && !empty($attributes['expiry_date'])) ? $attributes['expiry_date'] : '', DataType::TYPE_STRING);

                $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++) . $sheetThreeRow)
                    ->setValueExplicit(($aRow['unique_identifier']), DataType::TYPE_STRING);
                $sheetThree->getCell(Coordinate::stringFromColumnIndex($sheetThreeCol++) . $sheetThreeRow)
                    ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], DataType::TYPE_STRING);


                if (isset($config->evaluation->tb->documentationScore) && $config->evaluation->tb->documentationScore > 0) {
                    $documentScore = (($aRow['documentation_score'] / $config->evaluation->tb->documentationScore) * 100);
                } else {
                    $documentScore = 0;
                }

                //<------------ Total score sheet ------------

                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit(($aRow['unique_identifier']), DataType::TYPE_STRING);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], DataType::TYPE_STRING);

                //------------ Total score sheet ------------>
                // Zend_Debug::dump($aRow);die;
                if (count($aRow['response']) > 0) {
                    $countCorrectResult = 0;
                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {

                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['mtb_detected']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rif_resistance']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['spc']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['probe_d']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['probe_c']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['probe_e']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['probe_b']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['probe_a']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['is1081_is6110']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rpo_b1']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rpo_b2']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rpo_b3']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rpo_b4']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['test_date']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['tester_name']), DataType::TYPE_STRING);
                        $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['error_code']), DataType::TYPE_STRING);
                    }
                    for ($f = 0; $f < $aRow['number_of_samples']; $f++) {
                        $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['response'][$f]['calculated_score'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        if (isset($aRow['response'][$f]['calculated_score']) && $aRow['response'][$f]['calculated_score'] == 20 && $aRow['response'][$f]['sample_id'] == $refResult[$f]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }
                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);


                    $sheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($aRow['user_comment'], DataType::TYPE_STRING);


                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($countCorrectResult, DataType::TYPE_STRING);
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($totPer, DataType::TYPE_STRING);
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($totPer * 0.9, DataType::TYPE_STRING);
                }
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit($documentScore, DataType::TYPE_STRING);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit($aRow['documentation_score'], DataType::TYPE_STRING);
                $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                    ->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), DataType::TYPE_STRING);

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCell(Coordinate::stringFromColumnIndex($i + 1) . $sheetThreeRow)
                        ->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                /* for ($i = 0; $i < $docScoreHeadingsCount; $i++) {
                    $cellName = $docScoreSheet->getCellByColumnAndRow($i, $docScoreRow)->getColumn();
                    $docScoreSheet->getStyle($cellName . $docScoreRow)->applyFromArray($borderStyle);
                } */

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $totalScoreSheet->getStyle(Coordinate::stringFromColumnIndex($i + 1) . $totScoreRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;

                $sheetThreeRow++;
                $docScoreRow++;
                $totScoreRow++;
            }
        }

        //----------- Second Sheet End----->

        $excel->setActiveSheetIndex(0);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
        $filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }
    public function getDataForSummaryPDF($shipmentId)
    {
        $summaryPDFData = [];
        $sql = $this->db->select()
            ->from(array('ref' => 'reference_result_tb'))
            ->where("ref.shipment_id = ?", $shipmentId)
            ->group('ref.sample_label');

        $sqlRes = $this->db->fetchAll($sql);

        $summaryPDFData['referenceResult'] = $sqlRes;

        $sQuery = "SELECT count(*) AS 'enrolled',

				SUM(CASE WHEN (`spm`.response_status is not null AND `spm`.response_status like 'responded') THEN 1 ELSE 0 END)
					AS 'participated',
				SUM(CASE WHEN (`spm`.shipment_score is not null AND `spm`.shipment_score = 100) THEN 1 ELSE 0 END)
					AS 'sitesScoring100',
				SUM(CASE WHEN (`spm`.attributes is not null AND `spm`.attributes->>'$.assay_name' = 1) THEN 1 ELSE 0 END)
					AS 'mtb_rif',
				SUM(CASE WHEN (`spm`.attributes is not null AND `spm`.attributes->>'$.assay_name' = 2) THEN 1 ELSE 0 END)
					AS 'mtb_rif_ultra'
				
				FROM shipment_participant_map as `spm`
				WHERE `spm`.shipment_id = $shipmentId";
        $sQueryRes = $this->db->fetchRow($sQuery);
        $summaryPDFData['summaryResult'] = $sQueryRes;


        $tQuery = "SELECT `ref`.sample_label,
				count(`spm`.map_id) as `numberOfSites`,
				`rta`.id as `tb_assay_id`,
				`rta`.name as `tb_assay`,
				SUM(CASE WHEN (`res`.mtb_detected is not null AND `res`.mtb_detected like 'detected') THEN 1 ELSE 0 END)
					AS `mtbDetected`,
				SUM(CASE WHEN (`res`.mtb_detected is not null AND `res`.mtb_detected like 'not-detected') THEN 1 ELSE 0 END)
					AS `mtbNotDetected`,
				SUM(CASE WHEN (`res`.mtb_detected is not null AND `res`.mtb_detected like 'invalid') THEN 1 ELSE 0 END)
					AS `mtbInvalid`,
				SUM(CASE WHEN (`res`.rif_resistance is not null AND `res`.rif_resistance like 'detected') THEN 1 ELSE 0 END)
					AS `rifDetected`,
				SUM(CASE WHEN (`res`.rif_resistance is not null AND `res`.rif_resistance like 'not-detected') THEN 1 ELSE 0 END)
					AS `rifNotDetected`,
				SUM(CASE WHEN (`res`.rif_resistance is not null AND `res`.rif_resistance like 'indeterminate') THEN 1 ELSE 0 END)
					AS `rifIndeterminate`
				FROM `response_result_tb` as `res`
				INNER JOIN `reference_result_tb` as `ref` ON `ref`.sample_id = `res`.sample_id
				INNER JOIN `shipment` as `s` ON `ref`.shipment_id = `s`.shipment_id
				INNER JOIN `shipment_participant_map` as `spm`
					ON (`spm`.map_id = `res`.shipment_map_id)
				INNER JOIN `r_tb_assay` as `rta` ON `rta`.id = `spm`.attributes->>'$.assay_name'
				WHERE `s`.shipment_id = $shipmentId
				GROUP BY `ref`.sample_label, tb_assay_id
				ORDER BY tb_assay_id, `ref`.sample_label";

        $summaryPDFData['aggregateCounts'] = $this->db->fetchAll($tQuery);


        $mtbRifSummaryQuery = $this->db->select()
            ->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('ref' => 'reference_result_tb'),
                'ref.shipment_id = spm.shipment_id',
                array(
                    'sample_label' => 'ref.sample_label',
                    'ref_expected_ct' => new Zend_Db_Expr("CASE WHEN ref.mtb_detected like 'detected' THEN ref.probe_a ELSE 0 END")
                )
            )
            ->joinLeft(
                array('res' => 'response_result_tb'),
                'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id',
                array(
                    'average_ct' => new Zend_Db_Expr('SUM(CASE WHEN IFNULL(`res`.`calculated_score`, \'pass\') NOT IN (\'fail\', \'noresult\') THEN IFNULL(CASE WHEN `res`.`probe_a` = \'\' THEN 0 ELSE `res`.`probe_a` END, 0) ELSE 0 END) / SUM(CASE WHEN IFNULL(CASE WHEN `res`.`probe_a` = \'\' THEN 0 ELSE `res`.`probe_a` END, 0) = 0 OR IFNULL(`res`.`calculated_score`, \'pass\') IN (\'fail\', \'noresult\') THEN 0 ELSE 1 END)')
                )
            )
            ->joinLeft(
                array('rta' => 'r_tb_assay'),
                'rta.id = spm.attributes->>"$.assay_name"'
            )
            ->where("spm.shipment_id = ?", $shipmentId)
            ->where("substring(spm.evaluation_status,4,1) != '0'")
            ->where("spm.is_excluded = 'no'")
            ->where("IFNULL(spm.is_pt_test_not_performed, 'no') = 'no'")
            ->where("rta.id = 1")
            ->group("ref.sample_id")
            ->order("ref.sample_id");

        $summaryPDFData['mtbRifReportSummary'] = $this->db->fetchAll($mtbRifSummaryQuery);
        $mtbRifUltraSummaryQuery = $this->db->select()->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('ref' => 'reference_result_tb'),
                'ref.shipment_id = spm.shipment_id',
                array(
                    'sample_label' => 'ref.sample_label',
                    'ref_expected_ct' => new Zend_Db_Expr("CASE WHEN ref.mtb_detected like 'detected' THEN LEAST(ref.rpo_b1, ref.rpo_b2, ref.rpo_b3, ref.rpo_b4) ELSE 0 END")
                )
            )
            ->joinLeft(
                array('res' => 'response_result_tb'),
                'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id',
                array('average_ct' => new Zend_Db_Expr('SUM(CASE WHEN IFNULL(`res`.`calculated_score`, \'pass\') NOT IN (\'fail\', \'noresult\') THEN  LEAST(IFNULL(`res`.`rpo_b1`, 0), IFNULL(`res`.`rpo_b2`, 0), IFNULL(`res`.`rpo_b3`, 0), IFNULL(`res`.`rpo_b4`, 0)) ELSE 0 END) / SUM(CASE WHEN LEAST(IFNULL(CASE WHEN `res`.`rpo_b1` = \'\' THEN 0 ELSE `res`.`rpo_b1` END, 0), IFNULL(CASE WHEN `res`.`rpo_b2` = \'\' THEN 0 ELSE `res`.`rpo_b2` END, 0), IFNULL(CASE WHEN `res`.`spc` = \'\' THEN 0 ELSE `res`.`spc` END, 0), IFNULL(CASE WHEN `res`.`rpo_b4` = \'\' THEN 0 ELSE `res`.`rpo_b4` END, 0)) = 0 OR IFNULL(`res`.`calculated_score`, \'pass\') IN (\'fail\', \'noresult\') THEN 0 ELSE 1 END)')
                )
            )
            ->joinLeft(
                array('rta' => 'r_tb_assay'),
                'rta.id = spm.attributes->>"$.assay_name"'
            )
            ->where("spm.shipment_id = ?", $shipmentId)
            ->where("substring(spm.evaluation_status,4,1) != '0'")
            ->where("spm.is_excluded = 'no'")
            ->where("IFNULL(spm.is_pt_test_not_performed, 'no') = 'no'")
            ->where("rta.id = 2")
            ->group("ref.sample_id")
            ->order("ref.sample_id");


        $summaryPDFData['mtbRifUltraReportSummary'] = $this->db->fetchAll($mtbRifUltraSummaryQuery);

        return $summaryPDFData;
    }

    public function addTbSampleNameInArray($shipmentId, $headings, $heading = false)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $query = $db->select()->from('reference_result_tb', array('sample_label'))
            ->where("shipment_id = ?", $shipmentId)->order("sample_id");
        $result = $db->fetchAll($query);
        foreach ($result as $res) {
            if ($heading) {
                $loop = array(
                    '(' . $res['sample_label'] . ') - MTBC',
                    '(' . $res['sample_label'] . ') - Rif Resistance',
                    '(' . $res['sample_label'] . ') - SPC',
                    '(' . $res['sample_label'] . ') - Probe D',
                    '(' . $res['sample_label'] . ') - Probe C',
                    '(' . $res['sample_label'] . ') - Probe E',
                    '(' . $res['sample_label'] . ') - Probe B',
                    '(' . $res['sample_label'] . ') - Probe A',
                    '(' . $res['sample_label'] . ') - IS1081-IS6110',
                    '(' . $res['sample_label'] . ') - rpoB1',
                    '(' . $res['sample_label'] . ') - rpoB2',
                    '(' . $res['sample_label'] . ') - rpoB3',
                    '(' . $res['sample_label'] . ') - rpoB4',
                    '(' . $res['sample_label'] . ') - Test Date',
                    '(' . $res['sample_label'] . ') - Tester Name',
                    '(' . $res['sample_label'] . ') - Error Code'
                );
                $headings = array_merge($headings, $loop);
            } else {

                array_push($headings, $res['sample_label']);
            }
        }
        return $headings;
    }
    public function generateFormPDF($shipmentId, $participantId = null)
    {
        $query = $this->db->select()
            ->from(array('s' => 'shipment'))
            ->join(array('ref' => 'reference_result_tb'), 's.shipment_id=ref.shipment_id')
            ->where("shipment_id = ?", $shipmentId);
        if ($participantId != null) {
            $query = $query
                ->join(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id')
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id')
                ->where("participant_id = ?", $participantId);
        }

        $result = $this->db->fetchAll($query);

        // now we will use this result to create an Excel file and then generate the PDF
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load(FILES_PATH . "/tb-excel-form.xlsx");
        $sheet = $reader->getSheet(0);

        $sheet->getCell('C5')->setValue($result[0]['first_name'] . " " . $result[0]['last_name']);
        $sheet->getCell('C7')->setValue($result[0]['unique_identifier']);

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($reader, 'Mpdf');
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $shipmentId . "-" . $result[0]['unique_identifier'] . "-tb-form.pdf");
    }
}
