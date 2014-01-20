<?php

class Admin_ShipmentController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->addActionContext('get-sample-form', 'html')
                ->addActionContext('remove', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'configurations';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getAllShipments($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if($this->_hasParam('did')){
            $this->view->selectedDistribution = (int)base64_decode($this->_getParam('did'));
        }else{
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
            if(isset($params['selectedDistribution']) && $params['selectedDistribution'] != "" && $params['selectedDistribution'] != null){
                $this->_redirect("/admin/shipment/index/did/".base64_encode($params['selectedDistribution']));
            }else{
                $this->_redirect("/admin/shipment");    
            }
            
        }
    }

    public function getSampleFormAction()
    {
        if ($this->getRequest()->isPost()) {
            
            $this->view->scheme = $sid = strtolower($this->_getParam('sid'));
                          
            if($sid == 'vl'){
                $scheme = new Application_Service_Schemes();
                $this->view->vlControls = $scheme->getSchemeControls($sid);
            }
            else if($sid == 'eid'){
                $scheme = new Application_Service_Schemes();
                $this->view->eidControls = $scheme->getSchemeControls($sid);
                $this->view->eidPossibleResults = $scheme->getPossibleResults($sid);
            }
            else if($sid == 'dts'){
                $scheme = new Application_Service_Schemes();
                $this->view->dtsPossibleResults = $scheme->getPossibleResults($sid);
                $this->view->allTestKits = $scheme->getAllDtsTestKit();    
            }
            else if($sid == 'dbs'){
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
        if($this->getRequest()->isPost()){
            $params = $this->getRequest()->getPost();
                $shipmentService->shipItNow($params);
                $this->_redirect("/admin/shipment");
                
        }else{
            if($this->_hasParam('sid')){
                $participantService = new Application_Service_Participants();
                $sid = (int)base64_decode($this->_getParam('sid'));
                $this->view->shipment = $shipmentDetails = $shipmentService->getShipment($sid);            
                $this->view->previouslySelected = $previouslySelected = $participantService->getEnrolledByShipmentId($sid);
                if($previouslySelected == "" || $previouslySelected == null){
                    $this->view->enrolledParticipants = $participantService->getEnrolledBySchemeCode($shipmentDetails['scheme_type']);
                    $this->view->unEnrolledParticipants = $participantService->getUnEnrolled($shipmentDetails['scheme_type']);                    
                }else{
                    $this->view->previouslyUnSelected = $participantService->getUnEnrolledByShipmentId($sid);
                }                
            }
        }
    }

    public function removeAction()
    {
        if($this->_hasParam('sid')){
            $sid = (int)base64_decode($this->_getParam('sid'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->message = $shipmentService->removeShipment($sid);
        }else{
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }

    public function editAction(){
        if($this->getRequest()->isPost()){
            $shipmentService = new Application_Service_Shipments();
            $params = $this->getRequest()->getPost();
            Zend_Debug::dump($params);
            die;
            $shipmentService->updateShipment($params);
            $this->_redirect("/admin/shipment"); 
        }else{
            if($this->_hasParam('sid')){
                $sid = (int)base64_decode($this->_getParam('sid'));
                $shipmentService = new Application_Service_Shipments();
                $this->view->shipmentData = $response = $shipmentService->getShipmentForEdit($sid);
                $schemeService = new Application_Service_Schemes();
                $this->view->wb = $schemeService->getDbsWb();
                $this->view->eia = $schemeService->getDbsEia();
                $this->view->allTestKits = $schemeService->getAllDtsTestKit();
                if($response === false){
                    $this->_redirect("/admin/shipment");        
                }
            }else{
                $this->_redirect("/admin/shipment");    
            }
        }

    }


}











