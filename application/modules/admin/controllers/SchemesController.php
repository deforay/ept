<?php

class Admin_SchemesController extends Zend_Controller_Action
{
    public function init()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        // $ajaxContext->addActionContext('index', 'html')
        $ajaxContext->addActionContext('test-results', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function testResultsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $parameters = $this->getAllParams();
            $commonServices = new Application_Service_Common();
            $commonServices->getAllPossibleResultsInGrid($parameters);
        }
    }

    public function manageTestResultsAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $commonServices = new Application_Service_Common();
        $alertMsgInit = new Zend_Session_Namespace('alertSpace');
        if ($request->isPost()) {
            $params = $request->getPost();
            $result = $commonServices->savePossibleResultsTest($params);
            if ($result) {
                $alertMsgInit->message = 'Saved successfully';
                $this->redirect('/admin/schemes/test-results');
            } else {
                $alertMsgInit->message = 'Seomthing went wrong please try again later.';
            }
        } elseif ($this->hasParam('id')) {
            $id = base64_decode($this->_getParam('id'));
            $this->view->results = $commonServices->getPossibleResultById($id);
        }
    }
}
