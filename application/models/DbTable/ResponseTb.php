<?php

class Application_Model_DbTable_ResponseTb extends Zend_Db_Table_Abstract {

    protected $_name = 'response_result_tb';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params) {

        $sampleIds = $params['sampleId'];

        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if ($res == null || count($res) == 0) {
                $this->insert(array(
                    'shipment_map_id' => $params['smid'],
                    'sample_id' => $sampleId,
                    'date_tested' => Pt_Commons_General::dateFormat($params['dateTested'][$key]),
                    'mtb_detected' => $params['mtbDetected'][$key],
                    'rif_resistance' => $params['rifResistance'][$key],
                    'probe_d' => $params['probeD'][$key],
                    'probe_c' => $params['probeC'][$key],
                    'probe_e' => $params['probeE'][$key],
                    'probe_b' => $params['probeB'][$key],
                    'spc' => $params['spc'][$key],
                    'probe_a' => $params['probeA'][$key],
                    'created_by' => $authNameSpace->dm_id,
                    'created_on' => new Zend_Db_Expr('now()')
                ));
            } else {
                $this->update(array(
                    'shipment_map_id' => $params['smid'],
                    'sample_id' => $sampleId,
                    'date_tested' => Pt_Commons_General::dateFormat($params['dateTested'][$key]),
                    'mtb_detected' => $params['mtbDetected'][$key],
                    'rif_resistance' => $params['rifResistance'][$key],
                    'probe_d' => $params['probeD'][$key],
                    'probe_c' => $params['probeC'][$key],
                    'probe_e' => $params['probeE'][$key],
                    'probe_b' => $params['probeB'][$key],
                    'spc' => $params['spc'][$key],
                    'probe_a' => $params['probeA'][$key],
                    'updated_by' => $authNameSpace->UserID,
                    'updated_on' => new Zend_Db_Expr('now()')
                        ), "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }

    

}
