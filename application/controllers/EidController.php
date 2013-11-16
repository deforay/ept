<?php

class EidController extends Zend_Controller_Action
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
        
        $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
        $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
        
    	if($this->getRequest()->isPost())
    	{

    		$data = $this->getRequest()->getPost();
           
            $shipmentService->updateEidResults($data);
    		
    		// Zend_Debug::dump($data);die;
    		
    		$this->_redirect("/participant/dashboard");
    		
    		//die;            
        }else{
            $sID= $this->getRequest()->getParam('sid');
            $pID= $this->getRequest()->getParam('pid');
            $eID =$this->getRequest()->getParam('eid');
        
            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            //Zend_Debug::dump($schemeService->getEidSamples($sID,$pID));
			
			$this->view->eidPossibleResults = $schemeService->getPossibleResults('eid');
			
            $this->view->allSamples =$schemeService->getEidSamples($sID,$pID);
			
            $shipment = $schemeService->getShipmentData($sID,$pID);
			$shipment['attributes'] = json_decode($shipment['attributes'],true);
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;
    
            $this->view->isEditable = $shipmentService->isShipmentEditable($sID,$pID);
    	}
    }


}



