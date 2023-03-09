<?php


class Application_Model_GenericTest
{

    public function __construct()
    {
    }

    public function getSamplesForParticipant($sId, $pId)
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(array('ref' => 'reference_result_generic_test'))
            ->join(array('s' => 'shipment'), 's.shipment_id=ref.shipment_id')
            ->join(array('sp' => 'shipment_participant_map'), 's.shipment_id=sp.shipment_id')
            ->joinLeft(array('res' => 'response_result_generic_test'), 'res.shipment_map_id = sp.map_id and res.sample_id = ref.sample_id')
            ->where('sp.shipment_id = '.$sId.' AND sp.participant_id = '.$pId.'');
        // die($sql);
        return ($db->fetchAll($sql));
    }

    public function getAllTbAssays()
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchAllTbAssay();
    }

    public function getTbAssayName($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->getTbAssayName($assayId);
    }
    
    public function getTbAssayDrugResistanceStatus($assayId)
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchTbAssayDrugResistanceStatus($assayId);
    }
}
