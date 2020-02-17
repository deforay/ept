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
        $params = $this->_getAllParams();
        $shipmentService = new Application_Service_Shipments();
        $result = $shipmentService->getIndividualReportAPI($params);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
}
