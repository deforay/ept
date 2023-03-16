<?php

class Admin_PublicationsController extends Zend_Controller_Action
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
            $parameters = $this->getAllParams();
            $publicationService = new Application_Service_Publication();
            $publicationService->getAllPublication($parameters);
        }
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $publicationService = new Application_Service_Publication();
            $publicationService->addPublication($params);
            $this->redirect("/admin/publications");
        }
    }

    public function editAction()
    {
        $publicationService = new Application_Service_Publication();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $publicationService->updatePublication($params);
            $this->redirect("/admin/publications");
        }
        if ($this->hasParam('id')) {
            $publicationId = (int)$this->_getParam('id');
            $this->view->publication = $publicationService->getPublication($publicationId);
        } else {
            $this->redirect("/admin/publications");
        }
    }
}
