<?php

class Admin_ParticipantsController extends Zend_Controller_Action
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
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('view-participants', 'html')
            ->addActionContext('get-datamanager', 'html')
            ->addActionContext('get-datamanager-names', 'html')
            ->addActionContext('get-participant', 'html')
            ->addActionContext('delete-participant', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getAllParticipants($params);
        }
    }

    public function addAction()
    {
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $participantService->addParticipant($params);
            $this->redirect("/admin/participants");
        }

        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
    }

    public function bulkImportAction()
    {
        $participantService = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $this->view->response = $participantService->uploadBulkParticipants();
        }
    }

    public function participantUploadStatisticsAction()
    {
        $participantService = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $result = $participantService->uploadBulkParticipants();
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

        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
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
        $participantService = new Application_Service_Participants();
        $dataManagerService = new Application_Service_DataManagers();
        $commonService = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            
            $params = $this->getRequest()->getPost();
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
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
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
        $this->_helper->layout()->disableLayout();
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $this->view->mappedParticipant = $dataManagerService->getDatamanagerParticipantList($params);
            $this->view->participants = $dataManagerService->getParticipantDatamanagerList($params);
        }
    }

    public function deleteParticipantAction()
    {
        $participantService = new Application_Service_Participants();
        if ($this->hasParam('participantId')) {
            $participantId = $this->_getParam('participantId');
            $this->view->result = $participantService->deleteParticipant($participantId);
        }
    }

    public function exportParticipantsDetailsAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $params['type'] = 'from-participant';
            $participantService = new Application_Service_Participants();
            $this->view->result = $participantService->exportShipmentRespondedParticipantsDetails($params);
        } else {
            return false;
        }
    }
}
