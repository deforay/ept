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
        // action body
    }

    public function dashboardAction()
    {
    	
        $authNameSpace = new Zend_Session_Namespace('Zend_Auth');
    	$this->view->authNameSpace = $authNameSpace;
    	//echo $authNameSpace->UserID; 
    	// get overview Info and pass to view 
    	$db = Zend_Db_Table_Abstract::getDefaultAdapter();
    	$stmt = $db->prepare("call SHIPMENT_OVERVIEW()");
    	$stmt->execute();
    	$this->view->rsOverview = $stmt->fetchAll();
    	
    	$stmt = $db->prepare("call SHIPMENT_CURRENT(?)");
    	$stmt->execute(array( $authNameSpace->UserID));
    	$this->view->rsShipCurr = $stmt->fetchAll();
    	 
    	$stmt = $db->prepare("call SHIPMENT_DEFAULTED()");
    	$stmt->execute();
    	$this->view->rsShipDef = $stmt->fetchAll();
    	
    	$currentPage = $this->_getParam('page',1);
    	//$noOfItems = 4;
    	
    	$stmt = $db->prepare("call SHIPMENT_ALL(?,?)");
    	
    	$stmt->execute(array($this->noOfItems * $currentPage ,$this->noOfItems));
    	//`$this->view->rsShipAll = $stmt->fetchAll();

    	$pag = Zend_Paginator::factory($stmt->fetchAll());
    	$pag->setItemCountPerPage($this->noOfItems);
    	$pag->setCurrentPageNumber($currentPage);
    	$this->view->rsShipAll = $pag;

    	
    	//Zend_Debug::dump($this->view->rs);
    	//foreach($this->view->rs as $site){
    		//echo $site['SCHEME'];
    	//}
    	//echo $this->view->rs['SCHEME'];
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
        
    }

    public function passwordAction()
    {
	if($this->getRequest()->isPost()){
		$user = new Application_Service_DataManagers();
		$newPassword = $this->getRequest()->getPost('newpassword');
		$oldPassword = $this->getRequest()->getPost('oldpassword');
		$user->changePassword($oldPassword,$newPassword);
	}
    }

    public function testereditAction()
    {
        // action body
        // Get
    	$dbParticipant = new Application_Service_Participants();
    	if($this->getRequest()->isPost())
    	{
    		$data = $this->getRequest()->getPost();
    		$dbParticipant->updateParticipant($data);
    		$this->_redirect('/participant/testers');	    	
    	}
    	else {
	    	$this->view->rsParticipant = $dbParticipant->getParticipantDetails($this->_getParam('psid'));    		
    	}
    	
    	
    }

    public function schemeinfoAction()
    {
        // action body
    }


}

















