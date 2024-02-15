<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_participant_feedback_form';
    public function fetchFeedBackQuestions($sid)
    {
        return $this->fetchAll(array("question_status = 'active'", "shipment_id = " . $sid))->toArray();
    }

    function fetchFeedBackAnswers($sid, $pid, $mid){
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(array('pfa' => 'participant_feedback_answer'), array('*'))
        ->join(array('rpff' => 'r_participant_feedback_form'), 'pfa.question_id=rpff.question_id', array('question_text'))
        ->where("pfa.shipment_id =?", $sid)
        ->where("pfa.participant_id =?", $pid)
        ->where("pfa.map_id =?", $mid);
        return $db->fetchAll($sql);
    }
}

