<?php
class Api_InitController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
    }

    public function getAction()
    {
        $params = $this->getAllParams();
        $clientsServices = new Application_Service_ApiServices();
        $result = $clientsServices->getApiReferences($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }
}
