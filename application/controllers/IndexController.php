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

    public function indexAction(){
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $this->_helper->layout()->activeMenu = 'home';
        $commonServices = new Application_Service_Common(); 
        $publicationService = new Application_Service_Publication();
        $partnerService = new Application_Service_Partner();
        if(!isset($authNameSpace->dm_id)){
            $this->_helper->layout()->setLayout('home');
        }
        $this->view->banner = $commonServices->getHomeBanner();
        $this->view->publications = $publicationService->getAllActivePublications();
        $this->view->partners = $partnerService->getAllActivePartners();
    }


}

