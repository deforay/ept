<?php

class Admin_DataManagersController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if (!in_array('config-ept', $privileges) && !in_array('manage-participants', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-participants-names', 'html')
            ->addActionContext('reset-password', 'html')
            ->addActionContext('save-password', 'html')
            ->addActionContext('check-dm-duplicate', 'html')
            ->addActionContext('export-ptcc', 'html')
            ->addActionContext('mapped-participants', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_DataManagers();
            $clientsServices->getAllUsers($params);
        }
        if ($this->hasParam('ptcc')) {
            $this->view->ptcc = $this->_getParam('ptcc');
        }
    }

    /* Returns the list of participants mapped to a data manager, rendered as a fragment
       to be inserted into a DataTables child row on /admin/data-managers. */
    public function mappedParticipantsAction()
    {
        $this->_helper->layout()->disableLayout();
        $dmId = (int)$this->_getParam('id');
        $this->view->dmId = $dmId;
        $this->view->mappedParticipants = [];
        if ($dmId > 0) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from(array('pmm' => 'participant_manager_map'), array())
                ->join(array('p' => 'participant'), 'p.participant_id = pmm.participant_id', array('participant_id', 'unique_identifier', 'first_name', 'last_name', 'institute_name', 'email', 'status'))
                ->joinLeft(array('c' => 'countries'), 'c.id = p.country', array('iso_name'))
                ->where('pmm.dm_id = ?', $dmId)
                ->order(array('p.institute_name', 'p.unique_identifier'));
            $this->view->mappedParticipants = $db->fetchAll($select);
        }
    }

    public function addAction()
    {
        $userService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        $sessionAlert = new Zend_Session_Namespace('alertSpace');
        $participantService = new Application_Service_Participants();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $userService->addUser($params);
            if (isset($params['ptcc']) && $params['ptcc'] == 'yes') {
                $this->redirect("/admin/data-managers/index/ptcc/1");
            } else if (isset($params['btnName']) && $params['btnName'] == 'save_and_map') {
                $this->redirect("/admin/participants/participant-manager-map");
            }
            $this->redirect("/admin/data-managers");
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
        $this->view->countries = $participantService->getParticipantCountriesList();
        $this->view->province = $commonService->getParticipantsProvinceList();
        $this->view->district = $commonService->getParticipantsDistrictList();
        $this->view->networksTier = $commonService->getAllnetwork();
        $this->view->affiliation = $commonService->getAllParticipantAffiliates();
        $this->view->institutes = $commonService->getAllInstitutes();
        $this->view->dmId = 0;
        $this->view->preSelectedParticipants = [];
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
    }

    public function editAction()
    {
        $participantService = new Application_Service_Participants();
        $userService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService->updateUser($params);
            if (isset($params['ptcc']) && $params['ptcc'] == 'yes') {
                $this->redirect("/admin/data-managers/index/ptcc/1");
            } else if (isset($params['btnName']) && $params['btnName'] == 'save_and_map') {
                $this->redirect("/admin/participants/participant-manager-map");
            } else {
                $this->redirect("/admin/data-managers");
            }
        } else {
            if ($this->hasParam('id')) {
                $userId = (int) $this->_getParam('id');
                if ($this->hasParam('ptcc')) {
                    $this->view->ptcc = $this->_getParam('ptcc');
                    $this->view->countryList = $userService->getPtccCountryMap($userId, 'implode', true);
                }
                $this->view->rsUser = $userService->getUserInfoBySystemId($userId);
                $this->view->participants = $participantService->getAllActiveParticipants();
                $this->view->participantList = $participantService->getActiveParticipantDetails($userId);
                $this->view->countriesList = $commonService->getcountriesList();
                $this->view->provinceList = $commonService->getParticipantsProvinceList();
                $this->view->districtList = $commonService->getParticipantsDistrictList();
                $this->view->countries = $participantService->getParticipantCountriesList();
                $this->view->province = $commonService->getParticipantsProvinceList();
                $this->view->district = $commonService->getParticipantsDistrictList();
                $this->view->networksTier = $commonService->getAllnetwork();
                $this->view->affiliation = $commonService->getAllParticipantAffiliates();
                $this->view->institutes = $commonService->getAllInstitutes();
                $this->view->dmId = $userId;
                $this->view->preSelectedParticipants = array_map(function ($p) {
                    return (int)$p['participant_id'];
                }, $this->view->participantList ?: []);
                $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
                $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->resetPasswordFromAdmin($params);
        } elseif ($this->hasParam('id')) {
            $userId = (int) $this->_getParam('id');
            $this->view->user = $userService->getUserInfoBySystemId($userId);
        }
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $this->view->loginUrl = rtrim((string) $conf->domain, "/") . '/auth/login';
    }

    /* public function savePasswordAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->resetPasswordFromAdmin($params);
        }
    } */

    public function checkDmDuplicateAction() // This action created for checking ptcc and actual dm replacement using primary email
    {
        $this->_helper->layout()->disableLayout();
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->checkSystemDuplicate($params);
        }
    }
    public function exportPtccAction()
    {
        $this->_helper->layout()->disableLayout();
        $userService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->result = $userService->exportPTCCDetails($params);
        }
    }

    public function bulkImportPtccAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $userService = new Application_Service_DataManagers();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->response = $userService->uploadBulkDatamanager($params);
            $this->redirect("/admin/data-managers/index/ptcc/1");
        }
    }
}
