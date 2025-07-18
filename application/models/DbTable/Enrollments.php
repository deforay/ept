<?php

use Symfony\Component\Uid\Ulid;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Application_Model_DbTable_Enrollments extends Zend_Db_Table_Abstract
{

    protected $_name = 'enrollments';
    protected $_primary = ['scheme_id', 'participant_id'];

    public function getAllEnrollments($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['p.unique_identifier', 'p.first_name', 'iso_name', 's.scheme_name', "DATE_FORMAT(e.enrolled_on,'%d-%b-%Y')"];

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




        $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'))
            ->join(['c' => 'countries'], 'c.id=p.country')
            ->joinLeft(['e' => 'enrollments'], 'p.participant_id = e.participant_id')
            ->joinLeft(['s' => 'scheme_list'], 'e.scheme_id = s.scheme_id', ['scheme_name' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.scheme_name ORDER BY s.scheme_name SEPARATOR ', ')")])
            ->where("p.status='active'")
            ->group("p.participant_id");

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != "") {
            $sQuery = $sQuery->where("s.scheme_id = ? ", $parameters['scheme']);
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
        $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'], new Zend_Db_Expr("COUNT('p.participant_id')"))
            ->join(['c' => 'countries'], 'c.id=p.country')
            ->joinLeft(['e' => 'enrollments'], 'p.participant_id = e.participant_id', [])
            ->joinLeft(['s' => 'scheme_list'], 'e.scheme_id = s.scheme_id', [])
            ->where("p.status='active'")
            ->group("p.participant_id");

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

        /*
         * Output
         */
        $output = [
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => []
        ];


        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['first_name'] . " " . $aRow['last_name'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_General::humanReadableDateFormat($aRow['enrolled_on']);
            if (trim($aRow['scheme_name']) != "") {
                $row[] = '<a href="/admin/enrollments/view/pid/' . $aRow['participant_id'] . '/sid/' . strtolower($aRow['scheme_id']) . '" class="btn btn-info btn-xs" style="margin-right: 2px;"><i class="icon-eye-open"></i> Know More</a>';
            } else {
                $row[] = "--";
            }

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function enrollParticipants($params)
    {

        if (!empty($params['schemeId'])) {
            $common = new Application_Service_Common();
            $enrollmentListId = Pt_Commons_General::generateULID();
            $listName = (isset($params['listName']) && $params['listName'] !== '') ? $params['listName'] : 'default';
            $where = [];
            $where[] = " list_name='$listName' ";

            if (!empty($params['schemeId'])) {
                $where[] = " scheme_id = '{$params['schemeId']}'";
            }

            // $this->delete(implode(' AND ', $where));
            $params['selectedForEnrollment'] = json_decode($params['selectedForEnrollment'], true);
            foreach ($params['selectedForEnrollment'] as $participant) {
                $data = [
                    'enrollment_id' => $enrollmentListId,
                    'list_name' => $listName,
                    'participant_id' => $participant,
                    'scheme_id' => $params['schemeId'],
                    'status' => 'enrolled',
                    'enrolled_on' => new Zend_Db_Expr('now()')
                ];
                $common->insertIgnore($this->_name, $data);
            }
        }
    }

    public function enrollParticipantToSchemes($participantId, $schemes, $listName = 'default')
    {

        $this->delete("participant_id=$participantId");

        foreach ($schemes as $scheme) {
            $data = [
                'list_name' => $listName,
                'participant_id' => $participantId,
                'scheme_id' => $scheme,
                'status' => 'enrolled',
                'enrolled_on' => new Zend_Db_Expr('now()')
            ];
            $this->insert($data);
        }
    }

    public function uploadBulkEnrollmentDetails($params)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $common = new Application_Service_Common();
            $allowedExtensions = ['xls', 'xlsx', 'csv'];
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
            $fileName = str_replace(" ", "-", $fileName);
            $random = Pt_Commons_MiscUtility::generateRandomString(6);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName = "$random-$fileName";
            $tempDirectory = realpath(TEMP_UPLOAD_PATH);
            if (in_array($extension, $allowedExtensions)) {
                if (!file_exists($tempDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                    if (move_uploaded_file($_FILES['fileName']['tmp_name'], $tempDirectory . DIRECTORY_SEPARATOR . $fileName)) {


                        $objPHPExcel = IOFactory::load($tempDirectory . DIRECTORY_SEPARATOR . $fileName);
                        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
                        $count = count($sheetData);
                        $listName = (isset($params['listName']) && $params['listName'] !== '') ? $params['listName'] : 'default';

                        $where = [];
                        $where[] = " list_name='$listName' ";

                        if (!empty($params['scheme'])) {
                            $where[] = " scheme_id = '{$params['scheme']}'";
                        }

                        $this->delete(implode(' AND ', $where));

                        $enrollmentListId = Pt_Commons_General::generateULID();
                        for ($i = 2; $i <= $count; ++$i) {

                            if (empty($sheetData[$i]['A'])) {
                                continue;
                            }
                            $pID = Pt_Commons_MiscUtility::cleanString($sheetData[$i]['A']);

                            // Fetch participant data based on the unique identifier
                            $participantData = $db->fetchRow(
                                $db->select()
                                    ->from('participant', ['participant_id']) // Fetch participant_id
                                    ->where('unique_identifier = ?', $pID)
                            );

                            if ($participantData) {


                                // Prepare raw SQL query for insertion with ON DUPLICATE KEY UPDATE
                                $query = "INSERT INTO `enrollments` (`enrollment_id`, `list_name`, `participant_id`, `status`, `enrolled_on`)
                                            VALUES (:enrollment_id, :list_name, :participant_id, :status, NOW())
                                                    ON DUPLICATE KEY UPDATE
                                                        `status` = VALUES(`status`),
                                                        `enrolled_on` = VALUES(`enrolled_on`)";

                                // Bind the data
                                $bind = [
                                    'enrollment_id' => $enrollmentListId,
                                    'list_name'     => $listName,
                                    'scheme_id'   => $params['scheme'] ?? null,
                                    'participant_id' => $participantData['participant_id'],
                                    'status'        => 'enrolled'
                                ];

                                // Execute the query
                                $db->query($query, $bind);
                            } else {
                                // Handle missing participant case
                                throw new Exception("Participant with unique identifier '$pID' does not exist.");
                            }
                        }
                        $auditDb = new Application_Model_DbTable_AuditLog();
                        $auditDb->addNewAuditLog("Bulk imported enrollment", "enrollment");
                        $alertMsg->message = 'Your file was imported successfully.';
                    } else {
                        $alertMsg->message = 'File not uploaded contact administrator to access permission';
                    }
                }
            } else {
                $alertMsg->message = 'Uploaded file entension not allowed. Only xls, xlsx and csv allowed';
            }
        } catch (Exception $e) {
            // If any of the queries failed and threw an exception,
            // we want to roll back the whole transaction, reversing
            // changes made in the transaction, even those that succeeded.
            // Thus all changes are committed together, or none are.
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }
}
