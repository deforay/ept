<?php

class Application_Model_DbTable_ResponseVl extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_vl';
    protected $_primary = array('shipment_map_id', 'sample_id');

    const NOW = 'now()';

    public function updateResults($params)
    {
        $sampleIds = $params['sampleId'];
        foreach ($sampleIds as $key => $sampleId) {

            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $adminAuthNameSpace = new Zend_Session_Namespace('administrators');
            //Set tnd value if Yes
            $tnd = null;
            if (isset($params['isPtTestNotPerformed']) && $params['isPtTestNotPerformed'] == 'yes') {
                $params['vlResult'][$key] = '';
            } elseif ((!empty($params['vlResult'][$key]) && $params['vlResult'][$key] == 0) || (!empty($params['tnd'][$key]))) {
                $tnd = 'yes';
                $params['vlResult'][$key] = 0;
            }

            $data = [
                'shipment_map_id' => $params['smid'],
                'vl_assay' => (isset($params['vlAssay']) && !empty($params['vlAssay'])) ? (int)$params['vlAssay'] : null,
                'sample_id' => $sampleId,
                'reported_viral_load' => (float)$params['vlResult'][$key],
                'is_tnd' => $tnd ?? null,
                'is_result_invalid' => $params['invalidVlResult'][$key] ?? null,
                'error_code' => $params['errorCode'][$key] ?? null,
                'module_number' => $params['moduleNumber'][$key] ?? null,
                'comment' => $params['comment'][$key] ?? null
            ];
            // Zend_Debug::dump($data);die;
            $res = $this->fetchRow("shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            if (empty($res)) {
                $data['created_by'] = $authNameSpace->dm_id;
                $data['created_on'] = new Zend_Db_Expr(self::NOW);
                $this->insert($data);
            } else {
                $data['updated_by'] = $authNameSpace->dm_id;
                $data['updated_on'] = new Zend_Db_Expr(self::NOW);
                $this->update($data, "shipment_map_id = " . $params['smid'] . " and sample_id = " . $sampleId);
            }
        }
    }

    public function updateResultsByAPI($params, $dm)
    {

        $sampleIds = $params["vlData"]->Section3->data->no->tableRowTxt->id;
        foreach ($sampleIds as $key => $sampleId) {
            $res = $this->fetchRow("shipment_map_id = " . $params['mapId'] . " and sample_id = '" . $sampleId . "'");
            //Set tnd value if Yes
            $tnd = null;
            if ($params["vlData"]->Section3->data->isPtTestNotPerformedRadio == 'yes') {
                $params["vlData"]->Section3->data->no->vlResult[$key] = '';
            } elseif ($params["vlData"]->Section3->data->no->tndReferenceRadioSelected[$key] == 'yes') {
                $tnd = 'yes';
                $params["vlData"]->Section3->data->no->vlResult[$key] = '0.00';
            }
            if ($res == null || $res === false) {
                $this->insert(array(
                    'shipment_map_id'       =>  $params['mapId'],
                    'sample_id'             =>  $sampleId,
                    'reported_viral_load'   =>  $params["vlData"]->Section3->data->no->vlResult[$key],
                    'is_tnd'                =>  $tnd,
                    'created_by'            =>  $dm['dm_id'],
                    'created_on'            =>  new Zend_Db_Expr('now()')
                ));
            } else {
                $this->update(array(
                    'shipment_map_id'       =>  $params['mapId'],
                    'sample_id'             =>  $sampleId,
                    'reported_viral_load'   =>  $params["vlData"]->Section3->data->no->vlResult[$key],
                    'is_tnd'                =>  $tnd,
                    'updated_by'            =>  $dm['dm_id'],
                    'updated_on'            =>  new Zend_Db_Expr('now()')
                ), "shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
            }
        }
        return true;
    }

    public function updateResultsByAPIV2($params)
    {
        try {
            $id = 0;
            $sampleIds = $params['sample_id'];
            foreach ($sampleIds as $key => $sampleId) {
                $res = $this->fetchRow("shipment_map_id = " . $params['mapId'] . " and sample_id = '" . $sampleId . "'");
                //Set tnd value if Yes
                if ($params['isPtTestNotPerformed'] == 'yes') {
                    $params['vlResult']->$key = '';
                } elseif ($params['tnd']->$key == 'yes') {
                    $params["vlResult"]->$key = '0.00';
                }
                $data = [
                    'shipment_map_id' => $params['mapId'],
                    'vl_assay' => $params['vlAssay'] ?? null,
                    'sample_id' => $sampleId,
                    'reported_viral_load' => $params['vlResult']->$key ?? null,
                    'is_tnd' => $params['tnd']->$key ?? null,
                    'is_result_invalid' => $params['invalidVlResult']->$key ?? null,
                    'error_code' => $params['errorCode']->$key ?? null,
                    'module_number' => $params['moduleNumber']->$key ?? null,
                    'comment' => $params['comment']->$key ?? null
                ];
                if ($res == null || $res === false) {
                    $data['created_by'] = $params['dmId'];
                    $data['created_on'] = new Zend_Db_Expr('now()');
                    $id = $this->insert($data);
                } else {
                    $data['updated_by'] = $params['dmId'];
                    $data['updated_on'] = new Zend_Db_Expr('now()');
                    $id = $this->update($data, "shipment_map_id = " . $params['mapId'] . " and sample_id = " . $sampleId);
                }
            }
            return $id;
        } catch (Throwable $exc) {
            error_log($exc->getMessage());
            Pt_Commons_LoggerUtility::log('error', $exc->getFile() . ":" . $exc->getLine() . " - " . $exc->getMessage());
            return 0;
        }
    }
}
