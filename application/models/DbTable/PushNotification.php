<?php

class Application_Model_DbTable_PushNotification extends Zend_Db_Table_Abstract
{
    protected $_name = 'push_notification';
    
    public function fetchAllPushNotify($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */
	
        $aColumns = array("notification_json");
        $orderColumns = array('notification_json');
	
        /*
         * Paging
         */
        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }
	
        /*
         * Ordering
         */
        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }
	    
            $sOrder = substr_replace($sOrder, "", -2);
        }
	
        /*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
         */
        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);
		
                for ($i = 0; $i < $colSize; $i++) {
                    if($aColumns[$i] == "" || $aColumns[$i] == null){
                        continue;
                    }
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }
	
        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }
	
        /*
         * SQL queries
         * Get data to display
         */
		
		$dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(array($this->_name));
				
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
	
        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }
	
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
		
        $rResult = $dbAdapter->fetchAll($sQuery);
	
        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);
	
        /* Total data set length */
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);
		
        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );
        
        // Zend_Debug::dump($rResult);
        foreach ($rResult as $aRow) {
            $notify = json_decode($aRow['notification_json']);
            // Zend_Debug::dump($notify);die;
            if($aRow['push_status'] == 'refuse'){
                $back = 'danger';
            } else if($aRow['push_status'] == 'pending'){
                $back = 'warning';
            } else if($aRow['push_status'] == 'send'){
                $back = 'success';
            } else if($aRow['push_status'] == 'not-send'){
                $back = 'muted';
            }
            $row = array();
            $row[] = '<div class="panel panel-'.$back.'">
                        <div class="panel-heading" style=" padding: 3px; ">
                        <div class="row">
                            <div class="col-xs-5">
                                <h2> Notify Status :'.ucwords($aRow['push_status']).'</h2>
                            </div>
                            <div class="col-xs-7 text-right">
                            <p class="announcement-heading">Identify Type: '.ucwords($aRow['identify_type']).'</p>
                            <p class="announcement-text">Notification Type : '.ucwords($aRow['identify_type']).'</p>
                            <p class="announcement-text"><h3>Title : '.ucwords($notify->title).'</h3></p>
                            </div>
                        </div>
                        </div>
                        <div class="panel-footer announcement-bottom" style=" text-align: left; ">
                            <div class="row">
                            <div class="col-xs-10" style="color:#2c3e50;font-size: larger;">
                            '.$notify->body.'
                            </div>
                        </div>
                    </div>';
            if($aRow['push_status'] == 'refuse'){
                $approve = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="approveNotify(\''.base64_encode($aRow['id']).'\')"><span><i class="icon-check"></i> Approve</span></a>';  
            } else{
                $approve = '';
            }
            $edit = '<a style=" margin-left: 10px; " class="btn btn-info btn-xs" href="/admin/push-notification/edit/id/'.$aRow['id'].'"><span><i class="icon-check"></i> Edit</span></a>';
            $row[] = $approve . $edit;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    
    public function approveNotify($params){
        $authNameSpace = new Zend_Session_Namespace('administrators');
        return $this->update(array('push_status'=>'pending','approved_by' => $authNameSpace->admin_id, 'approved_on' => new Zend_Db_Expr('now()')),"id = ".base64_decode($params['notifyId']));
    }
    
    public function insertPushNotificationDetails($title,$msgBody,$dataMsg,$icon,$shipmentId,$identifyType,$notificationType,$announcementId){
        $notification = array(
            "title" =>  $title,
            "body"  =>  $msgBody,
            "icon"  =>  $icon
        );
        $data = array(
            'notification_json' => json_encode($notification),
            'data_json'         => $dataMsg,
            'push_status'       => 'pending',
            'token_identify_id' => $shipmentId,
            'identify_type'     => $identifyType,
            'notification_type' => $notificationType
        );
        if(isset($announcementId) && $announcementId != ''){
            $data['announcement_id'] = $announcementId;
        }
        $rowSet = $this->fetchAll($this->select()->from($this->_name)
        ->where('push_status = "pending"')
        ->where('token_identify_id = "'.$shipmentId.'"')
        ->where('identify_type = "'.$identifyType.'"')
        ->where('notification_type = "'.$notificationType.'"')
        )->toArray();
        // Zend_Debug::dump($rowSet);die;
        if(count($rowSet) == 0){
            $data['created_on'] = new Zend_Db_Expr('now()');
            return $this->insert($data);
        }
    }

    public function fetchPushNotificationDetailsById($id)
    {
        return $this->fetchRow($this->select()->from($this->_name)->where('id ='.$id));
    }
    
    public function fetchNotificationByAPI($params)
    {
        $response = array();
        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        /* Check the app versions & parameters */
        if (!isset($params['appVersion'])) {
            return array('status' =>'version-failed','message'=>'App version is not updated. Kindly go to the play store and update the app');
        }
        if (!isset($params['authToken'])) {
            return array('status' =>'auth-fail','message'=>'Something went wrong. Please log in again');
        }
        
        /* Validate new auth token and app-version */
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        if ($aResult == 'app-version-failed') {
            return array('status' =>'version-failed','message'=>'App version is not updated. Kindly go to the play store and update the app');
        }
        if(!$aResult){
            return array('status' =>'auth-fail','message'=>'Something went wrong. Please log in again');
        }
        $notification = $this->fetchAll($this->select()->from($this->_name)->order('created_on DESC'))->toArray();
        if(isset($notification) && count($notification) > 0){
            foreach($notification as $notify){
                if($notify['notification_type'] == 'announcement'){
                    $subQuery = $dbAdapter->select()
                    ->from(array('s' => 'shipment'),array('shipment_id', 'shipment_code'))
                    ->join(array('spm'=>'shipment_participant_map'),'spm.shipment_id=s.shipment_id',array('map_id'))
                    ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=spm.participant_id',array('dm_id'))
                    ->join(array('dm'=>'data_manager'),'pmm.dm_id=dm.dm_id',array('primary_email', 'push_notify_token', 'marked_push_notify'))
                    ->where("dm.auth_token=?", $params['authToken'])
                    ->where("dm.dm_id IN (".$notify['token_identify_id'].")")
                    ->group('dm.dm_id');
                } else{
                    $subQuery = $dbAdapter->select()
                    ->from(array('s' => 'shipment'),array('shipment_id', 'shipment_code'))
                    ->join(array('spm'=>'shipment_participant_map'),'spm.shipment_id=s.shipment_id',array('map_id'))
                    ->join(array('pmm'=>'participant_manager_map'),'pmm.participant_id=spm.participant_id',array('dm_id'))
                    ->join(array('dm'=>'data_manager'),'pmm.dm_id=dm.dm_id',array('primary_email', 'push_notify_token', 'marked_push_notify'))
                    ->where("s.shipment_id=?", $notify['token_identify_id'])
                    ->where("dm.auth_token=?", $params['authToken'])
                    ->group('dm.dm_id');
                }
                // die($subQuery);
                $subResult = $dbAdapter->fetchRow($subQuery);
                // Zend_Debug::dump($subResult);die;
                if($subResult){
                    if(isset($subResult['marked_push_notify']) && $subResult['marked_push_notify'] != ''){
                        $notifyArray = explode(",", $subResult['marked_push_notify']);
                        foreach($notifyArray as $notifyId){
                            $notifyList[] = $notifyId;
                        }
                    } else{
                        $notifyList = array();
                    }
                    $response['status'] =  'success';
                    $response['data'][] =  array(
                        'notification'      => json_decode($notify['notification_json']),
                        'createdOn'         => date('d-M-Y h:i:s a',strtotime($notify['created_on'])),
                        'notificationType'  => $notify['notification_type'],
                        'notifyId'          => $notify['id'],
                        'markAsRead'        => (in_array($notify['id'],$notifyList))?true:false,
                    );
                }
            }
        } else{
            $response['status'] =  'fail';
            $response['message'] =  'No notification found'; 
        }
        return $response;
    }
}