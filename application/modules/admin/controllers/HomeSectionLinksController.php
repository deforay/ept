<?php

class Admin_HomeSectionLinksController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
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
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $homeSectionService = new Application_Service_HomeSection();
            $homeSectionService->getAllHomeSectionInGrid($params);
        }
    }

    public function addAction()
    {
        $homeSectionService = new Application_Service_HomeSection();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $homeSectionService->saveHomeSection($params);
            $this->redirect("/admin/home-section-links");
        }
    }

    public function editAction()
    {
        $homeSectionService = new Application_Service_HomeSection();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $homeSectionService->saveHomeSection($params);
            $this->redirect("/admin/home-section-links");
        }
        if ($this->hasParam('id')) {
            $id = (int) base64_decode($this->_getParam('id'));
            $this->view->result = $homeSectionService->getHomeSectionById($id);
        }
    }
}
