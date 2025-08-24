<?php

class Admin_EmailParticipantsController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext
            ->addActionContext('get-mail-template', 'html')
            ->addActionContext('get-mail-template-by-subject', 'html')
            ->addActionContext('get-subject-list', 'html')
            ->initContext();
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
        /** @var Zend_Controller_Request_Http $request */
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
        if ($this->hasParam('sid')) {
            $this->view->shipmentId = base64_decode($this->_getParam('sid'));
        }
        $common = new Application_Service_Common();
        $this->view->templates = $common->getAllEmailTemplateDetails();
        $this->view->shipment = $shipment->getAllShipmentCode();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    public function getMailTemplateAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $purpose = $request->getParam('mailPurpose');
            $common = new Application_Service_Common();
            $this->view->result = $common->getEmailTemplate($purpose);
        }
    }

    public function getMailTemplateBySubjectAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $subject = preg_replace('/[^a-zA-Z0-9]/', '', $request->getParam('subject'));
            $common = new Application_Service_Common();
            $this->view->result = $common->getEmailTemplateBySubject($subject);
        }
    }

    public function getSubjectListAction()
    {
        $this->_helper->layout()->disableLayout();
        $common = new Application_Service_Common();
        if ($this->hasParam('search')) {
            $subject = $this->_getParam('search');
            $this->view->search = $subject;
            $this->view->method = $this->_getParam('method');;
            $this->view->subjects = $common->getEmailParticipantSubjects($subject);
        }
    }
}
