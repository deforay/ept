<?php
class Api_ShipmentsController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
    }

    public function getAction()
    {
        $params = $this->_getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($params);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
    
    public function getShipmentFormAction()
    {
        // $authToken = $this->_getParam('authToken');
        $params = $this->_getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($params,'form');
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
}
