<?php

class ParticipantController extends Zend_Controller_Action
{

    private $noOfItems = 10;

    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
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
            ->addActionContext('view', 'html')
            ->addActionContext('get-datamanager', 'html')
            ->addActionContext('get-datamanager-names', 'html')
            ->addActionContext('get-participant', 'html')
            ->addActionContext('delete-participant', 'html')
            ->addActionContext('response-report', 'html')
            ->addActionContext('response-report-list', 'html')
            ->addActionContext('participant-performance', 'html')
            ->addActionContext('participant-performance-export-pdf', 'html')
            ->addActionContext('aberrant-test-results', 'html')
            ->addActionContext('participant-performance-timeliness-barchart', 'html')
            ->addActionContext('participant-performance-export', 'html')
            ->addActionContext('region-wise-participant-report', 'html')
            ->addActionContext('participant-performance-region-wise-export', 'html')
            ->addActionContext('shipment-response-report', 'html')
            ->addActionContext('participant-response', 'html')
            ->addActionContext('shipments-reports', 'html')
            ->addActionContext('get-shipment-participant-list', 'html')
            ->addActionContext('tb-results', 'html')
            ->addActionContext('results-count', 'html')
            ->addActionContext('tb-participants-per-country', 'html')
            ->addActionContext('participants-count', 'html')
            ->addActionContext('xtpt-indicators', 'html')
            ->addActionContext('tb-all-sites-results', 'html')
            ->addActionContext('download-pending-sites', 'html')
            //->addActionContext('download-file', 'html')
            ->initContext();
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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

        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'user-info';
        $userService = new Application_Service_DataManagers();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $userService->updateUser($params);
        }
        // whether it is a GET or POST request, we always show the user info
        $this->view->rsUser = $userInfo = $userService->getUserInfo();
        if ($authNameSpace->force_profile_check_primary == 'yes') {
            $userService->updateForceProfileCheck(base64_encode($userInfo['primary_email']));
        }
        $commonService = new Application_Service_Common();
        $this->view->participantEditName = $commonService->getConfig('participants_can_edit_name');
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $user = new Application_Service_DataManagers();
            $newPassword = $request->getPost('newpassword');
            $oldPassword = $request->getPost('oldpassword');
            $response = $user->changePassword($oldPassword, $newPassword);
            if ($response) {
                $this->redirect('/participant/current-schemes');
            }
        }
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
    }

    public function changePrimaryEmailAction()
    {
        $this->_helper->layout()->activeSubMenu = 'change-primary-email';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $user = new Application_Service_DataManagers();
            $params = $this->getAllParams();
            $response = $user->confirmPrimaryMail($params, true);
            if ($response) {
                $this->redirect('/participant/current-schemes');
            }
        }
    }

    public function participantMessageAction()
    {
        $this->_helper->layout()->activeSubMenu = 'participant-message';

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $pmService = new Application_Service_ParticipantMessages();
            $response = $pmService->addParticipantMessage($params);
            $sessionAlert = new Zend_Session_Namespace('alertSpace');
            if ($response['status'] === 'success') {
                $sessionAlert->message = "Mail set successfully";
                $sessionAlert->status = "success";
                return true;
            } else {
                $sessionAlert->message = "Sorry, we could not process this message. Please try again";
                $sessionAlert->status = "failure";
                return false;
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
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
        $this->view->participantEditName = $commonService->getConfig('participants_can_edit_name');
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $participantService->addParticipantForDataManager($data);
            $this->redirect('/participant/testers');
        }
        $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
        $this->view->passLength = $globalConfigDb->getValue('participant_login_password_length');
        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
        $this->view->participantEditName = $commonService->getConfig('participants_can_edit_name');
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

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            //SHIPMENT_DEFAULTED
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getShipmentDefault($params);
        }
    }

    public function currentSchemesAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->activeMenu = 'current-schemes';
        if ($request->isPost()) {
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $this->_helper->layout()->activeMenu = 'all-schemes';
        if ($request->isPost()) {
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
            $sQuery = $db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id'))
                ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('s.shipment_code'))
                ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
                ->where("spm.map_id = ?", $id);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (!empty($authNameSpace->dm_id)) {
                $sQuery = $sQuery
                    ->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
                    ->where("pmm.dm_id = ?", $authNameSpace->dm_id);
            }
            $this->view->result = $db->fetchRow($sQuery);
        } else {
            $this->redirect("/participant/dashboard");
        }
    }

    public function resentMailVerificationAction()
    {
        $this->_helper->layout()->disableLayout();

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $dmService = new Application_Service_DataManagers();
            $this->view->result = $dmService->resentDMVerifyMail($params);
        }
    }

    public function shipmentReportAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getSummaryReport($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function addQcAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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

    public function downloadTbAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('file')) {
            $params = $this->getAllParams();
            // die(base64_decode($file['file']));
            $file = base64_decode($params['file']);
            if (!isset($params['file']) || empty($params['file']) || !file_exists($file)) {
                $shipmentService = new Application_Service_Shipments();
                $file = $shipmentService->generateTbPdf($params['sid'], $params['pid']);
            }
            $this->view->file = $params['file'];
        } else {
            $this->redirect("/participant/current-scheme");
        }
    }

    public function downloadFileAction()
    {
        if ($this->hasParam('fileName')) {
            $params = $this->getAllParams();
            $this->view->parameters = $params;
        } else {
            $this->redirect("/participant/file-download");
        }
    }

    public function viewAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'ptcc-participant';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getAllParticipants($params);
        }
    }

    public function addParticipantAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'ptcc-participant';
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        $dataManagerService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participantService->addParticipant($params);
            $this->redirect("/participant/view");
        }

        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
    }

    public function editParticipantAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'ptcc-participant';
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participantService->updateParticipant($params);
            $this->redirect("/participant/view");
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

    public function getDatamanagerNamesAction()
    {
        $this->_helper->layout()->disableLayout();
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->hasParam('search')) {
            $participant = $this->_getParam('search');
            $this->view->paticipantManagers = $dataManagerService->getParticipantDatamanagerSearch($participant);
        }
    }

    public function participantManagerMapAction()
    {
        $this->_helper->layout()->activeMenu = 'my-account';
        $this->_helper->layout()->activeSubMenu = 'ptcc-participant-map';
        $participantService = new Application_Service_Participants();
        $dataManagerService = new Application_Service_DataManagers();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participantService->addParticipantManagerMap($params, 'participant-side');
            $this->redirect("/participant/participant-manager-map");
        }
        $this->view->participants = $participantService->getAllActiveParticipants();
        $this->view->dataManagers = $dataManagerService->getDataManagerList(false);
    }

    public function getDatamanagerAction()
    {
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->hasParam('participantId')) {
            $participantId = $this->_getParam('participantId');
            $this->view->paticipantManagers = $dataManagerService->getParticipantDatamanagerListByPid($participantId);
        }
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
    }

    public function getParticipantAction()
    {
        $participantService = new Application_Service_Participants();
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->hasParam('datamanagerId')) {
            $datamanagerId = $this->_getParam('datamanagerId');
            $this->view->mappedParticipant = $dataManagerService->getDatamanagerParticipantListByDid($datamanagerId);
        }
        $this->view->participants = $participantService->getAllActiveParticipants();
    }

    public function responseReportAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'participant-response-reports';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantDetailedReport($params);
            $this->view->response = $response;
            $this->view->type = $params['reportType'];
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function responseReportListAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllParticipantDetailedReport($params);
        }
    }

    public function participantPerformanceAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'participant-performance-reports';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantPerformanceReport($params);
            $this->view->response = $response;
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if (isset($_COOKIE['did']) && $_COOKIE['did'] != '' && $_COOKIE['did'] != null && $_COOKIE['did'] != 'NULL') {
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentDetails = $data = $shipmentService->getShipment($_COOKIE['did']);
            $this->view->schemeDetails = $scheme->getScheme($data["scheme_type"]);
        }
    }

    public function participantPerformanceTimelinessBarchartAction()
    {
        $reportService = new Application_Service_Reports();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getChartInfo($params);
        }
    }

    public function aberrantTestResultsAction()
    {
        $reportService = new Application_Service_Reports();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->result = $reportService->getAberrantChartInfo($params);
        }
    }

    public function participantPerformanceExportPdfAction()
    {
        $reportService = new Application_Service_Reports();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->header = $reportService->getReportConfigValue('report-header');
            $this->view->logo = $reportService->getReportConfigValue('logo');
            $this->view->logoRight = $reportService->getReportConfigValue('logo-right');
            $this->view->result = $reportService->exportParticipantTrendsReportInPdf();
            $this->view->dateRange = $params['dateRange'];
            $this->view->shipmentName = $params['shipmentName'];
        }
    }

    public function participantPerformanceExportAction()
    {
        $reportService = new Application_Service_Reports();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportParticipantPerformanceReport($params);
        }
    }

    public function regionWiseParticipantReportAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantPerformanceRegionWiseReport($params);
            $this->view->response = $response;
        }
    }

    public function participantPerformanceRegionWiseExportAction()
    {
        $reportService = new Application_Service_Reports();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->exported = $reportService->exportParticipantPerformanceRegionReport($params);
        }
    }

    public function shipmentResponseReportAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'shipment-response-report';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getShipmentResponseReportReport($params);
            $this->view->result = $response;
        }
        $participants = new Application_Service_Participants();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->countries = $participants->getParticipantCountriesList();
        $this->view->regions = $participants->getAllParticipantRegion();
        $this->view->states = $participants->getAllParticipantStates();
        $this->view->districts = $participants->getAllParticipantDistricts();
    }


    public function participantResponseAction()
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $this->view->response = $participantService->getShipmentResponseReport($parameters);
        }
    }

    public function exportParticipantsResponseDetailsAction()
    {
        $this->_helper->layout()->disableLayout();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $this->view->result = $participantService->exportParticipantsResponseDetails($params);
        } else {
            return false;
        }
    }

    public function shipmentsReportsAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'shipments-reports';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->getAllShipments($params);
        }

        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();

        $dataManagerService = new Application_Service_DataManagers();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
    }

    public function getShipmentParticipantListAction()
    {
        $reportService = new Application_Service_Reports();
        if ($this->hasParam('shipmentId')) {
            $shipmentId = base64_decode($this->_getParam('shipmentId'));
            $schemeType = ($this->_getParam('schemeType'));
            $this->view->result = $reportService->getShipmentParticipant($shipmentId, $schemeType);
        }
    }

    public function responseChartAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'shipments-reports';
        if ($this->hasParam('id')) {
            //Zend_Debug::dump(base64_decode($this->_getParam('shipmentCode')));die;
            $shipmentId = (int) base64_decode($this->_getParam('id'));
            $reportService = new Application_Service_Reports();
            $this->view->responseCount = $reportService->getShipmentResponseCount($shipmentId, base64_decode($this->_getParam('shipmentDate')));
            $this->view->shipmentDate = base64_decode($this->_getParam('shipmentDate'));
            $this->view->shipmentCode = base64_decode($this->_getParam('shipmentCode'));
        } else {
            $this->redirect("/admin/index");
        }
    }

    public function tbResultsAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'tb-results';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getResultsPerSiteReport($params);
            $this->view->response = $response;
        }
    }

    public function resultsCountAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->resultsCount = $reportService->getResultsPerSiteCount($params);
        }
    }

    public function tbParticipantsPerCountryAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'tb-participants-per-country';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $response = $reportService->getParticipantsPerCountryReport($params);
            $this->view->response = $response;
        }
    }

    public function participantsCountAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->participantsCount = $reportService->getParticipantsPerCountryCount($params);
        }
    }

    public function xtptIndicatorsAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'tb-xtpt-indicators';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();

            /* $evalService = new Application_Service_Evaluation();
            $evalService->getEvaluateReportsInPdf($params["shipmentId"], null, null); */

            $reportService = new Application_Service_Reports();
            $response = $reportService->getXtptIndicatorsReport($params);
            $this->view->response = $response;
        }
    }

    public function tbAllSitesResultsAction()
    {
        $this->_helper->layout()->activeMenu = 'ptcc-reports';
        $this->_helper->layout()->activeSubMenu = 'tb-all-sites-results';
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();

            /* $evalService = new Application_Service_Evaluation();
            $evalService->getEvaluateReportsInPdf($params["shipmentId"], null, null); */

            $reportService = new Application_Service_Reports();
            $response = $reportService->getTbAllSitesResultsReport($params);
            $this->view->response = $response;
        }
    }

    public function downloadPendingSitesAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $this->view->response = $reportService->getStatusOfMappedSites($parameters);
        }
    }

    public function feedBackAction()
    {
        $feedbackService = new Application_Service_FeedBack();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $this->view->response = $feedbackService->saveFeedBackForms($params);
            $this->redirect("/participant/report");
        } else {
            $this->view->sID = $sid = $request->getParam('sid');
            $this->view->pID = $pid = $request->getParam('pid');
            $this->view->mID = $mid = $request->getParam('mid');
            $this->view->questions = $feedbackService->getFeedBackQuestions($sid);
            $this->view->ans = $feedbackService->getFeedBackAnswers($sid, $pid, $mid);
        }
    }
}
