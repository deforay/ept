<?php

class Admin_DataManagersController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges) && !in_array('manage-participants', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-participants-names', 'html')
            ->addActionContext('reset-password', 'html')
            ->addActionContext('save-password', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
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
            if (isset($params['ptcc']) && $params['ptcc'] == 'yes') {
                $this->redirect("/admin/data-managers/index/ptcc/1");
            } else {
                $this->redirect("/admin/data-managers");
            }
        } else {
            $this->view->participants = $participantService->getAllActiveParticipants();
        }
        if ($this->hasParam('ptcc')) {
            $this->view->ptcc = $this->_getParam('ptcc');
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
            if (isset($params['ptcc']) && $params['ptcc'] == 'yes') {
                $this->redirect("/admin/data-managers/index/ptcc/1");
            } else {
                $this->redirect("/admin/data-managers");
            }
        } else {
            if ($this->hasParam('id')) {
                $userId = (int) $this->_getParam('id');
                if ($this->hasParam('ptcc')) {
                    $this->view->ptcc = $this->_getParam('ptcc');
                    $this->view->countryList = $userService->getPtccCountryMap($userId, 'implode');
                }
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
