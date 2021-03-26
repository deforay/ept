<?php

class Application_Model_DbTable_ShipmentParticipantMap extends Zend_Db_Table_Abstract
{

    protected $_name = 'shipment_participant_map';
    protected $_primary = 'map_id';

    public function shipItNow($params)
    {
        try {
            $commonServices = new Application_Service_Common();
            $general = new Pt_Commons_General();
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $this->delete('shipment_id=' . $params['shipmentId']);
            $params['selectedForEnrollment'] = json_decode($params['selectedForEnrollment'], true);
            foreach ($params['selectedForEnrollment'] as $participant) {
                $data = array(
                    'shipment_id' => $params['shipmentId'],
                    'participant_id' => $participant,
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    "created_on_admin" => new Zend_Db_Expr('now()')
                );
                $this->insert($data);
            }
            
            $shipmentDb = new Application_Model_DbTable_Shipments();
            $shipmentDb->updateShipmentStatus($params['shipmentId'], 'ready');
            
            $shipmentRow = $shipmentDb->fetchRow('shipment_id=' . $params['shipmentId']);
            
            $resultSet = $shipmentDb->fetchAll($shipmentDb->select()->where("status = 'pending' AND distribution_id = " . $shipmentRow['distribution_id']));
            
            if (count($resultSet) == 0) {
                $distroService = new Application_Service_Distribution();
                $distroService->updateDistributionStatus($shipmentRow['distribution_id'], 'configured');
            }
            /* New shipment push notification start */
            // $pushContent = $commonServices->getPushTemplateByPurpose('new-shipment');
            // $participantDb = new Application_Model_DbTable_Participants();
            // $participantRow = $participantDb->fetchRow('participant_id=' . $participant);
            // // Zend_Debug::dump($participantRow);die;
            // $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
            // $replace = array($participantRow['first_name'] . ' ' . $participantRow['last_name'], $shipmentRow['shipment_code'], $shipmentRow['scheme_type'], '', '');
            // $title = str_replace($search, $replace, $pushContent['notify_title']);
            // $msgBody = str_replace($search, $replace, $pushContent['notify_body']);
            // if (isset($pushContent['data_msg']) && $pushContent['data_msg'] != '') {
            //     $dataMsg = str_replace($search, $replace, $pushContent['data_msg']);
            // } else {
            //     $dataMsg = '';
            // }
            // $commonServices->insertPushNotification($title, $msgBody, $dataMsg, $pushContent['icon'], $shipmentRow['shipment_id'], 'new-shipment', 'shipment');
            // /* New shipment push notification end */

            // /* New shipment mail alert start */
            // $notParticipatedMailContent = $commonServices->getEmailTemplate('new_shipment');
            // $subQuery = $this->select()
            //     ->from(array('s' => 'shipment'), array('shipment_code', 'scheme_type'))
            //     ->join(array('spm' => 'shipment_participant_map'), 'spm.shipment_id=s.shipment_id', array('map_id'))
            //     ->join(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=spm.participant_id', array('dm_id'))
            //     ->join(array('p' => 'participant'), 'p.participant_id=pmm.participant_id', array('participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
            //     ->join(array('dm' => 'data_manager'), 'pmm.dm_id=dm.dm_id', array('primary_email', 'push_notify_token'))
            //     ->where("s.shipment_id=?", $shipmentRow['shipment_id'])
            //     ->group('dm.dm_id')->setIntegrityCheck(false);
            // // echo $subQuery;die;
            // $subResult = $this->fetchAll($subQuery);
            // foreach ($subResult as $dm) {
            //     $search = array('##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',);
            //     $replace = array($dm['participantName'], $dm['shipment_code'], $dm['scheme_type'], '', '');
            //     $content = $notParticipatedMailContent['mail_content'];
            //     $message = str_replace($search, $replace, $content);
            //     $subject = $notParticipatedMailContent['mail_subject'];
            //     $fromEmail = $notParticipatedMailContent['mail_from'];
            //     $fromFullName = $notParticipatedMailContent['from_name'];
            //     $toEmail = $dm['primary_email'];
            //     $cc = $notParticipatedMailContent['mail_cc'];
            //     $bcc = $notParticipatedMailContent['mail_bcc'];
            //     $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
            // }
            /* New shipment mail alert end */
            $this->getAdapter()->commit();
            return true;
        } catch (Exception $e) {
            $this->getAdapter()->rollBack();
            die($e->getMessage());
            error_log($e->getTraceAsString());
            return false;
        }
    }

    public function updateShipment($params, $shipmentMapId, $lastDate)
    {
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

        // only if current date is LATER than last date we make status = 2
        $date = new DateTime();
        $lastDate = new DateTime($lastDate);

        // only if current date is LATER than last date we make status = 2
        if ($date > $lastDate) {
            $params['evaluation_status'][3] = 2;
        } else {
            $params['evaluation_status'][3] = 1;
        }
        $params['mode_of_response'] = 'web';
        return $this->update($params, "map_id = " . $shipmentMapId);
    }

    public function removeShipmentMapDetails($params, $mapId)
    {
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

    public function isShipmentEditable($shipmentId, $participantId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipment = $db->fetchRow($db->select()->from(array('s' => 'shipment'))
            ->where("s.shipment_id = ?", $shipmentId));
        if ($shipment["status"] == 'finalized' || $shipment["response_switch"] == 'off') {
            return false;
        } else {
            return true;
        }
    }

    public function addEnrollementDetails($params)
    {
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $size = count($params['participants']);
            for ($i = 0; $i < $size; $i++) {
                $data = array(
                    'shipment_id' => base64_decode($params['shipmentId']),
                    'participant_id' => base64_decode($params['participants'][$i]),
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    "created_on_admin" => new Zend_Db_Expr('now()')
                );
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

    public function enrollShipmentParticipant($shipmentId, $participantId)
    {
        $insertCount = 0;
        try {
            $this->getAdapter()->beginTransaction();
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $participantId = explode(',', $participantId);
            $count = count($participantId);
            for ($i = 0; $i < $count; $i++) {
                $data = array(
                    'shipment_id' => $shipmentId,
                    'participant_id' => base64_decode($participantId[$i]),
                    'evaluation_status' => '19901190',
                    'created_by_admin' => $authNameSpace->admin_id,
                    "created_on_admin" => new Zend_Db_Expr('now()')
                );
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

    public function addQcInfo($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (isset($params['mapId']) && trim($params['mapId']) != "") {
            $participantMapId = explode(',', $params['mapId']);
            $count = count($participantMapId);
            $qcDate = Pt_Commons_General::dateFormat($params['qcDate']);
            for ($i = 0; $i < $count; $i++) {
                if (trim($participantMapId[$i]) != "") {
                    $data = array(
                        'qc_date' => $qcDate,
                        'qc_done_by' => $authNameSpace->dm_id,
                        "qc_created_on" => new Zend_Db_Expr('now()')
                    );
                    $result = $this->update($data, "map_id = " . $participantMapId[$i]);
                }
            }
            return $result;
        }
    }

    public function fetchParticipantShipments($pId)
    {
        $query = $this->getAdapter()->select()->distinct()->from(array('sp' => 'shipment_participant_map'), array('shipment_id'))
            ->join(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id', array('scheme_type', 'year' => "YEAR(shipment_date)"))
            ->join(array('p' => 'participant'), 'p.participant_id=sp.participant_id', array('p.unique_identifier', 'p.participant_id'))
            ->where("sp.participant_id = ?", $pId)
            ->where("s.scheme_type ='vl' OR s.scheme_type='eid'")
            ->where("sp.shipment_test_date!='0000-00-00'")
            ->group('year')
            ->group('s.scheme_type');
        return $this->getAdapter()->fetchAll($query);
    }

    public function updateShipmentByAPI($data, $dm, $params)
    {
        $row = $this->fetchRow("map_id = " . $params['mapId']);
        if ($row != "") {
            if (trim($row['created_on_user']) == "" || $row['created_on_user'] == NULL) {
                $this->update(array('created_on_user' => new Zend_Db_Expr('now()')), "map_id = " . $params['mapId']);
            }
        }
        $data['shipment_id']        = $params['shipmentId'];
        $data['participant_id']     = $params['participantId'];
        $data['evaluation_status']  = $params['evaluationStatus'];
        $data['updated_by_user']    = $dm['dm_id'];
        if ($params['schemeType'] == 'dts') {
            $lastDate   = $params['dtsData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'vl') {
            $lastDate   = $params['vlData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'eid') {
            $lastDate   = $params['eidData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'recency') {
            $lastDate   = $params['recencyData']->Section2->data->resultDueDate;
        }
        if ($params['schemeType'] == 'covid19') {
            $lastDate   = $params['covid19Data']->Section2->data->resultDueDate;
        }

        // changing evaluation status 3rd character to 1 = responded
        $data['evaluation_status'][2] = 1;

        // changing evaluation status 5th character to 1 = via web user
        $data['evaluation_status'][4] = 1;

        // changing evaluation status 4th character to 1 = timely response or 2 = delayed response
        $date = new DateTime();
        $lastDate = new DateTime($lastDate);

        // only if current date is LATER than last date we make status = 2
        if ($date > $lastDate) {
            $data['evaluation_status'][3] = 2;
        } else {
            $data['evaluation_status'][3] = 1;
        }
        $data['synced'] = 'yes';
        $data['synced_on'] = new Zend_Db_Expr('now()');
        $data['mode_of_response'] = 'app';
        // Zend_Debug::dump($data);die;

        return $this->update($data, "map_id = " . $params['mapId']);
    }
}
