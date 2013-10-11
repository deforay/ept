<?php

class Admin_ShipmentController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->addActionContext('get-sample-form', 'html')
                ->initContext();
        $this->_helper->layout()->pageName = 'configurations';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->getAllShipments($params);
        }
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        $distro = new Application_Service_Distribution();
        $this->view->unshippedDistro = $distro->getUnshippedDistributions();        
    }

    public function addAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $shipmentService = new Application_Service_Shipments();
            $shipmentService->addShipment($params);
            //$this->_redirect("/admin/shipment");
        }
    }

    public function getSampleFormAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->view->scheme = $sid = strtolower($this->_getParam('sid'));
            if($sid == 'vl'){
                $scheme = new Application_Service_Schemes();
                $this->view->vlControls = $scheme->getSchemeControls($sid);
            }
            else if($sid == 'eid'){
                $scheme = new Application_Service_Schemes();
                $this->view->eidControls = $scheme->getSchemeControls($sid);
                $this->view->eidPossibleResults = $scheme->getPossibleResults($sid);
            }
            else if($sid == 'dts'){
                $scheme = new Application_Service_Schemes();
                $this->view->dtsPossibleResults = $scheme->getPossibleResults($sid);
            }
        }        
    }


}





