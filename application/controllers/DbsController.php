<?php

class DbsController extends Zend_Controller_Action
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
			$shipmentService->updateDbsResults($data);
			if(isset($data['comingFrom']) && trim($data['comingFrom'])!=''){
			$this->_redirect("/participant/".$data['comingFrom']);
			}else{
				$this->_redirect("/participant/dashboard");
			}
			
    	}
    	else{
			$sID= $this->getRequest()->getParam('sid');
			$pID= $this->getRequest()->getParam('pid');
			$eID =$this->getRequest()->getParam('eid');
			$this->view->comingFrom =$this->getRequest()->getParam('comingFrom');
			
			$participantService = new Application_Service_Participants();
			$this->view->participant = $participantService->getParticipantDetails($pID);
			$response =$schemeService->getDbsSamples($sID,$pID);
            //Zend_Debug::dump($response);
			$this->view->allSamples = $response;
			
			$shipment = $schemeService->getShipmentData($sID,$pID);
			$shipment['attributes'] = json_decode($shipment['attributes'],true);
			$this->view->shipment = $shipment;
			
			//Zend_Debug::dump($this->view->shipment);
			$this->view->possibleResults = $schemeService->getPossibleResults('dbs');
			$this->view->wb = $schemeService->getDbsWb();
			$this->view->eia = $schemeService->getDbsEia();
			$this->view->shipId = $sID;
			$this->view->participantId = $pID;
			$this->view->eID = $eID;
			//
			$this->view->isEditable = $shipmentService->isShipmentEditable($sID,$pID);
			
			$commonService = new Application_Service_Common();
			$this->view->modeOfReceipt=$commonService->getAllModeOfReceipt();
			$this->view->globalQcAccess=$commonService->getConfig('qc_access');
    	}
    }


}



