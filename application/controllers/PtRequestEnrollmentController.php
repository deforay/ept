<?php

class PtRequestEnrollmentController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }
    
    public function indexAction(){
        $this->_helper->layout()->setLayout('home');
        $participantService = new Application_Service_Participants();
        $commonService = new Application_Service_Common();
        $dataManagerService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $participantService->requestParticipant($params);
            $this->_redirect("/pt-request-enrollment");
        }

        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        $this->view->countriesList = $commonService->getcountriesList();
        $this->view->enrolledPrograms = $participantService->getEnrolledProgramsList();
        $this->view->siteType = $participantService->getSiteTypeList();
    }


}

