<?php

class ParticipantController extends Zend_Controller_Action
{

    private $noOfItems = 10;

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('defaulted-schemes', 'html')
            ->addActionContext('current-schemes', 'html')
            ->addActionContext('all-schemes', 'html')
            ->addActionContext('report', 'html')
            ->addActionContext('corrective', 'html')
            ->addActionContext('summary-report', 'html')
            ->addActionContext('shipment-report', 'html')
            ->addActionContext('add-qc', 'html')
            ->addActionContext('scheme', 'html')
            ->addActionContext('profile-update-redirect', 'html')
            ->addActionContext('get-participant-scheme-chart', 'html')
            ->addActionContext('resent-mail-verification', 'html')
            //->addActionContext('download-file-details', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            //SHIPMENT_OVERVIEW
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentOverview($params);
        } else {
            $this->redirect("/participant/dashboard");
        }
    }

    public function dashboardAction()
    {
        $this->_helper->layout()->activeMenu = 'dashboard';
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $scheme = new Application_Service_Schemes();
        $dm = new Application_Service_DataManagers();
        $this->view->participants = $dm->getParticipantsByDM();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->authNameSpace = $authNameSpace;
    }

    public function reportAction()
    {
        $this->_helper->layout()->activeMenu = 'view-reports';
        $this->_helper->layout()->activeSubMenu = 'individual-reports';
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getindividualReport($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function correctiveAction()
    {
        $this->_helper->layout()->activeMenu = 'corrective-action';
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getCorrectiveActionReport($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function userInfoAction()
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $sessionAlert = new Zend_Session_Namespace('alertSpace');

        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'user-info';
        $userService = new Application_Service_DataManagers();
        if ($this->_request->isPost()) {
            $params = $this->_request->getPost();
            $userService->updateUser($params);
        }
        // whether it is a GET or POST request, we always show the user info
        $this->view->rsUser = $userInfo = $userService->getUserInfo();
        if ($authNameSpace->force_profile_check_primary == 'yes') {
            $userService->updateForceProfileCheck(base64_encode($userInfo['primary_email']));
        }
    }

    public function testersAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'testers';
        $dbUsersProfile = new Application_Service_Participants();
        $this->view->rsUsersProfile = $dbUsersProfile->getUsersParticipants();
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if ($authNameSpace->view_only_access == 'yes') {
            $this->view->isEditable = false;
        } else {
            $this->view->isEditable = true;
        }
    }

    public function schemeAction()
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $dbUsersProfile = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $parameters = $this->getAllParams();
            $dbUsersProfile->getParticipantSchemesBySchemeId($parameters);
        } else {
            $this->_helper->layout()->activeMenu = 'my-account';
            $this->_helper->layout()->activeSubMenu = 'scheme';
            $this->view->participantSchemes = $dbUsersProfile->getParticipantSchemes($authNameSpace->dm_id);
        }
    }

    public function passwordAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'change-password';
        if ($this->getRequest()->isPost()) {
            $user = new Application_Service_DataManagers();
            $newPassword = $this->getRequest()->getPost('newpassword');
            $oldPassword = $this->getRequest()->getPost('oldpassword');
            $response = $user->changePassword($oldPassword, $newPassword);
            if ($response) {
                $this->redirect('/participant/current-schemes');
            }
        }
    }

    public function testereditAction()
    {
        // action body
        // Get
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'testers';
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost();
            $participantService->updateParticipant($data);
            $this->redirect('/participant/testers');
        } else {
            $this->view->rsParticipant = $participantService->getParticipantDetails($this->_getParam('psid'));
        }

        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->networks = $participantService->getNetworkTierList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if ($authNameSpace->view_only_access == 'yes') {
            $this->view->isEditable = false;
        } else {
            $this->view->isEditable = true;
        }
    }

    public function schemeinfoAction()
    {
        // action body
    }

    public function addAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'testers';
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $data = $this->getRequest()->getPost();
            $participantService->addParticipantForDataManager($data);
            $this->redirect('/participant/testers');
        }

        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if ($authNameSpace->view_only_access == 'yes') {
            $this->view->isEditable = false;
        } else {
            $this->view->isEditable = true;
        }
    }

    public function defaultedSchemesAction()
    {
        $this->_helper->layout()->activeMenu = 'defaulted-schemes';
        if ($this->getRequest()->isPost()) {
            //SHIPMENT_DEFAULTED
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentDefault($params);
        }
    }

    public function currentSchemesAction()
    {
        $this->_helper->layout()->activeMenu = 'current-schemes';
        if ($this->getRequest()->isPost()) {
            //SHIPMENT_CURRENT
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentCurrent($params);
        }
        $shipment = new Application_Service_Shipments();
        $this->view->shipment = $shipment->getAllShipmentCode();

        $province = new Application_Service_Participants();
        $this->view->province = $province->getUniqueState();
    }

    public function allSchemesAction()
    {
        $this->_helper->layout()->activeMenu = 'all-schemes';
        if ($this->getRequest()->isPost()) {
            //SHIPMENT_ALL
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentAll($params);
        }
        $commonService = new Application_Service_Common();
        $this->view->globalQcAccess = $commonService->getConfig('qc_access');
    }

    public function downloadAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('d92nl9d8d')) {
            $id = (int) base64_decode($this->_getParam('d92nl9d8d'));
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $this->view->result = $db->fetchRow($db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id'))
                ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('s.shipment_code'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
                ->where("spm.map_id = ?", $id));
        } else {
            $this->redirect("/participant/dashboard");
        }
    }

    public function resentMailVerificationAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $dmService = new Application_Service_DataManagers();
            $this->view->result = $dmService->resentDMVerifyMail($params);
        }
    }

    public function shipmentReportAction()
    {
        if ($this->getRequest()->isPost()) {
            //SHIPMENT_ALL
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentReport($params);
        }
    }

    public function summaryReportAction()
    {
        $this->_helper->layout()->activeMenu = 'view-reports';
        $this->_helper->layout()->activeSubMenu = 'summary-reports';
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getSummaryReport($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function addQcAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $this->view->result = $shipmentService->addQcDetails($params);
        }
    }

    public function profileUpdateRedirectAction()
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $authNameSpace->force_profile_updation = 0;
        $this->view->authNameSpace = $authNameSpace;
    }

    public function getParticipantSchemeChartAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $this->view->result = $shipmentService->getShipmentListBasedOnParticipant($params);
            $this->view->shipmentType = $params['shipmentType'];
            $this->view->render = $params['render'];
        }
    }

    public function certificateAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'testers';
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();

        if ($this->hasParam('pid')) {
            $pId = $this->_getParam('pid');
            $shipmentService = new Application_Service_Shipments();
            $this->view->certificate = $shipmentService->getParticipantShipments($pId);
            //$this->view->psId='5001';
            //echo "came";die;
        } else {
            $this->redirect("/participant/dashboard");
        }
    }
    public function fileDownloadsAction()
    {
        $this->_helper->layout()->activeMenu = 'file-download';
        $participantService = new Application_Service_Participants();
        $this->view->download = $participantService->getParticipantUniqueIdentifier();
    }

    public function downloadFileDetailsAction()
    {
        if ($this->hasParam('fileName')) {
            $params = $this->getAllParams();
            $this->view->parameters = $params;
        } else {
            $this->redirect("/participant/file-download");
        }
    }
}
