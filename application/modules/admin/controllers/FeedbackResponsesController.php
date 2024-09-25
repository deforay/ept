<?php

class Admin_FeedbackResponsesController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->getAllFeedBackResponses($parameters, "");
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->saveFeedbackQuestions($params);
            $this->redirect("/admin/feedback-responses/questions");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $feedbackService = new Application_Service_FeedBack();
        if ($request->isPost()) {
            $params = $request->getPost();
            $feedbackService->saveFeedbackQuestions($params);
            $this->redirect("/admin/feedback-responses/questions");
        }
        if ($this->hasParam('id')) {
            $id = (int)base64_decode($this->_getParam('id'));
            $this->view->questions = $feedbackService->getFeedBackQuestionsById($id);
        } else {
            $this->redirect("/admin/feedback-responses/questions");
        }
    }

    public function shipmentQuestionsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->getAllFeedBackResponses($parameters, 'mapped');
        }
    }

    public function feedbackFormAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $feedbackService = new Application_Service_FeedBack();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $feedbackService->saveShipmentQuestionMap($params);
            $this->redirect("/admin/feedback-responses/shipment-questions");
        }
        if ($this->hasParam('id')) {
            $id = (int)base64_decode($this->_getParam('id'));
            $this->view->sid = $id;
            $this->view->type = $this->_getParam('type');
            $this->view->questions = $feedbackService->getAllIrelaventActiveQuestions($id);
            $this->view->result = $feedbackService->getFeedBackQuestionsById($id, 'mapped');
        }
        $shipmentService = new Application_Service_Shipments();
        $this->view->shipments = $shipmentService->getAllShipmentCode();
    }
    public function getQuestionsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->disableLayout();
        $feedbackService = new Application_Service_FeedBack();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $this->view->questions = $feedbackService->getAllIrelaventActiveQuestions($parameters['sid']);
            $this->view->result = $feedbackService->getFeedBackQuestionsById($parameters['sid'], 'mapped');
        }
    }
}
