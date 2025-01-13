<?php

class Admin_EnrollmentsController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges) && !in_array('manage-participants', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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
        if ($this->hasParam('pid') && $this->hasParam('sid')) {
            $pid = $this->_getParam('pid');
            $this->view->sid = $sid = $this->_getParam('sid');
            $participantService = new Application_Service_Participants();
            $this->view->enrollmentDetails = $participantService->getEnrollmentDetails($pid, $sid);
        } else {
            $this->redirect("/admin/enrollments");
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $request->getPost();
            $participants = new Application_Service_Participants();
            $participants->enrollParticipants($params);
            $this->redirect("/admin/enrollments");
        } else {
            if ($this->hasParam('scheme')) {
                $participants = new Application_Service_Participants();
                $this->view->scheme = $scheme = $this->_getParam('scheme');
                $this->view->participants = $participants->getUnEnrolled($scheme);
                $this->view->enrolled = $participants->getEnrolledBySchemeCode($scheme);
            }
        }
    }

    public function bulkEnrollmentAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $participantService = new Application_Service_Participants();
        if ($request->isPost()) {
            $params = $request->getPost();
            $this->view->response = $participantService->uploadBulkEnrollment($params);
            $this->redirect("/admin/enrollments/bulk-enrollment");
        } else {
            $scheme = new Application_Service_Schemes();
            $this->view->schemes = $scheme->getAllSchemes();
        }
    }
}
