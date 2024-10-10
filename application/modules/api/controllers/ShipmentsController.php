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
        $params = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function getShipmentFormAction()
    {
        $params = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($params, 'form');
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function saveFormAction()
    {
        $params = json_decode(file_get_contents('php://input'));
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->saveShipmentsFormByAPI((array)$params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function dtsAction()
    {
        $params = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getDtsShipmentDetailsInAPI($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }
}
