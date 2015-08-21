<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }
    
    public function preDispatch(){
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if(isset($authNameSpace->ForcePasswordReset) && ($authNameSpace->ForcePasswordReset == 1 || $authNameSpace->ForcePasswordReset == '1')){
            $this->_redirect("/participant/password");
        }
    }

    public function indexAction()
    {
        //$this->_helper->layout()->setLayout('home');
        $this->_helper->layout()->sideBarCollapse = true;
        
    }


}

