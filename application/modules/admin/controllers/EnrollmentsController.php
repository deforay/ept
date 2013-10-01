<?php

class Admin_EnrollmentsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->initContext();        
        $this->_helper->layout()->pageName = 'manage';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $participantService = new Application_Service_Participants();
            $participantService->getAllEnrollments($params);
        }
    }

    public function viewAction()
    {
        if($this->_hasParam('pid') && $this->_hasParam('sid')){
            $pid = $this->_getParam('pid');
            $this->view->sid = $sid = $this->_getParam('sid');
            $participantService = new Application_Service_Participants();
            $this->view->enrollmentDetails = $participantService->getEnrollmentDetails($pid,$sid);
        }else{
            $this->_redirect("/admin/enrollments");
        }
    }


}



