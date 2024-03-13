<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_participant_feedback_form';
    
    protected $_primary = 'question_id';

    public function fetchFeedBackQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rpff' => 'r_participant_feedback_form'), array('*'))
        ->join(array('sl' => 'scheme_list'), 'rpff.scheme_type=sl.scheme_id', array('scheme_name'))
        ->join(array('s' => 'shipment'), 'rpff.shipment_id=s.shipment_id', array('shipment_code'))
        ->where("rpff.shipment_id =?", $sid);
        return $db->fetchAll($sql);
    }

    public function fetchFeedBackAnswers($sid, $pid, $mid, $type = "options"){
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pfa' => 'participant_feedback_answer'), array('*'))
        ->join(array('rpff' => 'r_participant_feedback_form'), 'pfa.question_id=rpff.question_id', array('question_text'))
        ->join(array('sl' => 'scheme_list'), 'rpff.scheme_type=sl.scheme_id', array('scheme_name'))
        ->join(array('s' => 'shipment'), 'pfa.shipment_id=s.shipment_id', array('shipment_code'))
        ->where("pfa.shipment_id =?", $sid)
        ->where("pfa.participant_id =?", $pid)
        ->where("pfa.map_id =?", $mid);
        $result = $db->fetchAll($sql);
        $response = [];
        if($type == "options"){
            foreach($result as $key=>$q){
                $response[$q['question_id']] = $q['answer'];
            }
        }else{
            $response = $result;
        }
        return $response;
    }

    public function saveFeedbackQuestionsDetails($params){
        $data = [];
        if(isset($params['question']) && !empty($params['question'])){
            $authNameSpace = new Zend_Session_Namespace('administrators');
            foreach($params['question'] as $key => $q){
                $data = array(
                    'shipment_id'           => $params['shipmentId'],
                    'scheme_type'           => $params['schemeType'],
                    'question_text'         => $q,
                    'response_type'         => $params['responseType'][$key],
                    'response_attributes'   => json_encode($params['options'][$key], true),
                    'question_code'         => $params['questionCode'][$key],
                    'is_response_mandatory' => $params['mandatory'][$key],
                    'question_status'       => $params['questionStatus'][$key],
                    'updated_datetime'      => new Zend_Db_Expr('now()'),
                    'modified_by'           => $authNameSpace->admin_id
                );

                if(isset($params['questionID'][$key]) && !empty($params['questionID'][$key])){
                    $this->update($data, $this->_primary . " = " . base64_decode($params['questionID'][$key]));
                }else{
                    $this->insert($data);-*
                }
            }
        }
    }

    public function fetchAllFeedBackResponses($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('shipment_code', 'scheme_name', 'question_text', 'question_code', 'is_response_mandatory', 'question_status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;


        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        /*
         * Ordering
         */
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

        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
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


        /*
         * SQL queries
         * Get data to display
         */

        $sQuery = $this->getAdapter()->select()->from(array('rpff' => $this->_name))
                    ->join(array('s' => 'shipment'), 'rpff.shipment_id=s.shipment_id', array('shipment_code'))
                    ->join(array('sl' => 'scheme_list'), 'rpff.scheme_type=sl.scheme_id', array('scheme_name'));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
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
            foreach($aColumns as $heading){
                $row[] = ucwords($aRow[$heading]);
            }
            $row[] = '<a href="/admin/feedback-responses/edit/sid/' . base64_encode($aRow['shipment_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}

