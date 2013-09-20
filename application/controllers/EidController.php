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
    	if(!$this->_request->isPost())
    	{
    	$sID= $this->getRequest()->getParam('sid');
    	$pID= $this->getRequest()->getParam('pid');
    	$eID =$this->getRequest()->getParam('eid');
    
        $participantService = new Application_Service_Participants();
    	$this->view->participant = $participantService->getParticipantDetails($pID);
        
    	$response =$schemeService->getEidResponse($sID,$pID);
    	$this->view->allSamples = $response;
    	
    	//echo $dtsResponse->getDTSResponse(3, 4);
    	//echo "sID = " . $sID;
    	//echo "<br>pID = " . $pID;
    	
    	
    	$this->view->shipment = $dtsResponseDb->getDTSShipment( $sID,$pID);
    	//Zend_Debug::dump($this->view->shipment);
    	$this->view->allTestKits = $dtsResponseDb->getAllTestKit();
    	$this->view->result = $dtsResponseDb->getPossibleResult('DTS', 'DTS_TEST');
    	//Zend_debug::dump($this->view->shipment );
    	$this->view->fresult = $dtsResponseDb->getPossibleResult('DTS', 'DTS_FINAL');
    	$this->view->shipId = $sID;
    	$this->view->participantId = $pID;
    	$this->view->eID = $eID;

    	$isEditable = $dtsResponseDb->IsgetDTSResponseEditable($eID);
    	}
    	else{
    		$data = $this->_request->getParams();
    		$dtsResponseDb->saveResponse($data);
    		//Zend_Debug::dump($data);
    		//echo "data Saved"; 
    		$this->_forward('dashboard', 'Participant',null,array('msg'=>'Saved'));
    		
    		//die;
    	}
    }


}



