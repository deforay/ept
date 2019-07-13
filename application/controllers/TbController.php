<?php

class TbController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        // action body
    }

    public function responseAction() {
        
        $schemeService = new Application_Service_Schemes();
        $shipmentService = new Application_Service_Shipments();
        
    	if($this->getRequest()->isPost())
    	{

    	    $data = $this->getRequest()->getPost();
           
            //Zend_Debug::dump($data);die;
           
            $shipmentService->updateTbResults($data);
            $this->_redirect("/participant/dashboard");
    		
    		//die;            
        }else{
            $sID= $this->getRequest()->getParam('sid');
            $pID= $this->getRequest()->getParam('pid');
            $eID =$this->getRequest()->getParam('eid');
        
            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            $this->view->allSamples =$schemeService->getTbSamples($sID,$pID);
            $shipment = $schemeService->getShipmentData($sID,$pID);
	    $shipment['attributes'] = json_decode($shipment['attributes'],true);
            $this->view->shipment = $shipment;
            $this->view->shipId = $sID;
            $this->view->participantId = $pID;
            $this->view->eID = $eID;
    
            $this->view->isEditable = $shipmentService->isShipmentEditable($sID,$pID);
	    
	    $commonService = new Application_Service_Common();
	    $this->view->modeOfReceipt=$commonService->getAllModeOfReceipt();
	    $this->view->globalQcAccess=$commonService->getConfig('qc_access');
    	}
    }
    


}



