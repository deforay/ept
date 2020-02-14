<?php
class Api_ParticipantController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();
    }

    public function getAction()
    {
        $authToken = $this->_getParam('authToken');
        $shipmentService = new Application_Service_Shipments();
        $result = $shipmentService->getIndividualReportAPI($authToken);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
}
