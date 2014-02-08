<?php

class ParticipantController extends Zend_Controller_Action
{

    private $noOfItems = 10;

    public function init()
    {
	$ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
		->addActionContext('default-scheme', 'html')
		->addActionContext('current-schemes', 'html')
		->addActionContext('all-schemes', 'html')
                ->initContext();
    }

    public function indexAction()
    {
	if($this->getRequest()->isPost()){
	    //SHIPMENT_OVERVIEW
            $params = $this->_getAllParams();
	    $shipmentService = new Application_Service_Shipments();
	    $shipmentService->getShipmentOverview($params);
        }else{
	    $this->_redirect("/participant/dashboard");
	}
        
    }

    public function dashboardAction()
    {
    	
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
    	$this->view->authNameSpace = $authNameSpace;
	
    }

    public function reportAction()
    {
        // action body
    }

    public function userInfoAction()
    {
	$userService = new Application_Service_DataManagers();
    	if($this->_request->isPost()){
	    $params = $this->_request->getPost();
	    $userService->updateUser($params);  
    	}
	// whether it is a GET or POST request, we always show the user info
	$this->view->rsUser = $userService->getUserInfo();	  	
    }

    public function testersAction()
    {
    	$dbUsersProfile = new Application_Service_Participants();
    	$this->view->rsUsersProfile = $dbUsersProfile->getUsersParticipants();
    }

    public function schemeAction()
    {
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
        $dbUsersProfile = new Application_Service_Participants();
		$this->view->participantSchemes = $dbUsersProfile->getParticipantSchemes($authNameSpace->dm_id);
    }

    public function passwordAction()
    {
	if($this->getRequest()->isPost()){
		$user = new Application_Service_DataManagers();
		$newPassword = $this->getRequest()->getPost('newpassword');
		$oldPassword = $this->getRequest()->getPost('oldpassword');
		$response = $user->changePassword($oldPassword,$newPassword);
		if($response){
			$this->_redirect("/participant/dashboard");
		}
	}
    }

    public function testereditAction()
    {
        // action body
        // Get
    	$participantService = new Application_Service_Participants();
    	if($this->getRequest()->isPost())
    	{
    		$data = $this->getRequest()->getPost();
    		$participantService->updateParticipant($data);
    		$this->_redirect('/participant/testers');	    	
    	}
    	else {
	    	$this->view->rsParticipant = $participantService->getParticipantDetails($this->_getParam('psid'));    		
    	}
    	
    	$this->view->affiliates = $participantService->getAffiliateList();
		$this->view->networks = $participantService->getNetworkTierList();
    }

    public function schemeinfoAction()
    {
        // action body
    }

    public function addAction()
    {
	$participantService = new Application_Service_Participants();
    	if($this->getRequest()->isPost())
    	{
    		$data = $this->getRequest()->getPost();
    		$participantService->addParticipantForDataManager($data);
    		$this->_redirect('/participant/testers');	    	
    	}
    	
    	$this->view->affiliates = $participantService->getAffiliateList();
		$this->view->networks = $participantService->getNetworkTierList();
		$scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();		
    }

    public function defaultSchemeAction()
    {
        if($this->getRequest()->isPost()){
	    //SHIPMENT_DEFAULTED
            $params = $this->_getAllParams();
	    $shipmentService = new Application_Service_Shipments();
	    $shipmentService->getShipmentDefault($params);
        }
    }

    public function currentSchemesAction()
    {
        if($this->getRequest()->isPost()){
	    //SHIPMENT_CURRENT
            $params = $this->_getAllParams();
	    $shipmentService = new Application_Service_Shipments();
	    $shipmentService->getShipmentCurrent($params);
        }
    }

    public function allSchemesAction()
    {
        if($this->getRequest()->isPost()){
	    //SHIPMENT_ALL
            $params = $this->_getAllParams();
	    $shipmentService = new Application_Service_Shipments();
	    $shipmentService->getShipmentAll($params);
        }
    }

    public function downloadAction()
    {
	$this->_helper->layout()->disableLayout();
        if($this->_hasParam('d92nl9d8d')){            
            $id = (int) base64_decode($this->_getParam('d92nl9d8d'));
	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $this->view->result = $db->fetchRow($db->select()->from(array('spm'=>'shipment_participant_map'),array('spm.map_id'))
				->join(array('p'=>'participant'),'p.participant_id=spm.participant_id',array('p.first_name','p.last_name'))
				->where("spm.map_id = ?",$id));
	    
        }else{
            $this->_redirect("/participant/dashboard");
        }
    }


}


