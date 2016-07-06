<?php

class Admin_LoginController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->disableLayout();
    }

    public function indexAction()
    {
        if($this->getRequest()->isPost()){
            $params = $this->getRequest()->getPost();
    	    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    	    $adapter = new Zend_Auth_Adapter_DbTable($db, "system_admin", "primary_email", "password");
            
            $select = $adapter->getDbSelect();
            $select->where('status = "active"');
            
    		$adapter->setIdentity($params['username']);
    		$adapter->setCredential($params['password']);
    		
    		$auth = Zend_Auth::getInstance();
    		$res = $auth->authenticate($adapter);
    		
			
    		if($res->isValid()){
		       Zend_Session::rememberMe(36000); // keeping the session cookie active for 10 hours

    			$rs = $adapter->getResultRowObject();
    			
    			$authNameSpace = new Zend_Session_Namespace('administrators');
    			$authNameSpace->primary_email = $params['username'];
	    		$authNameSpace->admin_id = $rs->admin_id;
	    		$authNameSpace->first_name = $rs->first_name;
	    		$authNameSpace->last_name = $rs->last_name;
	    		$authNameSpace->phone = $rs->phone;
	    		$authNameSpace->secondary_email = $rs->secondary_email;
	    		$authNameSpace->force_password_reset = $rs->force_password_reset;
				
			$schemeService = new Application_Service_Schemes();
			$allSchemes = $schemeService->getAllSchemes();
			$schemeList = array();
			foreach($allSchemes as $scheme){
			    $schemeList[] = $scheme->scheme_id;
			}
			$authNameSpace->activeSchemes = $schemeList;
	    		
    			$this->_redirect('/admin');
    		
    		}else
    		{
    			$sessionAlert = new Zend_Session_Namespace('alertSpace');
				$sessionAlert->message = "Sorry. Unable to log you in. Please check your login credentials";
				$sessionAlert->status = "failure";
    		}
            
            
        }else{
            // We are destroying the session here in case this person has
            // logged in as a User as well..
            // We don't want that
            Zend_Auth::getInstance()->clearIdentity();
        }

    }

    public function logOutAction()
    {
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::destroy();
        $this->_redirect('/');
    }


}



