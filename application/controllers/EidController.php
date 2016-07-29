<?php

class EidController extends Zend_Controller_Action
{

    public function init()
    {

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
			$data['uploadedFilePath'] = "";
    		// Zend_Debug::dump($data);die;
			
			if((!empty($_FILES["uploadedFile"])) && ($_FILES['uploadedFile']['error'] == 0)) {
				
				$filename = basename($_FILES['uploadedFile']['name']);
				$ext = substr($filename, strrpos($filename, '.') + 1);
				if (($_FILES["uploadedFile"]["size"] < 5000000)) {
					$dirpath = "dts-early-infant-diagnosis".DIRECTORY_SEPARATOR.$data['schemeCode'].DIRECTORY_SEPARATOR.$data['participantId'];
					$uploadDir = UPLOAD_PATH.DIRECTORY_SEPARATOR.$dirpath;
					if(!is_dir($uploadDir)){
						mkdir($uploadDir,0777,true);
					}
					
					// Let us clear the folder before uploading the file
					$files = glob($uploadDir.'/*{,.}*', GLOB_BRACE); // get all file names
					foreach($files as $file){ // iterate files
					  if(is_file($file))
						unlink($file); // delete file
					}
					
				  //Determine the path to which we want to save this file
					$data['uploadedFilePath'] = $dirpath.DIRECTORY_SEPARATOR.$filename;
					$newname = $uploadDir.DIRECTORY_SEPARATOR.$filename;
					
					move_uploaded_file($_FILES['uploadedFile']['tmp_name'],$newname);
					
				}
			  }			
			
			Zend_Debug::dump($data);die;
			
            $shipmentService->updateEidResults($data);

    		$this->_redirect("/participant/current-schemes");

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
	    
	    $commonService = new Application_Service_Common();
	    $this->view->modeOfReceipt=$commonService->getAllModeOfReceipt();
	    $this->view->globalQcAccess=$commonService->getConfig('qc_access');
    	}
    }

    public function downloadAction()
    {
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
		$this->view->referenceDetails = $schemeService->getEidReferenceData($sID);
	    
		$shipment = $schemeService->getShipmentData($sID,$pID);
	    $shipment['attributes'] = json_decode($shipment['attributes'],true);
	    $this->view->shipment = $shipment;
    }

    public function deleteAction()
    {
        
    }


}


