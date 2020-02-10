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
        $dmId = $this->_getParam('dmId');
        $clientsServices = new Application_Service_Shipments();
        $result = $clientsServices->getShipmentDetailsByDmIdInAPI($dmId);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
}
