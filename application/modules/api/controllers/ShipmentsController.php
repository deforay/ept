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
        $arguments = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function getShipmentFormAction()
    {
        $arguments = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsInAPI($arguments, 'form');
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function saveFormAction()
    {
        $arguments = json_decode(file_get_contents('php://input'));
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->saveShipmentsFormByAPI((array)$arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function dtsAction()
    {
        $arguments = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $arguments['schemeType'] = 'dts';
        $result = $clientsServices->getSchemeTypeShipmentDetailsInAPIV2($arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function saveDtsAction()
    {
        $arguments = json_decode(file_get_contents('php://input'));
        $clientsServices = new Application_Service_ApiServices();
        $result = $clientsServices->saveShipmentDetailsFromAPI((array)$arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function vlAction()
    {
        $arguments = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $arguments['schemeType'] = 'vl';
        $result = $clientsServices->getSchemeTypeShipmentDetailsInAPI($arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function saveVlAction()
    {
        $arguments = json_decode(file_get_contents('php://input'));
        $clientsServices = new Application_Service_ApiServices();
        $result = $clientsServices->saveShipmentDetailsFromAPI((array)$arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function eidAction()
    {
        $arguments = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $arguments['schemeType'] = 'eid';
        $result = $clientsServices->getSchemeTypeShipmentDetailsInAPI($arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function saveEidAction()
    {
        $arguments = json_decode(file_get_contents('php://input'));
        $clientsServices = new Application_Service_ApiServices();
        $result = $clientsServices->saveShipmentDetailsFromAPI((array)$arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function customTestsAction()
    {
        $arguments = $this->getAllParams();
        $clientsServices = new Application_Service_Shipments();
        $arguments['schemeType'] = 'custom-tests';
        $result = $clientsServices->getSchemeTypeShipmentDetailsInAPI($arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function saveCustomTestsAction()
    {
        $arguments = json_decode(file_get_contents('php://input'));
        $clientsServices = new Application_Service_ApiServices();
        $result = $clientsServices->saveShipmentDetailsFromAPI((array)$arguments);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }
}
