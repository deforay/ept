<?php

class Reports_FinalizeController extends Zend_Controller_Action
{

    public function init()
    {
       $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                 ->addActionContext('get-shipments', 'html')
                  ->initContext();        
        $this->_helper->layout()->pageName = 'finalize';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $distributionService = new Application_Service_Distribution();
            $distributionService->getAllDistributionReports($params);
        }
    }

    public function getShipmentsAction()
    {
        if($this->_hasParam('did')){            
            $id = (int)($this->_getParam('did'));
            $shipmentService = new Application_Service_Shipments();
            $this->view->shipments = $shipmentService->getShipmentInReports($id);            
        }else{
            $this->view->shipments = false;
        }
    }


}





