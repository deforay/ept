<?php

class VlController extends Zend_Controller_Action
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
        
        $this->view->vlAssay = $schemeService->getVlAssay();
        
    	if($this->getRequest()->isPost())
    	{

    		$data = $this->getRequest()->getPost();
           
           // Zend_Debug::dump($data);die;
           
            $shipmentService->updateVlResults($data);
    		
    		
    		
    		$this->_redirect("/participant/dashboard");
    		
    		//die;            
        }else{
            $sID= $this->getRequest()->getParam('sid');
            $pID= $this->getRequest()->getParam('pid');
            $eID =$this->getRequest()->getParam('eid');
        
            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            //Zend_Debug::dump($schemeService->getVlSamples($sID,$pID));
            $this->view->allSamples =$schemeService->getVlSamples($sID,$pID);
            $this->view->shipment = $schemeService->getShipmentVl( $sID,$pID);
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;
    
            $isEditable = true;
    	}
    }


}



