<?php

class Application_Model_DbTable_ShipmentParticipantMap extends Zend_Db_Table_Abstract {

    protected $_name = 'shipment_participant_map';
    protected $_primary = 'map_id';

    public function shipItNow($params) {
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $this->delete('shipment_id=' . $params['shipmentId']);
            foreach ($params['participants'] as $participant) {


                //$row = $this->fetchRow('shipment_id='.$params['shipmentId'] .' and participant_id='.$participant);
                //if($row != null && $row != ""){
                //    echo('shipment_id='.$params['shipmentId'] .' and participant_id='.$participant);
                //    $data = array('shipment_id'=>$params['shipmentId'],
                //                  'participant_id'=>$participant,
                //                  'updated_by_admin' => $authNameSpace->admin_id,
                //                  "updated_on_admin"=>new Zend_Db_Expr('now()'));
                //    $this->update($data,'shipment_id='.$params['shipmentId'] .' and participant_id='.$participant);                    
                //}else{
                $data = array('shipment_id' => $params['shipmentId'],
                    'participant_id' => $participant,
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    "created_on_admin" => new Zend_Db_Expr('now()'));
                $this->insert($data);
                //}
            }

            $shipmentDb = new Application_Model_DbTable_Shipments();
            $shipmentDb->updateShipmentStatus($params['shipmentId'], 'ready');

            $shipmentRow = $shipmentDb->fetchRow('shipment_id=' . $params['shipmentId']);

            $resultSet = $shipmentDb->fetchAll($shipmentDb->select()->where("status = 'pending' AND distribution_id = " . $shipmentRow['distribution_id']));

            if (count($resultSet) == 0) {
                $distroService = new Application_Service_Distribution();
                $distroService->updateDistributionStatus($shipmentRow['distribution_id'], 'configured');
            }

            $this->getAdapter()->commit();
            return true;
        } catch (Exception $e) {
            $this->getAdapter()->rollBack();
            die($e->getMessage());
            error_log($e->getTraceAsString());
            return false;
        }
    }

    public function updateShipment($params, $shipmentMapId, $lastDate) {
        $row = $this->fetchRow("map_id = " . $shipmentMapId);
        if ($row != "") {
            if (trim($row['created_on_user']) == "" || $row['created_on_user'] == NULL) {
                $this->update(array('created_on_user' => new Zend_Db_Expr('now()')), "map_id = " . $shipmentMapId);
            }
        }

        $params['evaluation_status'] = $row['evaluation_status'];

        // changing evaluation status 3rd character to 1 = responded
        $params['evaluation_status'][2] = 1;

        // changing evaluation status 5th character to 1 = via web user
        $params['evaluation_status'][4] = 1;

        // changing evaluation status 4th character to 1 = timely response or 2 = delayed response

        $date = new Zend_Date();
        $lastDate = new Zend_Date($lastDate, Zend_Date::ISO_8601);
        // only if current date is LATER than last date we make status = 2
        if ($date->compare($lastDate) == 1) {
            $params['evaluation_status'][3] = 2;
        } else {
            $params['evaluation_status'][3] = 1;
        }

        return $this->update($params, "map_id = " . $shipmentMapId);
    }

    public function removeShipmentMapDetails($params, $mapId) {
        $row = $this->fetchRow("map_id = " . $mapId);
        if ($row != "") {
            if (trim($row['created_on_user']) == "" || $row['created_on_user'] == NULL) {
                $this->update(array('created_on_user' => new Zend_Db_Expr('now()')), "map_id = " . $mapId);
            }
        }
        $params['evaluation_status'] = $row['evaluation_status'];
        // changing evaluation status 3rd character to 9 = not responded
        $params['evaluation_status'][2] = 9;

        // changing evaluation status 5th character to 1 = via web user
        $params['evaluation_status'][4] = 1;

        // changing evaluation status 4th character to 0 = no response
        $params['evaluation_status'][3] = 0;

        return $this->update($params, "map_id = " . $mapId);
    }

    public function isShipmentEditable($shipmentId, $participantId) {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $row = $this->fetchRow("shipment_id = " . $shipmentId . " AND participant_id = " . $participantId);
        $shipment = $db->fetchRow($db->select()->from(array('s' => 'shipment'))
                        ->where("s.shipment_id = ?", $shipmentId));
        $responseAfterFinalised = Application_Service_Common::getConfig('response_after_evaluate');
        $date = new Zend_Date();
        $lastDate = new Zend_Date($shipment["lastdate_response"], Zend_Date::ISO_8601);
        
        if($shipment["status"] == 'finalized' || $shipment["response_switch"] == 'off'){
            return false;
        }else{
            return true;
        }
        
        //if ($responseAfterFinalised == 'yes') {
        //    // only if current date is lesser than last date
        //    if ($date->compare($lastDate,Zend_Date::DATES) <= 0 || $shipment["status"] == 'finalized') {
        //        return true;
        //    } else {
        //        return false;
        //    }
        //} else {
        //    if ($date->compare($lastDate,Zend_Date::DATES) <= 0) {
        //        return true;
        //    } else {
        //        return false;
        //    }
        //}

        //$now= date("Y-m-d");
        //$todaydate= strtotime($now);
        //$lastResponseDate = strtotime($shipment["lastdate_response"]);
        //$dateDifference = $lastResponseDate - $todaydate;
        //$day=floor($dateDifference/3600/24);
        //$canEdit =  substr($row['evaluation_status'],2 ,1); // getting the 3rd character
        // if($canEdit == 9){
        //if($day<0){
        //   return false; 
        //}else{
        //   return true; 
        //}
        //  }else{
        //      return false;
        //   }
    }

    public function addEnrollementDetails($params) {
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $size = count($params['participants']);
            for ($i = 0; $i < $size; $i++) {
                $data = array('shipment_id' => base64_decode($params['shipmentId']),
                    'participant_id' => base64_decode($params['participants'][$i]),
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    "created_on_admin" => new Zend_Db_Expr('now()'));
                $this->insert($data);
            }
            $this->getAdapter()->commit();
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = "Participants added successfully";
        } catch (Exception $e) {
            $this->getAdapter()->rollBack();
            die($e->getMessage());
            error_log($e->getTraceAsString());
            return false;
        }
    }

    public function enrollShipmentParticipant($shipmentId, $participantId) {
        $insertCount = 0;
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $participantId = explode(',', $participantId);
            $count = count($participantId);
            for ($i = 0; $i < $count; $i++) {
                $data = array('shipment_id' => $shipmentId,
                    'participant_id' => base64_decode($participantId[$i]),
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    "created_on_admin" => new Zend_Db_Expr('now()'));
                   $insertCount = $this->insert($data);
            }
            $this->getAdapter()->commit();
            return $insertCount;
        } catch (Exception $e) {
            $this->getAdapter()->rollBack();
            die($e->getMessage());
            error_log($e->getTraceAsString());
            return 0;
        }
    }

}
