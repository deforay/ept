<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_feedback_questions';

    protected $_primary = 'question_id';

    public function fetchFeedBackQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rfq' => 'r_feedback_questions'), array('*'))
            ->join(array('rpfq' => 'r_participant_feedback_form_question_map'), 'rfq.question_id=rpfq.question_id', array('is_response_mandatory', 'sort_order'))
            ->join(array('sl' => 'scheme_list'), 'rpfq.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('s' => 'shipment'), 'rpfq.shipment_id=s.shipment_id', array('shipment_code'))
            ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('spm.map_id', 'final_result'))
            ->where("rpfq.shipment_id =?", $sid)
            ->where("(
                    rfq.question_show_to IS NULL
                    OR rfq.question_show_to = 'all-participants' 
                    OR (rfq.question_show_to = 'passing-participants' AND spm.final_result = 1 AND rfq.question_show_to != 'passing-participants' AND rfq.question_show_to != 'all-participants')
                    OR (rfq.question_show_to = 'failing-participants' AND spm.final_result != 1 AND rfq.question_show_to != 'passing-participants' AND rfq.question_show_to != 'all-participants')
                )")
            ->order('rpfq.sort_order asc')
            ->group('rfq.question_id');
        $result['result'] = $db->fetchAll($sql);

        // Fetch feedback form results
        $feedbackFormSql = $db->select()
            ->from(['rpf' => 'r_participant_feedback_form'], ['*'])
            ->join(array('sl' => 'scheme_list'), 'rpf.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('s' => 'shipment'), 'rpf.shipment_id=s.shipment_id', array('shipment_code'))
            ->where('rpf.shipment_id = ?', $sid);
        $result['feedback_form_results'] = $db->fetchRow($feedbackFormSql);

        // Fetch feedback form files mapping results
        $filesMapSql = $db->select()
            ->from(['rpff' => 'r_participant_feedback_form_files_map'], ['*'])
            ->where('rpff.shipment_id = ?', $sid);
        $result['feedback_form_files_results'] = $db->fetchAll($filesMapSql);

        return $result;
    }
    public function fetchFeedBackQuestionsById($id, $type)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rfq' => 'r_feedback_questions'), array('*'));
        if ($type == 'mapped') {
            $sql = $sql->join(array('rpfq' => 'r_participant_feedback_form_question_map'), 'rfq.question_id=rpfq.question_id', array('*'));
            $sql = $sql->join(['rpff' => 'r_participant_feedback_form_files_map'], 'rpfq.rpff_id=rpff.rpff_id',  ['*']);
            $sql = $sql->where("rpfq.shipment_id =?", $id);
            return $db->fetchAll($sql);
        } else {
            $sql = $sql->where("rfq.question_id =?", $id);
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
            ->join(['rpfq' => 'r_participant_feedback_form_question_map'], 'rfq.question_id=rpfq.question_id', ['*'])
            ->where('rpfq.shipment_id = ?', $id);
        $result['feedback_form_question_results'] = $db->fetchAll($questionMapSql);

        // Fetch feedback form files mapping results
        $filesMapSql = $db->select()
            ->from(['rpff' => 'r_participant_feedback_form_files_map'], ['*'])
            ->where('rpff.shipment_id = ?', $id);
        $result['feedback_form_files_results'] = $db->fetchAll($filesMapSql);

        return $result;
    }
    public function fetchAllIrelaventActiveQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rfq' => 'r_feedback_questions'), array('question_id', 'question_text', 'question_code'))
            ->joinLeft(array('rpff' => 'r_participant_feedback_form_question_map'), 'rfq.question_id=rpff.question_id', array('is_response_mandatory', 'sort_order'))
            ->where("rfq.question_status ='active'")
            ->where("(rpff.shipment_id != " . $sid . " OR rpff.shipment_id IS null OR rpff.shipment_id like '')")
            ->group('question_id');
        return $db->fetchAll($sql);
    }

    public function fetchFeedBackAnswers($sid, $pid, $mid, $type = "options")
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pfa' => 'participant_feedback_answer'), array('*'))
            ->join(array('rpff' => 'r_participant_feedback_form_question_map'), 'pfa.question_id=rpff.question_id', array('is_response_mandatory', 'sort_order'))
            ->join(array('rfq' => 'r_feedback_questions'), 'pfa.question_id=rfq.question_id', array('question_text', 'question_code'))
            ->join(array('sl' => 'scheme_list'), 'rpff.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('s' => 'shipment'), 'pfa.shipment_id=s.shipment_id', array('shipment_code'))
            ->where("rfq.question_status ='active'")
            ->where("pfa.shipment_id =?", $sid)
            ->where("pfa.participant_id =?", $pid)
            ->where("pfa.map_id =?", $mid)
            ->order('sort_order asc');
        $result = $db->fetchAll($sql);
        $response = [];
        if ($type == "options") {
            foreach ($result as $key => $q) {
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
            $data = array(
                'question_text'         => $params['question'],
                'question_type'         => $params['questionType'],
                'response_attributes'   => ($params['questionType'] == 'dropdown') ? json_encode($params['options'], true) : null,
                'question_code'         => $params['questionCode'],
                'question_status'       => $params['questionStatus'],
                'question_show_to'       => $params['questionTo'],
                'updated_datetime'      => new Zend_Db_Expr('now()'),
                'modified_by'           => $authNameSpace->admin_id
            );

            if (isset($params['questionID']) && !empty($params['questionID']) && $params['formType'] != 'clone') {
                return $this->update($data, $this->_primary . " = " . base64_decode($params['questionID']));
            } else {
                return $this->insert($data);
            }
        }
    }

    /**
     * Save shipment question mapping details including feedback form data, files, and question mappings
     *
     * @param array $params Contains shipmentId, rfId, question, formFiles, mandatory, and sortOrder data
     * @return bool|void Returns false if shipmentId is not provided, void otherwise
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
                ->from('shipment', array('scheme_type'))
                ->where('shipment_id = ?', $params['shipmentId'])
        );

        // Prepare feedback form data
        $feedbackFormData = [
            'form_show_to' => $params['formTo'],
            'shipment_id' => $params['shipmentId'],
            'scheme_type' => $shipmentResult['scheme_type'] ?? null,
            'form_content' => $params['formContent'] ?? null,
        ];

        // Update or insert feedback form record
        if (isset($params['rfId']) && !empty($params['rfId'])) {
            // Update existing record
            $feedbackFormId = base64_decode($params['rfId']);
            $db->update(
                'r_participant_feedback_form',
                $feedbackFormData,
                'rpff_id = ' . (int)$feedbackFormId
            );
        } else {
            // Insert new record
            $feedbackFormId = $db->insert('r_participant_feedback_form', $feedbackFormData);
        }

        // Process questions if provided
        if (!isset($params['question']) || empty($params['question'])) {
            return;
        }

        // ===== PROCESS AND SAVE UPLOADED FILES =====
        if (isset($params['formFiles']['name']) && !empty($params['formFiles']['name'])) {
            // Delete existing file mappings for this shipment
            $db->delete(
                'r_participant_feedback_form_files_map',
                'shipment_id = ' . (int)$params['shipmentId']
            );

            // Prepare upload directory
            $uploadFolder = realpath(UPLOAD_PATH);
            $dirPath = 'feedback-forms' . DIRECTORY_SEPARATOR . $params['shipmentId'];
            $uploadDir = $uploadFolder . DIRECTORY_SEPARATOR . $dirPath;

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                Application_Service_Common::makeDirectory($uploadDir);
            }

            // Process each uploaded file
            foreach ($params['formFiles']['name'] as $key => $fileName) {
                $fileData = [
                    'rpff_id' => $feedbackFormId,
                    'shipment_id' => $params['shipmentId'],
                    'scheme_type' => $shipmentResult['scheme_type'] ?? null,
                    'feedback_file' => null,
                    'file_name' => $fileName ?? null,
                    'sort_order' => $params['formFiles']['sort'][$key] ?? null,
                ];
                // Handle file upload if file is valid
                if (
                    !empty($_FILES['formFiles']['name']['files'][$key])
                    && $_FILES['formFiles']['error']['files'][$key] === 0
                ) {
                    // Get file extension
                    $originalFileName = $_FILES['formFiles']['name']['files'][$key];
                    $extension = substr($originalFileName, strrpos($originalFileName, '.') + 1);
                    // Generate unique filename
                    $newFileName = Application_Service_Common::generateRandomString(6) . '.' . $extension;
                    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

                    // Move uploaded file and update file data
                    if (move_uploaded_file($_FILES['formFiles']['tmp_name']['files'][$key], $targetPath)) {
                        $fileData['feedback_file'] = $newFileName;
                    }
                }

                // Insert file mapping record
                $db->insert('r_participant_feedback_form_files_map', $fileData);
            }
        }

        // ===== PROCESS AND SAVE QUESTION MAPPINGS =====
        // Delete existing question mappings for this shipment
        $db->delete(
            'r_participant_feedback_form_question_map',
            'shipment_id = ' . (int)$params['shipmentId']
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
    }

    public function fetchAllFeedBackResponses($parameters, $type)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
        if ($type == 'mapped') {
            $aColumns = array('shipment_code', 'scheme_name', 'numberofquestion');
        } else {
            $aColumns = array('question_text', 'question_code', 'question_type', 'question_status');
        }

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;



        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }


        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }


        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }




        $sQuery = $this->getAdapter()->select()->from(array('pfa' => $this->_name));

        if ($type == 'mapped') {
            $sQuery = $sQuery->join(['rpff' => 'r_participant_feedback_form_question_map'], 'pfa.question_id=rpff.question_id', ['shipment_id', 'is_response_mandatory', 'sort_order', 'numberofquestion' => new Zend_Db_Expr("COUNT(*)")]);
            $sQuery = $sQuery->join(['s' => 'shipment'], 'rpff.shipment_id=s.shipment_id', ['shipment_code']);
            $sQuery = $sQuery->joinLeft(['sl' => 'scheme_list'], 'rpff.scheme_type=sl.scheme_id', ['scheme_name']);
        }
        if (isset($sWhere) && $sWhere != "") {
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

        // die($sQuery);

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
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

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
