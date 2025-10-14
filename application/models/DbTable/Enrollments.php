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
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
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
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300);

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $uploadedFilePath = null;

        try {
            // Validate file upload
            if (!isset($_FILES['fileName']) || $_FILES['fileName']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error occurred.');
            }

            $allowedExtensions = ['xls', 'xlsx', 'csv'];
            $extension = strtolower(pathinfo($_FILES['fileName']['name'], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception('Invalid file extension. Only XLS, XLSX, and CSV files are allowed.');
            }

            // Process file upload
            $originalFileName = $_FILES['fileName']['name'];
            $sanitizedFileName = preg_replace('/[^A-Za-z0-9.]/', '-', $originalFileName);
            $random = substr(md5(uniqid(rand(), true)), 0, 6);
            $fileName = "{$random}-{$sanitizedFileName}";

            $tempDirectory = realpath(TEMP_UPLOAD_PATH);
            $uploadedFilePath = $tempDirectory . DIRECTORY_SEPARATOR . $fileName;

            if (!move_uploaded_file($_FILES['fileName']['tmp_name'], $uploadedFilePath)) {
                throw new Exception('Failed to move uploaded file.');
            }

            // Load Excel data
            $objPHPExcel = IOFactory::load($uploadedFilePath);
            $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

            if (empty($sheetData) || count($sheetData) < 2) {
                throw new Exception('Excel file is empty or has no data rows.');
            }

            // Prepare parameters
            $listName = !empty($params['listName']) ? $params['listName'] : 'default';
            $schemeId = $params['scheme'] ?? null;

            // Begin transaction
            $db->beginTransaction();

            // Clear existing enrollments
            $whereClause = "list_name = " . $db->quote($listName);
            if (!empty($schemeId)) {
                $whereClause .= " AND scheme_id = " . $db->quote($schemeId);
            }
            $db->delete('enrollments', $whereClause);

            // Process enrollments
            $enrollmentListId = uniqid('enroll_', true);
            $processedCount = 0;

            for ($i = 2; $i <= count($sheetData); $i++) {
                if (empty($sheetData[$i]['A'])) {
                    continue;
                }

                $uniqueIdentifier = trim($sheetData[$i]['A']);

                if (empty($uniqueIdentifier)) {
                    continue;
                }

                // Get participant
                $participantData = $db->fetchRow(
                    $db->select()
                        ->from('participant', ['participant_id'])
                        ->where('unique_identifier = ?', $uniqueIdentifier)
                        ->limit(1)
                );

                if (!$participantData) {
                    error_log("Participant not found: {$uniqueIdentifier}");
                    continue;
                }

                // Insert or update enrollment using ON DUPLICATE KEY UPDATE
                $insertData = [
                    'enrollment_id' => $enrollmentListId,
                    'list_name' => $listName,
                    'scheme_id' => $schemeId,
                    'participant_id' => $participantData['participant_id'],
                    'status' => 'enrolled',
                    'enrolled_on' => new Zend_Db_Expr('NOW()')
                ];

                // Remove null values
                $insertData = array_filter($insertData, function ($value) {
                    return $value !== null;
                });

                // Build column names and values for the query
                $columns = array_keys($insertData);
                $placeholders = array_fill(0, count($insertData), '?');
                $values = array_values($insertData);

                // Handle Zend_Db_Expr for NOW()
                for ($idx = 0; $idx < count($values); $idx++) {
                    if ($values[$idx] instanceof Zend_Db_Expr) {
                        $placeholders[$idx] = $values[$idx]->__toString();
                        unset($values[$idx]);
                    }
                }
                $exist = $this->fetchRow(
                    $this->select()
                        ->where('enrollment_id', $enrollmentListId)
                        ->where('list_name', $listName)
                        ->where('scheme_id', $schemeId)
                        ->where('participant_id', $participantData['participant_id'])
                );
                if (!$exist) {
                    $sql = "INSERT INTO enrollments (" . implode(', ', $columns) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")
                        ON DUPLICATE KEY UPDATE 
                            enrollment_id = VALUES(enrollment_id),
                            scheme_id = VALUES(scheme_id),
                            status = VALUES(status),
                            enrolled_on = VALUES(enrolled_on)";

                    $db->query($sql, array_values($values));
                    $processedCount++;
                }
            }

            // Log audit trail
            try {
                $auditDb = new Application_Model_DbTable_AuditLog();
                $auditDb->addNewAuditLog("Bulk imported {$processedCount} enrollments", "enrollment");
            } catch (Exception $e) {
                error_log("Audit log failed: " . $e->getMessage());
            }

            // Commit transaction
            $db->commit();

            $alertMsg->message = "Successfully imported {$processedCount} enrollments.";

            return [
                'success' => true,
                'message' => $alertMsg->message,
                'processed_count' => $processedCount
            ];
        } catch (Exception $e) {
            // Rollback transaction
            if ($db->getConnection()->inTransaction()) {
                $db->rollback();
            }

            error_log("BULK ENROLLMENT ERROR: " . $e->getMessage());

            $alertMsg->message = 'Error during import: ' . $e->getMessage();

            return [
                'success' => false,
                'message' => $alertMsg->message,
                'error' => $e->getMessage()
            ];
        } finally {
            // Clean up uploaded file
            if ($uploadedFilePath && file_exists($uploadedFilePath)) {
                unlink($uploadedFilePath);
            }
        }
    }
}
