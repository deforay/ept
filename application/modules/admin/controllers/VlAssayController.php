<?php

class Admin_VlAssayController extends Zend_Controller_Action{

    public function init(){
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction(){
        if ($this->getRequest()->isPost()) {
            $parameters = $this->_getAllParams();
            $vlAssayService = new Application_Service_VlAssay();
            $vlAssayService->getAllVlAssay($parameters);
        } 
    }
    
    public function addAction(){
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            $vlAssayService->addVlAssay($params);
            $this->_redirect("/admin/vl-assay");
        }
    }
    
    public function editAction(){
        $vlAssayService = new Application_Service_VlAssay();
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $vlAssayService->updateVlAssay($params);
            $this->_redirect("/admin/vl-assay");
        }
        if($this->_hasParam('id')){
            $id = (int)$this->_getParam('id');
            $this->view->vlAssay = $vlAssayService->getVlAssay($id);
        }else{
            $this->_redirect("/admin/vl-assay");
        }
    }
}