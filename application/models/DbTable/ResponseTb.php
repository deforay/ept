<?php

class Application_Model_DbTable_ResponseTb extends Zend_Db_Table_Abstract {

    protected $_name = 'response_result_tb';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params) {

        $sampleIds = $params['sampleId'];
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $count = (isset($res) && $res != "")?count($res):0;
            $data = array(
                'shipment_map_id' => $params['smid'],
                'sample_id' => $sampleId,
                'date_tested' => Pt_Commons_General::dateFormat($params['dateTested'][$key]),
                'mtb_detected' => $params['mtbcDetected'][$key],
                'rif_resistance' => $params['rifResistance'][$key],
                'probe_d' => $params['probeD'][$key],
                'probe_c' => $params['probeC'][$key],
                'probe_e' => $params['probeE'][$key],
                'probe_b' => $params['probeB'][$key],
                'spc' => $params['spc'][$key],
                'probe_a' => $params['probeA'][$key],
                'test_date' => Pt_Commons_General::dateFormat($params['dateTested'][$key]),
                'tester_name' => $params['testerName'][$key],
                'error_code' => $params['errCode'][$key]
            );
            
            if ($res == null || $count == 0) {
                $data['created_by'] = $authNameSpace->dm_id;
                $data['created_on'] = new Zend_Db_Expr('now()');
                $this->insert($data);
            } else {
                $data['updated_by'] = $authNameSpace->dm_id;
                $data['updated_on'] = new Zend_Db_Expr('now()');
                $this->update(array($data), "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }

    

}
