<?php

class Admin_ParticipantsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
	            ->addActionContext('view-participants', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'manage';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getAllParticipants($params);
        }
    }

    public function addAction()
    {
        $participantService = new Application_Service_Participants();
	$commonService = new Application_Service_Common();
	$dataManagerService = new Application_Service_DataManagers();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $participantService->addParticipant($params);
            $this->_redirect("/admin/participants");
        }
        
        $this->view->affiliates = $participantService->getAffiliateList();
        $this->view->networks = $participantService->getNetworkTierList();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        $this->view->countriesList = $commonService->getcountriesList();
    }

    public function editAction()
    {

        $participantService = new Application_Service_Participants();
	$commonService = new Application_Service_Common();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $participantService->updateParticipant($params);
            $this->_redirect("/admin/participants");
        }else{
            if($this->_hasParam('id')){
                $userId = (int)$this->_getParam('id');
                $this->view->participant = $participantService->getParticipantDetails($userId);
            }
            $this->view->affiliates = $participantService->getAffiliateList();
            $dataManagerService = new Application_Service_DataManagers();
            $this->view->networks = $participantService->getNetworkTierList();
            $this->view->dataManagers = $dataManagerService->getDataManagerList();
	    $this->view->countriesList = $commonService->getcountriesList();
        }
		$scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $this->view->participantSchemes = $participantService->getSchemesByParticipantId($userId);
    }

    public function pendingAction()
    {
        // action body
    }

    public function viewParticipantsAction()
    {
	$this->_helper->layout()->setLayout('modal');
	 $participantService = new Application_Service_Participants();
	 if($this->_hasParam('id')){
		$dmId = (int)$this->_getParam('id');
		$this->view->participant = $participantService->getAllParticipantDetails($dmId);
	 }
       
    }


}









