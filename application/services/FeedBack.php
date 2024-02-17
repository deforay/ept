<?php

class Application_Service_FeedBack
{

    public function getFeedBackQuestions($sid){
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackQuestions($sid);
    }
    
    public function getFeedBackAnswers($sid, $pid, $mid){
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchFeedBackAnswers($sid, $pid, $mid);
    }
    
    public function saveFeedBackForms($params){
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        Zend_Debug::dump($params);
        foreach($params['questionId'] as $key=>$q){
            $db->insert(
                'participant_feedback_answer',
                array(
                    'shipment_id'      => $params["shipmentId"],
                    'question_id'      => $q,
                    'participant_id'   => $params['participantId'],
                    'map_id'           => $params['mapId'],
                    'answer'           => $params['answer'][$key],
                    'updated_datetime' => Pt_Commons_General::getDateTime(),
                    'modified_by'      => $authNameSpace->admin_id
                )
            );
        }
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $alertMsg->message = "Your feedback response successfully submitted.";
    }
}
