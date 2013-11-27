<?php

class Admin_EvaluateController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('get-shipments', 'html')
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
            $id = (int)base64_decode($this->_getParam('did'));
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
            $evalService = new Application_Service_Evaluation();
            $this->view->shipment = $evalService->getShipmentToEvaluate($id);
        }else{
            $this->_redirect("/admin/evaluate/");
        }
    }

    public function viewAction()
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
            $this->_redirect("/admin/evaluate/view/sid/$shipmentId/pid/$participantId/scheme/$scheme");
            
        }else{
            if($this->_hasParam('sid') && $this->_hasParam('pid')  && $this->_hasParam('scheme') ){            
                $sid = (int)base64_decode($this->_getParam('sid'));
                $pid = (int)base64_decode($this->_getParam('pid'));
                $this->view->scheme = $scheme = base64_decode($this->_getParam('scheme'));
                if($scheme == 'eid'){
                    
                    $schemeService = new Application_Service_Schemes();        
                    $this->view->extractionAssay = $schemeService->getEidExtractionAssay();
                    $this->view->detectionAssay = $schemeService->getEidDetectionAssay();
                    
                }
                $evalService = new Application_Service_Evaluation();
                $this->view->evaluateData = $evalService->viewEvaluation($sid,$pid,$scheme);            
            }else{
                $this->_redirect("/admin/evaluate/");
            }            
        }
        

    }


}







