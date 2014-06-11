<?php

class Application_Model_DbTable_SystemAdmin extends Zend_Db_Table_Abstract
{

    protected $_name = 'system_admin';
    protected $_primary = 'admin_id';

    
    public function getAllAdmin($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('first_name', 'last_name', 'primary_email', 'phone');

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

        $sQuery = $this->getAdapter()->select()->from(array('a' => $this->_name));
	
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


        foreach ($rResult as $aRow) {
            $row = array();
            $row[] = $aRow['first_name'];
            $row[] = $aRow['last_name'];
            $row[] = $aRow['primary_email'];
            $row[] = $aRow['phone'];
            $row[] = '<a href="/admin/system-admins/edit/id/' . $aRow['admin_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    
    public function addSystemAdmin($params){
	$authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
                      'first_name'=>$params['firstName'],
                      'last_name'=>$params['lastName'],
                      'primary_email'=>$params['primaryEmail'],
                      'secondary_email'=>$params['secondaryEmail'],
                      'password'=>$params['password'],
                      'phone'=>$params['phone'],
                      'status'=>$params['status'],
                      'force_password_reset'=>1,
		      'created_by' => $authNameSpace->admin_id,
                      'created_on' => new Zend_Db_Expr('now()')
                      );
        return $this->insert($data);
    }
    
    public function getSystemAdminDetails($adminId){
        return $this->fetchRow($this->select()->where("admin_id = ? ",$adminId));
    }
    
    public function updateSystemAdmin($params){
	$authNameSpace = new Zend_Session_Namespace('administrators');
        $data = array(
                      'first_name'=>$params['firstName'],
                      'last_name'=>$params['lastName'],
                      'primary_email'=>$params['primaryEmail'],
                      'secondary_email'=>$params['secondaryEmail'],
                      'phone'=>$params['phone'],
                      'status'=>$params['status'],
		      'updated_by' => $authNameSpace->admin_id,
                      'updated_on' => new Zend_Db_Expr('now()')
                      );
        if(isset($params['password']) && $params['password'] !=""){
            $data['password']= $params['password'];
            $data['force_password_reset']= 1;
        }
        return $this->update($data,"admin_id=".$params['adminId']);
    }

}

