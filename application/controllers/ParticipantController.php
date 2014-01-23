<?php

class ParticipantController extends Zend_Controller_Action
{

    private $noOfItems = 10;

    public function init()
    {
    	//if(!Zend_Auth::getInstance()->hasIdentity()){
    	//	$this->_redirect('login/login');
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        $this->_redirect("/participant/dashboard");
    }

    public function dashboardAction()
    {
    	
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
    	$this->view->authNameSpace = $authNameSpace;
	
	//S HIPMENT_OVERVIEW
	$shipmentService = new Application_Service_Shipments();
	$this->view->rsOverview=$shipmentService->getShipmentOverview();
	
	//SHIPMENT_CURRENT
	$this->view->rsShipCurr=$shipmentService->getShipmentCurrent();
	
	//SHIPMENT_DEFAULTED
	$this->view->rsShipDef=$shipmentService->getShipmentDefault();
	
	//SHIPMENT_ALL
	$this->view->rsShipAll=$shipmentService->getShipmentAll();
	
    	
    	
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


}



















