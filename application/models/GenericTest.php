<?php


class Application_Model_GenericTest
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

            $lastDate = new DateTime($shipment['lastdate_response']);
            $results = $this->getSamplesForParticipant($shipmentId, $shipment['participant_id']);
            $totalScore = 0;
            $calculatedScore = 0;
            $maxScore = 0;
            $failureReason = [];
            $mandatoryResult = "";
            $scoreResult = "";
            if ($createdOn >= $lastDate) {
                $failureReason[] = array(
                    'warning' => "Response was submitted after the last response date."
                );
                $shipment['is_excluded'] = 'yes';
                $failureReason = array('warning' => "Response was submitted after the last response date.");
                $db->update('shipment_participant_map', array('failure_reason' => json_encode($failureReason)), "map_id = " . $shipment['map_id']);
            }
            foreach ($results as $result) {
                if (isset($result['reference_result']) && !empty($result['reference_result']) && isset($result['reported_result']) && !empty($result['reported_result'])) {

                    // matching reported and reference results without Rif
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

                $db->update('response_result_generic_test', array('calculated_score' => $calculatedScore), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
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
            $counter++;
        }

        $db->update('shipment', array('max_score' => $maxScore, 'status' => 'evaluated'), "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }

    public function getSamplesForParticipant($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(array('ref' => 'reference_result_generic_test'), array('shipment_id', 'sample_id', 'sample_label', 'reference_result', 'control', 'mandatory', 'sample_score'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_generic_test'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id', array('shipment_map_id', 'result', 'repeat_result', 'reported_result', 'additional_detail', 'comments'))
            ->where('sp.shipment_id = ' . $sId . ' AND sp.participant_id = ' . $pId . '');
        // die($sql);
        return $db->fetchAll($sql);
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

    public function generateGenericTestExcelReport($shipmentId)
    {
        $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        //$sheet = $excel->getActiveSheet();



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
            'alignment' => array(
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );

        $query = $db->select()->from('shipment', array('shipment_id', 'shipment_code', 'scheme_type', 'number_of_samples', 'shipment_attributes'))
            ->where("shipment_id = ?", $shipmentId);
        $result = $db->fetchRow($query);

        $refQuery = $db->select()->from(array('reference_result_generic_test'), array('sample_label', 'sample_id', 'sample_score', 'reference_result'))
            ->where("shipment_id = ?", $shipmentId);
        $refResult = $db->fetchAll($refQuery);
        
        $firstSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Instructions');
        $excel->addSheet($firstSheet, 0);
        $firstSheet->setTitle('Instructions', true);
        $firstSheetHeading = array('Tab Name', 'Description');
        $firstSheetColNo = 0;
        $firstSheetRow = 1;

        $firstSheetStyle = array(
            'alignment' => array(
                //'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            ),
            'borders' => array(
                'outline' => array(
                    'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ),
            )
        );

        foreach ($firstSheetHeading as $value) {
            $firstSheet->getCellByColumnAndRow($firstSheetColNo + 1, $firstSheetRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $firstSheet->getStyleByColumnAndRow($firstSheetColNo + 1, $firstSheetRow, null, null)->getFont()->setBold(true);
            $cellName = $firstSheet->getCellByColumnAndRow($firstSheetColNo + 1, $firstSheetRow)->getColumn();
            $firstSheet->getStyle($cellName . $firstSheetRow)->applyFromArray($firstSheetStyle, true);
            $firstSheetColNo++;
        }

        $firstSheet->getCellByColumnAndRow(1, 2)->setValueExplicit(html_entity_decode("Participant List", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 2)->setValueExplicit(html_entity_decode("Includes dropdown lists for the following: region, department, position, RT, ELISA, received logbook", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getDefaultRowDimension()->setRowHeight(10);
        $firstSheet->getColumnDimensionByColumn(0)->setWidth(20);
        $firstSheet->getDefaultRowDimension()->setRowHeight(70);
        $firstSheet->getColumnDimensionByColumn(1)->setWidth(100);

        $firstSheet->getCellByColumnAndRow(1, 3)->setValueExplicit(html_entity_decode("Results Reported", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 3)->setValueExplicit(html_entity_decode("This tab should include no commentary from PT Admin staff.  All fields should only reflect results or comments reported on the results form.  If no report was submitted, highlight site data cells in red.  Explanation of missing results should only be comments that the site made, not PT staff.  All dates should be formatted as DD/MM/YY.  Dropdown menu legend is as followed: negative (NEG), positive (POS), invalid (INV), indeterminate (IND), not entered or reported (NE), not tested (NT) and should be used according to the way the site reported it.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 4)->setValueExplicit(html_entity_decode("Panel Score", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 4)->setValueExplicit(html_entity_decode("This tab is automatically populated.  Panel score calculated 6/6.  If a panel member must be omitted from the calculation (ie, loss of sample, etc) you must revise the equation manually by changing the number 6 to 5,4,etc. accordingly. Example seen for Akai House Clinic.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 5)->setValueExplicit(html_entity_decode("Documentation Score", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 5)->setValueExplicit(html_entity_decode("The points breakdown for this tab are listed in the row above the sites for each column.  Data should be entered in manually by PT staff.  A site scores 1.5/3 if they used the wrong test kits got a 100% panel score.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 6)->setValueExplicit(html_entity_decode("Total Score", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 6)->setValueExplicit(html_entity_decode("Columns C-F are populated automatically.  Columns G, H and I must be selected from the dropdown menu for each site based on the criteria listed in the 'Decision Tree' tab.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 7)->setValueExplicit(html_entity_decode("Follow-up Calls", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 7)->setValueExplicit(html_entity_decode("Final comments or outcomes should be updated continuously with receipt dates included.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 8)->setValueExplicit(html_entity_decode("Dropdown Lists", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 8)->setValueExplicit(html_entity_decode("This tab contains all of the dropdown lists included in the rest of the database, any modifications should be performed with caution.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 9)->setValueExplicit(html_entity_decode("Decision Tree", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 9)->setValueExplicit(html_entity_decode("Lists all of the appropriate corrective actions and scoring critieria.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 10)->setValueExplicit(html_entity_decode("Feedback Report", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 10)->setValueExplicit(html_entity_decode("This tab is populated automatically and used to export data into the Feedback Reports generated in MS Word.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $firstSheet->getCellByColumnAndRow(1, 11)->setValueExplicit(html_entity_decode("Comments", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $firstSheet->getCellByColumnAndRow(2, 11)->setValueExplicit(html_entity_decode("This tab lists all of the more detailed comments that will be given to the sites during site visits and phone calls.", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);


        for ($counter = 1; $counter <= 11; $counter++) {
            $firstSheet->getStyleByColumnAndRow(2, $counter, null, null)->getAlignment()->setWrapText(true);
            $firstSheet->getStyle("A$counter")->applyFromArray($firstSheetStyle, true);
            $firstSheet->getStyle("B$counter")->applyFromArray($firstSheetStyle, true);
        }
        //<------------ Participant List Details Start -----

        $headings = array('Participant Code', 'Participant Name',  'Institute Name', 'Department', 'Address', 'Province', 'District', 'City', 'Facility Telephone', 'Email');

        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Participant List');
        $excel->addSheet($sheet, 1);
        $sheet->setTitle('Participant List', true);

        $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
            ->join(array('sp' => 'shipment_participant_map'), 'sp.shipment_id=s.shipment_id', array('sp.map_id', 'sp.participant_id', 'sp.attributes', 'sp.shipment_test_date', 'sp.shipment_receipt_date', 'sp.shipment_test_report_date', 'sp.supervisor_approval', 'sp.participant_supervisor', 'sp.shipment_score', 'sp.documentation_score', 'sp.user_comment', 'sp.final_result'))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
            ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
            ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
            ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
            ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
            ->where("s.shipment_id = ?", $shipmentId)
            ->group(array('sp.map_id'));
        //echo $sql;die;
        $shipmentResult = $db->fetchAll($sql);
        //die;
        $colNo = 0;
        $currentRow = 1;
        $type = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        foreach ($headings as $field => $value) {
            $sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo + 1, $currentRow, null, null)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
            $colNo++;
        }

        if (isset($shipmentResult) && count($shipmentResult) > 0) {
            $currentRow += 1;
            foreach ($shipmentResult as $key => $aRow) {
                $resQuery = $db->select()->from('response_result_generic_test')
                    ->where("shipment_map_id = ?", $aRow['map_id']);
                $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);


                $sheet->getCellByColumnAndRow(1, $currentRow)->setValueExplicit(ucwords($aRow['unique_identifier']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(2, $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(3, $currentRow)->setValueExplicit($aRow['institute_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(4, $currentRow)->setValueExplicit($aRow['department_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(5, $currentRow)->setValueExplicit($aRow['address'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(6, $currentRow)->setValueExplicit($aRow['province'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(7, $currentRow)->setValueExplicit($aRow['district'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(8, $currentRow)->setValueExplicit($aRow['city'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(9, $currentRow)->setValueExplicit($aRow['mobile'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCellByColumnAndRow(10, $currentRow)->setValueExplicit(strtolower($aRow['email']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                for ($i = 0; $i <= 8; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i + 1, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                $currentRow++;
                $shipmentCode = $aRow['shipment_code'];
            }
        }

        //------------- Participant List Details End ------>

        //<-------- Second sheet start
        $reportHeadings = array('Participant Code', 'Participant Name', 'Region', 'Shipment Receipt Date', 'Testing Date');
        $shipmentAttributes = json_decode($result['shipment_attributes'], true);
        // Zend_Debug::dump($shipmentAttributes);die;
        if (isset($shipmentAttributes['noOfTest']) && $shipmentAttributes['noOfTest'] == 2) {
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        }else{
            $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        }
        // For final Results
        $reportHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $reportHeadings);
        if (isset($shipmentAttributes['additionalDetailLabel']) && !empty($shipmentAttributes['additionalDetailLabel'])) {
            array_push($reportHeadings, $shipmentAttributes['additionalDetailLabel']);
        }

        // Zend_Debug::dump($reportHeadings);die;
        $sheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Results Reported');
        $excel->addSheet($sheet, 2);
        $sheet->setTitle('Results Reported', true);
        $sheet->getDefaultColumnDimension()->setWidth(24);
        $sheet->getDefaultRowDimension()->setRowHeight(18);


        $colNo = 0;
        $currentRow = 2;
        $n = count($reportHeadings);
        $finalResColoumn = $n - ($result['number_of_samples'] + 1);
        $c = 1;
        $endMergeCell = ($finalResColoumn + $result['number_of_samples']) - 1;

        $firstCellName = $sheet->getCellByColumnAndRow($finalResColoumn + 1, 1)->getColumn();
        $secondCellName = $sheet->getCellByColumnAndRow($endMergeCell + 1, 1)->getColumn();
        $sheet->mergeCells($firstCellName . "1:" . $secondCellName . "1");
        $sheet->getStyle($firstCellName . "1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle($firstCellName . "1")->applyFromArray($borderStyle, true);
        $sheet->getStyle($secondCellName . "1")->applyFromArray($borderStyle, true);

        foreach ($reportHeadings as $field => $value) {

            $sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->getStyleByColumnAndRow($colNo + 1, $currentRow, null, null)->getFont()->setBold(true);
            $cellName = $sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
            $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);

            $cellName = $sheet->getCellByColumnAndRow($colNo + 1, 3)->getColumn();
            $sheet->getStyle($cellName . "3")->applyFromArray($borderStyle, true);

            if ($colNo >= $finalResColoumn) {
                if ($c <= $result['number_of_samples']) {

                    $sheet->getCellByColumnAndRow($colNo + 1, 1)->setValueExplicit(html_entity_decode("Final Results", ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $cellName = $sheet->getCellByColumnAndRow($colNo + 1, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
                    $l = $c - 1;
                    $sheet->getCellByColumnAndRow($colNo + 1, 3)->setValueExplicit(html_entity_decode(str_replace("-", " ", ucwords($refResult[$l]['reference_result'])), ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $c++;
            }
            $sheet->getStyle($cellName . '3')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFA0A0A0');
            $sheet->getStyle($cellName . '3')->getFont()->getColor()->setARGB('FFFFFF00');

            $colNo++;
        }

        $sheet->getStyle("A2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("B2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("C2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("D2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
        $sheet->getStyle("E2")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        $cellName = $sheet->getCellByColumnAndRow($n + 1, 3)->getColumn();
        //<-------- Sheet three heading -------
        $sheetThree = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Panel Score');
        $excel->addSheet($sheetThree, 3);
        $sheetThree->setTitle('Panel Score', true);
        $sheetThree->getDefaultColumnDimension()->setWidth(20);
        $sheetThree->getDefaultRowDimension()->setRowHeight(18);
        $panelScoreHeadings = array('Participant Code', 'Participant Name');
        $panelScoreHeadings = $this->addGenericTestSampleNameInArray($shipmentId, $panelScoreHeadings);
        array_push($panelScoreHeadings, 'Test# Correct', '% Correct');
        $sheetThreeColNo = 0;
        $sheetThreeRow = 1;
        $panelScoreHeadingCount = count($panelScoreHeadings);
        $sheetThreeColor = 1 + $result['number_of_samples'];
        foreach ($panelScoreHeadings as $sheetThreeHK => $value) {
            $sheetThree->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheetThree->getStyleByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow, null, null)->getFont()->setBold(true);
            $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->getColumn();
            $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);

            if ($sheetThreeHK > 1 && $sheetThreeHK <= $sheetThreeColor) {
                $cellName = $sheetThree->getCellByColumnAndRow($sheetThreeColNo + 1, $sheetThreeRow)->getColumn();
                $sheetThree->getStyle($cellName . $sheetThreeRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            }

            $sheetThreeColNo++;
        }
        //---------- Sheet Three heading ------->
        
        //<-------- Total Score Sheet Heading (Sheet Four)-------
        $totalScoreSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Total Score');
        $excel->addSheet($totalScoreSheet, 4);
        $totalScoreSheet->setTitle('Total Score', true);
        $totalScoreSheet->getDefaultColumnDimension()->setWidth(20);
        $totalScoreSheet->getDefaultRowDimension()->setRowHeight(30);
        $totalScoreHeadings = array('Participant Code', 'Participant Name', 'No. of Panels Correct (N=' . $result['number_of_samples'] . ')', 'Panel Score(100% Conv.)', 'Panel Score(90% Conv.)', 'Documentation Score(100% Conv.)', 'Documentation Score(10% Conv.)', 'Total Score', 'Overall Performance');

        $totScoreSheetCol = 0;
        $totScoreRow = 1;
        $totScoreHeadingsCount = count($totalScoreHeadings);
        foreach ($totalScoreHeadings as $sheetThreeHK => $value) {
            $totalScoreSheet->getCellByColumnAndRow($totScoreSheetCol + 1, $totScoreRow)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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
                $r = 0;
                $k = 0;
                $rehydrationDate = "";
                $shipmentTestDate = "";
                $sheetThreeCol = 0;
                $totScoreCol = 0;
                $countCorrectResult = 0;

                $colCellObj = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow);
                // $colCellObj->setValueExplicit(ucwords($aRow['unique_identifier']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $cellName = $colCellObj->getColumn();
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['dataManagerFirstName'] . ' ' . $aRow['dataManagerLastName'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['region'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $shipmentReceiptDate = "";
                if (isset($aRow['shipment_receipt_date']) && trim($aRow['shipment_receipt_date']) != "") {
                    $shipmentReceiptDate = $aRow['shipment_receipt_date'] = Pt_Commons_General::excelDateFormat($aRow['shipment_receipt_date']);
                }

                if (isset($aRow['shipment_test_date']) && trim($aRow['shipment_test_date']) != "" && trim($aRow['shipment_test_date']) != "0000-00-00") {
                    $shipmentTestDate = Pt_Commons_General::excelDateFormat($aRow['shipment_test_date']);
                }

                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentReceiptDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($shipmentTestDate, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit(ucwords($aRow['unique_identifier']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheetThreeCol++;
                $documentScore = (($aRow['documentation_score'] / $config->evaluation->covid19->documentationScore) * 100);
               
                
                //<------------ Total score sheet ------------
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(ucwords($aRow['unique_identifier']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $totScoreCol++;
                //------------ Total score sheet ------------>
                //Zend_Debug::dump($aRow['response']);
                if (count($aRow['response']) > 0) {
                    for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(str_replace("-", " ", ucwords($aRow['response'][$k]['result'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                    if (isset($shipmentAttributes['noOfTest']) && $shipmentAttributes['noOfTest'] == 2) {
                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(str_replace("-", " ", ucwords($aRow['response'][$k]['repeat_result'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        }
                    }

                    for ($f = 0; $f < $aRow['number_of_samples']; $f++) {
                        $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(str_replace("-", " ", ucwords($aRow['response'][$f]['reported_result'])), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                        $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($aRow['response'][$f]['calculated_score'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        if (isset($aRow['response'][$f]['calculated_score']) && $aRow['response'][$f]['calculated_score'] == 20 && $aRow['response'][$f]['sample_id'] == $refResult[$f]['sample_id']) {
                            $countCorrectResult++;
                        }
                    }
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit($aRow['user_comment'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($countCorrectResult, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                    $sheetThree->getCellByColumnAndRow($sheetThreeCol++, $sheetThreeRow)->setValueExplicit($totPer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($countCorrectResult, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($totPer, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                    $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($totPer * 0.9), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($documentScore, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($aRow['documentation_score'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $finalResultCell = ($aRow['final_result'] == 1) ? "Pass" : "Fail";
                $totalScoreSheet->getCellByColumnAndRow($totScoreCol++, $totScoreRow)->setValueExplicit($finalResultCell, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

                for ($i = 0; $i < $panelScoreHeadingCount; $i++) {
                    $cellName = $sheetThree->getCellByColumnAndRow($i + 1, $sheetThreeRow)->getColumn();
                    $sheetThree->getStyle($cellName . $sheetThreeRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $n; $i++) {
                    $cellName = $sheet->getCellByColumnAndRow($i + 1, $currentRow)->getColumn();
                    $sheet->getStyle($cellName . $currentRow)->applyFromArray($borderStyle, true);
                }

                for ($i = 0; $i < $totScoreHeadingsCount; $i++) {
                    $cellName = $totalScoreSheet->getCellByColumnAndRow($i + 1, $totScoreRow)->getColumn();
                    $totalScoreSheet->getStyle($cellName . $totScoreRow)->applyFromArray($borderStyle, true);
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
                'reported_count' => new Zend_Db_Expr("SUM(shipment_test_date not like  '0000-00-00' OR is_pt_test_not_performed !='yes')")
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
            //->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR spm.is_pt_test_not_performed ='yes'")
            ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00'")
            //->where("spm.is_pt_test_not_performed not like 'yes'")
            ->group('spm.map_id');

        $sQueryRes = $db->fetchAll($sQuery);
        //echo($sQuery);die;

        if (count($sQueryRes) > 0) {
            $shipmentResult['summaryResult'][] = $sQueryRes;
        }

        $cQuery = $db->select()->from(array('refGenTest' => 'reference_result_generic_test'), array('refGenTest.sample_id', 'refGenTest.sample_label', 'refGenTest.reference_result', 'refGenTest.mandatory'))
            ->join(array('s' => 'shipment'), 's.shipment_id=refGenTest.shipment_id', array('s.shipment_id'))
            ->join(array('spm' => 'shipment_participant_map'), 's.shipment_id=spm.shipment_id', array('spm.map_id', 'spm.attributes', 'spm.shipment_score'))
            ->joinLeft(array('resGenTest' => 'response_result_generic_test'), 'resGenTest.shipment_map_id = spm.map_id and resGenTest.sample_id = refGenTest.sample_id', array('reported_result'))
            ->where('spm.shipment_id = ? ', $shipmentId)
            ->where("spm.shipment_test_date IS NOT NULL AND spm.shipment_test_date not like '' AND spm.shipment_test_date not like '0000-00-00' OR spm.is_pt_test_not_performed ='yes'")
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
}
