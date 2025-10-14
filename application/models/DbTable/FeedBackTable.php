<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_feedback_questions';

    protected $_primary = 'question_id';

    public function fetchFeedBackQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rfq' => 'r_feedback_questions'), array('*'))
            ->join(array('rpff' => 'r_participant_feedback_form'), 'rfq.question_id=rpff.question_id', array('is_response_mandatory', 'sort_order'))
            ->join(array('sl' => 'scheme_list'), 'rpff.scheme_type=sl.scheme_id', array('scheme_name'))
            ->join(array('s' => 'shipment'), 'rpff.shipment_id=s.shipment_id', array('shipment_code'))
            ->where("rpff.shipment_id =?", $sid)
            ->order('sort_order asc');
        return $db->fetchAll($sql);
    }
    public function fetchFeedBackQuestionsById($id, $type)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rfq' => 'r_feedback_questions'), array('*'));
        if ($type == 'mapped') {
            $sql = $sql->join(array('rpff' => 'r_participant_feedback_form'), 'rfq.question_id=rpff.question_id', array('*'));
            $sql = $sql->where("rpff.shipment_id =?", $id);
            return $db->fetchAll($sql);
        } else {
            $sql = $sql->where("rfq.question_id =?", $id);
            return $db->fetchRow($sql);
        }
    }
    public function fetchAllIrelaventActiveQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rfq' => 'r_feedback_questions'), array('question_id', 'question_text', 'question_code'))
            ->joinLeft(array('rpff' => 'r_participant_feedback_form'), 'rfq.question_id=rpff.question_id', array('is_response_mandatory', 'sort_order'))
            ->where("rfq.question_status ='active'")
            ->where("(rpff.shipment_id != " . $sid . " OR rpff.shipment_id IS null OR rpff.shipment_id like '')")
            ->group('question_id');
        return $db->fetchAll($sql);
    }

    public function fetchFeedBackAnswers($sid, $pid, $mid, $type = "options")
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pfa' => 'participant_feedback_answer'), array('*'))
            ->join(array('rpff' => 'r_participant_feedback_form'), 'pfa.question_id=rpff.question_id', array('is_response_mandatory', 'sort_order'))
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
                'updated_datetime'      => new Zend_Db_Expr('now()'),
                'modified_by'           => $authNameSpace->admin_id
            );

            if (isset($params['questionID']) && !empty($params['questionID'])) {
                return $this->update($data, $this->_primary . " = " . base64_decode($params['questionID']));
            } else {
                return $this->insert($data);
            }
        }
    }

    public function saveShipmentQuestionMapDetails($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (isset($params['shipmentId']) && !empty($params['shipmentId']) && isset($params['question']) && !empty($params['question'])) {
            $shipmentResult = $db->fetchRow($db->select()->from('shipment', array('scheme_type'))->where('shipment_id = ?', $params['shipmentId']));
            $db->delete('r_participant_feedback_form', 'shipment_id = ' . $params['shipmentId'] . '');
            foreach ($params['question'] as $q) {
                $db->insert('r_participant_feedback_form', array(
                    'question_id' => $q,
                    'shipment_id' => $params['shipmentId'],
                    'scheme_type' => $shipmentResult['scheme_type'] ?? null,
                    'is_response_mandatory' => $params['mandatory'][$q] ?? null,
                    'sort_order' => $params['sortOrder'][$q] ?? null,
                ));
            }
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
            $sQuery = $sQuery->join(['rpff' => 'r_participant_feedback_form'], 'pfa.question_id=rpff.question_id', ['shipment_id', 'is_response_mandatory', 'sort_order', 'numberofquestion' => new Zend_Db_Expr("COUNT(*)")]);
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

            $row[] = $edit . $clone;

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
