<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_participant_feedback_form';
    public function fetchFeedBackQuestions($sid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('rpff' => 'r_participant_feedback_form'), array('*'))
        ->join(array('sl' => 'scheme_list'), 'rpff.scheme_type=sl.scheme_id', array('scheme_name'))
        ->join(array('s' => 'shipment'), 'rpff.shipment_id=s.shipment_id', array('shipment_code'))
        ->where("rpff.shipment_id =?", $sid);
        return $db->fetchAll($sql);
    }

    function fetchFeedBackAnswers($sid, $pid, $mid, $type = "options"){
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
}

