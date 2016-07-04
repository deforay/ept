<?php

class Admin_PublicationsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction(){
       if ($this->getRequest()->isPost()) {
            $parameters = $this->_getAllParams();
            $publicationService = new Application_Service_Publication();
            $publicationService->getAllPublication($parameters);
        }
    }
    
    public function addAction(){
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $publicationService = new Application_Service_Publication();
            $publicationService->addPublication($params);
            $this->_redirect("/admin/publications");
        }
    }
    
    public function editAction(){
        $publicationService = new Application_Service_Publication();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $publicationService->updatePublication($params);
            $this->_redirect("/admin/publications");
        }
        if($this->_hasParam('id')){
            $publicationId = (int)$this->_getParam('id');
            $this->view->publication = $publicationService->getPublication($publicationId);
        }else{
            $this->_redirect("/admin/publications");
        }
    }

}





