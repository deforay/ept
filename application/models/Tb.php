<?php


class Application_Model_Tb
{

    public function __construct()
    {
    }

    public function evaluate($shipmentResult, $shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->update('shipment', array('status' => 'evaluated'), "shipment_id = " . $shipmentId);
        return $shipmentResult;
    }
}
