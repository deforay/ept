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
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('shipment-questions', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
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
            $this->redirect("/admin/feedback-responses");
        }
        $shipmentService = new Application_Service_Shipments();
        $this->view->shipments = $shipmentService->getAllShipmentCode();
    }

    public function editAction()
    {
        $feedbackService = new Application_Service_FeedBack();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $feedbackService->saveFeedbackQuestions($params);
            $this->redirect("/admin/feedback-responses");
        }
        if ($this->hasParam('id')) {
            $id = (int)base64_decode($this->_getParam('id'));
            $this->view->questions = $feedbackService->getFeedBackQuestionsById($id);
        }else{
            $this->redirect("/admin/feedback-responses");
        }
    }

    public function shipmentQuestionsAction(){
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $feedbackService = new Application_Service_FeedBack();
            $feedbackService->getAllFeedBackResponses($parameters, 'mapped');
        }
    }
}
