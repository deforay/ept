<?php

class Admin_DistributionsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->addActionContext('view-shipment', 'html')
                    ->addActionContext('ship-distribution', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'manageMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $distributionService = new Application_Service_Distribution();
            $distributionService->getAllDistributions($params);
        }else if($this->_hasParam('searchString')){
           $this->view->searchData= $this->_getParam('searchString');
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

    public function shipDistributionAction()
    {
        if($this->_hasParam('did')){
            $id = (int)base64_decode($this->_getParam('did'));
            $distributionService = new Application_Service_Distribution();
            $this->view->message = $distributionService->shipDistribution($id);
            
       }else{
            $this->view->message = "Unable to ship. Please try again later or contact system admin for help";
        }
    }

    public function editAction()
    {
        $distributionService = new Application_Service_Distribution();
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();
            $distributionService->updateDistribution($params);
            $this->_redirect("/admin/distributions");
        }
        else if($this->_hasParam('d8s5_8d')){
            $id = (int)base64_decode($this->_getParam('d8s5_8d'));
            $this->view->result = $distributionService->getDistribution($id);
            $this->view->distributionDates = $distributionService->getDistributionDates();
            if($this->_hasParam('5h8pp3t')){
             
                $this->view->fromStatus = 'shipped';
            }
        }else{
            $this->_redirect('admin/distributions/index');
        }
        
    }


}









