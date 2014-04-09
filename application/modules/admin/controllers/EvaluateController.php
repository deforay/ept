<?php

class Admin_EvaluateController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('get-shipments', 'html')
                    ->addActionContext('update-shipment-comment', 'html')
                    ->initContext();        
        $this->_helper->layout()->pageName = 'analyze';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $evalService = new Application_Service_Evaluation();
            $evalService->getAllDistributions($params);
        }
    }

    public function getShipmentsAction()
    {
        if($this->_hasParam('did')){            
            $id = (int)($this->_getParam('did'));
            $evalService = new Application_Service_Evaluation();
            $this->view->shipments = $evalService->getShipments($id);            
        }else{
            $this->view->shipments = false;
        }
    }

    public function shipmentAction()
    {
        if($this->_hasParam('sid')){
            $id = (int)base64_decode($this->_getParam('sid'));
            $reEvaluate = false;
            if($this->_hasParam('re')){
                if(base64_decode($this->_getParam('re')) == 'yes'){
                    $reEvaluate = true;
                }
            }
            $evalService = new Application_Service_Evaluation();
            $shipment = $this->view->shipment = $evalService->getShipmentToEvaluate($id,$reEvaluate);
            $this->view->shipmentsUnderDistro = $evalService->getShipments($shipment[0]['distribution_id']);
        }else{
            $this->_redirect("/admin/evaluate/");
        }
    }

    public function viewAction()
    {       
            if($this->_hasParam('sid') && $this->_hasParam('pid')  && $this->_hasParam('scheme') ){
                $this->view->currentUrl = "/admin/evaluate/view/sid/".$this->_getParam('sid')."/pid/".$this->_getParam('pid')."/scheme/".$this->_getParam('scheme');
                $sid = (int)base64_decode($this->_getParam('sid'));
                $pid = (int)base64_decode($this->_getParam('pid'));
                $this->view->scheme = $scheme = base64_decode($this->_getParam('scheme'));
                if($scheme == 'eid'){
                    
                    $schemeService = new Application_Service_Schemes();        
                    $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                    $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
                    
                }
                if($scheme == 'dts'){
                    $schemeService = new Application_Service_Schemes(); 
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();                    
                }
                else if($scheme == 'vl'){
                    $schemeService = new Application_Service_Schemes();
                    $this->view->vlRange = $schemeService->getVlRange($sid);
                    $this->view->vlAssay = $schemeService->getVlAssay();              
                }
                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evalService->viewEvaluation($sid,$pid,$scheme);
                
                
            }else{
                $this->_redirect("/admin/evaluate/");
            }        
    }

    public function editAction()
    {
        if($this->getRequest()->isPost()){
            
            $params = $this->getRequest()->getPost();
            $evalService = new Application_Service_Evaluation();
            $evalService->updateShipmentResults($params);
            $shipmentId = base64_encode($params['shipmentId']);
            $participantId = base64_encode($params['participantId']);
            $scheme = base64_encode($params['scheme']);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = "Shipment Results updated successfully";
            if(isset($params['whereToGo']) && $params['whereToGo'] != ""){
               $this->_redirect($params['whereToGo']); 
            }else{
                $this->_redirect("/admin/evaluate/shipment/sid/$shipmentId");    
            }
            
        }else{
            if($this->_hasParam('sid') && $this->_hasParam('pid')  && $this->_hasParam('scheme') ){
                
                $this->view->currentUrl = "/admin/evaluate/edit/sid/".$this->_getParam('sid')."/pid/".$this->_getParam('pid')."/scheme/".$this->_getParam('scheme');
                
                $sid = (int)base64_decode($this->_getParam('sid'));
                $pid = (int)base64_decode($this->_getParam('pid'));
                $this->view->scheme = $scheme = base64_decode($this->_getParam('scheme'));
                if($scheme == 'eid'){
                    
                    $schemeService = new Application_Service_Schemes();        
                    $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                    $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
                    
                }
                else if($scheme == 'dts'){
                    $schemeService = new Application_Service_Schemes(); 
                    $this->view->allTestKits = $schemeService->getAllDtsTestKit();                    
                }
                else if($scheme == 'dbs'){
                    $schemeService = new Application_Service_Schemes(); 
                    $this->view->wb = $schemeService->getDbsWb();
                    $this->view->eia = $schemeService->getDbsEia();              
                }
                else if($scheme == 'vl'){
                    $schemeService = new Application_Service_Schemes();
                    $this->view->vlRange = $schemeService->getVlRange($sid);
                    $this->view->vlAssay = $schemeService->getVlAssay();              
                }
                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evalService->editEvaluation($sid,$pid,$scheme);
                
                
            }else{
                $this->_redirect("/admin/evaluate/");
            }            
        }
        

    }

    public function updateShipmentCommentAction()
    {
        if($this->_hasParam('sid')){            
            $sid = (int)base64_decode($this->_getParam('sid'));
            $comment = $this->_getParam('comment');
            $evalService = new Application_Service_Evaluation();
            $this->view->message = $evalService->updateShipmentComment($sid,$comment);
        }else{
            $this->view->message = "Unable to update shipment comment. Please try again later.";
        }
    }


}











