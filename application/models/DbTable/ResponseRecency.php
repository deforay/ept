<?php

class Application_Model_DbTable_ResponseRecency extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_recency';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params)
    {
        $sampleIds = $params['sampleId'];
        // Zend_Debug::dump($params);die;
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $params['controlLine'][$key] = '';
                $params['verificationLine'][$key] = '';
                $params['longtermLine'][$key] = '';
                $params['result'][$key] = '';
            }
            $count = (isset($res) && $res != "")?count($res):0;
            if ($res == null || $count == 0) {
                $this->insert(array(
                    'shipment_map_id' => $params['smid'],
                    'sample_id' => $sampleId,
                    'reported_result' => $params['result'][$key],
                    'control_line' => $params['controlLine'][$key],
                    'diagnosis_line' => $params['verificationLine'][$key],
                    'longterm_line' => $params['longtermLine'][$key],
                    'created_by' => $authNameSpace->dm_id,
                    'created_on' => new Zend_Db_Expr('now()')
                ));
            } else {
                $this->update(array(
                    'reported_result' => $params['result'][$key],
                    'control_line' => $params['controlLine'][$key],
                    'diagnosis_line' => $params['verificationLine'][$key],
                    'longterm_line' => $params['longtermLine'][$key],
                    'updated_by' => $authNameSpace->dm_id,
                    'updated_on' => new Zend_Db_Expr('now()')
                ), "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
        return true;
    }

    public function updateResultsByAPI($params, $dm)
    {
        $sampleIds = $params['recencyData']->Section3->data->samples->id;
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            
            if ($res == null || count($res) == 0) {
                $this->insert(array(
                    'shipment_map_id'   => $params['mapId'],
                    'sample_id'         => $sampleId,
                    'reported_result'   => $params['recencyData']->Section3->data->samples->yourResults[$key],
                    'control_line'      => $params['recencyData']->Section3->data->samples->controlLine[$key],
                    'diagnosis_line' => $params['recencyData']->Section3->data->samples->verificationLine[$key],
                    'longterm_line'     => $params['recencyData']->Section3->data->samples->longtermLine[$key],
                    'created_by'        => $dm['dm_id'],
                    'created_on'        => new Zend_Db_Expr('now()')
                ));
            } else {
                $update = $this->update(array(
                    'reported_result'   => $params['recencyData']->Section3->data->samples->yourResults[$key],
                    'control_line'      => $params['recencyData']->Section3->data->samples->controlLine[$key],
                    'diagnosis_line' => $params['recencyData']->Section3->data->samples->verificationLine[$key],
                    'longterm_line'     => $params['recencyData']->Section3->data->samples->longtermLine[$key],
                    'updated_by'        => $dm['dm_id'],
                    'updated_on'        => new Zend_Db_Expr('now()')
                ), "shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            }
        }
        return true;
    }
}
