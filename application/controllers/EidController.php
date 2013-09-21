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
        
        $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
        $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
        
        $dtsResponseDb = new Application_Model_DTSResponse();
    	if($this->getRequest()->isPost())
    	{

    		$data = $this->_request->getParams();
    		//$dtsResponseDb->saveResponse($data);
    		Zend_Debug::dump($data);
    		//echo "data Saved"; 
    		$this->_forward('dashboard', 'Participant',null,array('msg'=>'Saved'));
    		
    		//die;            
        }else{
            $sID= $this->getRequest()->getParam('sid');
            $pID= $this->getRequest()->getParam('pid');
            $eID =$this->getRequest()->getParam('eid');
        
            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            
            $this->view->allSamples =$schemeService->getEidSamples($sID,$pID);
            
            
            $this->view->shipment = $schemeService->getShipmentEid( $sID,$pID);
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;
    
            $isEditable = $dtsResponseDb->IsgetDTSResponseEditable($eID);
    	}
    }


}



