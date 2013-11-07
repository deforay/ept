<?php

class Application_Model_DbTable_ResponseDts extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_dts';
    protected $_primary = array('shipment_map_id','sample_id');
    
    public function updateResults($params){
        //Zend_Debug::dump($params);die;
        $sampleIds = $params['sampleId'];
        
        foreach($sampleIds as $key => $sampleId){
            //die("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId);
            $res = $this->fetchRow("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            if($res == null || count($res) == 0){
                $this->insert(array(
                                    'shipment_map_id'=>$params['smid'],
                                    'sample_id'=>$sampleId,
                                    'test_kit_name_1'=>$params['test_kit_name_1'],
                                    'lot_no_1'=>$params['lot_no_1'],
                                    'exp_date_1'=>Pt_Commons_General::dateFormat($params['exp_date_1']),
                                    'test_result_1'=>$params['test_result_1'][$key],
                                    'test_kit_name_2'=>$params['test_kit_name_2'],
                                    'lot_no_2'=>$params['lot_no_2'],
                                    'exp_date_2'=>Pt_Commons_General::dateFormat($params['exp_date_2']),
                                    'test_result_2'=>$params['test_result_2'][$key],
                                    'test_kit_name_3'=>$params['test_kit_name_3'],
                                    'lot_no_3'=>$params['lot_no_3'],
                                    'exp_date_3'=>Pt_Commons_General::dateFormat($params['exp_date_3']),
                                    'test_result_3'=>$params['test_result_3'][$key],
                                    'reported_result'=>$params['reported_result'][$key],
                                    'created_by' => $authNameSpace->dm_id,
                                    'created_on' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'test_kit_name_1'=>$params['test_kit_name_1'],
                                    'lot_no_1'=>$params['lot_no_1'],
                                    'exp_date_1'=>Pt_Commons_General::dateFormat($params['exp_date_1']),
                                    'test_result_1'=>$params['test_result_1'][$key],
                                    'test_kit_name_2'=>$params['test_kit_name_2'],
                                    'lot_no_2'=>$params['lot_no_2'],
                                    'exp_date_2'=>Pt_Commons_General::dateFormat($params['exp_date_2']),
                                    'test_result_2'=>$params['test_result_2'][$key],
                                    'test_kit_name_3'=>$params['test_kit_name_3'],
                                    'lot_no_3'=>$params['lot_no_3'],
                                    'exp_date_3'=>Pt_Commons_General::dateFormat($params['exp_date_3']),
                                    'test_result_3'=>$params['test_result_3'][$key],
                                    'reported_result'=>$params['reported_result'][$key],
                                    'updated_by' => $authNameSpace->dm_id,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
                
            }

        }
    }
    

}

