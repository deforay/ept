<?php

class Admin_AnnouncementController extends Zend_Controller_Action
{

    public function init()
    {

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
$ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
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
            $this->redirect("/admin/announcement");
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        if (isset($_COOKIE['did']) && $_COOKIE['did'] != '' && $_COOKIE['did'] != null && $_COOKIE['did'] != 'NULL') {
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipmentDetails = $data = $shipmentService->getShipment($_COOKIE['did']);
            $this->view->schemeDetails = $scheme->getScheme($data["scheme_type"]);
        }
        $participantService = new Application_Service_Participants();
        $this->view->participantCity    = $participantService->getUniqueCity();
        $this->view->participantState   = $participantService->getUniqueState();
        $this->view->participants       = $participantService->getAllActiveParticipants();
    }
}
