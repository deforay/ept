<?php

class Admin_EmailParticipantsController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
    }

    public function indexAction()
    {
        $participantService = new Application_Service_Participants();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost();
            $participantService->sendParticipantEmail($data);
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Email Participants", "config");
        }
        $shipment = new Application_Service_Shipments();
        if ($this->hasParam('id')) {
            $this->view->distributionId = $this->_getParam('id');
        }
        $this->view->shipment = $shipment->getAllShipmentCode();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }
}
