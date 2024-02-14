<?php

class Application_Service_FeedBack
{

    public function getFeedBackQuestions(){
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackQuestions();
    }
    
    public function saveFeedBackForms($params){
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        Zend_Debug::dump($params);
    }
}
