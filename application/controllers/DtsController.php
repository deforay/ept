<?php

class DtsController extends Zend_Controller_Action
{

    public function init()
    {

    }

    public function indexAction()
    {
        // action body
    }

    public function responseAction(){
	
	$schemeService = new Application_Service_Schemes();
	$shipmentService = new Application_Service_Shipments();		
	if($this->_request->isPost()){
	    $data = $this->getRequest()->getPost();			
	    $shipmentService->updateDtsResults($data);
		if(isset($data['comingFrom']) && trim($data['comingFrom'])!=''){
			$this->_redirect("/participant/".$data['comingFrom']);
		}else{
			$this->_redirect("/participant/current-schemes");
		}
	}
	else{
	    $sID= $this->getRequest()->getParam('sid');
	    $pID= $this->getRequest()->getParam('pid');
	    $eID =$this->getRequest()->getParam('eid');
		$this->view->comingFrom =$this->getRequest()->getParam('comingFrom');
		
		$access = $shipmentService->checkParticipantAccess($pID);
		if($access == false){
			$this->_redirect("/participant/current-schemes");
		}
	    
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
		
	    $globalConfigDb = new Application_Model_DbTable_GlobalConfig();
	    $this->view->customField1 = $globalConfigDb->getValue('custom_field_1');
	    $this->view->customField2 = $globalConfigDb->getValue('custom_field_2');
	    $this->view->haveCustom = $globalConfigDb->getValue('custom_field_needed');
		
	    $commonService = new Application_Service_Common();
	    $this->view->modeOfReceipt=$commonService->getAllModeOfReceipt();
	    $this->view->globalQcAccess=$commonService->getConfig('qc_access');

	}
    }
	
    public function deleteAction()
    {

    }
	
	public function downloadAction(){
		$this->_helper->layout()->disableLayout();
		$sID= $this->getRequest()->getParam('sid');
	    $pID= $this->getRequest()->getParam('pid');
	    $eID =$this->getRequest()->getParam('eid');
	    
		$reportService = new Application_Service_Reports();
        $this->view->header=$reportService->getReportConfigValue('report-header');
        $this->view->logo=$reportService->getReportConfigValue('logo');
        $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
			
	    $participantService = new Application_Service_Participants();
	    $this->view->participant = $participantService->getParticipantDetails($pID);
		$schemeService = new Application_Service_Schemes();
		$this->view->referenceDetails = $schemeService->getDtsReferenceData($sID);
	    
		$shipment = $schemeService->getShipmentData($sID,$pID);
	    $shipment['attributes'] = json_decode($shipment['attributes'],true);
	    $this->view->shipment = $shipment;
	}

}



