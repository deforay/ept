<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{
    protected $_name = 'r_feedback_questions';

    protected $_primary = 'question_id';

    /**
     * The feedback form one participant sees: its questions and files, with the
     * passing-only / failing-only ones kept to that participant's own result.
     *
     * @param int $sid Shipment id
     * @param int $mapId That participant's shipment_participant_map row
     */
    public function fetchFeedBackQuestions($sid, $mapId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $finalResult = $db->fetchOne(
            $db->select()
                ->from('shipment_participant_map', 'final_result')
                ->where('map_id = ?', (int) $mapId)
                ->where('shipment_id = ?', (int) $sid)
        );
        // Who an item is shown to, for this participant's result only. Before, this
        // joined every participant in the shipment, so one pass anywhere showed the
        // passing-only items to everyone.
        $visibleTo = ['all-participants'];
        if ((string) $finalResult === '1') {
            $visibleTo[] = 'passing-participants';
        } elseif ($finalResult !== false && $finalResult !== null && $finalResult !== '') {
            $visibleTo[] = 'failing-participants';
        }

        $sql = $db->select()->from(['rfq' => 'r_feedback_questions'], ['*'])
            ->join(['rpfq' => 'r_participant_feedback_form_question_map'], 'rfq.question_id=rpfq.question_id', ['is_response_mandatory', 'sort_order'])
            ->joinLeft(['sl' => 'scheme_list'], 'rpfq.scheme_type=sl.scheme_id', ['scheme_name'])
            ->joinLeft(['s' => 'shipment'], 'rpfq.shipment_id=s.shipment_id', ['shipment_code'])
            ->where('rpfq.shipment_id = ?', (int) $sid)
            ->where('rfq.question_show_to IS NULL OR rfq.question_show_to IN (?)', $visibleTo)
            ->order('rpfq.sort_order asc')
            ->group('rfq.question_id');
        $result['result'] = $db->fetchAll($sql);

        // Fetch feedback form results
        $feedbackFormSql = $db->select()
            ->from(['rpf' => 'r_participant_feedback_form'], ['*'])
            ->joinLeft(['sl' => 'scheme_list'], 'rpf.scheme_type=sl.scheme_id', ['scheme_name'])
            ->joinLeft(['s' => 'shipment'], 'rpf.shipment_id=s.shipment_id', ['shipment_code'])
            ->where('rpf.shipment_id = ?', (int) $sid);
        $result['feedback_form_results'] = $db->fetchRow($feedbackFormSql);

        $filesMapSql = $db->select()
            ->from(['rpff' => 'r_participant_feedback_form_files_map'], ['*'])
            ->where('rpff.shipment_id = ?', (int) $sid)
            ->where('rpff.files_show_to IS NULL OR rpff.files_show_to IN (?)', $visibleTo)
            ->order(new Zend_Db_Expr('rpff.sort_order IS NULL ASC'))
            ->order('rpff.sort_order ASC');
        $result['feedback_form_files_results'] = $db->fetchAll($filesMapSql);

        return $result;
    }
    public function fetchFeedBackQuestionsById($id, $type)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['rfq' => 'r_feedback_questions'], ['*']);
        if ($type == 'mapped') {
            $sql = $sql->join(['rpfq' => 'r_participant_feedback_form_question_map'], 'rfq.question_id=rpfq.question_id', ['*']);
            $sql = $sql->join(['rpff' => 'r_participant_feedback_form_files_map'], 'rpfq.rpff_id=rpff.rpff_id', ['*']);
            $sql = $sql->where('rpfq.shipment_id =?', $id);
            return $db->fetchAll($sql);
        } else {
            $sql = $sql->where('rfq.question_id =?', $id);
            return $db->fetchRow($sql);
        }
    }

    /**
     * Fetch feedback forms data by shipment ID with separate results for each table
     *
     * @param int $id Shipment ID
     * @return array Array containing separate results for feedback form, questions, and files
     */
    public function fetchFeedBackFormsById($id)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $result = [];

        // Fetch feedback form results
        $feedbackFormSql = $db->select()
            ->from(['rpf' => 'r_participant_feedback_form'], ['*'])
            ->where('rpf.shipment_id = ?', $id);
        $result['feedback_form_results'] = $db->fetchRow($feedbackFormSql);

        // Fetch feedback form question mapping results
        $questionMapSql = $db->select()
            ->from(['rfq' => 'r_feedback_questions'], ['*'])
            ->joinLeft(
                ['rpfq' => 'r_participant_feedback_form_question_map'],
                'rfq.question_id=rpfq.question_id AND rpfq.shipment_id = ' . $id,
                ['questionId' => 'question_id', '*']
            )
            ->joinLeft(
                ['spm' => 'shipment_participant_map'],
                'spm.shipment_id=rpfq.shipment_id',
                ['spm.map_id', 'final_result']
            )
            ->where("(
                    rfq.question_show_to IS NULL
                    OR rfq.question_show_to = 'all-participants' 
                    OR (rfq.question_show_to = 'passing-participants' AND spm.final_result = 1 AND rfq.question_show_to != 'failing-participants' AND rfq.question_show_to != 'all-participants')
                    OR (rfq.question_show_to = 'failing-participants' AND spm.final_result != 1 AND rfq.question_show_to != 'passing-participants' AND rfq.question_show_to != 'all-participants')
                )")
            ->group('rfq.question_id')
            ->order('rpfq.sort_order ASC');
        $result['feedback_form_question_results'] = $db->fetchAll($questionMapSql);

        // Every attachment on the form. This is the admin builder's view, so no
        // per-participant filtering: an attachment missing here is dropped on save.
        $filesMapSql = $db->select()
            ->from(['rpff' => 'r_participant_feedback_form_files_map'], ['*'])
            ->where('rpff.shipment_id = ?', $id)
            ->order(new Zend_Db_Expr('rpff.sort_order IS NULL ASC'))
            ->order('rpff.sort_order ASC')
            ->order('rpff.rpf_id ASC');
        $result['feedback_form_files_results'] = $db->fetchAll($filesMapSql);

        return $result;
    }
    /**
     * Every active question, carrying this shipment's mapping (mandatory flag + sort
     * order) where one exists.
     *
     * The previous version excluded questions mapped *only* to this shipment, so
     * re-saving an existing form silently dropped them from the picker. Mapped
     * questions come first, in their saved order.
     */
    public function fetchAllActiveQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['rfq' => 'r_feedback_questions'], ['question_id', 'question_text', 'question_code', 'question_show_to'])
            ->joinLeft(
                ['rpff' => 'r_participant_feedback_form_question_map'],
                'rfq.question_id = rpff.question_id AND rpff.shipment_id = ' . (int) $sid,
                ['is_response_mandatory', 'sort_order', 'mappedShipmentId' => 'shipment_id']
            )
            ->where("rfq.question_status ='active'")
            ->group('rfq.question_id')
            ->order(new Zend_Db_Expr('rpff.shipment_id IS NULL ASC'))
            ->order(new Zend_Db_Expr('rpff.sort_order IS NULL ASC'))
            ->order('rpff.sort_order ASC')
            ->order('rfq.question_id ASC');
        return $db->fetchAll($sql);
    }

    public function fetchFeedBackAnswers($sid, $pid, $mid, $type = 'options')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['pfa' => 'participant_feedback_answer'], ['*'])
            ->join(['rpff' => 'r_participant_feedback_form_question_map'], 'pfa.question_id=rpff.question_id', ['is_response_mandatory', 'sort_order'])
            ->join(['rfq' => 'r_feedback_questions'], 'pfa.question_id=rfq.question_id', ['question_text', 'question_code'])
            ->join(['sl' => 'scheme_list'], 'rpff.scheme_type=sl.scheme_id', ['scheme_name'])
            ->join(['s' => 'shipment'], 'pfa.shipment_id=s.shipment_id', ['shipment_code'])
            ->where("rfq.question_status ='active'")
            ->where('pfa.shipment_id =?', $sid)
            ->where('pfa.participant_id =?', $pid)
            ->where('pfa.map_id =?', $mid)
            ->order('sort_order asc');
        $result = $db->fetchAll($sql);
        $response = [];
        if ($type == 'options') {
            foreach ($result as $q) {
                $response[$q['question_id']] = $q['answer'];
            }
        } else {
            $response = $result;
        }
        return $response;
    }

    public function saveFeedbackQuestionsDetails($params)
    {
        $data = [];
        if (isset($params['question']) && !empty($params['question'])) {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $data = [
                'question_text' => $params['question'],
                'question_type' => $params['questionType'],
                'response_attributes' => ($params['questionType'] == 'dropdown') ? json_encode($params['options'], true) : null,
                'question_code' => $params['questionCode'],
                'question_status' => $params['questionStatus'],
                'question_show_to' => $params['questionTo'],
                'updated_datetime' => new Zend_Db_Expr('now()'),
                'modified_by' => $authNameSpace->admin_id,
            ];

            $auditDb = new Application_Model_DbTable_AuditLog();
            $code = $params['questionCode'] ?: '(no code)';

            if (isset($params['questionID']) && !empty($params['questionID']) && $params['formType'] != 'clone') {
                $result = $this->update($data, $this->_primary . ' = ' . base64_decode($params['questionID']));
                $auditDb->addNewAuditLog("Updated feedback question - {$code}", 'feedback');
                return $result;
            } else {
                $result = $this->insert($data);
                $verb = (isset($params['formType']) && $params['formType'] === 'clone') ? 'Cloned' : 'Added a new';
                $auditDb->addNewAuditLog("{$verb} feedback question - {$code}", 'feedback');
                return $result;
            }
        }
    }

    /**
     * Save shipment question mapping details including feedback form data, files, and question mappings
     *
     * @param array $params Contains shipmentId, rfId, question, formFiles, mandatory, and sortOrder data
     * @return array|false ['saved', 'feedbackTurnedOn', 'questionCount'], or false when nothing was saved
     */
    public function saveShipmentQuestionMapDetails($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Validate required shipment ID parameter
        if (!isset($params['shipmentId']) || empty($params['shipmentId'])) {
            return false;
        }

        // Fetch shipment details
        $shipmentResult = $db->fetchRow(
            $db->select()
                ->from('shipment', ['scheme_type', 'collect_feedback', 'finalized_at'])
                ->where('shipment_id = ?', $params['shipmentId'])
        );

        $openForDays = (int) ($params['openForDays'] ?? 0);
        if ($openForDays < 1 || $openForDays > 365) {
            $openForDays = Application_Service_FeedBack::DEFAULT_OPEN_FOR_DAYS;
        }

        // Prepare feedback form data
        $feedbackFormData = [
            'form_show_to' => $params['formTo'],
            'shipment_id' => $params['shipmentId'],
            'scheme_type' => $shipmentResult['scheme_type'] ?? null,
            'form_content' => $params['formContent'] ?? null,
            'open_for_days' => $openForDays,
        ];

        // One form per shipment: find it by shipment rather than trusting the posted id,
        // so saving a copied form onto a shipment that already has one updates that form
        // instead of adding a second.
        $feedbackFormId = $db->fetchOne(
            $db->select()
                ->from('r_participant_feedback_form', 'rpff_id')
                ->where('shipment_id = ?', (int) $params['shipmentId'])
        );
        if (!empty($feedbackFormId)) {
            $db->update(
                'r_participant_feedback_form',
                $feedbackFormData,
                'rpff_id = ' . (int) $feedbackFormId
            );
        } else {
            // insert() returns the affected row count, not the new id — reading it as an
            // id stamped every new form's question rows with rpff_id = 1.
            $db->insert('r_participant_feedback_form', $feedbackFormData);
            $feedbackFormId = $db->lastInsertId('r_participant_feedback_form', 'rpff_id');
        }

        // Process questions if provided. The form row above is already written, but with no
        // questions mapped there is nothing to collect, so collect_feedback is left alone and
        // this reports "nothing was saved" — which is what the caller already assumed a bare
        // return meant.
        if (!isset($params['question']) || empty($params['question'])) {
            return false;
        }

        // Already finalized: the form opened then, so move its closing date to match
        if (!empty($shipmentResult['finalized_at'])) {
            $db->update(
                'shipment',
                ['feedback_expiry_date' => date('Y-m-d', strtotime(substr($shipmentResult['finalized_at'], 0, 10) . " +{$openForDays} days"))],
                'shipment_id = ' . (int) $params['shipmentId']
            );
        }

        // ===== PROCESS AND SAVE ATTACHMENTS =====
        // The form posts every attachment row it shows (formFilesPosted), so the posted
        // rows replace the saved ones: a removed row is dropped, and a kept row with no
        // new upload keeps its saved file (formFiles[existing]).
        if (!empty($params['formFilesPosted'])) {
            $db->delete(
                'r_participant_feedback_form_files_map',
                'shipment_id = ' . (int) $params['shipmentId']
            );

            $uploadDir = realpath(UPLOAD_PATH) . DIRECTORY_SEPARATOR . 'feedback-forms' . DIRECTORY_SEPARATOR . (int) $params['shipmentId'];
            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'csv', 'txt', 'jpg', 'jpeg', 'png', 'gif'];

            foreach ((array) ($params['formFiles']['name'] ?? []) as $key => $fileName) {
                $storedFile = null;

                // Keep the saved file, but only one that really is in this shipment's folder
                $existing = basename((string) ($params['formFiles']['existing'][$key] ?? ''));
                if ($existing !== '' && is_file($uploadDir . DIRECTORY_SEPARATOR . $existing)) {
                    $storedFile = $existing;
                }

                if (
                    !empty($_FILES['formFiles']['name']['files'][$key])
                    && $_FILES['formFiles']['error']['files'][$key] === UPLOAD_ERR_OK
                ) {
                    $originalFileName = $_FILES['formFiles']['name']['files'][$key];
                    $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                    if (in_array($extension, $allowedExtensions, true)) {
                        if (!is_dir($uploadDir)) {
                            Application_Service_Common::makeDirectory($uploadDir);
                        }
                        $newFileName = Application_Service_Common::generateRandomString(6) . '.' . $extension;
                        if (move_uploaded_file($_FILES['formFiles']['tmp_name']['files'][$key], $uploadDir . DIRECTORY_SEPARATOR . $newFileName)) {
                            $storedFile = $newFileName;
                        }
                    }
                    if (trim((string) $fileName) === '') {
                        $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
                    }
                }

                // A row with no file is an empty line the admin never filled in
                if ($storedFile === null) {
                    continue;
                }

                $db->insert('r_participant_feedback_form_files_map', [
                    'rpff_id' => $feedbackFormId,
                    'shipment_id' => $params['shipmentId'],
                    'scheme_type' => $shipmentResult['scheme_type'] ?? null,
                    'feedback_file' => $storedFile,
                    // file_name is varchar(50)
                    'file_name' => mb_substr(trim((string) $fileName) !== '' ? trim((string) $fileName) : $storedFile, 0, 50),
                    'files_show_to' => ($params['formFiles']['filesTo'][$key] ?? '') ?: 'all-participants',
                    'sort_order' => is_numeric($params['formFiles']['sort'][$key] ?? null) ? (int) $params['formFiles']['sort'][$key] : null,
                ]);
            }
        }

        // ===== PROCESS AND SAVE QUESTION MAPPINGS =====
        // Delete existing question mappings for this shipment
        $db->delete(
            'r_participant_feedback_form_question_map',
            'shipment_id = ' . (int) $params['shipmentId']
        );

        // Insert new question mappings
        foreach ($params['question'] as $questionId) {
            $questionData = [
                'rpff_id' => $feedbackFormId,
                'question_id' => $questionId,
                'shipment_id' => $params['shipmentId'],
                'scheme_type' => $shipmentResult['scheme_type'] ?? null,
                'is_response_mandatory' => $params['mandatory'][$questionId] ?? null,
                'sort_order' => $params['sortOrder'][$questionId] ?? null,
            ];

            $db->insert('r_participant_feedback_form_question_map', $questionData);
        }

        $shipmentCode = $db->fetchOne(
            $db->select()->from('shipment', 'shipment_code')->where('shipment_id = ?', $params['shipmentId'])
        );
        $questionCount = count((array) $params['question']);
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog(
            'Mapped feedback form for shipment - ' . ($shipmentCode ?: "#{$params['shipmentId']}") . " ({$questionCount} questions)",
            'feedback'
        );

        // Building a form is the admin saying "collect feedback for this shipment", so
        // switch the shipment's flag on rather than leaving a form nobody will ever see.
        // The reverse (switching the flag off on the shipment form) keeps the form —
        // see the confirmation on the shipment edit screen.
        $feedbackTurnedOn = false;
        if ($shipmentResult['collect_feedback'] !== 'yes') {
            $db->update('shipment', ['collect_feedback' => 'yes'], 'shipment_id = ' . (int) $params['shipmentId']);
            $feedbackTurnedOn = true;
            $auditDb->addNewAuditLog(
                'Turned on Collect Feedback for shipment - ' . ($shipmentCode ?: "#{$params['shipmentId']}") . ' (feedback form saved)',
                'feedback'
            );
        }

        return ['saved' => true, 'feedbackTurnedOn' => $feedbackTurnedOn, 'questionCount' => $questionCount];
    }

    public function fetchAllFeedBackResponses($parameters, $type)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        if ($type == 'mapped') {
            $aColumns = ['shipment_code', 'scheme_name', 'numberofquestion'];
        } else {
            $aColumns = ['question_text', 'question_code', 'question_type', 'question_status'];
        }

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
				 	' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['pfa' => $this->_name]);

        if ($type == 'mapped') {
            $sQuery = $sQuery->join(['rpff' => 'r_participant_feedback_form_question_map'], 'pfa.question_id=rpff.question_id', ['shipment_id', 'is_response_mandatory', 'sort_order', 'numberofquestion' => new Zend_Db_Expr('COUNT(*)')]);
            $sQuery = $sQuery->join(['s' => 'shipment'], 'rpff.shipment_id=s.shipment_id', ['shipment_code']);
            $sQuery = $sQuery->joinLeft(['sl' => 'scheme_list'], 'rpff.scheme_type=sl.scheme_id', ['scheme_name']);
        }
        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }
        if ($type == 'mapped') {
            $sQuery = $sQuery->group('s.shipment_id');
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        // $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];
            $edit = '';
            $clone = '';
            foreach ($aColumns as $heading) {
                $row[] = ucwords($aRow[$heading]);
            }
            $file = 'edit';
            $field = 'question_id';
            if ($type == 'mapped') {
                $file = 'feedback-form';
                $field = 'shipment_id';
            }
            $clone = '<a href="/admin/feedback-responses/' . $file . '/id/' . base64_encode($aRow[$field]) . '/type/clone" class="btn btn-info btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Clone</a>';
            $edit = '<a href="/admin/feedback-responses/' . $file . '/id/' . base64_encode($aRow[$field]) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            // $downloadResponse = '<a href="javascript:void(0);" onclick="generateFeedbackResponseReports(' . $aRow['shipment_id'] . ')" class="btn btn-success btn-xs" style="margin-right: 2px;"><i class="icon-download"></i> Download Feedback</a>';

            $row[] = $edit . $clone;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
