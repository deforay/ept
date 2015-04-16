<?php

class Application_Model_DbTable_ResponseDbs extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_dbs';
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
                                    'eia_1'=>$params['eia_1'],
                                    'lot_no_1'=>$params['lot_no_1'],
                                    'exp_date_1'=>Pt_Commons_General::dateFormat($params['exp_date_1']),
                                    'od_1'=>$params['od_1'][$key],
                                    'cutoff_1'=>$params['cutoff_1'][$key],
                                    'eia_2'=>$params['eia_2'],
                                    'lot_no_2'=>$params['lot_no_2'],
                                    'exp_date_2'=>Pt_Commons_General::dateFormat($params['exp_date_2']),
                                    'od_2'=>$params['od_2'][$key],
                                    'cutoff_2'=>$params['cutoff_2'][$key],
                                    'eia_3'=>$params['eia_3'],
                                    'lot_no_3'=>$params['lot_no_3'],
                                    'exp_date_3'=>Pt_Commons_General::dateFormat($params['exp_date_3']),
                                    'od_3'=>$params['od_3'][$key],
                                    'cutoff_3'=>$params['cutoff_3'][$key],
                                    'wb'=>$params['wb'],
                                    'wb_lot'=>$params['wb_lot'],
                                    'wb_exp_date'=>Pt_Commons_General::dateFormat($params['wb_exp_date']),
                                    'wb_160'=>$params['wb_160'][$key],
                                    'wb_120'=>$params['wb_120'][$key],
                                    'wb_66'=>$params['wb_66'][$key],
                                    'wb_55'=>$params['wb_55'][$key],
                                    'wb_51'=>$params['wb_51'][$key],
                                    'wb_41'=>$params['wb_41'][$key],
                                    'wb_31'=>$params['wb_31'][$key],
                                    'wb_24'=>$params['wb_24'][$key],
                                    'wb_17'=>$params['wb_17'][$key],
                                    'reported_result'=>$params['reported_result'][$key],
                                    'created_by' => $authNameSpace->dm_id,
                                    'created_on' => new Zend_Db_Expr('now()')
                                   ));                
            }else{
                $this->update(array(
                                    'eia_1'=>$params['eia_1'],
                                    'lot_no_1'=>$params['lot_no_1'],
                                    'exp_date_1'=>Pt_Commons_General::dateFormat($params['exp_date_1']),
                                    'od_1'=>$params['od_1'][$key],
                                    'cutoff_1'=>$params['cutoff_1'][$key],
                                    'eia_2'=>$params['eia_2'],
                                    'lot_no_2'=>$params['lot_no_2'],
                                    'exp_date_2'=>Pt_Commons_General::dateFormat($params['exp_date_2']),
                                    'od_2'=>$params['od_2'][$key],
                                    'cutoff_2'=>$params['cutoff_2'][$key],
                                    'eia_3'=>$params['eia_3'],
                                    'lot_no_3'=>$params['lot_no_3'],
                                    'exp_date_3'=>Pt_Commons_General::dateFormat($params['exp_date_3']),
                                    'od_3'=>$params['od_3'][$key],
                                    'cutoff_3'=>$params['cutoff_3'][$key],
                                    'wb'=>$params['wb'],
                                    'wb_lot'=>$params['wb_lot'],
                                    'wb_exp_date'=>Pt_Commons_General::dateFormat($params['wb_exp_date']),
                                    'wb_160'=>$params['wb_160'][$key],
                                    'wb_120'=>$params['wb_120'][$key],
                                    'wb_66'=>$params['wb_66'][$key],
                                    'wb_55'=>$params['wb_55'][$key],
                                    'wb_51'=>$params['wb_51'][$key],
                                    'wb_41'=>$params['wb_41'][$key],
                                    'wb_31'=>$params['wb_31'][$key],
                                    'wb_24'=>$params['wb_24'][$key],
                                    'wb_17'=>$params['wb_17'][$key],                                    
                                    'reported_result'=>$params['reported_result'][$key],
                                    'updated_by' => $authNameSpace->dm_id,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   ), "shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
                
            }

        }
    }
    
    

}

