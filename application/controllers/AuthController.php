<?php

class AuthController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    	$this->_helper->layout()->setLayout('home');
    }

    public function indexAction()
    {
        $this->_redirect('/auth/login');
    }

    public function loginAction()
    {

    // action body
    	if($this->getRequest()->isPost()){
    		//die;
    		//echo "Post";
			$params['username'] = trim($params['username']);
			$params['password'] = trim($params['password']);
    		$params = $this->getRequest()->getPost();
    		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
    		$adapter = new Zend_Auth_Adapter_DbTable($db, "data_manager", "primary_email", "password");
    		$adapter->setIdentity($params['username']);
    		$adapter->setCredential($params['password']);
			
                $select = $adapter->getDbSelect();
                $select->where('status = "active"');			
			
    		// STEP 2 : Let's Authenticate
    		$auth = Zend_Auth::getInstance();
    		$res = $auth->authenticate($adapter); // -- METHOD 2 to authenticate , seems to work fine for me
    		
			//echo "hi";
    		if($res->isValid()){
			Zend_Session::rememberMe(60 * 60 * 5); // asking the session to be active for 5 hours

    			$rs = $adapter->getResultRowObject();
    			
    			$authNameSpace = new Zend_Session_Namespace('datamanagers');
    			$authNameSpace->UserID = $params['username'];
	    		$authNameSpace->dm_id = $rs->dm_id;
	    		$authNameSpace->first_name = $rs->first_name;
	    		$authNameSpace->last_name = $rs->last_name;
	    		$authNameSpace->phone = $rs->phone;
	    		$authNameSpace->email = $rs->primary_email;
	    		$authNameSpace->qc_access = $rs->qc_access;
	    		$authNameSpace->enable_adding_test_response_date = $rs->enable_adding_test_response_date;
	    		$authNameSpace->enable_choosing_mode_of_receipt = $rs->enable_choosing_mode_of_receipt;
	    		$authNameSpace->force_password_reset = $rs->force_password_reset;
	    		// PT Provider Dependent Configuration 
	    		//$authNameSpace->UserFld1 = $rs->UserFld1;
	    		//$authNameSpace->UserFld2 = $rs->UserFld2;
	    		//$authNameSpace->UserFld3 = $rs->UserFld3;
	    		
				$userService = new Application_Service_DataManagers();
				$userService->updateLastLogin($rs->dm_id);				
				
				
    			$this->_redirect('/participant/dashboard');
    		
    		}else
    		{
    			$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check your login credentials";
				$sessionAlert->status = "failure";
    		}
    	
    }
    }

    public function logoutAction()
    {
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::destroy();
        $this->_redirect('/');
    }

    public function resetPasswordAction()
    {
	if($this->getRequest()->isPost()){
		$email = $this->getRequest()->getPost('registeredEmail');
		$userService = new Application_Service_DataManagers();
		$userService->resetPassword($email);
		$this->_redirect('/auth/login');
	}		
        
    }


}







