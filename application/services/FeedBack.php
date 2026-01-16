<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class Application_Service_FeedBack
{
    protected $tempUploadDirectory;

    /** @var Zend_Translate */
    protected $translator;
    public function __construct()
    {
        $this->tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
        $this->translator = Zend_Registry::get('translate');
    }
    public function getFeedBackQuestions($sid)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackQuestions($sid);
    }

    public function getFeedBackQuestionsById($id, $type = '')
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackQuestionsById($id, $type);
    }

    public function getFeedBackFormsById($id, $type = '')
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackFormsById($id, $type);
    }

    public function getFeedBackAnswers($sid, $pid, $mid)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackAnswers($sid, $pid, $mid);
    }

    public function saveFeedbackQuestions($params)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        if ($db->saveFeedbackQuestionsDetails($params)) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Question saved successfully';
        }
    }
    public function saveShipmentQuestionMap($params)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        if ($db->saveShipmentQuestionMapDetails($params)) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Question mapped successfully';
        }
    }

    public function checkExpiry($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchRow($db->select()->from('shipment')->where("shipment_id = ?", $sid)->where("DATE(feedback_expiry_date) >= ?", date('Y-m-d')));
    }

    public function getAllFeedBackResponses($parameters, $type)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchAllFeedBackResponses($parameters, $type);
    }

    public function getAllIrelaventActiveQuestions($sid)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchAllIrelaventActiveQuestions($sid);
    }

    public function saveFeedBackForms($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        foreach ($params['questionId'] as $key => $q) {
            if (isset($params['answer'][$key]['date']) && !empty($params['answer'][$key]['date'])) {
                $answer = Pt_Commons_General::isoDateFormat($params['answer'][$key]['date']);
            } else {
                $answer = $params['answer'][$key];
            }

            $dataArr = [
                'shipment_id' => $params["shipmentId"],
                'question_id' => $q,
                'participant_id' => $params['participantId'],
                'map_id' => $params['mapId'],
                'answer' => $answer,
                'updated_datetime' => Pt_Commons_General::getDateTime(),
                'modified_by' => $authNameSpace->admin_id
            ];

            $db->insert(
                'participant_feedback_answer',
                $dataArr
            );
        }
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $alertMsg->message = "Your feedback response successfully submitted.";
    }

    public function exportFeedbackResponseReport($shipmentId)
    {
        try {
            $excel = new Spreadsheet();
            $sheet = $excel->getActiveSheet();
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();

            // Fetch feedback form details
            $feedbackFormSql = $db->select()
                ->from(['rpf' => 'r_participant_feedback_form'], ['*'])
                ->join(['sl' => 'scheme_list'], 'rpf.scheme_type=sl.scheme_id', ['scheme_name'])
                ->join(['s' => 'shipment'], 'rpf.shipment_id=s.shipment_id', ['shipment_code'])
                ->where('rpf.shipment_id = ?', $shipmentId);
            $feedbackForm = $db->fetchRow($feedbackFormSql);

            // Fetch all questions for this shipment
            $questionsSql = $db->select()
                ->from(['rfq' => 'r_feedback_questions'], ['question_id', 'question_text'])
                ->join(['rpfq' => 'r_participant_feedback_form_question_map'], 'rfq.question_id=rpfq.question_id', ['sort_order'])
                ->where('rpfq.shipment_id = ?', $shipmentId)
                ->order('rpfq.sort_order ASC');
            $questions = $db->fetchAll($questionsSql);

            // Fetch all participant responses
            $responsesSql = $db->select()
                ->from(['pfa' => 'participant_feedback_answer'], ['participant_id', 'question_id', 'answer', 'updated_datetime'])
                ->join(['p' => 'participant'], 'pfa.participant_id=p.participant_id', ['first_name', 'last_name', 'unique_identifier'])
                ->where('pfa.shipment_id = ?', $shipmentId)
                ->order('p.first_name ASC');
            // die($responsesSql);
            $responses = $db->fetchAll($responsesSql);

            // Organize data by participant
            $participantData = [];
            foreach ($responses as $response) {
                $participantId = $response['participant_id'];
                $participantName = trim($response['first_name'] . ' ' . $response['last_name']);

                if (!isset($participantData[$participantId])) {
                    $participantData[$participantId] = [
                        'name' => $participantName,
                        'unique_identifier' => $response['unique_identifier'],
                        'response_datetime' => $response['updated_datetime'],
                        'answers' => []
                    ];
                }

                // Keep the latest updated_datetime if there are multiple responses
                if (!empty($response['updated_datetime'])) {
                    if (
                        empty($participantData[$participantId]['response_datetime']) ||
                        strtotime($response['updated_datetime']) > strtotime($participantData[$participantId]['response_datetime'])
                    ) {
                        $participantData[$participantId]['response_datetime'] = $response['updated_datetime'];
                    }
                }

                $participantData[$participantId]['answers'][$response['question_id']] = $response['answer'];
            }

            /* // Set title and form info
            $sheet->mergeCells('A1:E1');
            $sheet->getCell('A1')->setValueExplicit(
                html_entity_decode('Feedback Response Report', ENT_QUOTES, 'UTF-8')
            );
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

            // Shipment Code and Form Content
            $lastCol = Coordinate::stringFromColumnIndex(count($questions) + 1);
            $sheet->mergeCells('A2:' . $lastCol . '2');
            $formInfo = 'Shipment Code: ' . ($feedbackForm['shipment_code'] ?? '') . ' | ' .
                ($feedbackForm['form_content'] ?? '');
            $sheet->getCell('A2')->setValueExplicit(
                html_entity_decode($formInfo, ENT_QUOTES, 'UTF-8')
            );
            $sheet->getStyle('A2')->getFont()->setBold(true);*/

            // Add empty row
            $rowIndex = 1;

            // Headers - Participant Name + Response Date/Time + Question columns
            $colNo = 0;

            // First column - Participant ID
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->setValueExplicit(html_entity_decode('Participant Identifier', ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD3D3D3');
            $colNo++;

            // Second column - Participant Name
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->setValueExplicit(html_entity_decode('Participant Name', ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD3D3D3');
            $colNo++;

            // Third column - Response Date/Time
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->setValueExplicit(html_entity_decode('Response Date/Time', ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD3D3D3');
            $colNo++;

            // Question columns
            foreach ($questions as $question) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                    ->setValueExplicit(html_entity_decode($question['question_text'], ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                    ->getFont()->setBold(true);
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                    ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD3D3D3');
                $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex)
                    ->getAlignment()->setWrapText(true);
                $colNo++;
            }

            // Data rows - One row per participant
            $rowIndex++;

            foreach ($participantData as $participantId => $participant) {
                $colNo = 0;

                // unique identifier
                $cellAddress = Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex;
                $sheet->getCell($cellAddress)->setValueExplicit(
                    html_entity_decode($participant['unique_identifier'], ENT_QUOTES, 'UTF-8')
                );
                $colNo++;

                // Participant Name
                $cellAddress = Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex;
                $sheet->getCell($cellAddress)->setValueExplicit(
                    html_entity_decode($participant['name'], ENT_QUOTES, 'UTF-8')
                );
                $colNo++;

                // Response Date/Time
                $cellAddress = Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex;
                $responseDateTime = !empty($participant['response_datetime'])
                    ? date('d-M-Y H:i:s', strtotime($participant['response_datetime']))
                    : '';
                $sheet->getCell($cellAddress)->setValueExplicit(
                    html_entity_decode($responseDateTime, ENT_QUOTES, 'UTF-8')
                );
                $colNo++;

                // Responses for each question
                foreach ($questions as $question) {
                    $questionId = $question['question_id'];
                    $answer = isset($participant['answers'][$questionId])
                        ? $participant['answers'][$questionId]
                        : '';

                    $cellAddress = Coordinate::stringFromColumnIndex($colNo + 1) . $rowIndex;
                    $sheet->getCell($cellAddress)->setValueExplicit(
                        html_entity_decode($answer, ENT_QUOTES, 'UTF-8')
                    );
                    $sheet->getStyle($cellAddress)->getAlignment()->setWrapText(true);
                    $colNo++;
                }

                $rowIndex++;
            }

            // Auto-size columns
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);

            // Set width for question columns
            for ($i = 2; $i <= count($questions) + 1; $i++) {
                $columnLetter = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getColumnDimension($columnLetter)->setWidth(30);
            }

            // Save file
            if (!file_exists($this->tempUploadDirectory) && !is_dir($this->tempUploadDirectory)) {
                mkdir($this->tempUploadDirectory, 0777, true);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = $feedbackForm['shipment_code'] . '-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($this->tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);

            return $filename;
        } catch (Exception $exc) {
            error_log("GENERATE-FEEDBACK-RESPONSE-REPORT-EXCEL--" . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return "";
        }
    }
}
