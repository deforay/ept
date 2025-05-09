<?php

class Application_Service_FeedBack
{

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
            $alertMsg->message = 'Question saved succssfully';
        }
    }
    public function saveShipmentQuestionMap($params)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        if ($db->saveShipmentQuestionMapDetails($params)) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Question mapped succssfully';
        }
    }

    public function getAllFeedBackResponses($parameters, $type)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchAllFeedBackResponses($parameters, $type);
    }

    public function getAllIrelaventActiveQuestions($sid)
    {
        $db = new Application_Model_DbTable_FeedBackTable();
        return $db->fetchAllIrelaventActiveQuestions($sid);
    }

    public function saveFeedBackForms($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        // Zend_Debug::dump($params);die;
        foreach ($params['questionId'] as $key => $q) {
            if (isset($params['answer'][$key]['date']) && !empty($params['answer'][$key]['date'])) {
                $answer = Pt_Commons_General::isoDateFormat($params['answer'][$key]['date']);
            } else {
                $answer = $params['answer'][$key];
            }
            $db->insert(
                'participant_feedback_answer',
                [
                    'shipment_id' => $params["shipmentId"],
                    'question_id' => $q,
                    'participant_id' => $params['participantId'],
                    'map_id' => $params['mapId'],
                    'answer' => $answer,
                    'updated_datetime' => Pt_Commons_General::getDateTime(),
                    'modified_by' => $authNameSpace->admin_id
                ]
            );
        }
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $alertMsg->message = "Your feedback response successfully submitted.";
    }
}
