<?php

class Admin_PossibleResultsController extends Zend_Controller_Action
{
    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
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
            $parameters = $this->getAllParams();
            $commonServices = new Application_Service_Common();
            $commonServices->getAllPossibleResultsInGrid($parameters);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $request->getPost();
            $commonServices->savePossibleResultsTest($params);
            $this->redirect('/admin/possible-results');
        }
        $this->view->allSchemes = $commonServices->getFullSchemesDetails();
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        if ($request->isPost()) {
            $params = $request->getPost();
            $commonServices->savePossibleResultsTest($params);
            $this->redirect('/admin/possible-results');
        } elseif ($this->hasParam('id')) {
            $id = base64_decode($this->_getParam('id'));
            $this->view->result = $commonServices->getPossibleResultById($id);
            $this->view->allSchemes = $commonServices->getFullSchemesDetails();
        }
    }
}
