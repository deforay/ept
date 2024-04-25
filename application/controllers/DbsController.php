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
			if (isset($data['reqAccessFrom']) && !empty($data['reqAccessFrom']) && $data['reqAccessFrom'] == 'admin') {
				$this->redirect("/admin/evaluate/shipment/sid/" . base64_encode($data['shipmentId']));
			} elseif(isset($data['comingFrom']) && trim($data['comingFrom'])!=''){
			$this->redirect("/participant/".$data['comingFrom']);
			}else{
				$this->redirect("/participant/dashboard");
			}
    	}
    	else{
			$sID= $this->getRequest()->getParam('sid');
			$pID= $this->getRequest()->getParam('pid');
			$eID =$this->getRequest()->getParam('eid');
			$uc = $this->getRequest()->getParam('uc');
			$this->view->comingFrom =$this->getRequest()->getParam('comingFrom');
			$reqFrom = $this->getRequest()->getParam('from');
            if (isset($reqFrom) && !empty($reqFrom) && $reqFrom == 'admin') {
                $evalService = new Application_Service_Evaluation();
				$this->view->evaluateData = $evalService->editEvaluation($sID, $pID, 'dbs', $uc);
				$this->_helper->layout()->setLayout('admin');
			}
			$participantService = new Application_Service_Participants();
			$this->view->participant = $participantService->getParticipantDetails($pID);
			$response =$schemeService->getDbsSamples($sID,$pID);
            //Zend_Debug::dump($response);
			$this->view->allSamples = $response;
			
			$shipment = $schemeService->getShipmentData($sID,$pID);
			$shipment['attributes'] = json_decode($shipment['attributes'],true);
			$this->view->shipment = $shipment;
			
			//Zend_Debug::dump($this->view->shipment);
			$this->view->possibleResults = $schemeService->getPossibleResults('dbs', 'admin');
			$this->view->wb = $schemeService->getDbsWb();
			$this->view->eia = $schemeService->getDbsEia();
			$this->view->shipId = $sID;
			$this->view->participantId = $pID;
			$this->view->eID = $eID;
			$this->view->reqFrom = $reqFrom;
			//
			$this->view->isEditable = $shipmentService->isShipmentEditable($sID,$pID);
			
			$commonService = new Application_Service_Common();
			$this->view->modeOfReceipt=$commonService->getAllModeOfReceipt();
			$this->view->globalQcAccess=$commonService->getConfig('qc_access');
    	}
    }


}



