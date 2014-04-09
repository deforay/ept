<?php

class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{

    protected $_name = 'participant';
    protected $_primary = 'participant_id';


    public function getParticipantsByUserSystemId($userSystemId)
    {
		return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name))
				     ->joinLeft(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id',array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
					 ->where("pmm.dm_id = ?" , $userSystemId)
					 //->where("p.status = 'active'")
				     ->group('p.participant_id'));	
    }

    public function getParticipant($partSysId)
    {
        return $this->getAdapter()->fetchRow($this->getAdapter()->select()->from(array('p' => $this->_name))
				     ->joinLeft(array('pmm'=>'participant_manager_map'),'pmm.participant_id=p.participant_id',array('data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")))
					 ->where("p.participant_id = ?", $partSysId)
				     ->group('p.participant_id'));
    }

    public function getAllParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('unique_identifier','first_name','country', 'mobile', 'phone', 'affiliation', 'email', 'status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "participant_id";
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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
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
	
        $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name),array('p.participant_id','p.unique_identifier','p.country','p.mobile','p.phone','p.affiliation','p.email','p.status','participantName' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")))
					->group("p.participant_id");
	
        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ",$parameters['withStatus']);
        }
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);
	
        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array("p"=>$this->_name), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));

        if (isset($parameters['withStatus']) && $parameters['withStatus'] != "") {
            $sQuery = $sQuery->where("p.status = ? ",$parameters['withStatus']);
        }		
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );


        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['country'];
            $row[] = $aRow['mobile'];
            $row[] = $aRow['phone'];
            $row[] = $aRow['affiliation'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/participants/edit/id/' . $aRow['participant_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function updateParticipant($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

       $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'country' => $params['country'],
            'zip' => $params['zip'],
            'long' => $params['long'],
	    'lat' => $params['lat'],
	    'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'affiliation' => $params['partAff'],
	    'network_tier' => $params['network'],
            'updated_on' => new Zend_Db_Expr('now()')
        );

        if(isset($params['status']) && $params['status'] != "" && $params['status'] != null){
            $data['status'] = $params['status'];
        }

        if(isset($authNameSpace->dm_id) && $authNameSpace->dm_id != ""){
            $data['updated_by'] = $authNameSpace->dm_id;
        }else{
			$authNameSpace = new Zend_Session_Namespace('administrators');
			if(isset($authNameSpace->primary_email) && $authNameSpace->primary_email != ""){
				$data['updated_by'] = $authNameSpace->primary_email;
			}			
		}


        $noOfRows = $this->update($data, "participant_id = " . $params['participantId']);
		
		if(isset($params['dataManager']) && $params['dataManager'] != ""){
			$db = Zend_Db_Table_Abstract::getAdapter();
			$db->delete('participant_manager_map',"participant_id = " . $params['participantId']);
			
			foreach($params['dataManager'] as $dataManager){
				$db->insert('participant_manager_map',array('dm_id'=>$dataManager,'participant_id'=>$params['participantId']));
			}
		}
		
		if(isset($params['scheme']) && $params['scheme'] != ""){
			$enrollDb = new Application_Model_DbTable_Enrollments();
			$enrollDb->enrollParticipantToSchemes($params['participantId'],$params['scheme']);
		}

		return $noOfRows;
    }

    public function addParticipant($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');

        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'country' => $params['country'],
            'zip' => $params['zip'],
            'long' => $params['long'],
	    'lat' => $params['lat'],
	    'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'affiliation' => $params['partAff'],
	    'network_tier' => $params['network'],
            'created_on' => new Zend_Db_Expr('now()'),
	    'created_by' => $authNameSpace->primary_email,
	    'status' => $params['status']
        );
		
		//Zend_Debug::dump($data);die;
        $participantId = $this->insert($data);
		
		$db = Zend_Db_Table_Abstract::getAdapter();
		
		foreach($params['dataManager'] as $dataManager){
			$db->insert('participant_manager_map',array('dm_id'=>$dataManager,'participant_id'=>$participantId));
		}				
		
		return $participantId;
    }

    public function addParticipantForDataManager($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        $data = array(
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'city' => $params['city'],
            'state' => $params['state'],
            'zip' => $params['zip'],
			'country' => $params['country'],
            'long' => $params['long'],
			'lat' => $params['lat'],
			'shipping_address' => $params['shippingAddress'],			
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'status' => 'pending',
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->UserID,
        );
		
	//Zend_Debug::dump($data);die;
	//Zend_Debug::dump($data);die;
        $participantId = $this->insert($data);
		
		
		if(isset($params['scheme']) && $params['scheme'] != ""){
			$enrollDb = new Application_Model_DbTable_Enrollments();
			$enrollDb->enrollParticipantToSchemes($participantId,$params['scheme']);
		}		
		
		$db = Zend_Db_Table_Abstract::getAdapter();
		$db->insert('participant_manager_map',array('dm_id'=>$authNameSpace->dm_id,'participant_id'=>$participantId));
			
		$participantName = $params['pfname']. " " .$params['plname'];
		$dataManager = $authNameSpace->first_name . " " .$authNameSpace->last_name;
		$common = new Application_Service_Common();
		$message = "Hi,<br/>  A new participant ($participantName) was added by $dataManager <br/><small>This is a system generated email. Please do not reply.</small>";
		$toMail = Application_Service_Common::getConfig('admin-email');			
		//$fromName = Application_Service_Common::getConfig('admin-name');			
		$common->sendMail($toMail,null,null,"New Participant Registered  ($participantName)",$message,$fromMail,"ePT Admin");			
	
		return $participantId;
    }
    
    public function fetchAllActiveParticipants(){
	return $this->fetchAll($this->select()->where("status='active'"));
    }
    
    public function getSchemeWiseParticipants($schemeType){
	if($schemeType!="all"){
	    $result=$this->getAdapter()->fetchAll($this->getAdapter()->select()->from(array('p' => $this->_name),array('p.address','p.long','p.lat'))
				->join(array('e'=>'enrollments'),'e.participant_id=p.participant_id')
				->where("e.scheme_id = ?", $schemeType)
				->where("p.status='active'")
				->group('p.participant_id'));
	}else{
	    $result=$this->fetchAll($this->select()->where("status='active'"));
	}
	
	return $result;
    }
    
    public function getEnrolledByShipmentDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('first_name','country','mobile','email','p.status');
	
        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "participant_id";
	
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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
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
	
        
	$sQuery = $this->getAdapter()->select()->from(array('p'=>'participant'))
				->join(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array('sp.map_id','sp.created_on_user','sp.attributes','sp.final_result'))
				->join(array('s'=>'shipment'),'sp.shipment_id=s.shipment_id',array())
				->where("p.status='active'");
				
        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ",$parameters['shipmentId']);
        }
	
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);
	
        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
	
        $sQuery = $this->getAdapter()->select()->from(array("p"=>$this->_name), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
				->join(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array())
				->join(array('s'=>'shipment'),'sp.shipment_id=s.shipment_id',array())
				->where("p.status='active'");
	    
        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ",$parameters['shipmentId']);
        }
	
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );


        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = ucwords($aRow['first_name']." ".$aRow['last_name']);
            $row[] = ucwords($aRow['country']);
            $row[] = $aRow['mobile'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['status']);
	    if(trim($aRow['created_on_user'])=="" && trim($aRow['final_result'])==""){
		$row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\''.base64_encode($aRow['map_id']).'\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
	    }else{
		$row[] = '';
	    }
	
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    
    public function getUnEnrolledByShipments($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('first_name','country','mobile','email','p.status');
	
        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = "participant_id";
	
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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
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
	
        
	$sQuery = $this->getAdapter()->select()->from(array('p'=>'participant'),array('participant_id'))
				->join(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array())
				->join(array('s'=>'shipment'),'sp.shipment_id=s.shipment_id',array())
				->where("p.status='active'");
	
	if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ",$parameters['shipmentId']);
        }
	
	$sQuery = $this->getAdapter()->select()->from(array('p'=>'participant'))->where("p.status='active'")->where("p.participant_id NOT IN ?", $sQuery);
        
        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }
	
        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);
	
        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
	
	
        $sQuery = $this->getAdapter()->select()->from(array("p"=>$this->_name), new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))
				->join(array('sp'=>'shipment_participant_map'),'sp.participant_id=p.participant_id',array())
				->join(array('s'=>'shipment'),'sp.shipment_id=s.shipment_id',array())
				->where("p.status='active'");
	    
        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != "") {
            $sQuery = $sQuery->where("s.shipment_id = ? ",$parameters['shipmentId']);
        }
	
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );


        foreach ($rResult as $aRow) {
            $row = array();
	    $row[] = '<input type="checkbox" class="isRequired" name="participants[]" id="'.$aRow['participant_id'].'" value="'.base64_encode($aRow['participant_id']). '" onclick="checkParticipantName(' . $aRow['participant_id'] . ',this)" title="Select atleast one participant">';
            $row[] = ucwords($aRow['first_name']." ".$aRow['last_name']);
            $row[] = ucwords($aRow['country']);
            $row[] = $aRow['mobile'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['status']);
	
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}

