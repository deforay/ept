<?php

class Admin_ShipmentController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('manage-shipments', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-sample-form', 'html')
            ->addActionContext('get-shipment-code', 'html')
            ->addActionContext('remove', 'html')
            ->addActionContext('view-enrollments', 'html')
            ->addActionContext('delete-shipment-participant', 'html')
            ->addActionContext('new-shipment-mail', 'html')
            ->addActionContext('unenrollments', 'html')
            ->addActionContext('response-switch', 'html')
            ->addActionContext('enroll-shipment-participant', 'html')
            ->addActionContext('shipment-responded-participants', 'html')
            ->addActionContext('shipment-not-responded-participants', 'html')
            ->addActionContext('shipment-not-enrolled-participants', 'html')
            ->addActionContext('export-shipment-responded-participants', 'html')
            ->addActionContext('export-shipment-not-responded-participants', 'html')
            ->addActionContext('get-participants', 'html')
            ->addActionContext('get-enrollment-list', 'html')
            ->addActionContext('generate-tb-form', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            //Zend_Debug::dump($params);die;
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getAllShipments($params);
        } elseif ($this->hasParam('searchString')) {
            $this->view->searchData = $this->_getParam('searchString');
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if ($this->hasParam('did')) {
            $this->view->selectedDistribution = (int) base64_decode($this->_getParam('did'));
        } else {
            $this->view->selectedDistribution = "";
        }
        $distro = new Application_Service_Distribution();
        $this->view->unshippedDistro = $distro->getUnshippedDistributions();
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->addShipment($params);
            if (isset($params['selectedDistribution']) && $params['selectedDistribution'] != "" && $params['selectedDistribution'] != null) {
                $this->redirect("/admin/shipment/index/did/" . base64_encode($params['selectedDistribution']));
            } else {
                $this->redirect("/admin/shipment");
            }
        }
        $common = new Application_Service_Common();
        $this->view->feedbackOption = $common->getConfig('participant_feedback');
    }

    public function getSampleFormAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $scheme = new Application_Service_Schemes();
            $this->view->scheme = $sid = strtolower($this->_getParam('sid'));
            $this->view->schemeDetails = $scheme->getSchemeById($sid);
            $this->view->userconfig = $userconfig = strtolower($this->_getParam('userconfig'));

            if ($sid == 'vl') {
                $vlModel       = new Application_Model_Vl();
                $this->view->vlControls = $scheme->getSchemeControls($sid);
                $this->view->vlAssay = $vlModel->getVlAssay();
            } elseif ($sid == 'eid') {
                $this->view->eidControls = $scheme->getSchemeControls($sid);
                $this->view->eidPossibleResults = $scheme->getPossibleResults('eid', 'admin');
            } elseif ($sid == 'dts') {

                $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
                $config = $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
                $common = new Application_Service_Common();
                $this->view->dtsConfig = $common->getSchemeConfig('dts');
                $reportService = new Application_Service_Reports();
                $this->view->reportType = $reportService->getReportConfigValue('report-layout');
                $dtsSchemeType = isset($config->evaluation->dts->dtsSchemeType) ? $config->evaluation->dts->dtsSchemeType : 'standard';
                $this->view->dtsPossibleResults = $scheme->getPossibleResults('dts', 'admin');
                if ($dtsSchemeType == 'updated-3-tests') {
                    $this->view->rtriPossibleResults = $scheme->getPossibleResults('recency', 'admin');
                }
                $this->view->allTestKits = $scheme->getAllDtsTestKit();

                $this->view->config = $config;

                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } elseif ($sid == 'dbs') {
                $this->view->dtsPossibleResults = $scheme->getPossibleResults('dbs', 'admin');
                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } elseif ($sid == 'recency') {
                $this->view->recencyPossibleResults = $scheme->getPossibleResults('recency', 'admin');
                $this->view->recencyAssay = $scheme->getRecencyAssay();
            } elseif ($sid == 'covid19') {
                $this->view->covid19PossibleResults = $scheme->getPossibleResults('covid19', 'admin');
                $this->view->allTestKits = $scheme->getAllCovid19TestType();

                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } elseif ($sid == 'tb') {
                $this->view->tbPossibleResults = $scheme->getPossibleResults('tb', 'admin');
                $tbModel = new Application_Model_Tb();
                $this->view->assay = $tbModel->getAllTbAssays();
            } elseif ($userconfig == 'yes') {
                $this->view->otherTestsPossibleResults = $scheme->getPossibleResults($sid, 'admin');
            }
        }
    }

    public function shipItAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $shipmentService = new Application_Service_Shipments();
        if ($request->isPost()) {
            $params = $request->getPost();
            $shipmentService->shipItNow($params);
            $this->redirect("/admin/shipment");
        } else {
            if ($this->hasParam('sid')) {
                $participantService = new Application_Service_Participants();
                $sid = (int) base64_decode($this->_getParam('sid'));
                $this->view->shipment = $shipmentDetails = $shipmentService->getShipment($sid);
                $this->view->previouslySelected = $previouslySelected = $participantService->getEnrolledByShipmentId($sid);

                $this->view->participantListsName  = $participantService->getParticipantsListNames();
                if ($previouslySelected == "" || $previouslySelected == null) {
                    $this->view->enrolledParticipants = $participantService->getEnrolledBySchemeCode($shipmentDetails['scheme_type']);
                    $this->view->unEnrolledParticipants = $participantService->getUnEnrolled($shipmentDetails['scheme_type']);
                } else {
                    $this->view->previouslyUnSelected = $participantService->getUnEnrolledByShipmentId($sid);
                }
            }
        }
    }

    public function getEnrollmentListAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $participantService = new Application_Service_Participants();
            $this->view->participantListsName  = $participantService->getParticipantsListNamesByUniqueId($params['unique']);
        }
    }

    public function removeAction()
    {
        if ($this->hasParam('sid')) {
            $sid = (int) base64_decode($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->message = $shipmentService->removeShipment($sid);
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            $shipmentService = new Application_Service_Shipments();
            $params = $request->getPost();
            $shipmentService->updateShipment($params);
            $this->redirect("/admin/shipment");
        } else {
            if ($this->hasParam('sid')) {
                $sid = (int) base64_decode($this->_getParam('sid'));
                $userConfig = base64_decode($this->_getParam('userConfig'));
                $schemeService = new Application_Service_Schemes();
                $shipmentService = new Application_Service_Shipments();
                $reportService = new Application_Service_Reports();
                $this->view->reportType = $reportService->getReportConfigValue('report-layout');
                $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb', 'admin');
                $this->view->shipmentData = $response = $shipmentService->getShipmentForEdit($sid);
                $this->view->schemeDetails = $schemeService->getSchemeById($response['shipment']['scheme_type']);
                if ($response['shipment']['scheme_type'] == 'dts') {
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();
                    $this->view->dtsPossibleResults = $schemeService->getPossibleResults('dts', 'admin');
                    $this->view->rtriPossibleResults = $schemeService->getPossibleResults('recency', 'admin');
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();
                    $this->view->dtsConfig = Pt_Commons_SchemeConfig::get('dts');
                } elseif ($response['shipment']['scheme_type'] == 'covid19') {
                    $this->view->covid19PossibleResults = $schemeService->getPossibleResults('covid19', 'admin');
                    $this->view->allTestTypes = $schemeService->getAllCovid19TestType();
                } elseif ($response['shipment']['scheme_type'] == 'vl') {
                    $vlModel       = new Application_Model_Vl();
                    $this->view->vlAssay = $vlModel->getVlAssay();
                } elseif ($response['shipment']['scheme_type'] == 'recency') {
                    $this->view->recencyPossibleResults = $schemeService->getPossibleResults('recency', 'admin');
                    $this->view->recencyAssay = $schemeService->getRecencyAssay();
                } elseif ($response['shipment']['scheme_type'] == 'tb') {
                    $tbModel = new Application_Model_Tb();
                    $this->view->assay = $tbModel->getAllTbAssays();
                } elseif ($userConfig == 'yes') {
                    $this->view->otherTestsPossibleResults = $schemeService->getPossibleResults($response['shipment']['scheme_type'], 'admin');
                }
                $common = new Application_Service_Common();
                $this->view->feedbackOption = $common->getConfig('participant_feedback');
                // Oops !! Nothing to edit....
                if ($response == null || $response == "" || $response === false) {
                    $this->redirect("/admin/shipment");
                }
            } else {
                $this->redirect("/admin/shipment");
            }
        }
    }

    public function viewEnrollmentsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        //$this->_helper->layout()->setLayout('modal');
        $participantService = new Application_Service_Participants();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $participantService->getShipmentEnrollement($params);
        }
        if ($this->hasParam('id')) {
            $shipmentId = (int) base64_decode($this->_getParam('id'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipment = $shipmentService->getShipment($shipmentId);
            $this->view->shipmentCode = $this->_getParam('shipmentCode');
        } else {
            $this->redirect("/admin/index");
        }
    }

    public function deleteShipmentParticipantAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($this->hasParam('mid')) {
            if ($request->isPost()) {
                $mapId = (int) base64_decode($this->_getParam('mid'));
                $sId = (int) base64_decode($this->_getParam('sid'));
                $shipmentService = new Application_Service_Shipments();
                $this->view->result = $shipmentService->removeShipmentParticipant($mapId, $sId);
            }
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function unenrollmentsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $participantService->getShipmentUnEnrollements($params);
        }
    }

    public function addEnrollmentsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->addEnrollements($params);
            $this->redirect("/admin/shipment/view-enrollments/id/" . $params['shipmentId']);
        }
    }

    public function getShipmentCodeAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $sid = strtolower($this->_getParam('sid'));
            $userconfig = strtolower($this->_getParam('userconfig'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->code = $shipmentService->getShipmentCode($sid, null, $userconfig);
        }
    }

    public function newShipmentMailAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $sid = strtolower(base64_decode($this->_getParam('sid')));
            $shipmentService = new Application_Service_Shipments();
            $this->view->pcount = $shipmentService->sendShipmentMailAlertToParticipants($sid);
        }
    }

    public function notParticipatedMailAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $sid = strtolower(base64_decode($this->_getParam('sid')));
            $shipmentService = new Application_Service_Shipments();
            $this->view->pcount = $shipmentService->getShipmentNotParticipated($sid);
            $this->_helper->layout()->disableLayout();
        }
    }

    public function manageEnrollAction()
    {
        if ($this->hasParam('sid')) {
            $shipmentId = (int) base64_decode($this->_getParam('sid'));
            $schemeType = base64_decode($this->_getParam('sctype'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipment = $shipmentService->getShipmentForEdit($shipmentId);
            $this->view->shipmentId = $shipmentId;
            $this->view->schemeType = $schemeType;
        }
    }

    public function shipmentRespondedParticipantsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getShipmentRespondedParticipants($params);
        }
    }

    public function shipmentNotRespondedParticipantsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getShipmentNotRespondedParticipants($params);
        }
    }

    public function shipmentNotEnrolledParticipantsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getShipmentNotEnrolledParticipants($params);
        }
    }

    public function enrollShipmentParticipantAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($this->hasParam('sid') && $this->hasParam('pid')) {
            if ($request->isPost()) {
                $shipmentId = (int) base64_decode($this->_getParam('sid'));
                $participantId = $this->_getParam('pid');
                $shipmentService = new Application_Service_Shipments();
                $this->view->result = $shipmentService->enrollShipmentParticipant($shipmentId, $participantId);
            }
        } else {
            $this->view->message = "Please try again later or contact system admin for help";
        }
    }

    public function responseSwitchAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($this->hasParam('sid') && $this->hasParam('switchStatus')) {
            if ($request->isPost()) {
                $shipmentId = (int) ($this->_getParam('sid'));
                $switchStatus = strtolower($this->_getParam('switchStatus'));
                $shipmentService = new Application_Service_Shipments();
                $this->view->message = $shipmentService->responseSwitch($shipmentId, $switchStatus);
            }
        } else {
            $this->view->message = "Unable to update status. Please try again later or contact system admin for help";
        }
    }

    public function exportShipmentRespondedParticipantsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $this->view->result = $clientsServices->exportShipmentRespondedParticipantsDetails($params);
        }
    }

    public function exportShipmentNotRespondedParticipantsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $clientsServices = new Application_Service_Participants();
            $this->view->result = $clientsServices->exportShipmentNotRespondedParticipantsDetails($params);
        }
    }

    public function getParticipantsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $params = $request->getPost();

            if ($params['sid']) {
                $participantService = new Application_Service_Participants();
                $shipmentService = new Application_Service_Shipments();
                $sid = $params['sid'];

                $this->view->shipment = $shipmentDetails = $shipmentService->getShipment($sid);
                $this->view->previouslySelected = $previouslySelected = $participantService->getEnrolledByShipmentId($sid);

                if (empty($previouslySelected) || $previouslySelected == "") {
                    $this->view->enrolledParticipants = $participantService->getEnrolledBySchemeCode($shipmentDetails['scheme_type']);
                    $this->view->unEnrolledParticipants = $participantService->getUnEnrolled($shipmentDetails['scheme_type'], $params);
                } else {

                    $this->view->previouslyUnSelected = $participantService->getUnEnrolledByShipmentId($sid, $params);
                }
            }
        }
    }

    public function downloadTbAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('file')) {
            $params = $this->getAllParams();
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

    public function generateTbFormAction()
    {
        $this->_helper->layout()->disableLayout();
        if ($this->hasParam('sid')) {
            $params = $this->getAllParams();
            // die(base64_decode($file['file']));
            $sid = base64_decode($params['sid']);
            $shipmentService = new Application_Service_Shipments();
            $this->view->status = $shipmentService->runTbFormCron($sid);
        }
    }
}
