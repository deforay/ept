<?php

class Admin_EmailParticipantsController extends Zend_Controller_Action
{

    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */

        $request = $this->getRequest();

        $this->_helper->layout()->pageName = 'configMenu';
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
        $common = new Application_Service_Common();
        $this->view->templates = $common->getAllEmailTemplateDetails();
        $this->view->shipment = $shipment->getAllShipmentCode();
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
    }

    function getMailTemplateAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $purpose = $request->getParam('mailPurpose');
            $common = new Application_Service_Common();
            $this->view->result = $common->getEmailTemplate($purpose);
        }
    }

    function getMailTemplateBySubjectAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $subject = $request->getParam('subject');
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
