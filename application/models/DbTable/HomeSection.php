<?php

class Application_Model_DbTable_HomeSection extends Zend_Db_Table_Abstract
{

    protected $_name = 'home_sections';
    protected $_primary = 'id';


    public function saveHomeSectionDetails($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $common = new Application_Service_Common();
        $sectionImage = null;
        $link = null;
        if (isset($params['link']) && $params['link'] != '') {
            $link = $params['link'];
        }

        // echo "<pre>";print_r($_FILES); die;
        if (isset($params['pre_section_image']) && $params['pre_section_image'] != '' && $_FILES['section_file']['tmp_name'] == '') {
            $sectionImage = $params['pre_section_image'];
        }
        if (isset($_FILES['section_file']['tmp_name']) && file_exists($_FILES['section_file']['tmp_name']) && is_uploaded_file($_FILES['section_file']['tmp_name'])) {

            $uploadDirectory = realpath(UPLOAD_PATH);
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'pdf', 'docx', 'doc', 'xlsx', 'xls');
            $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['section_file']['name']);
            $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
            $extension = strtolower(pathinfo($fileNameSanitized, PATHINFO_EXTENSION));
            $imageName = $common->generateRandomString(4) . '.' . $extension;

            if (in_array($extension, $allowedExtensions)) {
                // Determine the section folder based on $params['section']
                if ($params['section'] == 'section1') {
                    $section = 1;
                } else if ($params['section'] == 'section2') {
                    $section = 2;
                } else {
                    $section = 3;
                }
                $sectionFolder = "home" . DIRECTORY_SEPARATOR . "section" . $section;
                // Create the section directory if it doesn't exist
                $targetDirectory = $uploadDirectory . DIRECTORY_SEPARATOR . $sectionFolder;
                if (!file_exists($targetDirectory) && !is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0777, true); // Recursive directory creation
                }

                // Move the uploaded file to the target directory
                if (move_uploaded_file($_FILES["section_file"]["tmp_name"], $targetDirectory . DIRECTORY_SEPARATOR . $imageName)) {
                    $sectionImage = $sectionFolder . '/' . $imageName;
                }
            }
        }

        $data = array(
            'section' => $params['section'],
            'type' => $params['type'],
            'link' => $link,
            'section_file' => $sectionImage,
            'text' => $params['displayText'],
            'icon' => $params['icon'],
            'display_order' => $params['displayOrder'],
            'status' => $params['status'],
            'modified_by' => $authNameSpace->admin_id,
            'modified_date_time' => new Zend_Db_Expr('now()')
        );

        if (isset($params['homeSectionId']) && !empty($params['homeSectionId'])) {
            return $this->update($data, "id = '" . $params['homeSectionId'] . "'");
        } else {
            return $this->insert($data);
        }
    }

    public function getAllHomeSectionDetails($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('section', 'link', 'text', 'icon', 'display_order', 'status');

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




        $sQuery = $this->getAdapter()->select()->from(array('p' => $this->_name));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
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
            $row = [];
            $row[] = ucwords($aRow['section']);
            $row[] = $aRow['link'];
            $row[] = $aRow['text'];
            $row[] = $aRow['icon'];
            $row[] = $aRow['display_order'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/home-section-links/edit/id/' . base64_encode($aRow['id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchHomeSectionById($id)
    {
        $sql = $this->select();
        $sql = $sql->where("id= ? ", $id);
        return $this->fetchRow($sql);
    }

    public function fetchAllHomeSection()
    {
        $sql = $this->select();
        $sql = $sql->where("status= ? ", 'active')
            ->order("display_order ASC");
        $row =  $this->fetchAll($sql);
        $response = array();
        foreach ($row as $d) {
            $response[$d['section']][] = array(
                'link' => $d['link'],
                'icon' => $d['icon'],
                'text' => $d['text'],
                'type' => $d['type'],
                'section_file' => $d['section_file']
            );
        }
        return $response;
    }

    public function getMaxSortOrder($params)
    {
        $section = $params['section'];
        $status = 'active'; // Hardcoded or passed as a parameter

        // Updated SQL query with additional status condition
        $sql = "SELECT MAX(display_order) AS max_display_order FROM home_sections WHERE section = :section AND status = :status";

        // Execute the query and fetch all rows as an array
        $results = $this->getAdapter()->query($sql, ['section' => $section, 'status' => $status])->fetchAll();
        return  !empty($results) && isset($results[0]['max_display_order']) ? (int)$results[0]['max_display_order'] : 0;
    }
}
