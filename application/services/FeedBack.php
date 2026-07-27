<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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

    public function getFeedBackFormsById($id)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackFormsById($id);
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
        $result = $db->saveShipmentQuestionMapDetails($params);
        if (!empty($result['saved'])) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = !empty($result['feedbackTurnedOn'])
                ? 'Feedback form saved. Collect Feedback has been switched on for this shipment.'
                : 'Feedback form saved.';
        }
    }

    /**
     * Feedback is only ever collected for TB shipments, and only when the global
     * participant_feedback config is switched on.
     */
    public static function isFeedbackEnabledGlobally(): bool
    {
        static $enabled = null;
        if ($enabled === null) {
            $enabled = (Application_Service_Common::getConfig('participant_feedback') === 'yes');
        }
        return $enabled;
    }

    /** How many questions are mapped to this shipment's feedback form. */
    public static function getMappedQuestionCount($shipmentId): int
    {
        $shipmentId = (int) $shipmentId;
        if ($shipmentId <= 0) {
            return 0;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return (int) $db->fetchOne(
            $db->select()
                ->from('r_participant_feedback_form_question_map', new Zend_Db_Expr('COUNT(*)'))
                ->where('shipment_id = ?', $shipmentId)
        );
    }

    /**
     * Shipment ids that have `collect_feedback = 'yes'` but no questions mapped yet.
     *
     * Used to block finalization: once an admin promises participants a feedback form,
     * finalizing is what opens the feedback window, so an empty form would strand them
     * on a blank page. Read once per request — the map table is small and every report
     * grid calls this per row.
     *
     * @return array<int,bool> shipment_id => true
     */
    public static function shipmentsAwaitingFeedbackForm(): array
    {
        static $pending = null;
        if ($pending !== null) {
            return $pending;
        }
        $pending = [];
        if (!self::isFeedbackEnabledGlobally()) {
            return $pending;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['s' => 'shipment'], ['shipment_id'])
            ->joinLeft(
                ['m' => 'r_participant_feedback_form_question_map'],
                'm.shipment_id = s.shipment_id',
                []
            )
            ->where("s.collect_feedback = 'yes'")
            ->group('s.shipment_id')
            ->having('COUNT(m.question_id) = 0');
        foreach ($db->fetchCol($sql) as $shipmentId) {
            $pending[(int) $shipmentId] = true;
        }
        return $pending;
    }

    /** True when this shipment has feedback switched on but no form built yet. */
    public static function isAwaitingFeedbackForm($shipmentId): bool
    {
        return isset(self::shipmentsAwaitingFeedbackForm()[(int) $shipmentId]);
    }

    /**
     * shipment_id => number of mapped questions, for every shipment that has a form.
     * Read once per request so listing grids can badge each row without an N+1.
     *
     * @return array<int,int>
     */
    public static function feedbackFormQuestionCounts(): array
    {
        static $counts = null;
        if ($counts !== null) {
            return $counts;
        }
        $counts = [];
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from('r_participant_feedback_form_question_map', [
                    'shipment_id',
                    'questionCount' => new Zend_Db_Expr('COUNT(*)'),
                ])
                ->group('shipment_id')
        );
        foreach ($rows as $row) {
            $counts[(int) $row['shipment_id']] = (int) $row['questionCount'];
        }
        return $counts;
    }

    /** How many questions this shipment's form has, from the per-request map. */
    public static function questionCountFor($shipmentId): int
    {
        return self::feedbackFormQuestionCounts()[(int) $shipmentId] ?? 0;
    }

    /**
     * The form builder lives behind `config-ept`, so report/evaluate pages only offer
     * the shortcut to admins who can actually open it.
     */
    public static function currentAdminCanBuildForms(): bool
    {
        // Memoized: listing grids ask this once per row.
        static $canBuild = null;
        if ($canBuild === null) {
            $adminSession = new Zend_Session_Namespace('administrators');
            $canBuild = in_array('config-ept', explode(',', (string) $adminSession->privileges));
        }
        return $canBuild;
    }

    /**
     * Shipments that already have a feedback form, for the "copy from" picker.
     * Newest first so the previous round is the obvious first choice.
     */
    public function getShipmentsWithFeedbackForm($excludeShipmentId = null): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['s' => 'shipment'], ['shipment_id', 'shipment_code', 'shipment_date'])
            ->join(
                ['m' => 'r_participant_feedback_form_question_map'],
                'm.shipment_id = s.shipment_id',
                ['questionCount' => new Zend_Db_Expr('COUNT(m.question_id)')]
            )
            ->group('s.shipment_id')
            ->order('s.shipment_date DESC')
            ->order('s.shipment_id DESC');
        if (!empty($excludeShipmentId)) {
            $sql->where('s.shipment_id != ?', (int) $excludeShipmentId);
        }
        return $db->fetchAll($sql);
    }

    /**
     * Everything the builder needs to render a form for one shipment: the full active
     * question list flagged with that shipment's mapping, plus its saved content and
     * audience. Drives both "shipment changed" and "copy from another shipment" —
     * copying is read-only, the source form is never touched.
     */
    public function getFeedbackFormPayload($shipmentId): array
    {
        $shipmentId = (int) $shipmentId;
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $form = $db->fetchRow(
            $db->select()
                ->from('r_participant_feedback_form', ['form_content', 'form_show_to'])
                ->where('shipment_id = ?', $shipmentId)
        );

        $questions = [];
        foreach ($this->getAllActiveQuestions($shipmentId) as $row) {
            $questions[] = [
                'questionId' => (int) $row['question_id'],
                'label' => $row['question_text'] . ' (' . $row['question_code'] . ')',
                'selected' => !empty($row['mappedShipmentId']),
                'mandatory' => ($row['is_response_mandatory'] === 'yes'),
                'sortOrder' => $row['sort_order'],
            ];
        }

        return [
            'formContent' => $form['form_content'] ?? '',
            'formShowTo' => $form['form_show_to'] ?? '',
            'questions' => $questions,
        ];
    }

    public function checkExpiry($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchRow($db->select()->from('shipment')->where('shipment_id = ?', $sid)->where('DATE(feedback_expiry_date) >= ?', date('Y-m-d')));
    }

    public function getAllFeedBackResponses($parameters, $type)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchAllFeedBackResponses($parameters, $type);
    }

    public function getAllActiveQuestions($sid)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchAllActiveQuestions($sid);
    }

    public function saveFeedBackForms($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        foreach ($params['questionId'] as $key => $q) {
            if (isset($params['answer'][$key]['date']) && !empty($params['answer'][$key]['date'])) {
                $answer = Pt_Commons_DateUtility::isoDateFormat($params['answer'][$key]['date']);
            } else {
                $answer = $params['answer'][$key];
            }

            $dataArr = [
                'shipment_id' => $params['shipmentId'],
                'question_id' => $q,
                'participant_id' => $params['participantId'],
                'map_id' => $params['mapId'],
                'answer' => $answer,
                'updated_datetime' => Pt_Commons_DateUtility::getCurrentDateTime(),
                'modified_by' => $authNameSpace->admin_id,
            ];

            $db->insert(
                'participant_feedback_answer',
                $dataArr
            );
        }
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $alertMsg->message = 'Your feedback response successfully submitted.';
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
                        'answers' => [],
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
            Pt_Commons_LoggerUtility::logError('Failed to generate feedback response report (Excel): ' . $exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }
}
