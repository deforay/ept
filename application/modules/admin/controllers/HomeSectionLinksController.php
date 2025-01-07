<?php

class Admin_HomeSectionLinksController extends Zend_Controller_Action
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
        /** @var $ajaxContext Zend_Controller_Action_Helper_AjaxContext  */
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
            $homeSectionService = new Application_Service_HomeSection();
            $homeSectionService->getAllHomeSectionInGrid($params);
        }
    }

    public function addAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $homeSectionService->saveHomeSection($params);
            $this->redirect("/admin/home-section-links");
        }
    }

    public function editAction()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $homeSectionService->saveHomeSection($params);
            $this->redirect("/admin/home-section-links");
        }
        if ($this->hasParam('id')) {
            $id = (int) base64_decode($this->_getParam('id'));
            $this->view->result = $homeSectionService->getHomeSectionById($id);
        }
    }

    public function getDisplayOrderAction()
    {
        $request = $this->getRequest();
        $homeSectionService = new Application_Service_HomeSection();
        if ($request->isPost()) {
            $params = $request->getPost();
            $maxSortOrder = $homeSectionService->getDisplayOrder($params);
            // Send the response as JSON
            $this->_helper->json([
                'maxSortOrder' => $maxSortOrder
            ]);
        }
    }
}
