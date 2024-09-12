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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('view-participants', 'html')
            ->addActionContext('get-datamanager', 'html')
            ->addActionContext('get-datamanager-names', 'html')
            ->addActionContext('get-participant', 'html')
            ->addActionContext('delete-participant', 'html')
            ->addActionContext('export-participants-map', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getAllParticipants($params);
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
        $dataManagerService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        if ($request->isPost()) {

            $params = $request->getPost();
            $participantService->addParticipantManagerMap($params);
            $this->redirect("/admin/participants/participant-manager-map");
        }
        $this->view->participants = $participantService->getAllActiveParticipants();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        $this->view->countries = $participantService->getParticipantCountriesList();
        $this->view->province = $commonService->getParticipantsProvinceList();
        $this->view->district = $commonService->getParticipantsDistrictList();
        $this->view->networksTier = $commonService->getAllnetwork();
        $this->view->affiliation = $commonService->getAllParticipantAffiliates();
        $this->view->institutes = $commonService->getAllInstitutes();
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
}
