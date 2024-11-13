<?php

class Admin_ApiHistoryController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $apiServices = new Application_Service_ApiServices();
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
            $params = $this->getAllParams();
            $apiServices->fetchAllApiSyncDetailsByGrid($params);
        }
        $this->view->list = $apiServices->fetchTrackApiHistoryList();
    }

    public function apiParamsAction()
    {
        $this->_helper->layout->disableLayout();
        if ($this->_getParam('id')) {
            $id = base64_decode($this->_getParam('id'));
            $apiServices = new Application_Service_ApiServices();
            $this->view->result = $apiServices->getTrackApiParams($id);
        }
    }
}
