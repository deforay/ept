<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Application_Model_Tb
{

    private $db = null;
    private $common = null;

    public function __construct()
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->common = new Application_Service_Common();
    }

    public function evaluate($shipmentResult, $shipmentId)
    {
        $counter = 0;
        $maxScore = 0;
        $finalResult = null;
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $passingScore = $config->evaluation->tb->passPercentage ?? 80;

        $schemeService = new Application_Service_Schemes();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $consensusResults = $this->getConsensusResults($shipmentId);

        $this->db->update('shipment_participant_map', ['failure_reason' => null, 'is_followup' => 'no', 'is_excluded' => 'no', 'final_result' => null], "shipment_id = $shipmentId");
        $this->db->update(
            'shipment_participant_map',
            [
                'is_excluded' => 'yes',
                'shipment_score' => 0,
                'documentation_score' => 0,
                'final_result' => 3,
                'failure_reason' => json_encode([['warning' => 'Excluded from Evaluation']])
            ],
            "shipment_id = $shipmentId AND ((IFNULL(is_pt_test_not_performed, 'no') = 'yes') OR (response_status is not null AND response_status = 'draft'))"
        );


        foreach ($shipmentResult as $shipment) {

            if ($shipment['response_status'] === 'draft' || $shipment['is_pt_test_not_performed'] === 'yes') {
                continue;
            }

            // setting the following as no by default. Might become 'yes' if some conditions match
            $shipment['is_excluded'] = 'no';
            $shipment['is_followup'] = 'no';

            $totalScore = $calculatedScore = $maxScore = 0;
            $scoreResult = "";
            $failureReason = $results = [];

            $createdOnUser = explode(" ", $shipment['shipment_test_report_date'] ?? '');
            if (trim($createdOnUser[0]) != "" && $createdOnUser[0] != null && trim($createdOnUser[0]) != "0000-00-00") {
                $createdOn = new DateTime($createdOnUser[0]);
            } else {
                $createdOn = new DateTime('1970-01-01');
            }

            $lastDate = new DateTime($shipment['lastdate_response']);

            $results = $this->getTbSamplesForParticipant($shipmentId, $shipment['participant_id']);

            if ($createdOn->format('Y-m-d') > $lastDate->format('Y-m-d')) {
                $failureReason[] = array(
                    'warning' => "Response was submitted after the last response date."
                );
                $shipment['is_excluded'] = 'yes';
                $shipment['is_response_late'] = 'yes';
                $db->update(
                    'shipment_participant_map',
                    ['failure_reason' => json_encode($failureReason)],
                    "map_id = " . $shipment['map_id']
                );
            } else {
                $shipment['is_response_late'] = 'no';
            }
            if ($shipment['response_status'] === 'responded') {
                foreach ($results as $result) {

                    //if Sample is not mandatory, we will skip the evaluation
                    if (0 == $result['mandatory']) {
                        $this->db->update('response_result_tb', array('calculated_score' => "N.A."), "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']);
                        continue;
                    }


                    $assayShortName = "";
                    $attributes = isset($result['attributes']) ? json_decode($result['attributes'], true) : [];
                    if (isset($attributes['assay_name'])) {
                        $assayShortName = $this->getTbAssayShortName($attributes['assay_name']);
                    } elseif (isset($attributes['other_assay_name'])) {
                        $assayShortName = strtolower($attributes['other_assay_name']);
                    }

                    if (!empty($assayShortName) && $assayShortName == 'microscopy') {
                        // Assay is Microscopy
                        if (isset($result['mtb_detected']) && $result['mtb_detected'] != null) {
                            // For Negative Reference Results, the reported result should be negative
                            if ($result['reference_mtb_detected'] == 'negative') {
                                if ($result['mtb_detected'] == $result['reference_mtb_detected']) {
                                    if (0 == $result['control']) {
                                        $calculatedScore = $result['sample_score'];
                                        $totalScore += $calculatedScore;
                                    }
                                } else {
                                    if ($result['sample_score'] > 0) {
                                        $calculatedScore = 0;
                                        $totalScore += $calculatedScore;
                                        $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                    }
                                }
                            } else {
                                // For Non-Negative Reference Results, the reported result can be a bit flexible
                                $positiveResults = ['scanty', '1+', '2+', '3+'];
                                $awardedScore = 0;
                                //If they detect any positive result, then they should be awarded 0.5
                                if (in_array($result['mtb_detected'], $positiveResults)) {
                                    // if they report any of the positive results, then they should be awarded 0.5
                                    $awardedScore = 0.5;
                                    if ($result['mtb_detected'] == $result['reference_mtb_detected']) {
                                        // if they report the same result as the reference, then they should be awarded 1
                                        $awardedScore = 1;
                                    } elseif ($result['reference_mtb_detected'] == 'scanty' && in_array($result['mtb_detected'], ['scanty', '1+'])) {
                                        // for scanty, if they report scanty or 1+, then they should be awarded 1
                                        $awardedScore = 1;
                                    } elseif ($result['reference_mtb_detected'] == '1+' && in_array($result['mtb_detected'], ['scanty', '1+', '2+'])) {
                                        // for 1+, if they report scanty, 1+ or 2+, then they should be awarded 1
                                        $awardedScore = 1;
                                    } elseif ($result['reference_mtb_detected'] == '2+' && in_array($result['mtb_detected'], ['1+', '2+', '3+'])) {
                                        // for 2+, if they report 1+, 2+ or 3+, then they should be awarded 1
                                        $awardedScore = 1;
                                    } elseif ($result['reference_mtb_detected'] == '3+' && in_array($result['mtb_detected'], ['2+', '3+'])) {
                                        // for 3+, if they report 2+ or 3+, then they should be awarded 1
                                        $awardedScore = 1;
                                    }
                                    if (0 == $result['control']) {
                                        $calculatedScore = $awardedScore * $result['sample_score'];
                                        $totalScore += $calculatedScore;
                                    }
                                } else {
                                    if ($result['sample_score'] > 0) {
                                        $calculatedScore = 0;
                                        $totalScore += $calculatedScore;
                                        $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                    }
                                }
                            }
                        } else {
                            if ($result['sample_score'] > 0) {
                                $calculatedScore = 0;
                                $totalScore += $calculatedScore;
                                $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                            }
                        }
                    } else {
                        // Assay is Xpert MTB/RIF

                        $result['mtb_detected'] = $this->checkAndSetMTBDetected($result['mtb_detected']);
                        $result['reference_mtb_detected'] = $this->checkAndSetMTBDetected($result['reference_mtb_detected']);

                        $notAControl = $result['control'] == 0;
                        if (isset($result['drug_resistance_test']) && !empty($result['drug_resistance_test']) && $result['drug_resistance_test'] != "yes") {

                            // matching reported and reference results without Rif
                            if (isset($result['mtb_detected']) && !empty($result['mtb_detected'])) {
                                if ($result['mtb_detected'] == $result['reference_mtb_detected']) {
                                    // if it is not a control, we can award score
                                    if ($notAControl) {
                                        $calculatedScore = $result['sample_score'];
                                        $totalScore += $calculatedScore;
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
                            if (!empty($result['mtb_detected']) && !empty($result['rif_resistance'])) {
                                $mtbDetectedMatches = ($result['mtb_detected'] == $result['reference_mtb_detected']);

                                // For participants who selected N/A for MTB Detected, we will treat RIF as indeterminate
                                // if ($result['mtb_detected'] == 'detected' && $result['rif_resistance'] == 'na') {
                                //     $result['rif_resistance'] = 'indeterminate';
                                // }

                                // // For samples that have N/A reference result for MTB Detected, we will treat RIF as indeterminate
                                // if ($result['reference_mtb_detected'] == 'detected' && $result['reference_rif_resistance'] == 'na') {
                                //     $result['reference_rif_resistance'] = 'indeterminate';
                                // }

                                // // For participants who selected N/A for MTB NOT Detected, we will treat RIF as Not Detected
                                // if ($result['mtb_detected'] == 'not-detected' && $result['rif_resistance'] == 'na') {
                                //     $result['rif_resistance'] = 'not-detected';
                                // }

                                // // For samples that have N/A reference result for MTB Detected, we will treat RIF as indeterminate
                                // if ($result['reference_mtb_detected'] == 'not-detected' && $result['reference_rif_resistance'] == 'na') {
                                //     $result['reference_rif_resistance'] = 'not-detected';
                                // }

                                $rifResistanceMatches = ($result['rif_resistance'] == $result['reference_rif_resistance']);

                                // if it is not a control, we can award score
                                if ($notAControl) {
                                    $calculatedScore = 0;

                                    // Check for invalid, error, or no-result in mtb_detected
                                    if (in_array($result['mtb_detected'], ['invalid', 'error', 'no-result'])) {
                                        $calculatedScore = $result['sample_score'] * 0.25;
                                    }
                                    // mtb detected matches but rif resistance does not match
                                    elseif ($mtbDetectedMatches && !$rifResistanceMatches) {
                                        if (
                                            $result['mtb_detected'] == 'detected' &&
                                            (in_array($result['rif_resistance'], ['indeterminate']) ||
                                                in_array($result['reference_rif_resistance'], ['indeterminate', 'na']))
                                        ) {
                                            $calculatedScore = $result['sample_score'] * 0.5;
                                        } elseif ($result['mtb_detected'] == 'not-detected' && $result['rif_resistance'] == 'na') {
                                            $calculatedScore = $result['sample_score'];
                                        }
                                    }
                                    // both mtb detected and rif resistance matches
                                    elseif ($mtbDetectedMatches && $rifResistanceMatches) {
                                        $calculatedScore = $result['sample_score'];
                                    }




                                    // elseif ($mtbDetectedMatches && (in_array($result['reference_rif_resistance'], ['indeterminate', 'na']))) {
                                    //     if ($mtbDetectedMatches && $rifResistanceMatches) {
                                    //         $calculatedScore = $result['sample_score'];
                                    //     } elseif (in_array($result['rif_resistance'], ['detected', 'not-detected'])) {
                                    //         $calculatedScore = $result['sample_score'] * 0.5;
                                    //     }
                                    // } elseif ($mtbDetectedMatches && !$rifResistanceMatches && (in_array($result['rif_resistance'], ['indeterminate', 'na']))) {
                                    //     $calculatedScore = $result['sample_score'] * 0.5;
                                    // } elseif ($mtbDetectedMatches && $rifResistanceMatches) {
                                    //     $calculatedScore = $result['sample_score'];
                                    // } else {
                                    //     $calculatedScore = 0;
                                    // }

                                    $totalScore += $calculatedScore;
                                } else {
                                    $calculatedScore = 0;
                                }
                            } else {
                                if ($result['sample_score'] > 0) {
                                    $failureReason[]['warning'] = "Control/Sample <strong>" . $result['sample_label'] . "</strong> was reported wrongly";
                                }
                            }
                        }
                    }



                    if (0 == $result['control']) {
                        $maxScore += $result['sample_score'];
                    }

                    $db->update(
                        'response_result_tb',
                        ['calculated_score' => $calculatedScore],
                        "shipment_map_id = " . $result['map_id'] . " and sample_id = " . $result['sample_id']
                    );
                }
                if ($maxScore > 0 && $totalScore > 0) {
                    $totalScore = ($totalScore / $maxScore) * 100;
                }
            } else {
                $shipment['is_excluded'] = 'yes';
            }



            // if we are excluding this result, then let us not give pass/fail
            if ($shipment['is_excluded'] == 'yes' || $shipment['is_pt_test_not_performed'] == 'yes') {
                $finalResult = '';
                $totalScore = 0;
                $responseScore = 0;
                $shipmentResult[$counter]['shipment_score'] = $responseScore;
                $shipmentResult[$counter]['documentation_score'] = 0;
                // $shipmentResult[$counter]['display_result'] = '';
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
                if ($scoreResult == 'Fail') {
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

                // $shipmentResult[$counter]['display_result'] = $fRes[0];
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
                    // $shipmentResult[$counter]['display_result'] = $fRes[0];
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

    private function checkAndSetMTBDetected($value)
    {
        return in_array($value, ['very-low', 'low', 'medium', 'high', 'trace']) ? 'detected' : $value;
    }

    public function getTbSamplesForParticipant($sId, $pId, $type = null)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(
                array('ref' => 'reference_result_tb'),
                array(
                    'sample_id',
                    'sample_label',
                    'tb_isolate',
                    'reference_mtb_detected' => 'mtb_detected',
                    'reference_rif_resistance' => 'rif_resistance',
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
                    'spc_xpert',
                    'spc_xpert_ultra',
                    'probe_a',
                    'is1081_is6110',
                    'rpo_b1',
                    'rpo_b2',
                    'rpo_b2',
                    'rpo_b3',
                    'rpo_b4',
                    'instrument_serial_no',
                    'gene_xpert_module_no',
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
        if (!empty($pId)) {
            $sql = $sql->where("spm.participant_id = ?", $pId);
        }
        if (isset($type) && $type == "shipment") {
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

    public function getTbAssayShortName($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->getTbAssayShortName($assayId);
    }

    public function getTbAssayDrugResistanceStatus($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchTbAssayDrugResistanceStatus($assayId);
    }

    public function generateTbExcelReport($shipmentId)
    {
        try {
            ini_set('memory_limit', '-1');
            $config = new Zend_Config_Ini(APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini", APPLICATION_ENV);

            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $excel = new Spreadsheet();

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
                'Country',
                'Address',
                'Province',
                'District',
                'City',
                'Facility Telephone',
                'Email',
                'Report PDF Downloaded On',
                'Summary PDF Downloaded On'
            );

            $participantSheet = new Worksheet($excel, 'Participant List');
            $excel->addSheet($participantSheet, 0);
            $participantSheet->setTitle('Participant List', true);

            $sql = $db->select()->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.number_of_samples'))
                ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array(
                    'spm.map_id',
                    'spm.participant_id',
                    'spm.attributes',
                    'spm.shipment_test_date',
                    'spm.shipment_receipt_date',
                    'spm.shipment_test_report_date',
                    'spm.supervisor_approval',
                    'spm.participant_supervisor',
                    'spm.shipment_score',
                    'spm.documentation_score',
                    'spm.user_comment',
                    'spm.final_result',
                    'pt_test_not_performed_comments',
                    'failure_reason',
                    'individual_report_downloaded_on',
                    'summary_report_downloaded_on',
                    'is_pt_test_not_performed' => new Zend_Db_Expr("
                    CASE WHEN
                        (is_pt_test_not_performed = '' OR is_pt_test_not_performed IS NULL OR is_pt_test_not_performed like 'no') AND (response_status = 'responded')
                    THEN
                        'Tested'
                    ELSE
                        IF((response_status like 'noresponse'), 'No Response' ,  IF((is_pt_test_not_performed like 'yes'), 'Not Tested' ,'No Response'))
                    END
                    "),
                    'response_status' => new Zend_Db_Expr("CASE WHEN (response_status = 'noresponse') THEN 'No Response' ELSE response_status END")
                ))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.lab_name', 'p.region', 'p.first_name', 'p.last_name', 'p.address', 'p.city', 'p.mobile', 'p.email', 'p.status', 'province' => 'p.state', 'p.district'))
                ->joinLeft(array('pmp' => 'participant_manager_map'), 'pmp.participant_id=p.participant_id', array('pmp.dm_id'))
                ->joinLeft(array('dm' => 'data_manager'), 'dm.dm_id=pmp.dm_id', array('dm.institute', 'dataManagerFirstName' => 'dm.first_name', 'dataManagerLastName' => 'dm.last_name'))
                ->joinLeft(array('c' => 'countries'), 'c.id=p.country', array('iso_name'))
                ->joinLeft(array('st' => 'r_site_type'), 'st.r_stid=p.site_type', array('st.site_type'))
                ->joinLeft(array('en' => 'enrollments'), 'en.participant_id=p.participant_id', array('en.enrolled_on'))
                ->joinLeft(array('rtb' => 'r_tb_assay'), 'spm.attributes->>"$.assay_name" =rtb.id', array('short_name', 'assayName' => 'name'))
                ->joinLeft(array('ntr' => 'r_response_vl_not_tested_reason'), 'spm.vl_not_tested_reason =ntr.vl_not_tested_reason_id', array('ntTestedReason' => 'vl_not_tested_reason'))
                ->where("s.shipment_id = ?", $shipmentId)
                ->group(array('spm.map_id'));
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (!empty($authNameSpace->dm_id)) {
                $sql = $sql
                    ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array('pmm.dm_id'))
                    ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
            }
            $shipmentResult = $db->fetchAll($sql);
            $currentRow = 1;


            $participantListSheetData = [];

            if (isset($shipmentResult) && !empty($shipmentResult)) {
                $currentRow += 1;
                foreach ($shipmentResult as $key => $aRow) {
                    if ($result['scheme_type'] == 'tb') {
                        $resQuery = $db->select()->from(
                            ['rrtb' => 'response_result_tb'],
                            [
                                'sample_id',
                                'response_attributes',
                                'assay_id',
                                'mtb_detected' => new Zend_Db_Expr("IF((mtb_detected like 'na' AND mtb_detected like 'NA'), 'N/A', mtb_detected)"),
                                'rif_resistance' => new Zend_Db_Expr("IF((rif_resistance like 'na' AND rif_resistance like 'NA'), 'N/A', rif_resistance)"),
                                'probe_d' => new Zend_Db_Expr("IF((probe_d like 'na' AND probe_d like 'NA'), 'N/A', probe_d)"),
                                'probe_c' => new Zend_Db_Expr("IF((probe_c like 'na' AND probe_c like 'NA'), 'N/A', probe_c)"),
                                'probe_e' => new Zend_Db_Expr("IF((probe_e like 'na' AND probe_e like 'NA'), 'N/A', probe_e)"),
                                'probe_b' => new Zend_Db_Expr("IF((probe_b like 'na' AND probe_b like 'NA'), 'N/A', probe_b)"),
                                'spc_xpert' => new Zend_Db_Expr("IF((spc_xpert like 'na' AND spc_xpert like 'NA'), 'N/A', spc_xpert)"),
                                'spc_xpert_ultra' => new Zend_Db_Expr("IF((spc_xpert_ultra like 'na' AND spc_xpert_ultra like 'NA'), 'N/A', spc_xpert_ultra)"),
                                'probe_a' => new Zend_Db_Expr("IF((probe_a like 'na' AND probe_a like 'NA'), 'N/A', probe_a)"),
                                'test_date' => new Zend_Db_Expr("IF((test_date like 'na' AND test_date like 'NA'), 'N/A', test_date)"),
                                'is1081_is6110' => new Zend_Db_Expr("IF((is1081_is6110 like 'na' AND is1081_is6110 like 'NA'), 'N/A', is1081_is6110)"),
                                'rpo_b1' => new Zend_Db_Expr("IF((rpo_b1 like 'na' AND rpo_b1 like 'NA'), 'N/A', rpo_b1)"),
                                'rpo_b2' => new Zend_Db_Expr("IF((rpo_b2 like 'na' AND rpo_b2 like 'NA'), 'N/A', rpo_b2)"),
                                'rpo_b3' => new Zend_Db_Expr("IF((rpo_b3 like 'na' AND rpo_b3 like 'NA'), 'N/A', rpo_b3)"),
                                'rpo_b4' => new Zend_Db_Expr("IF((rpo_b4 like 'na' AND rpo_b4 like 'NA'), 'N/A', rpo_b4)"),
                                'instrument_serial_no' => new Zend_Db_Expr("IF((instrument_serial_no like 'na' AND instrument_serial_no like 'NA'), 'N/A', instrument_serial_no)"),
                                'gene_xpert_module_no' => new Zend_Db_Expr("IF((gene_xpert_module_no like 'na' AND gene_xpert_module_no like 'NA'), 'N/A', gene_xpert_module_no)"),
                                'tester_name',
                                'error_code' => new Zend_Db_Expr("IF((error_code like 'na' AND error_code like 'NA'), 'N/A', error_code)"),
                                'error_code',
                                'calculated_score'
                            ]
                        )
                            ->where("rrtb.shipment_map_id = ?", $aRow['map_id']);
                        // die($resQuery);
                        $shipmentResult[$key]['response'] = $db->fetchAll($resQuery);
                    }
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
                    $participantRow[] = strtolower($aRow['email']);
                    $participantRow[] = Pt_Commons_General::excelDateFormat($aRow['individual_report_downloaded_on']);
                    $participantRow[] = Pt_Commons_General::excelDateFormat($aRow['summary_report_downloaded_on']);


                    $currentRow++;
                    $shipmentCode = $aRow['shipment_code'];

                    $participantListSheetData[] = $participantRow;
                    //unset($participantRow);
                }
            }


            $participantSheet->fromArray($headings, null, "A1");
            $this->common->centerAndBoldRowInSheet($participantSheet, "A1");

            $participantSheet->fromArray($participantListSheetData, null, 'A2');

            //------------- Participant List Details End ------>

            //<-------- Second sheet start
            $reportHeadings = [
                'Participant Code',
                'Participant Name',
                'Region',
                'Shipment Receipt Date',
                'Testing Date',
                'Assay Name',
                'Assay Lot',
                'Assay Expiration',
                'Response Status',
                'Is PT Panel Not Tested?',
                'Reason for Not Testing'
            ];

            $reportHeadings = $this->addTbSampleNameInArray($shipmentId, $reportHeadings, true);

            array_push($reportHeadings, 'Comments');
            array_push($reportHeadings, 'Reason for Failure');
            array_push($reportHeadings, 'Total Score');
            array_push($reportHeadings, 'Final Result');
            /* Feed Back Response Section */
            $common = new Application_Service_Common();
            $questions = $common->getFeedBackQuestions($shipmentId, $reportHeadings);
            if (isset($questions) && !empty($questions['question'])) {
                $reportHeadings = $questions['heading'];
            }

            $resultReportedSheet = new Worksheet($excel, 'Results Reported');
            $excel->addSheet($resultReportedSheet, 1);
            $resultReportedSheet->setTitle('Results Reported', true);

            $currentRow = 1;

            $resultReportedSheet->fromArray($reportHeadings, null, "A1");
            $this->common->centerAndBoldRowInSheet($resultReportedSheet, "A1");


            //<-------- Sheet three heading -------
            $panelScoreSheet = new Worksheet($excel, 'Panel Score');
            $excel->addSheet($panelScoreSheet, 2);
            $panelScoreSheet->setTitle('Panel Score', true);
            $panelScoreHeadings = array('Participant Code', 'Participant Name');
            $panelScoreHeadings = $this->addTbSampleNameInArray($shipmentId, $panelScoreHeadings);
            array_push($panelScoreHeadings, 'Test# Correct', '% Correct', 'Reason for Failure');
            $sheetThreeRow = 1;

            $panelScoreSheet->fromArray($panelScoreHeadings, null, "A1");
            $this->common->centerAndBoldRowInSheet($panelScoreSheet, "A1");
            //---------- Sheet Three heading ------->

            $totalScoreSheet = new Worksheet($excel, 'Total Score');
            $excel->addSheet($totalScoreSheet, 3);
            $totalScoreSheet->setTitle('Total Score', true);
            $totalScoreHeadings = [
                'Participant Code',
                'Participant Name',
                'No. of Panels Correct (N=' . $result['number_of_samples'] . ')',
                'Panel Score(100% Conv.)',
                'Panel Score(90% Conv.)',
                'Documentation Score(100% Conv.)',
                'Documentation Score(10% Conv.)',
                'Total Score',
                'Overall Performance'
            ];


            $totScoreRow = 1;
            //$totScoreHeadingsCount = count($totalScoreHeadings);

            $totalScoreSheet->fromArray($totalScoreHeadings, null, "A1");
            $this->common->centerAndBoldRowInSheet($totalScoreSheet, "A1");

            //---------- Document Score Sheet Heading (Sheet Four)------->
            $currentRow = 2;
            $sheetThreeRow = 2;
            $docScoreRow = 3;
            $totScoreRow = 2;

            if (isset($shipmentResult) && !empty($shipmentResult)) {

                foreach ($shipmentResult as $aRow) {
                    $txtColor = "000000";
                    if ($aRow['final_result'] != 1 || ($aRow['shipment_score'] + $aRow['documentation_score']) == 0) {
                        $txtColor = "F66257";
                    }

                    $r = 1;
                    $k = 1;
                    $shipmentTestDate = "";
                    $panelScoreColumn = 1;
                    $totScoreCol = 1;

                    $attributes = json_decode($aRow['attributes'], true);

                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($aRow['unique_identifier'])->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name'])->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($aRow['region'])->getStyle()->getFont()->getColor()->setARGB($txtColor);
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
                    $expiryDate = '';
                    if (isset($attributes['expiry_date']) && trim($attributes['expiry_date']) != "" && trim($attributes['expiry_date']) != "0000-00-00") {
                        $expiryDate = Pt_Commons_General::excelDateFormat($attributes['expiry_date']);
                    }
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($aRow['shipment_receipt_date'])->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($shipmentTestDate)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit((isset($aRow['assayName']) && !empty($aRow['assayName'])) ? $aRow['assayName'] : '')->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit((isset($attributes['assay_lot_number']) && !empty($attributes['assay_lot_number'])) ? $attributes['assay_lot_number'] : '')->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($expiryDate)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit(ucwords($aRow['response_status']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    //$aRow['is_pt_test_not_performed'] = (!empty($aRow['is_pt_test_not_performed'] && $aRow['is_pt_test_not_performed'] == 'yes') ? true : false);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit($aRow['is_pt_test_not_performed'] ?? 'Not Tested')->getStyle()->getFont()->getColor()->setARGB($txtColor);
                    $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                        ->setValueExplicit(ucwords($aRow['ntTestedReason'] ?? $aRow['pt_test_not_performed_comments']))->getStyle()->getFont()->getColor()->setARGB($txtColor);

                    $panelScoreSheet->getCell(Coordinate::stringFromColumnIndex($panelScoreColumn++) . $sheetThreeRow)
                        ->setValueExplicit(($aRow['unique_identifier']));
                    $panelScoreSheet->getCell(Coordinate::stringFromColumnIndex($panelScoreColumn++) . $sheetThreeRow)
                        ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                    if (isset($config->evaluation->tb->documentationScore) && $config->evaluation->tb->documentationScore > 0) {
                        $documentScore = (($aRow['documentation_score'] / $config->evaluation->tb->documentationScore) * 100);
                    } else {
                        $documentScore = 0;
                    }

                    //<------------ Total score sheet ------------

                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit(($aRow['unique_identifier']));
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($aRow['first_name'] . ' ' . $aRow['last_name']);

                    //------------ Total score sheet ------------>
                    // Zend_Debug::dump($aRow);die;
                    if (count($aRow['response']) > 0) {
                        $countCorrectResult = 0;
                        for ($k = 0; $k < $aRow['number_of_samples']; $k++) {
                            // For participants who selected N/A for MTB NOT Detected, we will treat RIF as Not Detected
                            if (strtolower($aRow['response'][$k]['mtb_detected']) == 'not-detected' && $aRow['response'][$k]['rif_resistance'] == 'N/A') {
                                $aRow['response'][$k]['rif_resistance'] = 'not-detected';
                            }
                            if (strtolower($aRow['response'][$k]['mtb_detected']) == 'error') {
                                $aRow['response'][$k]['rif_resistance'] = 'error';
                            }
                            if (strtolower($aRow['response'][$k]['mtb_detected']) == 'invalid') {
                                $aRow['response'][$k]['rif_resistance'] = 'invalid';
                            }
                            if (strtolower($aRow['response'][$k]['mtb_detected']) == 'no-result') {
                                $aRow['response'][$k]['rif_resistance'] = 'no-result';
                            }
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['mtb_detected']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['rif_resistance']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((int) ($aRow['response'][$k]['calculated_score'] ?? 0), DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            if (isset($aRow['short_name']) && !empty($aRow['short_name']) && $aRow['short_name'] == 'xpert-mtb-rif') {
                                $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['spc_xpert']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            } else if (isset($aRow['short_name']) && !empty($aRow['short_name']) && $aRow['short_name'] == 'xpert-mtb-rif-ultra') {
                                $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['spc_xpert_ultra']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            }

                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['probe_d'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['probe_c'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['probe_e'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['probe_b'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['probe_a'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['is1081_is6110'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['rpo_b1'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['rpo_b2'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['rpo_b3'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit((float)$aRow['response'][$k]['rpo_b4'], DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['instrument_serial_no']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['gene_xpert_module_no']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['test_date']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['tester_name']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)->setValueExplicit(ucwords($aRow['response'][$k]['error_code']))->getStyle()->getFont()->getColor()->setARGB($txtColor);
                        }
                        for ($f = 0; $f < $aRow['number_of_samples']; $f++) {
                            $panelScoreSheet->getCellByColumnAndRow($panelScoreColumn++, $sheetThreeRow)->setValueExplicit($aRow['response'][$f]['calculated_score'], DataType::TYPE_NUMERIC);
                            if (isset($aRow['response'][$f]['calculated_score']) && $aRow['response'][$f]['calculated_score'] == 20 && $aRow['response'][$f]['sample_id'] == $refResult[$f]['sample_id']) {
                                $countCorrectResult++;
                            }
                        }
                        $panelScoreSheet->getCellByColumnAndRow($panelScoreColumn++, $sheetThreeRow)->setValueExplicit($countCorrectResult, DataType::TYPE_NUMERIC);

                        $totPer = round((($countCorrectResult / $aRow['number_of_samples']) * 100), 2);
                        $panelScoreSheet->getCellByColumnAndRow($panelScoreColumn++, $sheetThreeRow)->setValueExplicit($totPer, DataType::TYPE_NUMERIC);


                        $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                            ->setValueExplicit($aRow['user_comment'])->getStyle()->getFont()->getColor()->setARGB($txtColor);

                        $warning = (isset($aRow['failure_reason']) && !empty($aRow['failure_reason'])) ? json_decode($aRow['failure_reason'], true) : '';
                        $warning = (isset($warning) && !empty($warning)) ? str_replace(array('<strong>', '</strong>'), array('', ''), $warning[0]['warning']) : '';
                        $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                            ->setValueExplicit($warning)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                        $panelScoreSheet->getCellByColumnAndRow($panelScoreColumn++, $sheetThreeRow)->setValueExplicit($warning, DataType::TYPE_STRING);

                        $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                            ->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), DataType::TYPE_NUMERIC)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                        $finalResult = "Not Tested";
                        if ($aRow['final_result'] == 1) {
                            $finalResult = "Pass";
                        } else if ($aRow['final_result'] == 2) {
                            $finalResult = "Fail";
                        }
                        $finalCellColumn = ($r + 1);
                        $finalCellRow = $currentRow;
                        $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                            ->setValueExplicit($finalResult)->getStyle()->getFont()->getColor()->setARGB($txtColor);
                        /* Feed Back Response Section */
                        // Zend_Debug::dump($aRow);die;
                        $feedbackDb = new Application_Model_DbTable_FeedBackTable();
                        $answers = $feedbackDb->fetchFeedBackAnswers($aRow['shipment_id'], $aRow['participant_id'], $aRow['map_id']);
                        if (isset($questions['question']) && !empty($questions['question']) && isset($answers) && !empty($answers)) {
                            foreach ($questions['question'] as $q) {
                                $resultReportedSheet->getCell(Coordinate::stringFromColumnIndex($r++) . $currentRow)
                                    ->setValueExplicit($answers[$q])->getStyle()->getFont()->getColor()->setARGB($txtColor);
                            }
                        }
                        foreach ([$countCorrectResult, $totPer, ($totPer * 0.9)] as $row) {
                            $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)->setValueExplicit($countCorrectResult);
                        }
                    } else {
                        for ($f = 0; $f < 3; $f++) {
                            $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)->setValueExplicit(0, DataType::TYPE_NUMERIC);
                        }
                    }
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($documentScore, DataType::TYPE_NUMERIC);
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($aRow['documentation_score'], DataType::TYPE_NUMERIC);
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit(($aRow['shipment_score'] + $aRow['documentation_score']), DataType::TYPE_NUMERIC);
                    if ($aRow['final_result'] == 1) {
                        $finalResultCell = "Pass";
                    } else if ($aRow['final_result'] == 3) {
                        $finalResultCell = "Not Tested";
                    } else {
                        $finalResultCell = "Fail";
                    }
                    $totalScoreSheet->getCell(Coordinate::stringFromColumnIndex($totScoreCol++) . $totScoreRow)
                        ->setValueExplicit($finalResultCell, DataType::TYPE_STRING);

                    $currentRow++;
                    $sheetThreeRow++;
                    $docScoreRow++;
                    $totScoreRow++;
                }
            }

            $this->common->setAllColumnWidthsInSheet($participantSheet, 20);
            $this->common->setAllColumnWidthsInSheet($resultReportedSheet, 20);
            $this->common->setAllColumnWidthsInSheet($panelScoreSheet, 20);
            $this->common->setAllColumnWidthsInSheet($totalScoreSheet, 20);

            $excel->setActiveSheetIndex(0);

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = $shipmentCode . '-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            error_log("GENERATE-PARTICIPANT-SHIPMENT-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return "";
        }
    }


    private function calculateConsensus($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $mtbConsensusResults = [];
        $rifConsensusResults = [];
        $consolidatedConsensusResults = [];

        // Query for MTB Detection Consensus
        $consensusResultsQueryMtb = $db->select()
            ->from(['spm' => 'shipment_participant_map'], [])
            ->join(['ref' => 'reference_result_tb'], 'ref.shipment_id = spm.shipment_id', ['sample_id'])
            ->joinLeft(['res' => 'response_result_tb'], 'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id', [
                'sample_id',
                'assay_id',
                'mtb_detection_consensus_raw' => new Zend_Db_Expr("CASE
                WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') THEN 'detected'
                ELSE res.mtb_detected
            END"),
                'mtb_detection_consensus' => new Zend_Db_Expr("CASE
                WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') THEN 'Detected'
                WHEN res.mtb_detected = 'not-detected' THEN 'Not Detected'
                ELSE CONCAT(UPPER(LEFT(res.mtb_detected, 1)), LOWER(SUBSTRING(res.mtb_detected, 2)))
            END"),
                'mtb_occurrences' => new Zend_Db_Expr('COUNT(*)'),
                'total_responses_mtb' => new Zend_Db_Expr('(SELECT COUNT(*) FROM response_result_tb WHERE sample_id = ref.sample_id AND assay_id = spm.attributes->>"$.assay_name")')
            ])
            ->where("spm.shipment_id = ?", $shipmentId)
            ->where("spm.is_excluded = 'no'")
            ->where("spm.response_status = 'responded'")
            ->group(['res.sample_id', 'res.assay_id', 'mtb_detection_consensus_raw'])
            ->order(['res.sample_id', 'res.assay_id', 'mtb_occurrences DESC']);

        // error_log($consensusResultsQueryMtb);

        $mtbResults = $db->fetchAll($consensusResultsQueryMtb);


        foreach ($mtbResults as $mtb) {
            if (!isset($mtbConsensusResults[$mtb['sample_id']][$mtb['assay_id']])) {
                $mtbConsensusResults[$mtb['sample_id']][$mtb['assay_id']] = [
                    'sample_id' => $mtb['sample_id'],
                    'assay_id' => $mtb['assay_id'],
                    'mtb_detection_consensus' => $mtb['mtb_detection_consensus'],
                    'mtb_detection_consensus_raw' => $mtb['mtb_detection_consensus_raw'],
                    'mtb_occurrences' => $mtb['mtb_occurrences'],
                    'mtb_total_responses' => $mtb['total_responses_mtb'],
                    'mtb_consensus_percentage' => ($mtb['mtb_occurrences'] / $mtb['total_responses_mtb']) * 100
                ];
            }
        }


        // Query for RIF Resistance Consensus
        $consensusResultsQueryRif = $db->select()
            ->from(['spm' => 'shipment_participant_map'], [])
            ->join(['ref' => 'reference_result_tb'], 'ref.shipment_id = spm.shipment_id', ['sample_id'])
            ->joinLeft(['res' => 'response_result_tb'], 'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id', [
                'sample_id',
                'assay_id',
                'rif_resistance_consensus_raw' => 'res.rif_resistance',
                'rif_resistance_consensus' => new Zend_Db_Expr("CASE
                WHEN res.rif_resistance = 'na' THEN 'N/A'
                WHEN res.rif_resistance = 'not-detected' THEN 'Not Detected'
                WHEN res.rif_resistance = 'detected' THEN 'Detected'
                ELSE CONCAT(UPPER(LEFT(res.rif_resistance, 1)), LOWER(SUBSTRING(res.rif_resistance, 2)))
            END"),
                'rif_occurrences' => new Zend_Db_Expr('COUNT(*)'),
                'total_responses_rif' => new Zend_Db_Expr('(SELECT COUNT(*) FROM response_result_tb WHERE sample_id = ref.sample_id AND assay_id = spm.attributes->>"$.assay_name")')
            ])
            ->where("spm.shipment_id = ?", $shipmentId)
            ->where("spm.is_excluded = 'no'")
            ->where("spm.response_status = 'responded'")
            ->group(['res.sample_id', 'res.assay_id', 'res.rif_resistance'])
            ->order(['res.sample_id', 'res.assay_id', 'rif_occurrences DESC']);

        $rifResults = $db->fetchAll($consensusResultsQueryRif);

        // Processing RIF Resistance Consensus
        foreach ($rifResults as $rif) {
            if (!isset($rifConsensusResults[$rif['sample_id']][$rif['assay_id']])) {
                $rifConsensusResults[$rif['sample_id']][$rif['assay_id']] = [
                    'sample_id' => $rif['sample_id'],
                    'assay_id' => $rif['assay_id'],
                    'rif_resistance_consensus' => $rif['rif_resistance_consensus'],
                    'rif_resistance_consensus_raw' => $rif['rif_resistance_consensus_raw'],
                    'rif_occurrences' => $rif['rif_occurrences'],
                    'rif_total_responses' => $rif['total_responses_rif'],
                    'rif_consensus_percentage' => ($rif['rif_occurrences'] / $rif['total_responses_rif']) * 100
                ];
            }
        }


        // merge $mtbConsensusResults and $rifConsensusResults into $consolidatedConsensusResults

        foreach ($mtbConsensusResults as $sampleId => $assays) {
            foreach ($assays as $assayId => $mtbData) {
                $consolidatedConsensusResults[$sampleId][$assayId] = $mtbData;
            }
        }


        foreach ($rifConsensusResults as $sampleId => $assays) {
            foreach ($assays as $assayId => $rifData) {
                // If MTB data exists for the sample_id and assay_id, merge RIF data into it
                if (isset($consolidatedConsensusResults[$sampleId][$assayId])) {
                    $consolidatedConsensusResults[$sampleId][$assayId] = array_merge($consolidatedConsensusResults[$sampleId][$assayId], $rifData);
                } else {
                    // If no MTB data exists, just add the RIF data
                    $consolidatedConsensusResults[$sampleId][$assayId] = $rifData;
                }
            }
        }


        return $consolidatedConsensusResults;
    }

    public function getConsensusResults($shipmentId)
    {
        $calculatedConsensus = $this->calculateConsensus($shipmentId);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        foreach ($calculatedConsensus as $sampleId => $sampleConsensus) {


            $select = $db->select()
                ->from('reference_result_tb', ['mtb_detected', 'rif_resistance'])
                ->where('sample_id = ?', $sampleId)
                ->where('shipment_id = ?', $shipmentId);
            $referenceResults = $db->fetchRow($select);

            // 1 => MTB/RIF
            // 2 => MTB/RIF Ultra

            $mtbMatch = ($referenceResults['mtb_detected'] == $sampleConsensus[1]['mtb_detection_consensus_raw']) ? 'yes' : 'no';
            $mtbUltraMatch = ($referenceResults['mtb_detected'] == $sampleConsensus[2]['mtb_detection_consensus_raw']) ? 'yes' : 'no';
            $rifMatch = ($referenceResults['rif_resistance'] == $sampleConsensus[1]['rif_resistance_consensus_raw']) ? 'yes' : 'no';
            $rifUltraMatch = ($referenceResults['rif_resistance'] == $sampleConsensus[2]['rif_resistance_consensus_raw']) ? 'yes' : 'no';

            $calculatedConsensus[$sampleId][1]['mtb_match'] = $mtbMatch;
            $calculatedConsensus[$sampleId][2]['mtb_match'] = $mtbUltraMatch;
            $calculatedConsensus[$sampleId][1]['rif_match'] = $rifMatch;
            $calculatedConsensus[$sampleId][2]['rif_match'] = $rifUltraMatch;

            $db->update(
                'reference_result_tb',
                [
                    'mtb_detection_consensus' => $mtbMatch,
                    'mtb_ultra_detection_consensus' => $mtbUltraMatch,
                    'rif_resistance_consensus' => $rifMatch,
                    'rif_ultra_resistance_consensus' => $rifUltraMatch
                ],
                [
                    'sample_id = ?' => $sampleId
                ]
            );
        }

        return $calculatedConsensus;
    }


    public function getDataForIndividualPDF($mapId, $participantId)
    {

        $output = [];
        $sQuery = $this->db->select()->from(array('ref' => 'reference_result_tb'), array(
            'sample_id',
            'sample_label',
            'reference_mtb_detected' => new Zend_Db_Expr("CASE WHEN ref.mtb_detected = 'na' THEN 'N/A' else ref.mtb_detected END"),
            'reference_rif_resistance' => new Zend_Db_Expr("CASE WHEN ref.rif_resistance = 'na' THEN 'N/A' else ref.rif_resistance END"),
            'ref.control',
            'ref.mandatory',
            'ref.sample_score'
        ))
            ->joinLeft(
                array('spm' => 'shipment_participant_map'),
                'spm.shipment_id=ref.shipment_id',
                array(
                    'spm.shipment_id',
                    'spm.participant_id',
                    'spm.shipment_receipt_date',
                    'spm.shipment_test_date',
                    'spm.attributes',
                    'assay_name' => new Zend_Db_Expr('spm.attributes->>"$.assay_name"'),
                    'responseDate' => 'spm.shipment_test_report_date'
                )
            )
            ->joinLeft(
                array('res' => 'response_result_tb'),
                'spm.map_id = res.shipment_map_id AND ref.sample_id = res.sample_id',
                array(
                    'mtb_detected' => new Zend_Db_Expr("CASE WHEN res.mtb_detected = 'na' THEN 'N/A' else res.mtb_detected END"),
                    'rif_resistance' => new Zend_Db_Expr("CASE WHEN res.rif_resistance = 'na' THEN 'N/A' else res.rif_resistance END"),
                    'calculated_score'
                )
            )
            ->joinLeft(array('rtb' => 'r_tb_assay'), 'spm.attributes->>"$.assay_name" =rtb.id')
            ->joinLeft(array('s' => 'shipment'), 'spm.shipment_id = s.shipment_id')
            ->where("ref.control = 0")
            ->where("spm.response_status is not null AND spm.response_status not like 'noresponse'")
            // ->where(new Zend_Db_Expr("IFNULL(spm.is_excluded, 'no') = 'no'"))
            ->where("spm.map_id = ?", $mapId)
            ->order(array('ref.sample_id'))
            ->group(array('ref.sample_label'));
        $result = $this->db->fetchAll($sQuery);
        $response = [];
        foreach ($result as $key => $row) {
            $attributes = [];
            if (isset($row['attributes'])) {
                $attributes = json_decode($row['attributes'], true);
            }
            if (isset($attributes['assay_name']) && !empty($attributes['assay_name'])) {
                $row['assay_name'] = $this->getTbAssayName($attributes['assay_name']);
                $row['drug_resistance_test'] = $this->getTbAssayDrugResistanceStatus($attributes['assay_name']);
            }
            $response[$key] = $row;
        }

        $output['responseResult'] = $response;



        // Define a subquery to calculate the average shipment score for all participants
        $meanShipmentScoreSubQuery = $this->db->select()
            ->from(array('spm2' => 'shipment_participant_map'), array(
                'spm2.shipment_id',
                'mean_shipment_score' => new Zend_Db_Expr("AVG(CASE WHEN spm2.shipment_score > 0 THEN spm2.shipment_score ELSE NULL END)")
            ))
            ->where("IFNULL(spm2.is_pt_test_not_performed, 'no') = 'no'")
            ->where("spm2.response_status = 'responded'")
            ->group('spm2.shipment_id');

        // Now, incorporate this subquery into your main query
        $previousSixShipmentsSql = $this->db->select()
            ->from(array('s' => 'shipment'), array(
                's.shipment_id',
                's.shipment_code',
                's.shipment_date'
            ))
            ->joinLeft(
                array('meanScores' => new Zend_Db_Expr('(' . $meanShipmentScoreSubQuery . ')')),
                'meanScores.shipment_id = s.shipment_id',
                array('mean_shipment_score' => 'meanScores.mean_shipment_score')
            )
            ->join(
                array('spm' => 'shipment_participant_map'),
                's.shipment_id = spm.shipment_id AND spm.participant_id = ' . $participantId,
                array('participant_score' => 'spm.shipment_score') // Specific participant's score
            )
            ->where("spm.participant_id = ?", $participantId)
            ->where("spm.map_id = $mapId OR s.status = 'finalized'")
            ->group('s.shipment_id')
            ->order("s.shipment_date DESC")
            ->limit(6);
        // die($previousSixShipmentsSql);
        $previousSixShipments = $this->db->fetchAll($previousSixShipmentsSql);

        $participantPreviousSixShipments = [];
        if (!empty($previousSixShipments)) {
            $participantPreviousSixShipmentsSql = $this->db->select()
                ->from(array('spm' => 'shipment_participant_map'), array('shipment_id' => 'spm.shipment_id', 'shipment_score' => new Zend_Db_Expr("IFNULL(spm.shipment_score, 0) + IFNULL(spm.documentation_score, 0)")))
                ->where("spm.participant_id = ?", $participantId)
                ->where("spm.shipment_id IN (" . implode(",", array_column($previousSixShipments, "shipment_id")) . ")");

            $participantPreviousSixShipmentRecords = $this->db->fetchAll($participantPreviousSixShipmentsSql);
            foreach ($participantPreviousSixShipmentRecords as $participantPreviousSixShipmentRecord) {
                $participantPreviousSixShipments[$participantPreviousSixShipmentRecord['shipment_id']] = $participantPreviousSixShipmentRecord;
            }
        }
        $output['previous_six_shipments'] = [];
        for ($participantPreviousSixShipmentIndex = 0; $participantPreviousSixShipmentIndex <= count($previousSixShipments); $participantPreviousSixShipmentIndex++) {

            if (count($previousSixShipments) > $participantPreviousSixShipmentIndex) {
                $previousShipmentData['shipment_code'] = $previousSixShipments[$participantPreviousSixShipmentIndex]['shipment_code'];
                $previousShipmentData['mean_shipment_score'] = $previousSixShipments[$participantPreviousSixShipmentIndex]['mean_shipment_score'];
                if (isset($participantPreviousSixShipments[$previousSixShipments[$participantPreviousSixShipmentIndex]['shipment_id']])) {
                    $previousShipmentData['shipment_score'] = $participantPreviousSixShipments[$previousSixShipments[$participantPreviousSixShipmentIndex]['shipment_id']]['shipment_score'];
                }
                $output['previous_six_shipments'][$participantPreviousSixShipmentIndex] = $previousShipmentData;
            }
        }

        return $output;
    }


    public function getDataForSummaryPDF($shipmentId)
    {
        $summaryPDFData = [];
        $sql = $this->db->select()
            ->from(
                ['ref' => 'reference_result_tb'],
                [
                    'sample_label',
                    'tb_isolate',
                    'mtb_detected' => new Zend_Db_Expr("CASE WHEN ref.mtb_detected = 'na' THEN 'N/A' else ref.mtb_detected END"),
                    'rif_resistance' => new Zend_Db_Expr("CASE WHEN ref.rif_resistance = 'na' THEN 'N/A' else ref.rif_resistance END"),
                ]
            )
            ->where("ref.shipment_id = ?", $shipmentId)
            ->group('ref.sample_label');
        $sqlRes = $this->db->fetchAll($sql);

        $summaryPDFData['referenceResult'] = $sqlRes;

        $sQuery = "SELECT COUNT(*) AS 'enrolled',
				SUM(CASE WHEN ((`spm`.response_status is not null AND `spm`.response_status like 'responded') AND (IFNULL(spm.is_pt_test_not_performed, 'no') != 'yes')) THEN 1 ELSE 0 END)
					AS 'participated',
				SUM(CASE WHEN (`spm`.shipment_score is not null AND `spm`.shipment_score = 100) THEN 1 ELSE 0 END)
					AS 'sitesScoring100',
				SUM(CASE WHEN (`spm`.response_status is not null AND
                                `spm`.response_status like 'responded' AND
                                `spm`.attributes is not null AND
                                `spm`.attributes->>'$.assay_name' = 1) THEN 1 ELSE 0 END)
					AS 'mtb_rif',
				SUM(CASE WHEN (`spm`.response_status is not null AND
                                `spm`.response_status like 'responded' AND
                                `spm`.attributes is not null AND
                                `spm`.attributes->>'$.assay_name' = 2) THEN 1 ELSE 0 END)
					AS 'mtb_rif_ultra',
                `s`.shipment_comment, `spm`.shipment_test_date
				FROM shipment_participant_map as `spm`
                INNER JOIN `shipment` as `s` ON `spm`.shipment_id = `s`.shipment_id
                WHERE `spm`.shipment_id = $shipmentId";
        // die($sQuery);
        $sQueryRes = $this->db->fetchRow($sQuery);
        $summaryPDFData['summaryResult'] = $sQueryRes;


        $tQuery = "SELECT `ref`.sample_label, `s`.shipment_id,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id) THEN 1 ELSE 0 END) AS `numberOfSites`,
        `rta`.id as `tb_assay_id`,
        `rta`.name as `tb_assay`,
        `rta`.name as `assayName`,
        `rta`.short_name as `assayShortName`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace')) THEN 1 ELSE 0 END)
                AS `mtbDetected`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected like 'not-detected') THEN 1 ELSE 0 END)
                AS `mtbNotDetected`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected IN ('invalid', 'error', 'no-result')) THEN 1 ELSE 0 END)
                AS `mtbInvalid`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected like 'negative') THEN 1 ELSE 0 END)
                AS `mtbNegative`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected like 'scanty') THEN 1 ELSE 0 END)
                AS `mtbScanty`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected like '1+') THEN 1 ELSE 0 END)
                AS `mtbPlus1`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected like '2+') THEN 1 ELSE 0 END)
                AS `mtbPlus2`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.mtb_detected is not null AND `res`.mtb_detected like '3+') THEN 1 ELSE 0 END)
                AS `mtbPlus3`,



        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.rif_resistance is not null AND `res`.rif_resistance like 'detected') THEN 1 ELSE 0 END)
                AS `rifDetected`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.rif_resistance is not null AND (`res`.rif_resistance like 'not-detected' OR (`res`.mtb_detected like 'not-detected' AND `res`.rif_resistance like 'na'))) THEN 1 ELSE 0 END)
                AS `rifNotDetected`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND `res`.rif_resistance is not null AND `res`.rif_resistance like 'indeterminate') THEN 1 ELSE 0 END)
                AS `rifIndeterminate`,
        SUM(CASE WHEN (`spm`.attributes->>'$.assay_name' = `rta`.id AND
                        `res`.rif_resistance is not null AND
                        (`res`.mtb_detected IN ('invalid', 'error', 'no-result')  OR
                                (`res`.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') AND
                                `res`.rif_resistance like 'na'))) THEN 1 ELSE 0 END)
                AS `rifUninterpretable`
        FROM `response_result_tb` as `res`

        INNER JOIN `shipment_participant_map` as `spm` ON (`spm`.map_id = `res`.shipment_map_id)
        INNER JOIN `shipment` as `s` ON `spm`.shipment_id = `s`.shipment_id
        INNER JOIN `reference_result_tb` as `ref` ON (`ref`.sample_id = `res`.sample_id and `ref`.shipment_id = `spm`.shipment_id)
        INNER JOIN `r_tb_assay` as `rta` ON `rta`.id = `spm`.attributes->>'$.assay_name'
        WHERE `s`.shipment_id = $shipmentId
        AND (`spm`.response_status is not null AND `spm`.response_status like 'responded' AND `spm`.attributes is not null)
        GROUP BY `ref`.sample_label, tb_assay_id
        ORDER BY tb_assay_id, `ref`.sample_label";
        // error_log($tQuery);
        $summaryPDFData['aggregateCounts'] = $this->db->fetchAll($tQuery);


        $mtbRifSummaryQuery = $this->db->select()
            ->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('ref' => 'reference_result_tb'),
                'ref.shipment_id = spm.shipment_id',
                array(
                    'sample_label' => 'ref.sample_label',
                    'ref_expected_ct' => new Zend_Db_Expr("CASE WHEN ref.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') THEN ref.probe_a ELSE 0 END")
                )
            )
            ->joinLeft(
                array('res' => 'response_result_tb'),
                'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id',
                [
                    'average_ct' => new Zend_Db_Expr('AVG(CASE WHEN res.calculated_score IN (10, 20) AND res.probe_a > 0 THEN res.probe_a ELSE NULL END)')
                ]
            )->joinLeft(array('rta' => 'r_tb_assay'), 'rta.id=`spm`.attributes->>"$.assay_name"', array('assayName' => 'name', 'assayShortName' => 'short_name'))
            ->where("spm.shipment_id = ?", $shipmentId)
            ->where("spm.response_status = 'responded'")
            ->where("rta.id = 1")
            ->group("ref.sample_id")
            ->order("ref.sample_id");
        // die($mtbRifSummaryQuery);
        $summaryPDFData['mtbRifReportSummary'] = $this->db->fetchAll($mtbRifSummaryQuery);


        $mtbRifUltraSummaryQuery = $this->db->select()->from(array('spm' => 'shipment_participant_map'), array())
            ->join(
                array('ref' => 'reference_result_tb'),
                'ref.shipment_id = spm.shipment_id',
                array(
                    'sample_label' => 'ref.sample_label',
                    'ref_expected_ct' => new Zend_Db_Expr("CASE WHEN ref.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') THEN LEAST(ref.rpo_b1, ref.rpo_b2, ref.rpo_b3, ref.rpo_b4) ELSE 0 END")
                )
            )
            ->joinLeft(
                array('res' => 'response_result_tb'),
                'res.shipment_map_id = spm.map_id AND res.sample_id = ref.sample_id',
                [
                    'average_ct' => new Zend_Db_Expr('
                        AVG(
                            CASE
                                WHEN res.calculated_score IN (10, 20) AND
                                    LEAST(
                                        NULLIF(res.rpo_b1, 0),
                                        NULLIF(res.rpo_b2, 0),
                                        NULLIF(res.rpo_b3, 0),
                                        NULLIF(res.rpo_b4, 0)
                                    ) > 0
                                THEN LEAST(
                                        NULLIF(res.rpo_b1, 0),
                                        NULLIF(res.rpo_b2, 0),
                                        NULLIF(res.rpo_b3, 0),
                                        NULLIF(res.rpo_b4, 0)
                                    )
                                ELSE NULL
                            END
                        )
                    ')
                ]
            )->joinLeft(array('rta' => 'r_tb_assay'), 'rta.id=`spm`.attributes->>"$.assay_name"', array('assayName' => 'name', 'assayShortName' => 'short_name'))
            ->where("spm.shipment_id = ?", $shipmentId)
            ->where("spm.response_status = 'responded'")
            ->where("rta.id = 2")
            ->group("ref.sample_id")
            ->order("ref.sample_id");
        // die($mtbRifUltraSummaryQuery);
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
                    '(' . $res['sample_label'] . ') - Score',
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
                    '(' . $res['sample_label'] . ') - Instrument Serial No',
                    '(' . $res['sample_label'] . ') - Xpert Module No',
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

    public function addHeadersFooters(string $html): string
    {
        $issuingAuthority = $GLOBALS['issuingAuthority'];
        $formVersion = $GLOBALS['formVersion'] ?? '';
        $pagerepl = <<<EOF
            @page page0 {
            odd-header-name: html_myHeader1;
            even-header-name: html_myHeader1;
            odd-footer-name: html_myFooter2;
            even-footer-name: html_myFooter2;
            EOF;
        $html = preg_replace('/@page page0 {/', $pagerepl, $html);
        $bodystring = '/<body>/';
        $bodyrepl = <<<EOF
            <body>
                <htmlpageheader name="myHeader1" style="display:none">
                    <div style="text-align: right; font-weight: bold; font-size: 10pt;">
                    <table width="100%">
                        <tr>
                            <td style="text-align:center;font-weight:bold;border-bottom:solid 1px black;"><h2>Xpert TB Proficiency Test Result Form</h2></td>
                        </tr>
                    </table>
                    </div>
                </htmlpageheader>
                <htmlpagefooter name="myFooter2" style="display:none">
                    <table width="100%">
                        <tr>
                            <td width="33%">$formVersion</td>
                            <td width="33%" align="center">{PAGENO} of {nbpg}<br>Issuing Authority: $issuingAuthority</td>
                            <td width="33%" style="text-align: right;">Effective Date : 15-Mar-2024</td>
                        </tr>
                    </table>
                </htmlpagefooter>
            EOF;
        return preg_replace($bodystring, $bodyrepl, $html);
    }
    public function generateFormPDF($shipmentId, $participantId = null, $showCredentials = false, $bulkGeneration = false)
    {

        ini_set("memory_limit", -1);
        // ini_set('display_errors', 0);
        // ini_set('display_startup_errors', 0);
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $query = $this->db->select()
            ->from(['s' => 'shipment'])
            ->join(['ref' => 'reference_result_tb'], 's.shipment_id=ref.shipment_id')
            ->where("s.shipment_id = ?", $shipmentId);
        if (!empty($participantId)) {
            $query = $query
                ->join(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id')
                ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id')
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['id', 'iso_name'])
                ->joinLeft(['pmm' => 'participant_manager_map'], 'p.participant_id=pmm.participant_id', [''])
                ->joinLeft(['d' => 'data_manager'], 'pmm.dm_id=d.dm_id', ['primary_email', 'password'])
                ->where("p.participant_id = ?", $participantId)
                ->group('ref.sample_id');
        }
        // die($query);
        $result = $this->db->fetchAll($query);

        $fileName = "TB-FORM-" . $result[0]['shipment_code'];

        // now we will use this result to create an Excel file and then generate the PDF
        $reader = IOFactory::load(UPLOAD_PATH . "/../files/tb/tb-excel-form.xlsx");
        $sheet = $reader->getSheet(0);


        $sheet->setCellValue('A2', $result[0]['shipment_code']);
        $sheet->setCellValue('R2', Pt_Commons_General::humanReadableDateFormat($result[0]['lastdate_response']));

        if (isset($result[0]['iso_name']) && !empty($result[0]['iso_name'])) {
            $sheet->setCellValue('H2', $result[0]['iso_name']);
        }

        if ($showCredentials === true) {
            $sheet->setCellValue('C9', " " . $result[0]['unique_identifier']);
            $sheet->setCellValue('C11', " " . $result[0]['password']);
        }
        if (!empty($participantId)) {
            $sheet->setCellValue('C5', " " . $result[0]['first_name'] . " " . $result[0]['last_name']);
            $sheet->setCellValue('C7', " " . $result[0]['unique_identifier']);
            $fileName .= "-" . $result[0]['unique_identifier'];
        }
        $eptDomain = rtrim($conf->domain, "/");
        // Create a new RichText object
        $richText = new RichText();

        // Add the first part of the text
        $text = $richText->createText("This form is for your site's proficiency test records only. All results must be submitted in ePT at ");

        // Make the next part bold
        $bold = $richText->createTextRun($eptDomain);
        $bold->getFont()->setBold(true);

        // Add the last part of the text
        $text = $richText->createText(" using your username and password above.");

        // Set the rich text to the cell
        $sheet->setCellValue('A25', $richText);


        $sheet->setCellValue('A43', " " . "If you are experiencing challenges testing the panel or submitting results please contact your Country's PT Coordinator");
        $sheet->getStyle('C14:P14')->getAlignment()->setTextRotation(90);

        $sampleLabelRow = 15;
        foreach ($result as $sampleRow) {
            $sheet->setCellValue('A' . $sampleLabelRow, $sampleRow['sample_label']);
            $sampleLabelRow++;
        }

        $GLOBALS['issuingAuthority'] = $result[0]['issuing_authority'] ?? null;
        if (isset($result[0]['shipment_attributes']) && !empty($result[0]['shipment_attributes'])) {
            $shipmentAttribute = json_decode($result[0]['shipment_attributes'], true);
            $GLOBALS['formVersion'] = $shipmentAttribute['form_version'] ?? null;
        }

        $fileName .= ".pdf";

        $maxRow = 50;
        if ($sheet->getHighestRow() > $maxRow) {
            $sheet->removeRow(($maxRow + 1), $sheet->getHighestRow() - $maxRow);
        }

        $writer = new Mpdf($reader);
        $writer->setEditHtmlCallback([$this, 'addHeadersFooters']);

        if (!file_exists(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . $result[0]['shipment_code'])) {
            mkdir(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . $result[0]['shipment_code'], 0777, true);
        }
        /* if (!file_exists(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . $result[0]['shipment_code'] . DIRECTORY_SEPARATOR . $result[0]['iso_name'])) {
            mkdir(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . $result[0]['shipment_code'] . DIRECTORY_SEPARATOR . $result[0]['iso_name'], 0777, true);
        } */
        $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $result[0]['shipment_code'] . DIRECTORY_SEPARATOR . $fileName);


        return $fileName;
    }

    public function fetchXtptIndicatorsReport($params)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        try {
            /* To get shipment details */
            $shipmentQuery = $db->select()
                ->from('shipment', ['shipment_code'])
                ->where('shipment_id=?', $params['shipmentId']);
            $shipmentResult = $db->fetchRow($shipmentQuery);
            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();

            /* Panel Statistics */
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $panelStatisticsQuery = "SELECT COUNT(spm.map_id) AS participating_sites,
                SUM(CASE WHEN SUBSTRING(spm.evaluation_status, 3, 1) = '1' THEN 1 ELSE 0 END) AS response_received,
                SUM(CASE WHEN spm.is_excluded = 'yes' THEN 1 ELSE 0 END) AS excluded,
                SUM(CASE WHEN IFNULL(spm.is_pt_test_not_performed, 'no') = 'no' THEN 1 ELSE 0 END) AS able_to_submit,
                SUM(CASE WHEN spm.shipment_score >= 80 THEN 1 ELSE 0 END) AS scored_higher_than_80,
                SUM(CASE WHEN spm.shipment_score = 100 THEN 1 ELSE 0 END) AS scored_100
                FROM shipment_participant_map AS spm";
            if (!empty($authNameSpace->dm_id)) {
                $panelStatisticsQuery .= " JOIN participant_manager_map AS pmm ON pmm.participant_id = pmm.participant_id ";
            }
            $panelStatisticsQuery .= " JOIN participant AS p ON p.participant_id = spm.participant_id
                WHERE spm.shipment_id = " . $params['shipmentId'];
            if (!empty($authNameSpace->dm_id)) {
                $panelStatisticsQuery .= " AND pmm.dm_id IN(" . $authNameSpace->dm_id . ") ";
            }
            $panelStatisticsQuery .= ";";
            $panelStatistics = $db->fetchRow($panelStatisticsQuery);
            $sheetIndex = 0;
            $panelStatisticsSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($excel, 'Panel Statistics');
            $excel->addSheet($panelStatisticsSheet, $sheetIndex);
            $panelStatisticsSheet->setTitle('Panel Statistics', true);
            $sheetIndex++;
            $panelStatisticsSheet->mergeCells('A1:D1');
            $panelStatisticsSheet->getCellByColumnAndRow(1, 1)->setValueExplicit(html_entity_decode('Panel Statistics for ' . $shipmentResult['shipment_code'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex(1) . 1)->getFont()->setBold(true);
            $rowIndex = 3;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Participating Sites', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["participating_sites"], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Responses Received', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["response_received"], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);


            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Responses Excluded', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["excluded"], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Participants Able to Submit', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["able_to_submit"], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Participants Scoring 80% or Higher', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["scored_higher_than_80"], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Participants Scoring 100%', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($panelStatistics["scored_100"], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

            $rowIndex = ($rowIndex + 2);
            $columnIndex = 1;

            /* Non participant country */
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $nonParticipatingCountriesQuery = "SELECT countries.iso_name AS country_name,
                CASE WHEN IFNULL(spm.is_pt_test_not_performed, 'no') = 'yes' THEN IFNULL(rntr.ntr_reason, 'Unknown') ELSE NULL END AS not_tested_reason,
                SUM(CASE WHEN IFNULL(spm.is_pt_test_not_performed, 'no') = 'yes' THEN 1 ELSE 0 END) AS is_pt_test_not_performed,
                COUNT(spm.map_id) AS number_of_participants
                FROM shipment_participant_map AS spm
                JOIN participant AS p ON p.participant_id = spm.participant_id
                JOIN countries ON countries.id = p.country ";
            if (!empty($authNameSpace->dm_id)) {
                $nonParticipatingCountriesQuery .= " JOIN participant_manager_map AS pmm ON p.participant_id = pmm.participant_id ";
            }
            $nonParticipatingCountriesQuery .= " LEFT JOIN r_response_not_tested_reasons AS rntr ON rntr.ntr_id = spm.vl_not_tested_reason
                WHERE spm.shipment_id = ?";
            if (!empty($authNameSpace->dm_id)) {
                $nonParticipatingCountriesQuery .= " AND pmm.dm_id IN(" . $authNameSpace->dm_id . ") ";
            }
            $nonParticipatingCountriesQuery .= " GROUP BY countries.iso_name, rntr.ntr_reason ORDER BY countries.iso_name, rntr.ntr_reason ASC;";
            $nonParticipantingCountries = $db->query($nonParticipatingCountriesQuery, array($params['shipmentId']))->fetchAll();
            $nonParticipatingCountriesExist = false;
            $nonParticipationReasons = array();
            foreach ($nonParticipantingCountries as $nonParticipantingCountry) {
                if (isset($nonParticipantingCountry['not_tested_reason']) && !in_array($nonParticipantingCountry['not_tested_reason'], $nonParticipationReasons)) {
                    $nonParticipatingCountriesExist = true;
                    array_push($nonParticipationReasons, $nonParticipantingCountry['not_tested_reason']);
                }
            }
            sort($nonParticipationReasons);
            if ($nonParticipatingCountriesExist) {
                $nonParticipatingCountriesMap = array();
                foreach ($nonParticipantingCountries as $nonParticipantingCountry) {
                    if (!array_key_exists($nonParticipantingCountry['country_name'], $nonParticipatingCountriesMap)) {
                        $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']] = array(
                            'not_participated' => 0,
                            'total_participants' => 0
                        );
                        foreach ($nonParticipationReasons as $nonParticipationReason) {
                            $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']][$nonParticipationReason] = 0;
                        }
                    }
                    $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']]['total_participants'] += intval($nonParticipantingCountry['number_of_participants']);
                    if (isset($nonParticipantingCountry['not_tested_reason'])) {
                        $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']][$nonParticipantingCountry['not_tested_reason']] = intval($nonParticipantingCountry['is_pt_test_not_performed']);
                        $nonParticipatingCountriesMap[$nonParticipantingCountry['country_name']]['not_participated'] += intval($nonParticipantingCountry['is_pt_test_not_performed']);
                    }
                }
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('List of countries with non-participating sites', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
                $columnIndex++;
                foreach ($nonParticipationReasons as $nonParticipationReason) {
                    $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipationReason, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                    $columnIndex++;
                }
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Total', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Rate non-participation', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);

                $rowIndex++;
                foreach ($nonParticipatingCountriesMap as $nonParticipatingCountryName => $nonParticipatingCountryData) {
                    if ($nonParticipatingCountryData['not_participated'] > 0) {
                        $columnIndex = 1;
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipatingCountryName, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                        $columnIndex++;
                        foreach ($nonParticipationReasons as $nonParticipationReason) {
                            if (isset($nonParticipatingCountryData[$nonParticipationReason])) {
                                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipatingCountryData[$nonParticipationReason], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                            }
                            $columnIndex++;
                        }
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($nonParticipatingCountryData['not_participated'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                        $columnIndex++;
                        $notParticipatedRatio = 0;
                        if ($nonParticipatingCountryData['total_participants'] > 0) {
                            $notParticipatedRatio = $nonParticipatingCountryData['not_participated'] / $nonParticipatingCountryData['total_participants'];
                        }
                        $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($notParticipatedRatio, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                    }
                }
                $rowIndex++;
                $columnIndex = 1;
            }
            /* Error code */
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $errorCodesQuery = "SELECT res.error_code, COUNT(*) AS number_of_occurrences
                FROM shipment_participant_map AS spm
                JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
                JOIN participant AS p ON p.participant_id = spm.participant_id ";
            if (!empty($authNameSpace->dm_id)) {
                $errorCodesQuery .= " JOIN participant_manager_map AS pmm ON p.participant_id = pmm.participant_id ";
            }
            $errorCodesQuery .= " WHERE spm.shipment_id = ?
                AND res.error_code <> ''";
            if (!empty($authNameSpace->dm_id)) {
                $errorCodesQuery .= " AND pmm.dm_id IN(" . $authNameSpace->dm_id . ") ";
            }
            $errorCodesQuery .= " GROUP BY res.error_code ORDER BY error_code ASC;";
            // die($errorCodesQuery);
            $errorCodes = $db->query($errorCodesQuery, array($params['shipmentId']))->fetchAll();
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Error Codes Encountered', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Number of Occurrences', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $rowIndex++;
            $columnIndex = 1;
            foreach ($errorCodes as $errorCode) {
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($errorCode['error_code'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($errorCode['number_of_occurrences'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $rowIndex++;
                $columnIndex = 1;
            }

            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $discordantResultsInnerQuery = "FROM (
                SELECT p.unique_identifier,
                    p.lab_name,
                    ref.sample_id,
                    ref.sample_label,
                    res.mtb_detected AS res_mtb,
                    CASE WHEN a.short_name = 'xpert-mtb-rif-ultra' THEN ref.mtb_detected ELSE ref.mtb_detected END AS ref_mtb,
                    res.rif_resistance AS res_rif,
                    CASE WHEN a.short_name = 'xpert-mtb-rif-ultra' THEN ref.rif_resistance ELSE ref.rif_resistance END AS ref_rif,
                    CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') THEN 1 ELSE 0 END AS res_mtb_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace')) OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace')) THEN 1 ELSE 0 END AS ref_mtb_detected,
                    CASE WHEN res.mtb_detected = 'not-detected' THEN 1 ELSE 0 END AS res_mtb_not_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.mtb_detected = 'not-detected') OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.mtb_detected = 'not-detected') THEN 1 ELSE 0 END AS ref_mtb_not_detected,
                    CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') AND res.rif_resistance = 'detected' THEN 1 ELSE 0 END AS res_rif_resistance_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.rif_resistance = 'detected') OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.rif_resistance = 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_detected,
                    CASE WHEN res.mtb_detected IN ('not-detected', 'detected', 'high', 'medium', 'low', 'very-low') AND IFNULL(res.rif_resistance, '') IN ('not-detected', 'na', '') THEN 1 ELSE 0 END AS res_rif_resistance_not_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.rif_resistance <> 'detected') OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.rif_resistance <> 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_not_detected
                    FROM shipment_participant_map AS spm
                    JOIN participant AS p ON p.participant_id = spm.participant_id
                    JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
                    JOIN reference_result_tb AS ref ON ref.shipment_id = spm.shipment_id
                                                    AND ref.sample_id = res.sample_id
                    LEFT JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay_name\")) ";
            if (!empty($authNameSpace->dm_id)) {
                $discordantResultsInnerQuery .= " JOIN participant_manager_map AS pmm ON p.participant_id = pmm.participant_id ";
            }
            $discordantResultsInnerQuery .= " WHERE spm.shipment_id = ?
                    AND SUBSTR(spm.evaluation_status, 3, 1) = '1'
                    AND IFNULL(spm.is_pt_test_not_performed, 'no') <> 'yes'";

            if (!empty($authNameSpace->dm_id)) {
                $discordantResultsInnerQuery .= " AND pmm.dm_id IN(" . $authNameSpace->dm_id . ") ";
            }
            $discordantResultsInnerQuery .= " ) AS rifDetect";
            $discordantResultsQuery = "SELECT rifDetect.sample_label,
                SUM(CASE WHEN rifDetect.res_mtb_detected = 1 AND rifDetect.ref_mtb_not_detected = 1 THEN 1 ELSE 0 END) AS false_positives,
                SUM(CASE WHEN rifDetect.res_mtb_not_detected = 1 AND rifDetect.ref_mtb_detected = 1 THEN 1 ELSE 0 END) AS false_negatives,
                SUM(CASE WHEN rifDetect.res_rif_resistance_detected = 1 AND rifDetect.ref_rif_resistance_not_detected = 1 THEN 1 ELSE 0 END) AS false_resistances
                " . $discordantResultsInnerQuery . "
                GROUP BY rifDetect.sample_id
                ORDER BY rifDetect.sample_id ASC;";
            // die($discordantResultsQuery);
            $discordantResults = $db->query($discordantResultsQuery, array($params['shipmentId']))->fetchAll();
            $rowIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Discordant Results", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            foreach ($discordantResults as $discordantResultAggregate) {
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['sample_label'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
            }
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Total", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("False positives", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $falsePositivesTotal = 0;
            foreach ($discordantResults as $discordantResultAggregate) {
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['false_positives'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $falsePositivesTotal += intval($discordantResultAggregate['false_positives']);
            }
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($falsePositivesTotal, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("False negatives", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $falseNegativesTotal = 0;
            foreach ($discordantResults as $discordantResultAggregate) {
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['false_negatives'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $falseNegativesTotal += intval($discordantResultAggregate['false_negatives']);
            }
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($falseNegativesTotal, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("False resistance", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $falseResistanceTotal = 0;
            foreach ($discordantResults as $discordantResultAggregate) {
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantResultAggregate['false_resistances'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $falseResistanceTotal += intval($discordantResultAggregate['false_resistances']);
            }
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($falseResistanceTotal, ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);


            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $discordantCountriesQuery = "SELECT rifDetect.country_name,
                SUM(CASE WHEN (rifDetect.res_mtb_detected = 1 AND rifDetect.ref_mtb_not_detected = 1) OR (rifDetect.res_mtb_not_detected = 1 AND rifDetect.ref_mtb_detected = 1) OR (rifDetect.res_rif_resistance_detected = 1 AND rifDetect.ref_rif_resistance_not_detected = 1) THEN 1 ELSE 0 END) AS discordant,
                COUNT(rifDetect.country_id) AS total_results
                FROM (
                SELECT countries.id AS country_id,
                    countries.iso_name AS country_name,
                    CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') THEN 1 ELSE 0 END AS res_mtb_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace')) OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace')) THEN 1 ELSE 0 END AS ref_mtb_detected,
                    CASE WHEN res.mtb_detected = 'not-detected' THEN 1 ELSE 0 END AS res_mtb_not_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.mtb_detected = 'not-detected') OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.mtb_detected = 'not-detected') THEN 1 ELSE 0 END AS ref_mtb_not_detected,
                    CASE WHEN res.mtb_detected IN ('detected', 'high', 'medium', 'low', 'very-low', 'trace') AND res.rif_resistance = 'detected' THEN 1 ELSE 0 END AS res_rif_resistance_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.rif_resistance = 'detected') OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.rif_resistance = 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_detected,
                    CASE WHEN res.mtb_detected IN ('not-detected', 'detected', 'high', 'medium', 'low', 'very-low') AND IFNULL(res.rif_resistance, '') IN ('not-detected', 'na', '') THEN 1 ELSE 0 END AS res_rif_resistance_not_detected,
                    CASE WHEN (a.short_name = 'xpert-mtb-rif-ultra' AND ref.rif_resistance <> 'detected') OR (IFNULL(a.short_name, 'xpert-mtb-rif') = 'xpert-mtb-rif' AND ref.rif_resistance <> 'detected') THEN 1 ELSE 0 END AS ref_rif_resistance_not_detected
                FROM shipment_participant_map AS spm
                JOIN participant AS p ON p.participant_id = spm.participant_id
                JOIN countries ON countries.id = p.country
                JOIN response_result_tb AS res ON res.shipment_map_id = spm.map_id
                JOIN reference_result_tb AS ref ON ref.shipment_id = spm.shipment_id
                                                AND ref.sample_id = res.sample_id
                LEFT JOIN r_tb_assay AS a ON a.id = JSON_UNQUOTE(JSON_EXTRACT(spm.attributes, \"$.assay_name\")) ";
            if (!empty($authNameSpace->dm_id)) {
                $discordantCountriesQuery .= " JOIN participant_manager_map AS pmm ON p.participant_id = pmm.participant_id ";
            }
            $discordantCountriesQuery .= " WHERE spm.shipment_id = 23
                AND SUBSTR(spm.evaluation_status, 3, 1) = '1'
                AND IFNULL(spm.is_pt_test_not_performed, 'no') <> 'yes'";
            if (!empty($authNameSpace->dm_id)) {
                $discordantCountriesQuery .= " AND pmm.dm_id IN(" . $authNameSpace->dm_id . ") ";
            }
            $discordantCountriesQuery .= " ) AS rifDetect GROUP BY rifDetect.country_id ORDER BY rifDetect.country_name ASC;";
            // die($discordantCountriesQuery);
            $discordantCountries = $db->query($discordantCountriesQuery, array($params['shipmentId']))->fetchAll();
            $rowIndex++;
            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('List the countries reporting discordant results + count of discordant results', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $panelStatisticsSheet->mergeCells("A" . ($rowIndex) . ":C" . ($rowIndex));
            $rowIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('Country', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('# Discordant', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode('% Discordant', ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            foreach ($discordantCountries as $discordantCountry) {
                $rowIndex++;
                $columnIndex = 1;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantCountry['country_name'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode(intval($discordantCountry['discordant']), ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
                $columnIndex++;
                $countryDiscordantRatio = 0;
                if (intval($discordantCountry['total_results']) > 0) {
                    $countryDiscordantRatio = intval($discordantCountry['discordant']) /  intval($discordantCountry['total_results']);
                }
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($countryDiscordantRatio, ENT_QUOTES, 'UTF-8'), DataType::TYPE_NUMERIC);
            }

            $discordantResultsParticipantsQuery = "SELECT LPAD(rifDetect.unique_identifier, 10, '0') AS sorting_unique_identifier,
            rifDetect.unique_identifier,
            rifDetect.lab_name,
            rifDetect.sample_label,
            rifDetect.sample_id,
            CASE
                WHEN rifDetect.res_mtb = 'error' THEN 'Error'
                WHEN rifDetect.res_mtb = 'not-detected' THEN 'Not Detected'
                WHEN rifDetect.res_mtb = 'no-result' THEN 'No Result'
                WHEN rifDetect.res_mtb = 'very-low' THEN 'Very Low'
                WHEN rifDetect.res_mtb = 'trace' THEN 'Trace'
                WHEN rifDetect.res_mtb = 'na' THEN 'N/A'
                WHEN IFNULL(rifDetect.res_mtb, '') = '' THEN NULL
                ELSE CONCAT(UPPER(SUBSTRING(rifDetect.res_mtb, 1, 1)), SUBSTRING(rifDetect.res_mtb, 2, 254))
            END AS res_mtb_detected,
            CASE
                WHEN rifDetect.ref_mtb = 'error' THEN 'Error'
                WHEN rifDetect.ref_mtb = 'not-detected' THEN 'Not Detected'
                WHEN rifDetect.ref_mtb = 'no-result' THEN 'No Result'
                WHEN rifDetect.ref_mtb = 'very-low' THEN 'Very Low'
                WHEN rifDetect.ref_mtb = 'trace' THEN 'Trace'
                WHEN rifDetect.ref_mtb = 'na' THEN 'N/A'
                WHEN IFNULL(rifDetect.ref_mtb, '') = '' THEN NULL
                ELSE CONCAT(UPPER(SUBSTRING(rifDetect.ref_mtb, 1, 1)), SUBSTRING(rifDetect.ref_mtb, 2, 254))
            END AS ref_mtb_detected,
            CASE
                WHEN rifDetect.res_rif = 'error' THEN 'Error'
                WHEN rifDetect.res_rif = 'not-detected' THEN 'Not Detected'
                WHEN rifDetect.res_rif = 'no-result' THEN 'No Result'
                WHEN rifDetect.res_rif = 'invalid' THEN 'Invalid'
                WHEN rifDetect.res_rif IN ('detected', 'trace', 'very-low', 'low', 'medium', 'high') AND IFNULL(rifDetect.res_rif, 'na') = 'na' THEN 'Not Detected'
                WHEN rifDetect.res_rif = 'not-detected' THEN 'Not Detected'
                WHEN rifDetect.res_rif = 'no-result' THEN 'No Result'
                WHEN rifDetect.res_rif = 'very-low' THEN 'Very Low'
                WHEN rifDetect.res_rif = 'na' THEN 'N/A'
                WHEN rifDetect.res_rif = 'not-detected' AND IFNULL(rifDetect.res_rif, '') = '' THEN 'N/A'
                WHEN rifDetect.res_rif IN ('no-result', 'not-detected', 'invalid') AND IFNULL(rifDetect.res_rif, '') = '' THEN 'N/A'
                ELSE CONCAT(UPPER(SUBSTRING(rifDetect.res_rif, 1, 1)), SUBSTRING(rifDetect.res_rif, 2, 254))
            END AS res_rif_resistance,
            CASE
                WHEN rifDetect.ref_rif = 'error' THEN 'Error'
                WHEN rifDetect.ref_rif = 'not-detected' THEN 'Not Detected'
                WHEN rifDetect.ref_rif = 'no-result' THEN 'No Result'
                WHEN rifDetect.ref_rif = 'invalid' THEN 'Invalid'
                WHEN rifDetect.ref_rif IN ('detected', 'trace', 'very-low', 'low', 'medium', 'high') AND IFNULL(rifDetect.ref_rif, 'na') = 'na' THEN 'Not Detected'
                WHEN rifDetect.ref_rif = 'not-detected' THEN 'Not Detected'
                WHEN rifDetect.ref_rif = 'no-result' THEN 'No Result'
                WHEN rifDetect.ref_rif = 'very-low' THEN 'Very Low'
                WHEN rifDetect.ref_rif = 'na' THEN 'N/A'
                WHEN rifDetect.ref_rif = 'not-detected' AND IFNULL(rifDetect.ref_rif, '') = '' THEN 'N/A'
                WHEN rifDetect.ref_mtb IN ('no-result', 'not-detected', 'invalid') AND IFNULL(rifDetect.ref_rif, '') = '' THEN 'N/A'
                ELSE CONCAT(UPPER(SUBSTRING(rifDetect.ref_rif, 1, 1)), SUBSTRING(rifDetect.ref_rif, 2, 254))
            END AS ref_rif_resistance,
            CASE
                WHEN rifDetect.res_mtb_detected = 1 AND rifDetect.ref_mtb_not_detected = 1 THEN 'False Positive'
                WHEN rifDetect.res_mtb_not_detected = 1 AND rifDetect.ref_mtb_detected = 1 THEN 'False Negative'
                WHEN rifDetect.res_rif_resistance_detected = 1 AND rifDetect.ref_rif_resistance_not_detected = 1 THEN 'False Resistance Detected'
            END AS non_concordance_reason
            " . $discordantResultsInnerQuery . "
            WHERE (rifDetect.res_mtb_detected = 1 AND rifDetect.ref_mtb_not_detected = 1)
            OR (rifDetect.res_mtb_not_detected = 1 AND rifDetect.ref_mtb_detected = 1)
            OR (rifDetect.res_rif_resistance_detected = 1 AND rifDetect.ref_rif_resistance_not_detected = 1)
            ORDER BY sorting_unique_identifier ASC, sample_id ASC;";
            // die($discordantResultsParticipantsQuery);
            $discordantParticipants = $db->query($discordantResultsParticipantsQuery, array($params['shipmentId']))->fetchAll();
            $rowIndex++;
            $rowIndex++;
            $columnIndex = 1;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("List the participants reporting discordant results", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $panelStatisticsSheet->mergeCells("A" . ($rowIndex) . ":H" . ($rowIndex));
            $rowIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("PT ID", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Participant", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Sample", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("MTB Detected", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected MTB Detected", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Rif Resistance", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Expected Rif Resistance", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            $columnIndex++;
            $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode("Reason for Discordance", ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            $panelStatisticsSheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getFont()->setBold(true);
            foreach ($discordantParticipants as $discordantParticipant) {
                $rowIndex++;
                $columnIndex = 1;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['unique_identifier'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['lab_name'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['sample_label'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['res_mtb_detected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['ref_mtb_detected'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['res_rif_resistance'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['ref_rif_resistance'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
                $columnIndex++;
                $panelStatisticsSheet->getCellByColumnAndRow($columnIndex, $rowIndex)->setValueExplicit(html_entity_decode($discordantParticipant['non_concordance_reason'], ENT_QUOTES, 'UTF-8'), DataType::TYPE_STRING);
            }
            foreach (range('A', 'Z') as $columnID) {
                $panelStatisticsSheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            $this->fetchTbAllSitesResultsSheet($db, $params['shipmentId'], $excel, $sheetIndex);
            // die("hi");


            if (!file_exists(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-tb-reports")) {
                mkdir(TEMP_UPLOAD_PATH  . DIRECTORY_SEPARATOR . "generated-tb-reports", 0777, true);
            }
            $fileSafeShipmentCode = str_replace(' ', '-', str_replace(array_merge(
                array_map('chr', range(0, 31)),
                array('<', '>', ':', '"', '/', '\\', '|', '?', '*')
            ), '', $shipmentResult['shipment_code']));

            $excel->setActiveSheetIndex(0);
            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = $fileSafeShipmentCode . '-xtpt-indicators-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . 'generated-tb-reports' . DIRECTORY_SEPARATOR .  $filename);
            return array(
                "report-name" => $filename
            );
        } catch (Exception $exc) {
            error_log("GENERATE-PARTICIPANT-PERFORMANCE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return "";
        }
    }

    public function fetchTbAllSitesResultsSheet($db, $shipmentId, $excel, $sheetIndex)
    {
        $queryString = file_get_contents(sprintf('%s/Reports/getTbAllSitesResultsSheet.sql', __DIR__));

        $authNameSpace = new Zend_Session_Namespace('administrators');
        $userCondition = "";
        if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
            $queryString = str_replace("{USER_CONDITION}", $userCondition, $queryString);
            $query = $db->query($queryString, [$shipmentId]);
        } else {
            $queryString = str_replace("{USER_CONDITION}", $userCondition, $queryString);
            $query = $db->query($queryString, [$shipmentId]);
        }

        $results = $query->fetchAll();

        $sheet = new Worksheet($excel, "All Sites' Results");
        $excel->addSheet($sheet, $sheetIndex);
        $columnIndex = 0;
        if (!empty($results[0])) {
            foreach ($results[0] as $columnName => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . 1, html_entity_decode($columnName, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($columnIndex) . 1)->getFont()->setBold(true);
                $columnIndex++;
            }
        }

        $sheet->getDefaultRowDimension()->setRowHeight(15);
        $rowNumber = 1; // $row 0 is already the column headings

        foreach ($results as $result) {
            $rowNumber++;
            $columnIndex = 0;
            foreach ($result as $columnName => $value) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . $rowNumber, html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $columnIndex++;
            }
        }

        foreach (range('A', 'Z') as $columnID) {
            $sheet->getColumnDimension($columnID)
                ->setAutoSize(true);
        }
        return $sheet;
    }
}
