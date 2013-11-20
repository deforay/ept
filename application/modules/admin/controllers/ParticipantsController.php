<?php

class Admin_ParticipantsController extends Zend_Controller_Action
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
            $clientsServices = new Application_Service_Participants();
            $clientsServices->getAllParticipants($params);
        }
    }

    public function addAction()
    {
        $participantService = new Application_Service_Participants();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $participantService->addParticipant($params);
            $this->_redirect("/admin/participants");
        }
        $this->view->affiliates = $participantService->getAffiliateList();
        $dataManagerService = new Application_Service_DataManagers();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
    }

    public function editAction()
    {

        $participantService = new Application_Service_Participants();
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
            $this->view->dataManagers = $dataManagerService->getDataManagerList();            
        }
    }


}





