<?php

class Application_Model_DbTable_ResponseTb extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_tb';
    protected $_primary = array('shipment_map_id', 'sample_id');

    public function updateResults($params)
    {
        $sampleIds = $params['sampleId'];

        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $data = array(
                'shipment_map_id' => $params['smid'],
                'sample_id' => $sampleId,
                'response_attributes' => (isset($params['cepheidMTBXDRTest'][$sampleId]) && !empty($params['cepheidMTBXDRTest'][$sampleId]) && $params['mtbcDetected'][$key] == "detected") ? json_encode($params['cepheidMTBXDRTest'][$sampleId]) : null,
                'assay_id' => $params['assayName'],
                'mtb_detected' => (isset($params['mtbcDetected'][$key]) && !empty($params['mtbcDetected'][$key])) ? $params['mtbcDetected'][$key] : null,
                'rif_resistance' => (isset($params['rifResistance'][$key]) && !empty($params['rifResistance'][$key])) ? $params['rifResistance'][$key] : null,
                'probe_d' => (isset($params['probeD'][$key])) ? $params['probeD'][$key] : null,
                'probe_c' => (isset($params['probeC'][$key])) ? $params['probeC'][$key] : null,
                'probe_e' => (isset($params['probeE'][$key])) ? $params['probeE'][$key] : null,
                'probe_b' => (isset($params['probeB'][$key])) ? $params['probeB'][$key] : null,
                'spc' => (isset($params['spc'][$key])) ? $params['spc'][$key] : null,
                'probe_a' => (isset($params['probeA'][$key])) ? $params['probeA'][$key] : null,
                'is1081_is6110' => (isset($params['ISI'][$key])) ? $params['ISI'][$key] : null,
                'rpo_b1' => (isset($params['rpoB1'][$key])) ? $params['rpoB1'][$key] : null,
                'rpo_b2' => (isset($params['rpoB2'][$key])) ? $params['rpoB2'][$key] : null,
                'rpo_b3' => (isset($params['rpoB3'][$key])) ? $params['rpoB3'][$key] : null,
                'rpo_b4' => (isset($params['rpoB4'][$key])) ? $params['rpoB4'][$key] : null,
                'gene_xpert_module_no' => (isset($params['geneXpertModuleNo'][$key]) && !empty($params['geneXpertModuleNo'][$key])) ? $params['geneXpertModuleNo'][$key] : null,
                'test_date' => (isset($params['dateTested'][$key]) && !empty($params['dateTested'][$key])) ? Pt_Commons_General::isoDateFormat($params['dateTested'][$key]) : null,
                'tester_name' => (isset($params['testerName'][$key]) && !empty($params['testerName'][$key])) ? $params['testerName'][$key] : null,
                'error_code' => (isset($params['errCode'][$key]) && !empty($params['errCode'][$key])) ? $params['errCode'][$key] : null
            );
            if (empty($res)) {
                $data['created_by'] = $authNameSpace->dm_id;
                $data['created_on'] = new Zend_Db_Expr('now()');
                $this->insert($data);
            } else {
                $data['updated_by'] = $authNameSpace->dm_id;
                $data['updated_on'] = new Zend_Db_Expr('now()');
                $this->update($data, "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }

    public function removeShipmentResults($mapId)
    {

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $data = array(
            'response_attributes' => '',
            'assay_id' => '',
            'mtb_detected' => '',
            'rif_resistance' => '',
            'probe_d' => '',
            'probe_c' => '',
            'probe_e' => '',
            'probe_b' => '',
            'spc' => '',
            'probe_a' => '',
            'is1081_is6110' => '',
            'rpo_b1' => '',
            'rpo_b2' => '',
            'rpo_b3' => '',
            'rpo_b4' => '',
            'test_date' => '',
            'tester_name' => '',
            'error_code' => '',
            'updated_by' => $authNameSpace->dm_id,
            'updated_on' => new Zend_Db_Expr('now()')
        );

        return $this->update($data, "shipment_map_id = " . $mapId);
    }
}
