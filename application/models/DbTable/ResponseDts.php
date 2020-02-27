<?php

class Application_Model_DbTable_ResponseDts extends Zend_Db_Table_Abstract
{

    protected $_name = 'response_result_dts';
    protected $_primary = array('shipment_map_id','sample_id');
    
    public function updateResults($params){
        $sampleIds = $params['sampleId'];
        
        foreach($sampleIds as $key => $sampleId){
            //die("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId);
            $res = $this->fetchRow("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId );
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            if(isset($params['test_kit_name_1']) && trim($params['test_kit_name_1'])=='other'){
                $otherTestkitId1=$testkitsDb->addTestkitInParticipant($params['test_kit_other_name_update_1'],$params['test_kit_other_name_1'],'dts');
                $params['test_kit_name_1']=$otherTestkitId1;
            }
            
            if(isset($params['test_kit_name_2']) && trim($params['test_kit_name_2'])=='other'){
                $otherTestkitId2=$testkitsDb->addTestkitInParticipant($params['test_kit_other_name_update_2'],$params['test_kit_other_name_2'],'dts');
                $params['test_kit_name_2']=$otherTestkitId2;
            }
            
            if(isset($params['test_kit_name_3']) && trim($params['test_kit_name_3'])=='other'){
                $otherTestkitId3=$testkitsDb->addTestkitInParticipant($params['test_kit_other_name_update_3'],$params['test_kit_other_name_3'],'dts');
                $params['test_kit_name_3']=$otherTestkitId3;
            }
            
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
    public function removeShipmentResults($mapId) {

        $authNameSpace = new Zend_Session_Namespace('datamanagers');
       $data=array(
                                   'test_kit_name_1'=>'',
                                    'lot_no_1'=>'',
                                    'exp_date_1'=>'',
                                    'test_result_1'=>'',
                                    'test_kit_name_2'=>'',
                                    'lot_no_2'=>'',
                                    'exp_date_2'=>'',
                                    'test_result_2'=>'',
                                    'test_kit_name_3'=>'',
                                    'lot_no_3'=>'',
                                    'exp_date_3'=>'',
                                    'test_result_3'=>'',
                                    'reported_result'=>'',
                                    'updated_by' => $authNameSpace->dm_id,
                                    'updated_on' => new Zend_Db_Expr('now()')
                                   );
       
       return $this->update($data, "shipment_map_id = " . $mapId);
    }
    
    public function updateResultsByAPI($params,$dm,$allSamples){
        
        $sampleIds = $params['dtsData']->Heading4->sampleDetailArray->samples;
        foreach($sampleIds as $sampleId => $sampleArray){
            //die("shipment_map_id = ".$params['smid'] . " and sample_id = ".$sampleId);
            $res = $this->fetchRow("shipment_map_id = ".$params['mapId'] . " and sample_id = ".$sampleId );
            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            if(isset($params['dtsData']->Heading3->kitValue[0]) && trim($params['dtsData']->Heading3->kitValue[0])=='other'){
                $otherTestkitId1=$testkitsDb->addTestkitInParticipant($allSamples[0]["test_kit_name_1"],$params['dtsData']->Heading3->kitOther[0],'dts');
                $params['test_kit_name_1'] = $otherTestkitId1;
            }else{
                $params['test_kit_name_1'] = $params['dtsData']->Heading3->kitValue[0];
            }
            if(isset($params['dtsData']->Heading3->kitValue[1]) && trim($params['dtsData']->Heading3->kitValue[1])=='other'){
                $otherTestkitId1=$testkitsDb->addTestkitInParticipant($allSamples[0]["test_kit_name_2"],$params['dtsData']->Heading3->kitOther[1],'dts');
                $params['test_kit_name_2'] = $otherTestkitId1;
            }else{
                $params['test_kit_name_2'] = $params['dtsData']->Heading3->kitValue[1];
            }
            if(isset($params['dtsData']->Heading3->kitValue[2]) && trim($params['dtsData']->Heading3->kitValue[2])=='other'){
                $otherTestkitId1=$testkitsDb->addTestkitInParticipant($allSamples[0]["test_kit_name_3"],$params['dtsData']->Heading3->kitOther[2],'dts');
                $params['test_kit_name_3'] = $otherTestkitId1;
            }else{
                $params['test_kit_name_3'] = $params['dtsData']->Heading3->kitValue[2];
            }
            if($res == null || count($res) == 0){
                return $this->insert(array(
                    'shipment_map_id'   => $params['mapId'],
                    'sample_id'         => $sampleId,
                    'test_kit_name_1'   => $params['test_kit_name_1'],
                    'lot_no_1'          => $params['dtsData']->Heading3->lot[0],
                    'exp_date_1'        => date('Y-m-d',strtotime($params['dtsData']->Heading3->expDate[0])),
                    'test_result_1'     => $sampleArray->result_1,
                    'test_kit_name_2'   => $params['test_kit_name_2'],
                    'lot_no_2'          => $params['dtsData']->Heading3->lot[1],
                    'exp_date_2'        => date('Y-m-d',strtotime($params['dtsData']->Heading3->expDate[1])),
                    'test_result_2'     => $sampleArray->result_2,
                    'test_kit_name_3'   => $params['test_kit_name_3'],
                    'lot_no_3'          => $params['dtsData']->Heading3->lot[2],
                    'exp_date_3'        => date('Y-m-d',strtotime($params['dtsData']->Heading3->expDate[2])),
                    'test_result_3'     => $sampleArray->result_3,
                    'reported_result'   => $sampleArray->final_result,
                    'created_by'        => $dm['dm_id'],
                    'created_on'        => ($params['createdOn'] != "")?date('Y-m-d H:i:s',strtotime($params['createdOn'])):new Zend_Db_Expr('now()')
                ));
            }else{
                return $this->update(array(
                    'test_kit_name_1'   => $params['test_kit_name_1'],
                    'lot_no_1'          => $params['dtsData']->Heading3->lot[0],
                    'exp_date_1'        => date('Y-m-d',strtotime($params['dtsData']->Heading3->expDate[0])),
                    'test_result_1'     => $sampleArray->result_1,
                    'test_kit_name_2'   => $params['test_kit_name_2'],
                    'lot_no_2'          => $params['dtsData']->Heading3->lot[1],
                    'exp_date_2'        => date('Y-m-d',strtotime($params['dtsData']->Heading3->expDate[1])),
                    'test_result_2'     => $sampleArray->result_2,
                    'test_kit_name_3'   => $params['test_kit_name_3'],
                    'lot_no_3'          => $params['dtsData']->Heading3->lot[2],
                    'exp_date_3'        => date('Y-m-d',strtotime($params['dtsData']->Heading3->expDate[2])),
                    'test_result_3'     => $sampleArray->result_3,
                    'reported_result'   => $sampleArray->final_result,
                    'updated_by'        => $dm['dm_id'],
                    'updated_on'        => ($params['updatedOn'] != "")?date('Y-m-d H:i:s',strtotime($params['updatedOn'])):new Zend_Db_Expr('now()')
                ), "shipment_map_id = ".$params['mapId'] . " and sample_id = ".$sampleId );
            }
        }
    }
}

