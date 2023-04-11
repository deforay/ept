<?php

class DataManagersController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-participants-names', 'html')
            ->addActionContext('reset-password', 'html')
            ->addActionContext('save-password', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'ptcc-manager';
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_DataManagers();
            $clientsServices->getAllUsers($params);
        }
        if ($this->hasParam('ptcc')) {
            $this->view->ptcc = $this->_getParam('ptcc');
        }
    }

    public function addAction()
    {
        $userService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        $participantService = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $userService->addUser($params);
            $this->redirect("/data-managers");
        } else {
            $this->view->participants = $participantService->getAllActiveParticipants();
        }
        if ($this->hasParam('contact')) {
            $contact = new Application_Model_DbTable_ContactUs();
            $this->view->contact = $contact->getContact($this->_getParam('contact'));
        }
        $this->view->countriesList = $commonService->getcountriesList();
    }

    public function editAction()
    {
        $participantService = new Application_Service_Participants();
        $userService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $userService->updateUser($params);
            $this->redirect("/data-managers");
        } else {
            if ($this->hasParam('id')) {
                $userId = (int) $this->_getParam('id');
                $this->view->rsUser = $userService->getUserInfoBySystemId($userId);
                $this->view->participants = $participantService->getAllActiveParticipants();
                $this->view->participantList = $participantService->getActiveParticipantDetails($userId);
                $this->view->countriesList = $commonService->getcountriesList();
            }
        }
    }

    public function getParticipantsNamesAction()
    {
        $this->_helper->layout()->disableLayout();
        $participantService = new Application_Service_Participants();
        if ($this->hasParam('search')) {
            $search = $this->_getParam('search');
            $this->view->participants = $participantService->getParticipantSearch($search);
        }
    }
    
    public function resetPasswordAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        if ($this->hasParam('id')) {
            $userId = (int) $this->_getParam('id');
            $this->view->user = $userService->getUserInfoBySystemId($userId);
        }
    }
    
    public function savePasswordAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->_request->getPost();
            $this->view->result = $userService->resetPasswordFromAdmin($params);
        }
    }
}
