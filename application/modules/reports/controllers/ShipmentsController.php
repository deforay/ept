<?php

class Reports_ShipmentsController extends Zend_Controller_Action
{

    public function init()
    {
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
        $this->_helper->layout()->pageName = 'analyze';                
    }

    public function indexAction()
    {
        
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $shipmentService = new Application_Service_Reports();
            $shipmentService->getAllShipments($params);
        }
        
        $scheme = new Application_Service_Schemes();
        $this->view->schemes = $scheme->getAllSchemes();
        
        $dataManagerService = new Application_Service_DataManagers();
        $this->view->dataManagers = $dataManagerService->getDataManagerList();
        
    }


}

