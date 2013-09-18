<?php

class AuthController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    	//$this->_helper->layout()->disableLayout();
    }

    public function indexAction()
    {
        // action body
    }

    public function loginAction()
    {
    // action body
    	if($this->getRequest()->isPost()){
    		//die;
    		//echo "Post";
    		$params = $this->getRequest()->getPost();
    		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
    		$adapter = new Zend_Auth_Adapter_DbTable($db, "users", "UserID", "Password");
    		$adapter->setIdentity($params['username']);
    		$adapter->setCredential($params['password']);
    		// STEP 2 : Let's Authenticate
    		$auth = Zend_Auth::getInstance();
    		$res = $auth->authenticate($adapter); // -- METHOD 2 to authenticate , seems to work fine for me
    		
			//echo "hi";
    		if($res->isValid()){

    			$rs = $adapter->getResultRowObject();
    			
    			$authNameSpace = new Zend_Session_Namespace('Zend_Auth');
    			$authNameSpace->UserID = $params['username'];
	    		$authNameSpace->UserSystemID = $rs->UserSystemID;
	    		$authNameSpace->UserFName = $rs->UserFname;
	    		$authNameSpace->UserLName = $rs->UserLName;
	    		$authNameSpace->UserPhoneNumber = $rs->UserPhoneNumber;
	    		$authNameSpace->UserEmail = $rs->UserEmail;
	    		$authNameSpace->ForcePasswordReset = $rs->force_password_reset;
	    		// PT Provider Dependent Configuration 
	    		$authNameSpace->UserFld1 = $rs->UserFld1;
	    		$authNameSpace->UserFld2 = $rs->UserFld2;
	    		$authNameSpace->UserFld3 = $rs->UserFld3;
	    		
	    		/*
	    		http://randomitstuff.com/2010/05/04/setting-up-database-configuration-in-zend-framework/
	    		
	    		*/
    			//print_r($authNameSpace->UserFName);
    			//die;
    			$this->_redirect('/index/index');
    		
    		}else
    		{
    			$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check your login credentials";
				$sessionAlert->status = "failure";
    			//$messages = $res->getMessages();
    			//foreach($messages as $message){	
    			//	echo $message . "<br/>";
    			//}
    		}
    	
    }
    }

    public function logoutAction()
    {
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::destroy();
        $this->_redirect('/index/index');
    }

    public function resetPasswordAction()
    {
		if($this->getRequest()->isPost()){
			$email = $this->getRequest()->getPost('registeredEmail');
			$userService = new Application_Service_Users();
			$userService->resetPassword($email);
			$this->_redirect('/auth/login');
		}		
        
    }


}







