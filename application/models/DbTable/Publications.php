<?php

class Application_Model_DbTable_Publications extends Zend_Db_Table_Abstract
{

    protected $_name = 'publications';
    protected $_primary = 'publication_id';

    
    public function addSPublicationDetails($params){
        $publicationId = 0;
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
                      'content'=>$params['content'],
		      'added_by' => $authNameSpace->admin_id,
                      'added_on' => new Zend_Db_Expr('now()'),
                      'status' => 'active'
                      );
        $publicationId = $this->insert($data);
        if($publicationId >0){
	    $sortOrder = 1;
	    $publicationQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array('p.sort_order'))
                                     ->order("p.sort_order DESC");
	    $publicationResult = $this->getAdapter()->fetchRow($publicationQuery);
	    if($publicationResult){
		$sortOrder = $publicationResult['sort_order']+1;
	    }
	    $this->update(array('sort_order'=>$sortOrder),"publication_id = ".$publicationId);
            if(isset($_FILES['document']['name']) && trim($_FILES['document']['name'])!= ''){
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document')) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document');
                }
                
                $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['document']['name'], PATHINFO_EXTENSION));
                $fileName ="document".$publicationId.".".$extension;
                if(move_uploaded_file($_FILES["document"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR."document". DIRECTORY_SEPARATOR.$fileName)){
                    $this->update(array('file_name'=>$fileName),"publication_id = ".$publicationId);
                }
            }
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("User " . $authNameSpace->primary_email . " added a new publication ", "common");
        }
      return $publicationId;
    }
    
    public function fetchAllPublication($parameters){
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('content', 'file_name','sort_order','DATE_FORMAT(added_on,"%d-%b-%Y")','status');
        $orderColumns = array('content', 'file_name','sort_order','added_on','status');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;


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

        $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name));
	
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
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
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

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $file = '';
            $addedDateTime = explode(" ",$aRow['added_on']);
            if(isset($aRow['file_name']) && trim($aRow['file_name'])!= '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document'. DIRECTORY_SEPARATOR .$aRow['file_name'])){
                $file = '<a href="/uploads/document/'.$aRow['file_name'].'" target="_blank">'.$aRow['file_name'].'<a>';
            }
            $row = array();
            $row[] = $aRow['content'];
            $row[] = $file;
	    $row[] = $aRow['sort_order'];
            $row[] = $general->humanDateFormat($addedDateTime[0]);
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/publications/edit/id/' . $aRow['publication_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    
    public function fetchPublication($publicationId){
        return $this->fetchRow("publication_id = ".$publicationId);
    }
    
    public function updatePublicationDetails($params){
        $publicationId = 0;
        if(isset($params['publicationId']) && trim($params['publicationId'])!= '') {
            $sortOrderResult = $publicationId = $params['publicationId'];
            //Remove deleted img.
            if(isset($params['removedFile']) && trim($params['removedFile'])!= ''){
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document'. DIRECTORY_SEPARATOR . $params['removedFile'])) {
                    unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document'. DIRECTORY_SEPARATOR . $params['removedFile']);
                    $this->update(array('file_name'=>''),"publication_id = ".$publicationId);
                }
            }
            
            $data = array(
                          'content'=>$params['content'],
                          'status' =>$params['status']
                          );
            $this->update($data,"publication_id = ".$publicationId);
        if($publicationId >0){
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("User " . $authNameSpace->primary_email . " updated a publication ", "common");
        }
            if(isset($params['sortOrder']) && trim($params['sortOrder'])!= ''){
		$publicationOrderQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array('p.publication_id','p.sort_order'))
                                              ->order("p.sort_order ASC");
	        $publicationOrderResult = $this->getAdapter()->fetchAll($publicationOrderQuery);
		//Get Min/Max publication order
		$minMaxOrderQuery = $this->getAdapter()->select()->from(array('p' => $this->_name), array(new Zend_Db_Expr('min(sort_order) as minSortOrder'),new Zend_Db_Expr('max(sort_order) as maxSortOrder')));
	        $minMaxOrderResult = $this->getAdapter()->fetchRow($minMaxOrderQuery);
		if($params['sortOrder'] > $minMaxOrderResult['maxSortOrder']){
		    $sortOrderResult = -1;
		}else{
		    $sql = $this->select()->where("publication_id = ? ",$publicationId);
	            $sqlResult = $this->fetchRow($sql);
		    if($params['sortOrder'] == $sqlResult['sort_order']){
			$sortOrderResult = 1;
		    }elseif($params['sortOrder'] < $sqlResult['sort_order']){
			$b = 1;
			foreach($publicationOrderResult as $pubOrder){
			   $bSOrder = $b+1;
			   if($pubOrder['sort_order'] >= $params['sortOrder'] && $pubOrder['sort_order'] <= $sqlResult['sort_order']) {
				if($pubOrder['publication_id'] == $publicationId){
				    $sortOrderResult = $this->update(array('sort_order'=>$params['sortOrder']),'publication_id = '.$publicationId);
				}else{
				    $sortOrderResult = $this->update(array('sort_order'=>$bSOrder),'publication_id = '.$pubOrder['publication_id']);
				}
			    }
			   $b++; 
			}
		    }elseif($params['sortOrder'] > $sqlResult['sort_order']){
			$b = 1;
			foreach($publicationOrderResult as $pubOrder){
			   $bSOrder = $b-1;
			   if($pubOrder['sort_order'] >= $sqlResult['sort_order'] && $pubOrder['sort_order'] <= $params['sortOrder']) {
				if($pubOrder['publication_id'] == $publicationId){
				    $sortOrderResult = $this->update(array('sort_order'=>$params['sortOrder']),'publication_id = '.$publicationId);
				}else{
				    $sortOrderResult = $this->update(array('sort_order'=>$bSOrder),'publication_id = '.$pubOrder['publication_id']);
				}
			    }
			   $b++; 
			}
		    }
		}
	    }
	    
            if(isset($_FILES['document']['name']) && trim($_FILES['document']['name'])!= ''){
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document')) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'document');
                }
                
                $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['document']['name'], PATHINFO_EXTENSION));
                $fileName ="document".$publicationId.".".$extension;
                if(move_uploaded_file($_FILES["document"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR."document". DIRECTORY_SEPARATOR.$fileName)){
                    $this->update(array('file_name'=>$fileName),"publication_id = ".$publicationId);
                }
            }
        }
      return $sortOrderResult;
    }
    
    public function fetchAllActivePublications(){
	$sql = $this->select()->where("status = ? ","active")->order("sort_order ASC");
	return $this->fetchAll($sql);
    }
}