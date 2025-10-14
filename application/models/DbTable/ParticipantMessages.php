<?php

class Application_Model_DbTable_ParticipantMessages extends Zend_Db_Table_Abstract
{
    protected $_name = 'participant_messages';
    protected $_primary = 'id';

    public function addParticipantMessage($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $common = new Application_Service_Common();
        $attachedFile = null;
        $loggedUser = new Zend_Session_Namespace('loggedUser');
        $partcipant_id = $loggedUser->partcipant_id;
        $fromMail = $loggedUser->primary_email;
        $fromName = $loggedUser->first_name . $loggedUser->last_name;

        if (isset($params['subject']) && $params['subject'] != "") {
            $attachedFile = null;
            if (isset($_FILES['attachment']['name']) && !empty($_FILES['attachment']['name'][0])) {
                $pathPrefix = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'mail-attachments';
                if (!is_dir($pathPrefix)) {
                    mkdir($pathPrefix, 0777, true);
                }

                foreach ($_FILES['attachment']['name'] as $key => $fileName) {
                    $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $fileName);
                    $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                    $extension = strtolower(pathinfo($fileNameSanitized, PATHINFO_EXTENSION));
                    $uniqueFileName = Pt_Commons_MiscUtility::generateRandomString(4) . '.' . $extension;

                    if (move_uploaded_file($_FILES['attachment']['tmp_name'][$key], $pathPrefix . DIRECTORY_SEPARATOR . $uniqueFileName)) {
                        $files[] = $uniqueFileName; // Add file path to array
                        $attachedFiles[] = $pathPrefix . DIRECTORY_SEPARATOR . $uniqueFileName; // Add file path to array
                    }
                }
            }

            $data =  [
                "participant_id" => $partcipant_id,
                "subject" => $params['subject'],
                "message" => $params['message'],
                "status" => 'pending',
                "attached_file" => (!empty($files)) ? json_encode($files) : null,
                "created_at" => new Zend_Db_Expr('now()')
            ];
            // echo '<pre>'; print_r($data); die;

            $db->insert('participant_messages', $data);
            $insertId = $db->lastInsertId();
            $message = $params['message'];
            $subject = $params['subject'];
            $toMail = Application_Service_Common::getConfig('admin_email');
            $common->insertTempMail($toMail, null, null, $subject, $message, $fromMail, $fromName, $attachedFiles);
            $response['status'] = 'success';
            return $response;
        }
    }

    public function getParticipantMessage($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('participant_id', 'subject', 'attached_file', 'message', 'status', "DATE_FORMAT(created_at,'%d-%b-%Y')");
        $orderColumns = array('participant_id', 'subject', 'attached_file', 'message', 'status', "DATE_FORMAT(created_at,'%d-%b-%Y')");

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;



        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }


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
                    if ($aColumns[$i] == "" || $aColumns[$i] == null) {
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




        $sQuery = $this->getAdapter()->select()
            ->from(array('pm' => $this->_name)) // Main table
            ->join(
                array('dm' => 'data_manager'), // Alias for the data_manager table
                'pm.id = dm.dm_id',          // ON condition for the LEFT JOIN
                array('first_name', 'last_name')            // Columns to select from the joined table
            );
        // ->joinLeft(array('sl' => 'scheme_list'), 's.scheme_type=sl.scheme_id', array('scheme_name'))

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //die($sQuery);

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
            // Check if first_name or last_name is missing and set default if necessary
            $firstName = isset($aRow['first_name']) ? $aRow['first_name'] : '';
            $lastName = isset($aRow['last_name']) ? $aRow['last_name'] : '';

            // Concatenate first and last name for participant name
            $participantName = ucwords($firstName . ' ' . $lastName);

            $row = [];
            $row[] = $participantName;
            $row[] = ucwords($aRow['subject']);
            $row[] = ucwords($aRow['message']);
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['created_at']);
            $row[] = '<a class="btn btn-primary btn-xs" href="/admin/participant-messages/view/d8s5_8d/' . base64_encode($aRow['id']) . '"><span><i class="fa fa-eye"></i> view</span></a>';
            $output['aaData'][] = $row;
            // print_r($output); die;
        }

        // Add this code block for debugging and safe JSON encoding
        header('Content-Type: application/json'); // Set the response type to JSON

        try {
            $json = json_encode($output); // Encode the output array to JSON
            if ($json === false) {
                // JSON encoding error
                echo json_last_error_msg(); // Print error details
                exit;
            }
            echo $json; // Send the JSON response to the client
        } catch (Exception $e) {
            // Handle unexpected exceptions
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit; // Ensure no additional output is sent

    }
}
