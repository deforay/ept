<?php

class Admin_EnrollmentsController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges) && !in_array('manage-participants', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->initContext();        
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();            
            $participantService = new Application_Service_Participants();
            $participantService->getAllEnrollments($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->schemeCount = $scheme->countEnrollmentSchemes();
    }

    public function viewAction()
    {
        if($this->hasParam('pid') && $this->hasParam('sid')){
            $pid = $this->_getParam('pid');
            $this->view->sid = $sid = $this->_getParam('sid');
            $participantService = new Application_Service_Participants();
            $this->view->enrollmentDetails = $participantService->getEnrollmentDetails($pid,$sid);
        }else{
            $this->redirect("/admin/enrollments");
        }
    }

    public function addAction()
    {
        if($this->getRequest()->isPost()){
            
            $params = $this->getRequest()->getPost();
            $participants = new Application_Service_Participants();
            $participants->enrollParticipants($params);
            $this->redirect("/admin/enrollments");
        }else{
            if($this->hasParam('scheme')){
                $participants = new Application_Service_Participants();
                $this->view->scheme = $scheme = $this->_getParam('scheme');
                $this->view->participants = $participants->getUnEnrolled($scheme);
                $this->view->enrolled = $participants->getEnrolledBySchemeCode($scheme);
            }            
        }

    }


}





