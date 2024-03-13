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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
        } else if ($this->hasParam('searchString')) {
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
    }

    public function getSampleFormAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {

            $this->view->scheme = $sid = strtolower($this->_getParam('sid'));
            $this->view->userconfig = $userconfig = strtolower($this->_getParam('userconfig'));

            if ($sid == 'vl') {
                $scheme = new Application_Service_Schemes();
                $this->view->vlControls = $scheme->getSchemeControls($sid);
                $this->view->vlAssay = $scheme->getVlAssay();
            } else if ($sid == 'eid') {
                $scheme = new Application_Service_Schemes();
                $this->view->eidControls = $scheme->getSchemeControls($sid);
                $this->view->eidPossibleResults = $scheme->getPossibleResults($sid);
            } else if ($sid == 'dts') {

                $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
                $config = $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);

                $scheme = new Application_Service_Schemes();
                $dtsSchemeType = isset($config->evaluation->dts->dtsSchemeType) ? $config->evaluation->dts->dtsSchemeType : 'standard';
                $this->view->dtsPossibleResults = $scheme->getPossibleResults($sid);
                if ($dtsSchemeType == 'updated-3-tests') {
                    $this->view->rtriPossibleResults = $scheme->getPossibleResults('recency');
                }
                $this->view->allTestKits = $scheme->getAllDtsTestKit();

                $this->view->config = $config;

                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } else if ($sid == 'dbs') {
                $scheme = new Application_Service_Schemes();
                $this->view->dtsPossibleResults = $scheme->getPossibleResults($sid);
                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } else if ($sid == 'recency') {
                $scheme = new Application_Service_Schemes();
                $this->view->recencyPossibleResults = $scheme->getPossibleResults($sid);
                $this->view->recencyAssay = $scheme->getRecencyAssay();
            } else if ($sid == 'covid19') {
                $scheme = new Application_Service_Schemes();
                $this->view->covid19PossibleResults = $scheme->getPossibleResults($sid);
                $this->view->allTestKits = $scheme->getAllCovid19TestType();

                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } else if ($sid == 'tb') {
                $schemeService = new Application_Service_Schemes();
                $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb');
                $tbModel = new Application_Model_Tb();
                $this->view->assay = $tbModel->getAllTbAssays();
            } else if ($userconfig == 'yes') {
                $schemeService = new Application_Service_Schemes();
                $this->view->otherTestsPossibleResults = $schemeService->getPossibleResults($sid);
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

                $this->view->participantCity  = $participantService->getUniqueCity();
                $this->view->participantState  = $participantService->getUniqueState();
                $this->view->participantRegion  = $participantService->getUniqueRegion();
                $this->view->participantDistrict  = $participantService->getUniqueDistrict();
                $this->view->participantCountry  = $participantService->getUniqueCountry();

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
            $shipmentService = new Application_Service_Shipments();
            $participantService = new Application_Service_Participants();
            $sid = (int) $params['sid'];
            $this->view->shipment = $shipmentDetails = $shipmentService->getShipment($sid);
            $this->view->previouslySelected = $previouslySelected = $participantService->getEnrolledByShipmentId($sid);

            $this->view->participantCity  = $participantService->getUniqueCity();
            $this->view->participantState  = $participantService->getUniqueState();
            $this->view->participantListsName  = $participantService->getParticipantsListNamesByUniqueId($params['unique']);

            if ($previouslySelected == "" || $previouslySelected == null) {
                $this->view->enrolledParticipants = $participantService->getEnrolledBySchemeCode($shipmentDetails['scheme_type']);
                $this->view->unEnrolledParticipants = $participantService->getUnEnrolled($shipmentDetails['scheme_type']);
            } else {
                $this->view->previouslyUnSelected = $participantService->getUnEnrolledByShipmentId($sid);
            }
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
                $userConfig = (int) base64_decode($this->_getParam('userConfig'));
                $schemeService = new Application_Service_Schemes();
                $shipmentService = new Application_Service_Shipments();
                $this->view->tbPossibleResults = $schemeService->getPossibleResults('tb');
                $this->view->shipmentData = $response = $shipmentService->getShipmentForEdit($sid);
                $schemeService = new Application_Service_Schemes();
                if ($response['shipment']['scheme_type'] == 'dts') {
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();
                    $this->view->dtsPossibleResults = $schemeService->getPossibleResults('dts');
                    $this->view->rtriPossibleResults = $schemeService->getPossibleResults('recency');
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();
                    $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
                    $this->view->config = new Zend_Config_Ini($file, APPLICATION_ENV);
                } else if ($response['shipment']['scheme_type'] == 'covid19') {
                    $this->view->covid19PossibleResults = $schemeService->getPossibleResults('covid19');
                    $this->view->allTestTypes = $schemeService->getAllCovid19TestType();
                } else if ($response['shipment']['scheme_type'] == 'vl') {

                    $this->view->vlAssay = $schemeService->getVlAssay();
                } else if ($response['shipment']['scheme_type'] == 'recency') {
                    $scheme = new Application_Service_Schemes();
                    $this->view->recencyPossibleResults = $scheme->getPossibleResults($response['shipment']['scheme_type']);
                    $this->view->recencyAssay = $scheme->getRecencyAssay();
                } else if ($response['shipment']['scheme_type'] == 'tb') {
                    $tbModel = new Application_Model_Tb();
                    $this->view->assay = $tbModel->getAllTbAssays();
                } else if ($userConfig == 'yes') {
                    $scheme = new Application_Service_Schemes();
                    $this->view->otherTestsPossibleResults = $scheme->getPossibleResults($response['shipment']['scheme_type']);
                }

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

                //echo count($previouslySelected);die;
                if (count($previouslySelected) == 0 || $previouslySelected == "" || $previouslySelected == null) {
                    //echo"ss";die;
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
