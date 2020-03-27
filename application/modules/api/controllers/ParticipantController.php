<?php
class Api_ParticipantController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->layout->disableLayout();
    }
    
    public function getAction()
    {
        die("hi");
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->_getAllParams();
        $shipmentService = new Application_Service_Shipments();
        $result = $shipmentService->getIndividualReportAPI($params);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }
    public function summaryAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->_getAllParams();
        $shipmentService = new Application_Service_Shipments();
        $result = $shipmentService->getSummaryReportAPI($params);
        $this->getResponse()->setBody(json_encode($result,JSON_PRETTY_PRINT));
    }

    public function downloadAction() {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($params = $this->_getAllParams()) {
            // Zend_Debug::dump($params);die;
            $defaultParams = array('module','controller','action');
            foreach($params as $link=>$mapId){
                if(!in_array($link,$defaultParams)){
                    $downloadLink = $link;
                    $id = base64_decode($mapId);
                }
            }
            $result = $db->fetchRow($db->select()->from('data_manager')->where("download_link = ?", $downloadLink));
            if(!$result){
                $this->getResponse()->setBody(json_encode(array(
                    'status'    => 'fail',
                    'message'   => 'Your link was expired. Please contact admin'
                ),JSON_PRETTY_PRINT));
            }
            // die($id);
            $this->view->result = $db->fetchRow($db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id'))
            ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('s.shipment_code','s.scheme_type'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
            ->where("spm.map_id = ?", $id));
            if(!$this->view->result){
                $this->getResponse()->setBody(json_encode(array(
                    'status'    => 'fail',
                    'message'   => 'Report not ready'
                ),JSON_PRETTY_PRINT));
            }
        } else {
            $this->getResponse()->setBody(json_encode(array(
                'status'    => 'fail',
                'message'   => 'Something went wrong. Please contact admin'
            ),JSON_PRETTY_PRINT));
        }
    }

    public function downloadSummaryAction() {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if ($params = $this->_getAllParams()) {
            $defaultParams = array('module','controller','action');
            foreach($params as $link=>$mapId){
                if(!in_array($link,$defaultParams)){
                    $downloadLink = $link;
                    $id = base64_decode($mapId);
                }
            }
            $result = $db->fetchRow($db->select()->from('data_manager')->where("download_link = ?", $downloadLink));
            if(!$result){
                $this->getResponse()->setBody(json_encode(array(
                    'status'    => 'fail',
                    'message'   => 'Your link was expired. Please contact admin'
                ),JSON_PRETTY_PRINT));
            }
            // die($id);
            $this->view->result = $db->fetchRow($db->select()->from(array('spm' => 'shipment_participant_map'), array('spm.map_id'))
            ->join(array('s' => 'shipment'), 's.shipment_id=spm.shipment_id', array('s.shipment_code','s.scheme_type'))
            ->join(array('p' => 'participant'), 'p.participant_id=spm.participant_id', array('p.first_name', 'p.last_name'))
            ->where("spm.map_id = ?", $id));
            if(!$this->view->result){
                $this->getResponse()->setBody(json_encode(array(
                    'status'    => 'fail',
                    'message'   => 'Report not ready'
                ),JSON_PRETTY_PRINT));
            }
        } else {
            $this->getResponse()->setBody(json_encode(array(
                'status'    => 'fail',
                'message'   => 'Something went wrong. Please contact admin'
            ),JSON_PRETTY_PRINT));
        }
    }
}
