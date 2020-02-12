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
        $authToken = $this->_getParam('authToken');
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($authToken);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
    
    public function getShipmentFormAction()
    {
        $authToken = $this->_getParam('authToken');
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($authToken,'form');
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
}
