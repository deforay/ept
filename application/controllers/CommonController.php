<?php

class CommonController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('check-duplicate', 'html')
                    ->addActionContext('delete-response', 'html')
                   ->initContext();
    }

    public function indexAction()
    {
        // action body
    }

    public function sendMailAction()
    {
        
    }

    public function checkDuplicateAction()
    {
        if (!$this->_hasParam('tableName')) {
            $this->view->data = "";
        } else {
            $params = $this->_getAllParams();
            $commonServices = new Application_Service_Common();
            $this->view->data = $commonServices->checkDuplicate($params);
        }        
    }

    public function deleteAction()
    {

    }

    public function deleteResponseAction()
    {
        if($this->_hasParam('mid')){
            if ($this->getRequest()->isPost()) {
                $mapId = (int)base64_decode($this->_getParam('mid'));
                $schemeType = ($this->_getParam('schemeType'));
                $shipmentService = new Application_Service_Shipments();
                if($schemeType == 'dts'){
                    $this->view->result = $shipmentService->removeDtsResults($mapId);
                }else if($schemeType == 'eid'){
                    $this->view->result = $shipmentService->removeDtsEidResults($mapId);
                }else if($schemeType == 'vl'){
                    $this->view->result = $shipmentService->removeDtsVlResults($mapId);
                }
            }
        }else{
            $this->view->message = "Unable to delete. Please try again later or contact system admin for help";
        }
    }


}









