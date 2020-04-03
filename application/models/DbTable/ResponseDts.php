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
        $file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
        $config = new Zend_Config_Ini($file, APPLICATION_ENV);
        $testThreeOptional = false;
        if(isset($config->evaluation->dts->dtsOptionalTest3) && $config->evaluation->dts->dtsOptionalTest3 == 'yes'){
            $testThreeOptional = true;
        }
        $sampleIds = $params['dtsData']->Heading4->data->samples->id;
        foreach($sampleIds as $key => $sampleId){
            $res = $this->fetchRow("shipment_map_id = ".$params['mapId'] . " and sample_id = ".$sampleId );

            $testkitsDb = new Application_Model_DbTable_TestkitnameDts();
            if(isset($params['dtsData']->Heading3->data->kitValue[0]) && trim($params['dtsData']->Heading3->data->kitValue[0])=='other'){
                $otherTestkitId1=$testkitsDb->addTestkitInParticipant($allSamples[0]["test_kit_name_1"],$params['dtsData']->Heading3->data->kitOther[0],'dts');
                $params['test_kit_name_1'] = $otherTestkitId1;
            }else{
                $params['test_kit_name_1'] = (isset($params['dtsData']->Heading3->data->kitValue[0]) && $params['dtsData']->Heading3->data->kitValue[0] != '')?$params['dtsData']->Heading3->data->kitValue[0]:'';
            }
            if(isset($params['dtsData']->Heading3->data->kitValue[1]) && trim($params['dtsData']->Heading3->data->kitValue[1])=='other'){
                $otherTestkitId2=$testkitsDb->addTestkitInParticipant($allSamples[0]["test_kit_name_2"],$params['dtsData']->Heading3->data->kitOther[1],'dts');
                $params['test_kit_name_2'] = $otherTestkitId2;
            }else{
                $params['test_kit_name_2'] = (isset($params['dtsData']->Heading3->data->kitValue[1]) && $params['dtsData']->Heading3->data->kitValue[1] != '')?$params['dtsData']->Heading3->data->kitValue[1]:'';
            }
            if(isset($params['dtsData']->Heading3->data->kitValue[2]) && trim($params['dtsData']->Heading3->data->kitValue[2])=='other'){
                $otherTestkitId3=$testkitsDb->addTestkitInParticipant($allSamples[0]["test_kit_name_3"],$params['dtsData']->Heading3->data->kitOther[2],'dts');
                $params['test_kit_name_3'] = $otherTestkitId3;
            }else{
                $params['test_kit_name_3'] = (isset($params['dtsData']->Heading3->data->kitValue[2]) && $params['dtsData']->Heading3->data->kitValue[2] != '')?$params['dtsData']->Heading3->data->kitValue[2]:'';
            }
            $result3 = (isset($params['dtsData']->Heading4->data->samples->result3[$key]) && $params['dtsData']->Heading4->data->samples->result3[$key] !='')?$params['dtsData']->Heading4->data->samples->result3[$key]:'';
            if($testThreeOptional){
                $params['test_kit_name_3'] = '';
                $result3 = '';
            }
            // Zend_Debug::dump($params);die;
            if($res == null || count($res) == 0){
                $this->insert(array(
                    'shipment_map_id'   => $params['mapId'],
                    'sample_id'         => $sampleId,
                    'test_kit_name_1'   => $params['test_kit_name_1'],
                    'lot_no_1'          => (isset($params['dtsData']->Heading3->data->lot[0]) && $params['dtsData']->Heading3->data->lot[0] !='')?$params['dtsData']->Heading3->data->lot[0]:'',
                    'exp_date_1'        => (isset($params['dtsData']->Heading3->data->expDate[0]) && $params['dtsData']->Heading3->data->expDate[0] != '')?date('Y-m-d',strtotime($params['dtsData']->Heading3->data->expDate[0])):'',
                    'test_result_1'     => (isset($params['dtsData']->Heading4->data->samples->result1[$key]) && $params['dtsData']->Heading4->data->samples->result1[$key] != '')?$params['dtsData']->Heading4->data->samples->result1[$key]:'',
                    'test_kit_name_2'   => $params['test_kit_name_2'],
                    'lot_no_2'          => (isset($params['dtsData']->Heading3->data->lot[1]) && $params['dtsData']->Heading3->data->lot[1] != '')?$params['dtsData']->Heading3->data->lot[1]:'',
                    'exp_date_2'        => (isset($params['dtsData']->Heading3->data->expDate[1]) && $params['dtsData']->Heading3->data->expDate[1] != '')?date('Y-m-d',strtotime($params['dtsData']->Heading3->data->expDate[1])):'',
                    'test_result_2'     => (isset($params['dtsData']->Heading4->data->samples->result2[$key]) && $params['dtsData']->Heading4->data->samples->result2[$key] != '')?$params['dtsData']->Heading4->data->samples->result2[$key]:'',
                    'test_kit_name_3'   => $params['test_kit_name_3'],
                    'lot_no_3'          => (isset($params['dtsData']->Heading3->data->lot[2]) && $params['dtsData']->Heading3->data->lot[2] != '' && !$testThreeOptional)?$params['dtsData']->Heading3->data->lot[2]:'',
                    'exp_date_3'        => (isset($params['dtsData']->Heading3->data->expDate[2]) && $params['dtsData']->Heading3->data->expDate[2] != '' && !$testThreeOptional)?date('Y-m-d',strtotime($params['dtsData']->Heading3->data->expDate[2])):'',
                    'test_result_3'     => $result3,
                    'reported_result'   => (isset($params['dtsData']->Heading4->data->samples->finalResult[$key]) && $params['dtsData']->Heading4->data->samples->finalResult[$key] != '')?$params['dtsData']->Heading4->data->samples->finalResult[$key]:'',
                    'created_by'        => $dm['dm_id'],
                    'created_on'        => ($params['createdOn'] != "")?date('Y-m-d H:i:s',strtotime($params['createdOn'])):new Zend_Db_Expr('now()')
                ));
            }else{
                $this->update(array(
                    'test_kit_name_1'   => $params['test_kit_name_1'],
                    'lot_no_1'          => (isset($params['dtsData']->Heading3->data->lot[0]) && $params['dtsData']->Heading3->data->lot[0] !='')?$params['dtsData']->Heading3->data->lot[0]:'',
                    'exp_date_1'        => (isset($params['dtsData']->Heading3->data->expDate[0]) && $params['dtsData']->Heading3->data->expDate[0] != '')?date('Y-m-d',strtotime($params['dtsData']->Heading3->data->expDate[0])):'',
                    'test_result_1'     => (isset($params['dtsData']->Heading4->data->samples->result1[$key]) && $params['dtsData']->Heading4->data->samples->result1[$key] != '')?$params['dtsData']->Heading4->data->samples->result1[$key]:'',
                    'test_kit_name_2'   => $params['test_kit_name_2'],
                    'lot_no_2'          => (isset($params['dtsData']->Heading3->data->lot[1]) && $params['dtsData']->Heading3->data->lot[1] != '')?$params['dtsData']->Heading3->data->lot[1]:'',
                    'exp_date_2'        => (isset($params['dtsData']->Heading3->data->expDate[1]) && $params['dtsData']->Heading3->data->expDate[1] != '')?date('Y-m-d',strtotime($params['dtsData']->Heading3->data->expDate[1])):'',
                    'test_result_2'     => (isset($params['dtsData']->Heading4->data->samples->result2[$key]) && $params['dtsData']->Heading4->data->samples->result2[$key] != '')?$params['dtsData']->Heading4->data->samples->result2[$key]:'',
                    'test_kit_name_3'   => $params['test_kit_name_3'],
                    'lot_no_3'          => (isset($params['dtsData']->Heading3->data->lot[2]) && $params['dtsData']->Heading3->data->lot[2] != '' && !$testThreeOptional)?$params['dtsData']->Heading3->data->lot[2]:'',
                    'exp_date_3'        => (isset($params['dtsData']->Heading3->data->expDate[2]) && $params['dtsData']->Heading3->data->expDate[2] != '' && !$testThreeOptional)?date('Y-m-d',strtotime($params['dtsData']->Heading3->data->expDate[2])):'',
                    'test_result_3'     => $result3,
                    'reported_result'   => (isset($params['dtsData']->Heading4->data->samples->finalResult[$key]) && $params['dtsData']->Heading4->data->samples->finalResult[$key] != '')?$params['dtsData']->Heading4->data->samples->finalResult[$key]:'',
                    'updated_by'        => $dm['dm_id'],
                    'updated_on'        => ($params['updatedOn'] != "")?date('Y-m-d H:i:s',strtotime($params['updatedOn'])):new Zend_Db_Expr('now()')
                ), "shipment_map_id = ".$params['mapId'] . " and sample_id = ".$sampleId );
            }
        }
        return true;
    }
}

