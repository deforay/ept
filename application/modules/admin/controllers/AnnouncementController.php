<?php

class Admin_AnnouncementController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $service = new Application_Service_Announcement();
            $service->getAllAnnouncementByGrid($params);
        } 
    }

    public function composeAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $service = new Application_Service_Announcement();
            $service->composeNewAnnouncement($params);
            $this->_redirect("/admin/announcement");
        }
        $participantService = new Application_Service_Participants();
        $this->view->participantCity    = $participantService->getUniqueCity();
        $this->view->participantState   = $participantService->getUniqueState();
        $this->view->participants       = $participantService->getAllActiveParticipants();
    }
}