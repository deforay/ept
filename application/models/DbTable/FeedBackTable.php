<?php

class Application_Model_DbTable_FeedBackTable extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_participant_feedback_form';
    public function fetchFeedBackQuestions()
    {
        return $this->fetchAll("question_status = 'active'")->toArray();
    }
}

