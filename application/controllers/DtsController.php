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
    	$dtsResponseDb = new Application_Model_DTSResponse();
        
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();		
    	if(!$this->_request->isPost())
    	{
    	$sID= $this->getRequest()->getParam('sid');
    	$pID= $this->getRequest()->getParam('pid');
    	$eID =$this->getRequest()->getParam('eid');
    
		$participantService = new Application_Service_Participants();
		$this->view->participant = $participantService->getParticipantDetails($pID);
    	$response =$schemeService->getDtsSamples($sID,$pID);
    	$this->view->allSamples = $response;
    	
    	//echo $dtsResponse->getDTSResponse(3, 4);
    	//echo "sID = " . $sID;
    	//echo "<br>pID = " . $pID;
    	
    	
		$shipment = $schemeService->getShipmentData($sID,$pID);
		$shipment['attributes'] = json_decode($shipment['attributes'],true);
		$this->view->shipment = $shipment;
		
    	//Zend_Debug::dump($this->view->shipment);
    	$this->view->allTestKits = $dtsResponseDb->getAllDtsTestKit();
    	$this->view->dtsPossibleResults = $schemeService->getPossibleResults('dts');
    	$this->view->shipId = $sID;
    	$this->view->participantId = $pID;
    	$this->view->eID = $eID;
    	//
    	//$isEditable = $dtsResponseDb->IsgetDTSResponseEditable($eID);
    	}
    	else{
    		$data = $this->getRequest()->getPost();
           
            $shipmentService->updateDtsResults($data);
    		
    		// Zend_Debug::dump($data);die;
    		
    		$this->_redirect("/participant/dashboard");
    		
    		//die;
    	}
    }


}



