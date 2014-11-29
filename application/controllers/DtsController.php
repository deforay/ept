<?php

class DtsController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        // action body
    }

    public function responseAction()
    {
	
	$schemeService = new Application_Service_Schemes();
	$shipmentService = new Application_Service_Shipments();		
	if($this->_request->isPost()){
	    $data = $this->getRequest()->getPost();			
	    $shipmentService->updateDtsResults($data);
	    $this->_redirect("/participant/dashboard");
	}
	else{
	    $sID= $this->getRequest()->getParam('sid');
	    $pID= $this->getRequest()->getParam('pid');
	    $eID =$this->getRequest()->getParam('eid');
	    
	    $participantService = new Application_Service_Participants();
	    $this->view->participant = $participantService->getParticipantDetails($pID);
	    $response =$schemeService->getDtsSamples($sID,$pID);
	    $this->view->allSamples = $response;
	    
	    $shipment = $schemeService->getShipmentData($sID,$pID);
	    $shipment['attributes'] = json_decode($shipment['attributes'],true);
	    $this->view->shipment = $shipment;
	    
	    //Zend_Debug::dump($this->view->shipment);
	    $this->view->allTestKits = $schemeService->getAllDtsTestKitList(true);
	    $this->view->dtsPossibleResults = $schemeService->getPossibleResults('dts');
	    $this->view->shipId = $sID;
	    $this->view->participantId = $pID;
	    $this->view->eID = $eID;
	    //
	    $this->view->isEditable = $shipmentService->isShipmentEditable($sID,$pID);
	}
    }


}



