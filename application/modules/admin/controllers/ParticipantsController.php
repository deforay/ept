<?php

class Admin_ParticipantsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
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
            ->addActionContext('view-participants', 'html')
            ->addActionContext('get-datamanager', 'html')
            ->addActionContext('get-datamanager-names', 'html')
            ->addActionContext('get-participant', 'html')
            ->addActionContext('get-participant-list', 'html')
            ->addActionContext('delete-participant', 'html')
            ->addActionContext('export-participants-map', 'html')
            ->addActionContext('mapped-data-managers', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $clientsServices = new Application_Service_Participants();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices->getAllParticipants($params);
            return;
        }
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->view->pendingCount = (int)$db->fetchOne(
            $db->select()->from('participant', new Zend_Db_Expr('COUNT(*)'))->where("status = ?", 'pending')
        );
    }

    /* Returns the list of data managers mapped to a participant, rendered as a fragment
       to be inserted into a DataTables child row on /admin/participants. */
    public function mappedDataManagersAction()
    {
        $this->_helper->layout()->disableLayout();
        $participantId = (int)$this->_getParam('id');
        $this->view->participantId = $participantId;
        $this->view->mappedDms = [];
        if ($participantId > 0) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $select = $db->select()
                ->from(array('pmm' => 'participant_manager_map'), array())
                ->join(array('dm' => 'data_manager'), 'dm.dm_id = pmm.dm_id', array('dm_id', 'first_name', 'last_name', 'primary_email', 'data_manager_type', 'status', 'institute'))
                ->where('pmm.participant_id = ?', $participantId)
                ->order(array('dm.first_name', 'dm.last_name'));
            $this->view->mappedDms = $db->fetchAll($select);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        $dataManagerService = new Application_Service_DataManagers();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participantService->addParticipant($params);
            $this->redirect("/admin/participants");
        }
        $this->view->directParticipantLogin = $commonService->getConfig('direct_participant_login');
        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
        $this->view->passLength = $commonService->getConfig('participant_login_password_length');
    }

    public function bulkImportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();
        if ($request->isPost()) {
            $this->view->response = $participantService->uploadBulkParticipants();
        }
    }

    public function participantUploadStatisticsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $participantService->uploadBulkParticipants($params);
            if (!$result) {
                $this->redirect("/admin/participants");
            } else {
                $this->view->response = $result;
            }
        } else {
            $this->redirect("/admin/participants");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participantService->updateParticipant($params);
            $this->redirect("/admin/participants");
        } else {
            if ($this->hasParam('id')) {
                $partSysId = (int) $this->_getParam('id');
                $this->view->participant = $participantService->getParticipantDetails($partSysId);
            }
            $this->view->affiliates = $participantService->getAffiliateList();
            $dataManagerService = new Application_Service_DataManagers();
            $this->view->networks = $participantService->getNetworkTierList();
            $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
            $this->view->selectedPtLogins = $dataManagerService->getDataManagersByParticipantId($partSysId);
            $this->view->siteType = $participantService->getSiteTypeList();
            $this->view->dataManagers = $dataManagerService->getDataManagerList();
            $this->view->countriesList = $commonService->getcountriesList();
            $this->view->directParticipantLogin = $commonService->getConfig('direct_participant_login');
            $this->view->passLength = $commonService->getConfig('participant_login_password_length');
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->participantSchemes = $participantService->getSchemesByParticipantId($partSysId);
    }

    public function pendingAction()
    {
        // action body
    }

    public function viewParticipantsAction()
    {
        $this->_helper->layout()->setLayout('modal');
        $participantService = new Application_Service_Participants();
        if ($this->hasParam('id')) {
            $dmId = (int) $this->_getParam('id');
            $this->view->participant = $participantService->getAllParticipantDetails($dmId);
        }
    }

    public function participantManagerMapAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        if ($request->isPost()) {

            $params = $request->getPost();
            $participantService->addParticipantManagerMap($params);
            if (!empty($params['isModal']) && !empty($params['datamanagerId'])) {
                $this->redirect('/admin/participants/participant-manager-map/id/' . (int) $params['datamanagerId'] . '/modal/1');
            }
            $this->redirect("/admin/participants/participant-manager-map");
        }
        $this->view->countries = $participantService->getParticipantCountriesList();
        $this->view->province = $commonService->getParticipantsProvinceList();
        $this->view->district = $commonService->getParticipantsDistrictList();
        $this->view->networksTier = $commonService->getAllnetwork();
        $this->view->affiliation = $commonService->getAllParticipantAffiliates();
        $this->view->institutes = $commonService->getAllInstitutes();

        $this->view->isModal = (bool) $this->_getParam('modal', false);
        if ($this->view->isModal) {
            $this->_helper->layout()->setLayout('modal');
        }
        $preselectedDmId = (int) $this->_getParam('id', 0);
        if ($preselectedDmId > 0) {
            $dataManagerService = new Application_Service_DataManagers();
            $dm = $dataManagerService->getUserInfoBySystemId($preselectedDmId);
            if (!empty($dm) && (!isset($dm['data_manager_type']) || $dm['data_manager_type'] !== 'ptcc')) {
                $label = [];
                if (!empty(trim($dm['first_name'] . ' ' . $dm['last_name']))) {
                    $label[] = trim($dm['first_name'] . ' ' . $dm['last_name']);
                }
                if (!empty(trim($dm['institute']))) {
                    $label[] = trim($dm['institute']);
                }
                if (!empty(trim($dm['primary_email']))) {
                    $label[] = trim($dm['primary_email']);
                }
                $this->view->preselectedDmId = $preselectedDmId;
                $this->view->preselectedDmLabel = implode(', ', $label);
            }
        }
    }

    public function getDatamanagerAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $dataManagerService = new Application_Service_DataManagers();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->mappedParticipant = $dataManagerService->getDatamanagerParticipantList($params);
            $this->view->participants = $dataManagerService->getParticipantDatamanagerList($params);
        }
    }

    public function getDatamanagerNamesAction()
    {
        $this->_helper->layout()->disableLayout();
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->hasParam('search')) {
            $participant = $this->_getParam('search');
            $this->view->paticipantManagers = $dataManagerService->getParticipantDatamanagerSearch($participant);
        }
    }

    public function getParticipantAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->disableLayout();
        $dataManagerService = new Application_Service_DataManagers();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->mappedParticipant = $dataManagerService->getDatamanagerParticipantList($params);
            $this->view->participants = $dataManagerService->getParticipantDatamanagerList($params);
        }
    }

    public function getParticipantListAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->disableLayout();
        $clientsServices = new Application_Service_Participants();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->participants = $clientsServices->getParticipantList($params);
            if (isset($params['schemeId']) && !empty($params['schemeId'])) {
                $this->view->mappedParticipant = $clientsServices->getEnrolledBySchemeCode($params['schemeId']);
            } else {
                $this->view->mappedParticipant = [];
            }
        }
    }

    public function exportParticipantsMapAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->disableLayout();
        if ($request->isPost()) {
            $participantService  = new Application_Service_Participants();
            // $params = $request->getPost();
            $this->view->result = $participantService->exportParticipantMapDetails();
        }
    }

    public function deleteParticipantAction()
    {
        $participantService = new Application_Service_Participants();
        if ($this->hasParam('participantId')) {
            $participantId = $this->_getParam('participantId');
            //$this->view->result = $participantService->deleteParticipant($participantId);
        }
    }

    public function exportParticipantsDetailsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->disableLayout();
        if ($request->isPost()) {
            $params['type'] = 'from-participant';
            $participantService = new Application_Service_Participants();
            $this->view->result = $participantService->exportShipmentRespondedParticipantsDetails($params);
        } else {
            return false;
        }
    }

    public function filesAction()
    {
        $downloadDirectory = scandir(DOWNLOADS_FOLDER, true);
        $reportLayouts = array_diff(array_unique($downloadDirectory), ['.', '..', 'reports', 'index.php']);
        $participantService = new Application_Service_Participants();
        $this->view->participants = $participantService->getAllActiveParticipants();
    }
}
