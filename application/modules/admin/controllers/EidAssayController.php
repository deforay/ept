<?php

class Admin_EidAssayController extends Zend_Controller_Action{

    public function init(){
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('change-status', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction(){
        if ($this->getRequest()->isPost()) {
            $parameters = $this->_getAllParams();
            $vlAssayService = new Application_Service_VlAssay();
            if(isset($parameters['fromSource']) && $parameters['fromSource'] == "extraction"){
              $vlAssayService->getAllEidExtractionAssay($parameters);
            }elseif(isset($parameters['fromSource']) && $parameters['fromSource'] == "detection"){
               $vlAssayService->getAllEidDetectionAssay($parameters); 
            }
        }else{
            $this->view->source = "";
            if($this->_hasParam('fromSource')){
              $this->view->source = $this->_getParam('fromSource');
            } 
        }
    }
    
    public function addAction(){
         if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            if(isset($params['category']) && trim($params['category']) == 'extraction'){
              $vlAssayService->addEidExtractionAssay($params);
              $this->_redirect("/admin/eid-assay/index/fromSource/".$params['category']);
            }elseif(isset($params['category']) && trim($params['category']) == 'detection'){
              $vlAssayService->addEidDetectionAssay($params);
              $this->_redirect("/admin/eid-assay/index/fromSource/".$params['category']);
            }
            $this->_redirect("/admin/eid-assay/");
        }else{
            $this->view->source = "";
            if($this->_hasParam('source')){
              $this->view->source = $this->_getParam('source');
            }
        }
    }
    
    public function editAction(){
        $this->_redirect("/admin/eid-assay");
    }
    
    public function changeStatusAction(){
       if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost();
            $vlAssayService = new Application_Service_VlAssay();
            if(isset($params['formSource']) && $params['formSource'] == "extraction"){
               $this->view->result = $vlAssayService->changeEidExtractionNameStatus($params);
            }else if(isset($params['formSource']) && $params['formSource'] == "detection"){
               $this->view->result = $vlAssayService->changeEidDetectionNameStatus($params); 
            }
        } 
    }
}