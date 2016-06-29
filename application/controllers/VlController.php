<?php

class VlController extends Zend_Controller_Action
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
        
        $this->view->vlAssay = $schemeService->getVlAssay();
        
    	if($this->getRequest()->isPost())
    	{

    		$data = $this->getRequest()->getPost();
			$data['uploadedFilePath'] = "";
           if((!empty($_FILES["uploadedFile"])) && ($_FILES['uploadedFile']['error'] == 0)) {
				
				$filename = basename($_FILES['uploadedFile']['name']);
				$ext = substr($filename, strrpos($filename, '.') + 1);
				if (($_FILES["uploadedFile"]["size"] < 5000000)) {
					$dirpath = "dts-viral-load".DIRECTORY_SEPARATOR.$data['schemeCode'].DIRECTORY_SEPARATOR.$data['participantId'];
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
			
           
            $shipmentService->updateVlResults($data);
    		
    		
    		if(isset($data['comingFrom']) && trim($data['comingFrom'])!=''){
			$this->_redirect("/participant/".$data['comingFrom']);
			}else{
				$this->_redirect("/participant/current-schemes");
			}
    		
    		//die;            
        }else{
            $sID= $this->getRequest()->getParam('sid');
            $pID= $this->getRequest()->getParam('pid');
            $eID =$this->getRequest()->getParam('eid');
			$this->view->comingFrom =$this->getRequest()->getParam('comingFrom');
			
            $participantService = new Application_Service_Participants();
            $this->view->participant = $participantService->getParticipantDetails($pID);
            //Zend_Debug::dump($schemeService->getVlSamples($sID,$pID));
            $this->view->allSamples =$schemeService->getVlSamples($sID,$pID);
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
		$this->view->referenceDetails = $schemeService->getVlReferenceData($sID);
	    
		$shipment = $schemeService->getShipmentData($sID,$pID);
	    $shipment['attributes'] = json_decode($shipment['attributes'],true);
	    $this->view->shipment = $shipment;
    }

    public function deleteAction()
    {

    }


}





