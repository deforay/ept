<?php

class Admin_ShipmentController extends Zend_Controller_Action
{

    public function init()
    {
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
                ->addActionContext('shipment-responded-participants', 'html')
                ->addActionContext('shipment-not-responded-participants', 'html')
                ->addActionContext('shipment-not-enrolled-participants', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            //Zend_Debug::dump($params);die;
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getAllShipments($params);
        } else if ($this->_hasParam('searchString')) {
            $this->view->searchData = $this->_getParam('searchString');
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if ($this->_hasParam('did')) {
            $this->view->selectedDistribution = (int) base64_decode($this->_getParam('did'));
        } else {
            $this->view->selectedDistribution = "";
        }
        $distro = new Application_Service_Distribution();
        $this->view->unshippedDistro = $distro->getUnshippedDistributions();
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->addShipment($params);
            if (isset($params['selectedDistribution']) && $params['selectedDistribution'] != "" && $params['selectedDistribution'] != null) {
                $this->_redirect("/admin/shipment/index/did/" . base64_encode($params['selectedDistribution']));
            } else {
                $this->_redirect("/admin/shipment");
            }
        }
    }

    public function getSampleFormAction()
    {
        if ($this->getRequest()->isPost()) {

            $this->view->scheme = $sid = strtolower($this->_getParam('sid'));

            if ($sid == 'vl') {
                $scheme = new Application_Service_Schemes();
                $this->view->vlControls = $scheme->getSchemeControls($sid);
                $this->view->vlAssay = $scheme->getVlAssay();
            } else if ($sid == 'eid') {
                $scheme = new Application_Service_Schemes();
                $this->view->eidControls = $scheme->getSchemeControls($sid);
                $this->view->eidPossibleResults = $scheme->getPossibleResults($sid);
            } else if ($sid == 'dts') {
                $scheme = new Application_Service_Schemes();
                $this->view->dtsPossibleResults = $scheme->getPossibleResults($sid);
                $this->view->allTestKits = $scheme->getAllDtsTestKit();

                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            } else if ($sid == 'dbs') {
                $scheme = new Application_Service_Schemes();
                $this->view->dtsPossibleResults = $scheme->getPossibleResults($sid);


                $this->view->wb = $scheme->getDbsWb();
                $this->view->eia = $scheme->getDbsEia();
            }
        }
    }

    public function shipItAction()
    {
        $shipmentService = new Application_Service_Shipments();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $shipmentService->shipItNow($params);
            $this->_redirect("/admin/shipment");
        } else {
            if ($this->_hasParam('sid')) {
                $participantService = new Application_Service_Participants();
                $sid = (int) base64_decode($this->_getParam('sid'));
                $this->view->shipment = $shipmentDetails = $shipmentService->getShipment($sid);
                $this->view->previouslySelected = $previouslySelected = $participantService->getEnrolledByShipmentId($sid);
                if ($previouslySelected == "" || $previouslySelected == null) {
                    $this->view->enrolledParticipants = $participantService->getEnrolledBySchemeCode($shipmentDetails['scheme_type']);
                    $this->view->unEnrolledParticipants = $participantService->getUnEnrolled($shipmentDetails['scheme_type']);
                } else {
                    $this->view->previouslyUnSelected = $participantService->getUnEnrolledByShipmentId($sid);
                }
            }
        }
    }

    public function removeAction()
    {
        if ($this->_hasParam('sid')) {
            $sid = (int) base64_decode($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->message = $shipmentService->removeShipment($sid);
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function editAction()
    {

        if ($this->getRequest()->isPost()) {
            $shipmentService = new Application_Service_Shipments();
            $params = $this->getRequest()->getPost();
            $shipmentService->updateShipment($params);
            $this->_redirect("/admin/shipment");
        } else {
            if ($this->_hasParam('sid')) {
                $sid = (int) base64_decode($this->_getParam('sid'));
                $shipmentService = new Application_Service_Shipments();
                $this->view->shipmentData = $response = $shipmentService->getShipmentForEdit($sid);

                $schemeService = new Application_Service_Schemes();
                if($response['shipment']['scheme_type'] == 'dts'){
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();
                    $this->view->dtsPossibleResults = $schemeService->getPossibleResults('dts');
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();                    
                }else if($response['shipment']['scheme_type'] == 'vl'){
                    
                    $this->view->vlAssay = $schemeService->getVlAssay();
                    
                }
                
                // oOps !! Nothing to edit....
                if ($response== null || $response == "" || $response === false) {
                    $this->_redirect("/admin/shipment");
                }
            } else {
                $this->_redirect("/admin/shipment");
            }
        }
    }

    public function viewEnrollmentsAction()
    {
        //$this->_helper->layout()->setLayout('modal');
        $participantService = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $participantService->getShipmentEnrollement($params);
        }
        if ($this->_hasParam('id')) {
            $shipmentId = (int) base64_decode($this->_getParam('id'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipment = $shipmentService->getShipment($shipmentId);
            $this->view->shipmentCode = $this->_getParam('shipmentCode');
        } else {
            $this->_redirect("/admin/index");
        }
    }

    public function deleteShipmentParticipantAction()
    {
        if ($this->_hasParam('mid')) {
            if ($this->getRequest()->isPost()) {
                $mapId = (int) base64_decode($this->_getParam('mid'));
                $shipmentService = new Application_Service_Shipments();
                $this->view->result = $shipmentService->removeShipmentParticipant($mapId);
            }
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function unenrollmentsAction()
    {
        $participantService = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $participantService->getShipmentUnEnrollements($params);
        }
    }

    public function addEnrollmentsAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->addEnrollements($params);
            $this->_redirect("/admin/shipment/view-enrollments/id/" . $params['shipmentId']);
        }
    }

    public function getShipmentCodeAction()
    {
        if ($this->getRequest()->isPost()) {
            $sid = strtolower($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->code = $shipmentService->getShipmentCode($sid);
        }
    }

    public function newShipmentMailAction()
    {
        if ($this->getRequest()->isPost()) {
            $sid = strtolower(base64_decode($this->_getParam('sid')));
            $shipmentService = new Application_Service_Shipments();
            $this->view->pcount = $shipmentService->getShipmentParticipants($sid);
        }
    }

    public function notParticipatedMailAction()
    {
        if ($this->getRequest()->isPost()) {
            $sid = strtolower(base64_decode($this->_getParam('sid')));
            $shipmentService = new Application_Service_Shipments();
            $this->view->pcount = $shipmentService->getShipmentNotParticipated($sid);
        }
    }

    public function manageEnrollAction()
    {
         if ($this->_hasParam('sid')) {
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
           if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getShipmentRespondedParticipants($params);
        }
    }

    public function shipmentNotRespondedParticipantsAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getShipmentNotRespondedParticipants($params);
        }
    }

    public function shipmentNotEnrolledParticipantsAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getShipmentNotEnrolledParticipants($params);
        }
    }

    public function enrollShipmentParticipantAction()
    {
         if ($this->_hasParam('sid') && $this->_hasParam('pid')) {
            if ($this->getRequest()->isPost()) {
                $shipmentId = (int) base64_decode($this->_getParam('sid'));
                $participantId = $this->_getParam('pid');
                $shipmentService = new Application_Service_Shipments();
                $this->view->result = $shipmentService->enrollShipmentParticipant($shipmentId,$participantId);
            }
        } else {
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
        
    }

    public function responseSwitchAction()
    {
         if ($this->_hasParam('sid') && $this->_hasParam('switchStatus')) {
            if ($this->getRequest()->isPost()) {
                $shipmentId = (int) ($this->_getParam('sid'));
                $switchStatus = strtolower($this->_getParam('switchStatus'));
                $shipmentService = new Application_Service_Shipments();
                $this->view->message = $shipmentService->responseSwitch($shipmentId,$switchStatus);
            }
        } else {
            $this->view->message = "Unable to update status. Please try again later or contact system admin for help";
        }
    }


}














