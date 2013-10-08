<?php

class Admin_DistributionsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('view-shipment', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'configurations';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $distributionService = new Application_Service_Distribution();
            $distributionService->getAllDistributions($params);
        }
    }

    public function addAction()
    {
        $distributionService = new Application_Service_Distribution();
        
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $distributionService->addDistribution($params);
            $this->_redirect("/admin/distributions");
        }

        $this->view->distributionDates = $distributionService->getDistributionDates();
        
    }

    public function viewShipmentAction()
    {
        $this->_helper->layout()->disableLayout();
        if($this->_hasParam('id')){
            
            $id = (int)$this->_getParam('id');
            $distributionService = new Application_Service_Distribution();
            $this->view->shipments = $distributionService->getShipments($id);
            
        }else{
            
        }
    }


}





