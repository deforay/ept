<?php

class Application_Model_DbTable_Enrollments extends Zend_Db_Table_Abstract
{

    protected $_name = 'enrollments';
    protected $_primary = array('scheme_id','participant_id');
    
    public function getAllEnrollments($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('p.unique_identifier','p.first_name','iso_name','s.scheme_name', "DATE_FORMAT(e.enrolled_on,'%d-%b-%Y')");
	
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
	
	$sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'))
	                             ->join(array('c'=>'countries'),'c.id=p.country')
                                     ->joinLeft(array('e'=>'enrollments'),'p.participant_id = e.participant_id')
                                     ->joinLeft(array('s'=>'scheme_list'),'e.scheme_id = s.scheme_id',array('scheme_name' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.scheme_name ORDER BY s.scheme_name SEPARATOR ', ')")))
				     ->where("p.status='active'")
				     ->group("p.participant_id");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_id = ? ",$parameters['scheme']);
        }

        if (isset($sOrder) && $sOrder != "") {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        //die($parameters['scheme']);
        //die($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'), new Zend_Db_Expr("COUNT('p.participant_id')"))
	                                    ->join(array('c'=>'countries'),'c.id=p.country')
                                            ->joinLeft(array('e'=>'enrollments'),'p.participant_id = e.participant_id',array())
					    ->joinLeft(array('s'=>'scheme_list'),'e.scheme_id = s.scheme_id',array())
					    ->where("p.status='active'")
					    ->group("p.participant_id");
	
        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row[] = $aRow['first_name']. " " .$aRow['last_name'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_General::humanDateFormat($aRow['enrolled_on']);
	    if(trim($aRow['scheme_name'])!=""){
		$row[] = '<a href="/admin/enrollments/view/pid/' . $aRow['participant_id'] . '/sid/' . strtolower($aRow['scheme_id']) . '" class="btn btn-info btn-xs" style="margin-right: 2px;"><i class="icon-eye-open"></i> Know More</a>';
	    }else{
		$row[]="--";
	    }

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    
    public function enrollParticipants($params){
		
	$this->delete("scheme_id='".$params['schemeId']."'");
		
        foreach($params['participants'] as $participant){
            $data = array('participant_id'=>$participant,'scheme_id'=>$params['schemeId'],'status'=>'enrolled','enrolled_on'=>new Zend_Db_Expr('now()'));
            $this->insert($data);
        }
		
    }
    
    public function enrollParticipantToSchemes($participantId,$schemes){
		
		$this->delete("participant_id=".$participantId);
		
        foreach($schemes as $scheme){
            $data = array('participant_id'=>$participantId,'scheme_id'=>$scheme,'status'=>'enrolled','enrolled_on'=>new Zend_Db_Expr('now()'));
            $this->insert($data);
        }
		
    }


}
