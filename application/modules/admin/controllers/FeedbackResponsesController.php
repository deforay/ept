<?php

class Admin_FeedbackResponsesController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('questions', 'html')
                    ->addActionContext('shipment-questions', 'html')
                    ->addActionContext('get-questions', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function questionsAction()
    {
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->getAllFeedBackResponses($parameters, "");
        }
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->saveFeedbackQuestions($params);
            $this->redirect("/admin/feedback-responses/questions");
        }
    }

    public function editAction()
    {
        $feedbackService = new Application_Service_FeedBack();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $feedbackService->saveFeedbackQuestions($params);
            $this->redirect("/admin/feedback-responses/questions");
        }
        if ($this->hasParam('id')) {
            $id = (int)base64_decode($this->_getParam('id'));
            $this->view->questions = $feedbackService->getFeedBackQuestionsById($id);
        }else{
            $this->redirect("/admin/feedback-responses/questions");
        }
    }

    public function shipmentQuestionsAction(){
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->getAllFeedBackResponses($parameters, 'mapped');
        }
    }
    
    public function shipmentQuestionMapAction(){
        $feedbackService = new Application_Service_FeedBack();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $feedbackService->saveShipmentQuestionMap($params);
            $this->redirect("/admin/feedback-responses/shipment-questions");
        }
        if ($this->hasParam('id')) {
            $id = (int)base64_decode($this->_getParam('id'));
            $this->view->sid = $id;
            $this->view->questions = $feedbackService->getAllIrelaventActiveQuestions($id);
            $this->view->result = $feedbackService->getFeedBackQuestionsById($id, 'mapped');
        }
        $shipmentService = new Application_Service_Shipments();
        $this->view->shipments = $shipmentService->getAllShipmentCode();
    }
    public function getQuestionsAction(){
        $this->_helper->layout()->disableLayout();
        $feedbackService = new Application_Service_FeedBack();
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $this->view->questions = $feedbackService->getAllIrelaventActiveQuestions($parameters['sid']);
        }
    }
}
