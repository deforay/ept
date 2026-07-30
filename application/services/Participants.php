<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Application_Service_Participants
{
    private $db = null;
    private $common = null;

    public function __construct()
    {
        $this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->common = new Application_Service_Common();
    }

    public function getUsersParticipants($userSystemId = null)
    {
        if ($userSystemId == null) {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $userSystemId = $authNameSpace->dm_id;
        }

        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getParticipantsByUserSystemId($userSystemId);
    }

    public function getParticipantDetails($partSysId)
    {

        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getParticipant($partSysId);
    }

    public function addParticipant($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->addParticipant($params);
    }

    public function addParticipantForDataManager($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->addParticipantForDataManager($params);
    }

    public function updateParticipant($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->updateParticipant($params);
    }
    public function getAllParticipants($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getAllParticipants($params);
    }

    public function getAllEnrollments($params)
    {
        $enrollments = new Application_Model_DbTable_Enrollments();
        return $enrollments->getAllEnrollments($params);
    }
    public function getEnrollmentDetails($pid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'p.participant_id=sp.participant_id')
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['scheme_name'])
            ->where('p.participant_id = ?', (int) $pid)
            ->order('s.shipment_date DESC');
        return $db->fetchAll($sql);
    }

    public function getParticipantsListNames()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['eln' => 'enrollments'], ['*'])->group(['list_name']);
        return $db->fetchAll($sql);
    }

    public function getParticipantsListNamesByUniqueId($id)
    {
        if (isset($id) && sizeof($id) > 0) {
            $ids = [];
            foreach ($id as $d) {
                $ids[] = base64_decode($d);
            }
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sql = $db->select()->from(['eln' => 'enrollments'], ['*'])
                ->where("eln.list_name IN ('" . implode("', '", $ids) . "')");
            return $db->fetchAll($sql);
        }
    }
    public function getParticipantSchemes($dmId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'p.participant_id=sp.participant_id')
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id')
            ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type')
            ->where('pmm.dm_id= ?', $dmId)
            ->group(['sp.participant_id', 's.scheme_type'])
            ->order('p.first_name');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }

    public function getPendingParticipants()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], ['p.participant_id'])
            ->where('p.status= ?', 'pending');
        return $db->fetchAll($sql);
    }

    public function getUnEnrolled($scheme, $params = '')
    {

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $subSql = $db->select()->from(['e' => 'enrollments'], 'participant_id')->where('scheme_id = ?', $scheme);
        $sql = $db->select()->from(['p' => 'participant'])
            ->where('participant_id NOT IN ?', $subSql)
            ->where("p.status='active'")
            ->order('first_name')
            ->group('p.participant_id');
        $this->applyParticipantFilters($sql, $params);
        return $db->fetchAll($sql);
    }
    public function getEnrolledBySchemeCode($scheme, $schemeName = '')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['e' => 'enrollments'], [])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=e.participant_id')
            ->where('scheme_id = ?', $scheme)
            ->where("p.status='active'")
            ->order('first_name')
            ->group('p.participant_id');
        if (isset($schemeName) && !empty($schemeName)) {
            $sql = $sql->where("IFNULL(list_name, 'default') = ?", $schemeName);
        }
        return $db->fetchAll($sql);
    }

    public function getEnrolledByShipmentId($shipmentId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [])
            ->joinLeft(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', [])
            ->where('s.shipment_id = ?', $shipmentId)
            ->where("p.status='active'")
            ->order('p.first_name')
            ->group('p.participant_id');

        return $db->fetchAll($sql);
    }
    public function getSchemesByParticipantId($pid)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], [])
            ->joinLeft(['e' => 'enrollments'], 'e.participant_id=p.participant_id', [])
            ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=e.scheme_id', ['scheme_id'])
            ->where('p.participant_id = ?', $pid)
            ->order('p.first_name');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchCol($sql);
    }
    public function getUnEnrolledByShipmentId($shipmentId, $params = [])
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $subSql = $db->select()->from(['p' => 'participant'], ['participant_id'])
            ->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [])
            ->joinLeft(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', [])
            ->where('s.shipment_id = ?', $shipmentId)
            ->where("p.status='active'")
            ->group('p.participant_id');
        $sql = $db->select()->from(['p' => 'participant'])->where('participant_id NOT IN ?', $subSql)
            ->order('p.first_name');
        $this->applyParticipantFilters($sql, $params);
        return $db->fetchAll($sql);
    }

    private function applyParticipantFilters(Zend_Db_Select $sql, $params): void
    {
        if (!is_array($params)) {
            return;
        }
        $map = [
            'choosenPid' => 'p.institute_name',
            'selectedCountries' => 'p.country',
            'selectedRegions' => 'p.region',
            'selectedDistricts' => 'p.district',
            'selectedStates' => 'p.state',
            'selectedCities' => 'p.city',
            'selectedNetworks' => 'p.network_tier',
            'selectedAffiliations' => 'p.affiliation',
            'selectedSiteTypes' => 'p.site_type',
            'selectedEnrolledPrograms' => 'p.enrolled_programs',
        ];
        foreach ($map as $key => $column) {
            if (empty($params[$key])) {
                continue;
            }
            $values = is_array($params[$key])
                ? $params[$key]
                : explode(',', $params[$key]);
            $values = array_values(array_filter(array_map('trim', $values), static fn (string $v): bool => $v !== ''));
            if ($values) {
                $sql->where($column . ' IN (?)', $values);
            }
        }
    }

    public function enrollParticipants($params)
    {
        $enrollments = new Application_Model_DbTable_Enrollments();
        return $enrollments->enrollParticipants($params);
    }
    public function addParticipantManagerMap($params, $type = null)
    {
        $db = new Application_Model_DbTable_Participants();
        return $db->addParticipantManager($params, $type);
    }
    public function getAffiliateList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_participant_affiliates')->order('affiliate ASC'));
    }
    public function getEnrolledProgramsList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_enrolled_programs')->order('enrolled_programs ASC'));
    }
    public function getSiteTypeList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_site_type')->order('site_type ASC'));
    }
    public function getNetworkTierList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return $db->fetchAll($db->select()->from('r_network_tiers')->order('network_name ASC'));
    }
    public function getAllParticipantRegion()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], ['p.region'])
            ->group('p.region')->where('p.region IS NOT NULL')->where("p.region != ''")
            ->order('p.region');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }
    public function getAllParticipantStates()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], ['p.state'])
            ->group('p.state')->where('p.state IS NOT NULL')->where("p.state != ''")
            ->order('p.state');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }
    public function getAllParticipantDistricts()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], ['p.district'])
            ->group('p.district')->where('p.district IS NOT NULL')->where("p.district != ''")
            ->order('p.district');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }

    public function getAllParticipantDetails($dmId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'])
            ->join(['c' => 'countries'], 'c.id=p.country')
            ->joinLeft(['pmm' => 'participant_manager_map'], 'p.participant_id=pmm.participant_id')
            ->where('pmm.dm_id= ?', $dmId)
            ->group(['p.participant_id'])
            ->order('p.first_name');
        return $db->fetchAll($sql);
    }

    public function getAllActiveParticipants()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchAllActiveParticipants();
    }

    public function getSchemeWiseParticipants($schemeType)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getSchemeWiseParticipants($schemeType);
    }

    public function getShipmentEnrollement($parameters)
    {
        $db = new Application_Model_DbTable_Participants();
        $db->getEnrolledByShipmentDetails($parameters);
    }

    public function getShipmentUnEnrollements($parameters)
    {
        $db = new Application_Model_DbTable_Participants();
        $db->getUnEnrolledByShipments($parameters);
    }
    public function getShipmentRespondedParticipants($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getShipmentRespondedParticipants($params);
    }
    public function getShipmentNotRespondedParticipants($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getShipmentNotRespondedParticipants($params);
    }
    public function getShipmentNotEnrolledParticipants($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getShipmentNotEnrolledParticipants($params);
    }

    public function getParticipantSchemesBySchemeId($parameters)
    {
        $shipmentDb = new Application_Model_DbTable_Shipments();
        return $shipmentDb->fetchParticipantSchemesBySchemeId($parameters);
    }

    public function getNotRespondedParticipantsByDmId($dmId)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchNotRespondedParticipantsByDmId($dmId);
    }

    public function exportShipmentRespondedParticipantsDetails($params)
    {
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $colNo = 0;

            if ($params['type'] == 'from-participant') {
                $sheet->mergeCells('A1:E1');
                $sheet->getCell('A1')->setValue(html_entity_decode('Shipment Participant List', ENT_QUOTES, 'UTF-8'));
            } else {
                $sheet->mergeCells('A1:E1');
                $sheet->getCell('A1')->setValue(html_entity_decode('Responded Shipment Participant List', ENT_QUOTES, 'UTF-8'));

                if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != '') {
                    $sheet->getCell('A2')->setValue(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
                    $sheet->getCell('B2')->setValue(html_entity_decode($params['shipmentCode'], ENT_QUOTES, 'UTF-8'));
                }
                if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != '') {
                    $sheet->getCell('A3')->setValue(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'));
                    $sheet->getCell('B3')->setValue(html_entity_decode($params['shipmentDate'], ENT_QUOTES, 'UTF-8'));
                }
            }

            $headings = [
                'Lab/Participant ID',
                'Lab/Participant Name',
                'Institute Name',
                'State/Province/Region',
                'District/County',
                'Country',
                'Cell/Mobile',
                'Phone',
                'Affiliation',
                'Email',
            ];

            if ($params['type'] == 'from-participant') {
                $headings[] = 'Participant Status';
            } else {
                $headings[] = 'Response Status';
            }
            $sheet->fromArray($headings, null, 'A3');

            $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuery = $sQuerySession->shipmentRespondedParticipantQuery;
            if ($params['type'] == 'from-participant') {
                // $sQuery = $sQuery->where("p.status = ? ", 'active');
            }
            $rResult = $db->fetchAll($sQuery);

            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['unique_identifier'];
                $row[] = $aRow['participantName'];
                $row[] = $aRow['institute_name'];
                $row[] = $aRow['state'];
                $row[] = $aRow['district'];
                $row[] = $aRow['iso_name'];
                $row[] = $aRow['mobile'];
                $row[] = $aRow['phone'];
                $row[] = $aRow['affiliation'];
                $row[] = $aRow['email'];
                if ($params['type'] == 'from-participant') {
                    $row[] = ucwords($aRow['status']);
                } else {
                    $row[] = ucwords($aRow['RESPONSE']);
                }

                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $rRowCount = $rowNo + 4;
                $sheet->fromArray($rowData, null, 'A' . $rRowCount);
            }

            $sheet = $this->common->centerAndBoldRowInSheet($sheet, 'A3');
            $sheet = $this->common->applyBordersToSheet($sheet);
            $sheet = $this->common->setAllColumnWidthsInSheet($sheet, 20);

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $rowCount = count($rResult);
            if ($params['type'] == 'from-participant') {
                $filename = 'PARTICIPANT-LIST-' . date('d-M-Y-H-i-s') . '.xlsx';
                $auditAction = "Downloaded participant list ({$rowCount} rows)";
            } else {
                $shipmentCode = strtoupper((string) ($params['shipmentCode'] ?? ''));
                $filename = $shipmentCode . '-PARTICIPANT-RESPONSE-REPORT-' . date('d-M-Y-H-i-s') . '.xlsx';
                $auditAction = $shipmentCode !== ''
                    ? "Downloaded participant response report - {$shipmentCode} ({$rowCount} rows)"
                    : "Downloaded participant response report ({$rowCount} rows)";
            }
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog($auditAction, 'participants');
            return $filename;
        } catch (Exception $exc) {

            $sQuerySession->shipmentRespondedParticipantQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate participant report (Excel): ' . $exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            return '';
        }
    }

    public function exportShipmentNotRespondedParticipantsDetails($params)
    {
        try {
            $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $colNo = 0;

            $styleArray = [
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'outline' => [
                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $styleInboldArray = [
                'font' => [
                    'bold' => true,
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ];
            $borderStyle = [
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
                'borders' => [
                    'outline' => [
                        'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    ],
                ],
            ];
            $sheet->mergeCells('A1:E1');
            $sheet->getCell('A1')->setValue(html_entity_decode('Not Responded Shipment Participant List', ENT_QUOTES, 'UTF-8'));
            $sheet->getStyle('A1')->applyFromArray($styleInboldArray, true);

            if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != '') {
                $sheet->getCell('A2')->setValue(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('B2')->setValue(html_entity_decode($params['shipmentCode'], ENT_QUOTES, 'UTF-8'));
            }
            if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != '') {
                $sheet->getCell('A3')->setValue(html_entity_decode('Shipment Date', ENT_QUOTES, 'UTF-8'));
                $sheet->getCell('B3')->setValue(html_entity_decode($params['shipmentDate'], ENT_QUOTES, 'UTF-8'));
            }

            $sheet->getCell('A4')->setValue(html_entity_decode('Participant Id', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('B4')->setValue(html_entity_decode('Lab Name/Participant Name', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('C4')->setValue(html_entity_decode('Institute/Hospital Name', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('D4')->setValue(html_entity_decode('Department Name', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('E4')->setValue(html_entity_decode('Email', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('F4')->setValue(html_entity_decode('Cell/Mobile', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('G4')->setValue(html_entity_decode('City', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('H4')->setValue(html_entity_decode('State', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('I4')->setValue(html_entity_decode('District', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('J4')->setValue(html_entity_decode('Country', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('K4')->setValue(html_entity_decode('Phone', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('L4')->setValue(html_entity_decode('Affiliation', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('M4')->setValue(html_entity_decode('Response Status', ENT_QUOTES, 'UTF-8'));

            $sheet->getStyle('A4')->applyFromArray($styleArray, true);
            $sheet->getStyle('B4')->applyFromArray($styleArray, true);
            $sheet->getStyle('C4')->applyFromArray($styleArray, true);
            $sheet->getStyle('D4')->applyFromArray($styleArray, true);
            $sheet->getStyle('E4')->applyFromArray($styleArray, true);
            $sheet->getStyle('F4')->applyFromArray($styleArray, true);
            $sheet->getStyle('G4')->applyFromArray($styleArray, true);
            $sheet->getStyle('H4')->applyFromArray($styleArray, true);
            $sheet->getStyle('I4')->applyFromArray($styleArray, true);
            $sheet->getStyle('J4')->applyFromArray($styleArray, true);
            $sheet->getStyle('K4')->applyFromArray($styleArray, true);
            $sheet->getStyle('L4')->applyFromArray($styleArray, true);
            $sheet->getStyle('M4')->applyFromArray($styleArray, true);

            // When invoked from the Shipments Report listing the not-responded
            // DataTable was never opened, so the session query is empty. Build the
            // list directly from the shipment id; fall back to the session query
            // for callers (e.g. manage-enroll) that still rely on it.
            if (!empty($params['shipmentId'])) {
                $participantDb = new Application_Model_DbTable_Participants();
                $rResult = $participantDb->getNotRespondedParticipantsForExport($params['shipmentId']);
            } else {
                $sQuerySession = new Zend_Session_Namespace('notRespondedParticipantsExcel');
                $db = Zend_Db_Table_Abstract::getDefaultAdapter();
                $rResult = $db->fetchAll($sQuerySession->shipmentRespondedParticipantQuery);
            }

            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = $aRow['unique_identifier'];
                $row[] = $aRow['participantName'];
                $row[] = $aRow['institute_name'];
                $row[] = $aRow['department_name'];
                $row[] = $aRow['email'];
                $row[] = $aRow['mobile'];
                $row[] = $aRow['city'];
                $row[] = $aRow['state'];
                $row[] = $aRow['district'];
                $row[] = $aRow['iso_name'];
                $row[] = $aRow['phone'];
                $row[] = $aRow['affiliation'];
                $row[] = ucwords($aRow['RESPONSE']);

                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = '';
                    }
                    $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 5)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    $rRowCount = $rowNo + 5;
                    $cellName = $sheet->getCell(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 5)
                        ->getColumn();
                    $sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle, true);
                    $sheet->getDefaultRowDimension()->setRowHeight(18);
                    $sheet->getColumnDimensionByColumn($colNo + 1)->setWidth(22);
                    $sheet->getStyle(Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 5, null, null)->getAlignment()->setWrapText(true);
                    $colNo++;
                }
            }

            $rowCount = count($rResult);
            $shipmentCode = trim((string) ($params['shipmentCode'] ?? ''));
            $detail = $shipmentCode !== '' ? " - {$shipmentCode}" : '';
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Downloaded non-respondents report{$detail} ({$rowCount} rows)", 'shipment');

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $namePrefix = $shipmentCode !== '' ? $shipmentCode . '-' : '';
            $filename = $namePrefix . 'Non-Respondents-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate Non-Respondents report: ' . $exc->getMessage(), [
                'shipmentId' => $params['shipmentId'] ?? null,
                'shipmentCode' => $params['shipmentCode'] ?? null,
                'trace' => $exc->getTraceAsString(),
            ]);

            return '';
        }
    }
    public function checkParticipantsProfileUpdate($userSystemId)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->checkParticipantsProfileUpdateByUserSystemId($userSystemId);
    }
    public function getParticipantUniqueIdentifier()
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $userSystemId = $authNameSpace->dm_id;
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->getParticipantsByUserSystemId($userSystemId);
    }

    public function getUniqueCountry()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchUniqueCountry();
    }
    public function fetchFilterValues()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchFilterValues();
    }

    public function getUniqueRegion()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchUniqueRegion();
    }

    public function getUniqueDistrict()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchUniqueDistrict();
    }

    public function getUniqueState()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchUniqueState();
    }

    public function getUniqueCity()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchUniqueCity();
    }

    public function getActiveParticipantDetails($userId)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchMapActiveParticipantDetails($userId);
    }

    public function getParticipantSearch($search)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchParticipantSearch($search);
    }

    public function uploadBulkParticipants($params = null)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        try {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $participantDb = new Application_Model_DbTable_Participants();
            $allowedExtensions = ['xls', 'xlsx', 'csv'];
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
            $fileName = str_replace(' ', '-', $fileName);
            $random = Pt_Commons_MiscUtility::generateRandomString(6);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $fileName = "$random-$fileName";
            $response = [];
            $lastInsertedId = 0;
            if (in_array($extension, $allowedExtensions)) {
                $tempUploadDirectory = realpath(UPLOAD_PATH);
                if (!file_exists($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                    if (move_uploaded_file($_FILES['fileName']['tmp_name'], $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                        $response = $participantDb->processBulkImport($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName, false, $params);
                    } else {
                        $alertMsg->message = 'Data import failed';
                        return false;
                    }
                } else {
                    $alertMsg->message = 'File not uploaded. Please try again.';
                    return false;
                }
            } else {
                $alertMsg->message = 'File format not supported';
                return false;
            }
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError($exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            $alertMsg->message = $this->describeBulkImportFailure($exc);
            return false;
        }
        return $response;
    }

    private function describeBulkImportFailure(Throwable $exc): string
    {
        // Map common failure shapes to user-friendly hints. Sensitive details (paths,
        // SQL, stack traces) stay in error_log; the user only sees the category.
        if ($exc instanceof InvalidArgumentException) {
            return 'File not uploaded. ' . $exc->getMessage();
        }
        $class = get_class($exc);
        if (stripos($class, 'PhpSpreadsheet') !== false || stripos($class, 'PhpOffice') !== false) {
            return 'File not uploaded. The spreadsheet could not be read. Please open it, save again as .xlsx or .csv, and retry.';
        }
        if ($exc instanceof PDOException || $exc instanceof Zend_Db_Exception) {
            return 'File not uploaded. The data could not be saved — please check for duplicate IDs or invalid values and try again.';
        }
        if (stripos($exc->getMessage(), 'memory') !== false || stripos($exc->getMessage(), 'allowed memory') !== false) {
            return 'File not uploaded. The file is too large to process. Please split it into smaller batches and try again.';
        }
        return 'File not uploaded. An unexpected error occurred while processing the file. Please try again, or contact the administrator if the issue persists.';
    }

    public function getFilterDetailsAPI($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchFilterDetailsAPI($params);
    }

    public function getProfileCheckDetailsAPI($params)
    {
        $dmDb = new Application_Model_DbTable_DataManagers();
        return $dmDb->fetchProfileCheckDetailsAPI($params);
    }

    public function saveProfileByAPI($params)
    {
        $dmDb = new Application_Model_DbTable_DataManagers();
        return $dmDb->saveProfileDetailsByAPI($params);
    }

    public function deleteParticipant($participantId)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->deleteParticipantBId($participantId);
    }

    public function getParticipantCountriesList()
    {
        $countriesDb = new Application_Model_DbTable_Countries();
        return $countriesDb->fetchParticipantCountriesList();
    }

    public function getResponseFilters($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'])
            ->join(['c' => 'countries'], 'c.id=p.country')
            ->group(['c.id'])
            ->order('c.iso_name ASC');
        return $db->fetchAll($sql);
    }

    public function getShipmentResponseReport($parameters)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchShipmentResponseReport($parameters);
    }

    public function exportParticipantsResponseDetails($params)
    {
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
            $colNo = 0;

            $sheet->getCell('A1')->setValue(html_entity_decode('Participant Name', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('B1')->setValue(html_entity_decode('Institute Name', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('C1')->setValue(html_entity_decode('Country', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('D1')->setValue(html_entity_decode('State/Province', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('E1')->setValue(html_entity_decode('District/County', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('F1')->setValue(html_entity_decode('Shipment Code', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('G1')->setValue(html_entity_decode('Response Status', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('H1')->setValue(html_entity_decode('Responded On', ENT_QUOTES, 'UTF-8'));
            $sheet->getCell('I1')->setValue(html_entity_decode('Evaluation Result', ENT_QUOTES, 'UTF-8'));

            $sQuerySession = new Zend_Session_Namespace('participantResponseReportQuerySession');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $sQuery = $sQuerySession->participantResponseReportQuerySession;
            $rResult = $db->fetchAll($sQuery);

            $finalResult = [1 => 'Pass', 2 => 'Fail', 3 => 'Excluded'];
            foreach ($rResult as $aRow) {
                $row = [];
                $row[] = ucwords($aRow['participantName']);
                $row[] = ucwords($aRow['institute_name']);
                $row[] = ucwords($aRow['iso_name']);
                $row[] = ucwords($aRow['state']);
                $row[] = ucwords($aRow['district']);
                $row[] = $aRow['shipment_code'];
                $row[] = ucwords($aRow['RESPONSE']);
                $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_test_report_date'] ?? '');
                $row[] = (isset($finalResult[$aRow['final_result']]) && !empty($finalResult[$aRow['final_result']])) ? ucwords($finalResult[$aRow['final_result']]) : null;

                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $rRowCount = $rowNo + 2;
                $sheet->fromArray($rowData, null, 'A' . $rRowCount);
            }

            $sheet = $this->common->centerAndBoldRowInSheet($sheet, 'A1');
            $sheet = $this->common->applyBordersToSheet($sheet);
            $sheet = $this->common->setAllColumnWidthsInSheet($sheet, 20);
            $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'Shipment-Participant-Response-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            $rowCount = count($rResult);
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Downloaded participant response data ({$rowCount} rows)", 'participants');
            echo $filename;
        } catch (Exception $exc) {
            $sQuerySession->shipmentRespondedParticipantQuery = '';
            Pt_Commons_LoggerUtility::logError('Failed to generate shipment-responded participant report (Excel): ' . $exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            echo '';
        }
    }

    public function getParticipantsCertificates($params)
    {
        $dmDb = new Application_Model_DbTable_DataManagers();
        $dmDetails = $dmDb->fetchAuthToken($params);
        /* Validate new auth token and app-version */
        if (!$dmDetails) {
            return ['status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again'];
        }
        $participantDb = new Application_Model_DbTable_Participants();
        $downloads = $participantDb->getParticipantsByUserSystemId($dmDetails['dm_id']);

        $arrCount = count($downloads);
        $downloads[$arrCount]['unique_identifier'] = 'common';
        $response = [];

        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        $eptDomain = rtrim($conf->domain, '/');
        $common = new Application_Service_Common();

        if (!empty($downloads)) {
            foreach ($downloads as $uniqueId) {
                $path = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . $uniqueId['unique_identifier'];
                if (is_dir($path) && count(scandir($path)) > 2) {
                    $lab = Application_Model_DbTable_Participants::formatParticipantName($uniqueId);

                    $files = [];
                    $nameOfTheFile = [];
                    foreach (scandir($path) as $fileName) {
                        if ($fileName != '.' && $fileName != '..') {
                            $files[$fileName] = filemtime($path . '/' . $fileName);
                            $nameOfTheFile[] = $fileName;
                        }
                    }
                    if (!empty($files)) {
                        arsort($files);
                        $i = 0;
                        foreach (array_keys($files) as $key => $descFile) {
                            $response[$key]['unique'] = ucfirst($uniqueId['unique_identifier']);
                            $response[$key]['lab'] = ucfirst($lab);
                            $response[$key]['fileName'] = ucfirst($nameOfTheFile[$i]);
                            $response[$key]['url'] = $eptDomain . '/participant/download-file?fileName=' . urlencode(base64_encode($descFile . '#######' . $uniqueId['unique_identifier'] . '#######' . Pt_Commons_DateUtility::getCurrentDateTime()));
                            $i++;
                        }
                    }
                }
            }
        }
        return $response;
    }

    public function getTbInstruments($mapId)
    {
        $instrumentDb = new Application_Model_DbTable_TBInstruments();
        return $instrumentDb->fetchTbInstruments($mapId);
    }
    public function getAllPTDetails($data)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

        $host = strtolower(parse_url($conf->domain, PHP_URL_HOST) ?: '');

        $skipEmail = !empty($data['skipEmail']) && $data['skipEmail'] === 'on';

        // Status values that mean "do not deliver" — populated by
        // bin/check-participant-emails.php (syntax+MX) and bin/process-bounces.php
        // (hard bounces). 'valid' and 'unknown' both stay eligible: unknown
        // rows haven't been classified yet, and we'd rather attempt-and-bounce
        // than silently skip everything on a fresh install.
        $badStatuses = "'hard_bounce','invalid_domain','invalid_syntax'";

        // Pick the primary if its stamped status is OK; otherwise fall back to
        // the secondary; otherwise NULL (HAVING drops the row).
        $emailPicker = function (string $primaryCol, string $primaryStatusCol, string $secondaryCol, string $secondaryStatusCol) use ($badStatuses): string {
            return "CASE
                WHEN $primaryCol IS NOT NULL AND $primaryCol <> ''
                     AND $primaryStatusCol NOT IN ($badStatuses) THEN $primaryCol
                WHEN $secondaryCol IS NOT NULL AND $secondaryCol <> ''
                     AND $secondaryStatusCol NOT IN ($badStatuses) THEN $secondaryCol
                ELSE NULL
            END";
        };

        // GROUP BY / HAVING run on the `mailTo` alias, never on `email`:
        // `participant` has a real column called `email`, so as soon as that
        // table is joined MySQL binds the bare name to the column instead of
        // the select alias and silently groups unrelated people together
        // (four different PTCCs collapsing into one row). `mailTo` matches no
        // column in any joined table, so it can only mean the alias.
        $groupAndFilter = function (Zend_Db_Select $sql) use ($skipEmail, $host) {
            $sql->group('mailTo')
                ->having('mailTo IS NOT NULL');
            if ($skipEmail && $host !== '') {
                $sql->having('LOWER(mailTo) NOT LIKE ?', '%@' . $host)
                    ->having('LOWER(mailTo) NOT LIKE ?', '%@%.' . $host);
            }
            return $sql;
        };

        $result = [];

        // Every join below fans rows out, so anything that isn't grouped has to
        // be GROUP_CONCAT(DISTINCT ...) to stay stable.
        $countryExpr = new Zend_Db_Expr("GROUP_CONCAT(DISTINCT c.iso_name ORDER BY c.iso_name SEPARATOR ', ')");

        if (in_array('participant', (array) $data['sendMail'], true)) {
            $emailExpr = $emailPicker('p.email', 'p.email_status', 'p.additional_email', 'p.additional_email_status');
            $sql = $db->select()->from(['p' => 'participant'], [
                'email'  => new Zend_Db_Expr($emailExpr),
                'mailTo' => new Zend_Db_Expr($emailExpr),
                'name'  => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameGroupConcatExpr('p')),
                'role'  => new Zend_Db_Expr("'participant'"),
            ])
                ->joinLeft(['spm' => 'shipment_participant_map'], 'p.participant_id=spm.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.shipment_date'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country' => $countryExpr])
                ->where('s.shipment_id IN(?)', (array) $data['shipments'])
                ->where("p.status = 'active'");

            // When both audiences are selected the lab is the addressee and its
            // data managers ride along as Cc, so collect them per participant.
            if (!empty($data['ccDataManagers'])) {
                $ccPicker = $emailPicker('ccdm.primary_email', 'ccdm.primary_email_status', 'ccdm.secondary_email', 'ccdm.secondary_email_status');
                $sql->joinLeft(['ccpmm' => 'participant_manager_map'], 'ccpmm.participant_id=p.participant_id', [])
                    ->joinLeft(
                        ['ccdm' => 'data_manager'],
                        "ccdm.dm_id=ccpmm.dm_id AND ccdm.data_manager_type LIKE 'manager' AND ccdm.status = 'active'",
                        ['ccEmails' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT $ccPicker SEPARATOR ',')")]
                    );
            }

            $result[] = $db->fetchAll($groupAndFilter($sql));
        }

        $dmEmailExpr = $emailPicker('dm.primary_email', 'dm.primary_email_status', 'dm.secondary_email', 'dm.secondary_email_status');

        if (in_array('datamanager', (array) $data['sendMail'], true)) {
            // A data manager's country comes from the participants they manage.
            $sql = $db->select()->from(['dm' => 'data_manager'], [
                'email'  => new Zend_Db_Expr($dmEmailExpr),
                'mailTo' => new Zend_Db_Expr($dmEmailExpr),
                'name'  => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT dm.first_name,' ',dm.last_name ORDER BY dm.first_name SEPARATOR ', ')"),
                'role'  => new Zend_Db_Expr("'datamanager'"),
            ])
                ->joinLeft(['pmm' => 'participant_manager_map'], 'dm.dm_id=pmm.dm_id', [])
                ->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=pmm.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.shipment_date'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
                ->joinLeft(['p' => 'participant'], 'p.participant_id=pmm.participant_id', [])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country' => $countryExpr])
                ->where('s.shipment_id IN(?)', (array) $data['shipments'])
                ->where('data_manager_type LIKE "manager"')
                ->where("dm.status = 'active'")
                ->where("p.status = 'active'");

            $result[] = $db->fetchAll($groupAndFilter($sql));
        }

        if (in_array('ptcc', (array) $data['sendMail'], true)) {
            // A PTCC's country comes from its own coverage map, not from the labs.
            $sql = $db->select()->from(['dm' => 'data_manager'], [
                'email'  => new Zend_Db_Expr($dmEmailExpr),
                'mailTo' => new Zend_Db_Expr($dmEmailExpr),
                'name'  => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT dm.first_name,' ',dm.last_name ORDER BY dm.first_name SEPARATOR ', ')"),
                'role'  => new Zend_Db_Expr("'ptcc'"),
            ])
                ->joinLeft(['pmm' => 'participant_manager_map'], 'dm.dm_id=pmm.dm_id', [])
                ->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=pmm.participant_id', [])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.shipment_date'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
                ->joinLeft(['pcm' => 'ptcc_countries_map'], 'pcm.ptcc_id=dm.dm_id', [])
                ->joinLeft(['c' => 'countries'], 'c.id=pcm.country_id', ['country' => $countryExpr])
                ->joinLeft(['p' => 'participant'], 'p.participant_id=pmm.participant_id', [])
                ->where('s.shipment_id IN(?)', (array) $data['shipments'])
                ->where('data_manager_type LIKE "ptcc"')
                ->where("dm.status = 'active'")
                ->where("p.status = 'active'");

            $result[] = $db->fetchAll($groupAndFilter($sql));
        }

        return $result;
    }

    /**
     * The single source of truth for "who actually gets this email".
     *
     * Runs getAllPTDetails(), then applies exactly the filters the send loop
     * used to apply inline — syntax validation, the @domain skip, and the
     * cross-role de-dupe — so the preview screen and the real send can never
     * disagree. Keyed by lowercased address; first role to claim an address
     * keeps it, which is what guarantees one email per person.
     *
     * Selecting participants AND data managers together switches to
     * participant-led addressing: the lab is the To, its active data managers
     * are Cc'd on that same email, and a manager who shares the lab's address
     * is dropped from Cc so nobody is listed twice on one message. A manager
     * is only promoted to a To of their own when none of their sites has a
     * deliverable address — otherwise that site would hear nothing at all.
     *
     * @return array{recipients: array<string, array>, invalid: string[]}
     */
    public function resolveMailRecipients($data)
    {
        $commonServices = new Application_Service_Common();
        $host = strtolower(parse_url($commonServices->getConfig('domain'), PHP_URL_HOST) ?: '');
        $skip = !empty($data['skipEmail']) && $data['skipEmail'] === 'on';

        $roles = (array) ($data['sendMail'] ?? []);
        $ccDataManagers = in_array('participant', $roles, true) && in_array('datamanager', $roles, true);
        $data['ccDataManagers'] = $ccDataManagers;

        $recipients = [];
        $invalid = [];
        // Every address already on a message, as a To or a Cc. In paired mode
        // this is what stops a Cc'd manager also getting their own copy.
        $covered = [];

        $isSkipped = function (string $email) use ($skip, $host): bool {
            if (!$skip || $host === '') {
                return false;
            }
            $domain = strtolower(substr(strrchr($email, '@'), 1));
            return $domain === $host || substr($domain, -strlen('.' . $host)) === '.' . $host;
        };

        foreach ($this->getAllPTDetails($data) as $row) {
            foreach ($row as $pt) {
                $toMail = trim((string) ($pt['email'] ?? ''));
                if ($toMail === '') {
                    continue;
                }

                $toEmail = Application_Service_Common::validateEmail($toMail);
                if ($toEmail === null) {
                    $invalid[$toMail] = $toMail;
                    continue;
                }

                $key = strtolower($toEmail);
                if (isset($recipients[$key])) {
                    continue;
                }

                // A manager already Cc'd on their lab's email needs no second copy
                if ($ccDataManagers && ($pt['role'] ?? '') === 'datamanager' && isset($covered[$key])) {
                    continue;
                }

                if ($isSkipped($toEmail)) {
                    continue;
                }

                // Resolve the Cc list: validated, domain-skipped, never the To itself
                $cc = [];
                foreach (preg_split('/[,;\s]+/', (string) ($pt['ccEmails'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $raw) {
                    $ccEmail = Application_Service_Common::validateEmail($raw);
                    if ($ccEmail === null || $isSkipped($ccEmail)) {
                        continue;
                    }
                    $ccKey = strtolower($ccEmail);
                    if ($ccKey === $key || isset($cc[$ccKey])) {
                        continue;
                    }
                    $cc[$ccKey] = $ccEmail;
                }

                $pt['email'] = $toEmail;
                $pt['cc'] = array_values($cc);
                $recipients[$key] = $pt;

                $covered[$key] = true;
                foreach (array_keys($cc) as $ccKey) {
                    $covered[$ccKey] = true;
                }
            }
        }

        return ['recipients' => $recipients, 'invalid' => array_values($invalid)];
    }

    /**
     * Which roles each address matches across ALL three audiences, regardless
     * of what is selected. Lets the preview warn that an address queued as a
     * PTCC today is also an enrolled participant, and would therefore receive
     * a second copy from a separate send.
     *
     * @return array<string, string[]> lowercased email => roles
     */
    public function getMailRecipientRoleMap($data)
    {
        $data['sendMail'] = ['participant', 'datamanager', 'ptcc'];
        // Role membership only — the Cc join would just slow this down
        unset($data['ccDataManagers']);

        $map = [];
        foreach ($this->getAllPTDetails($data) as $row) {
            foreach ($row as $pt) {
                $email = Application_Service_Common::validateEmail(trim((string) ($pt['email'] ?? '')));
                if ($email === null) {
                    continue;
                }
                $key = strtolower($email);
                $map[$key][$pt['role']] = $pt['role'];
            }
        }

        return array_map('array_values', $map);
    }

    /**
     * Merge-field values for one recipient row. Shared by the send and the
     * preview so a previewed body is byte-identical to what goes out.
     *
     * @return array{0: string[], 1: string[]} [$search, $replace]
     */
    public static function mailMergeFields(array $pt): array
    {
        $surveyDate = Pt_Commons_DateUtility::humanReadableDateFormat($pt['distribution_date'] ?? null);
        $shipDate = !empty($pt['shipment_date'])
            ? Pt_Commons_DateUtility::humanReadableDateFormat($pt['shipment_date'])
            : $surveyDate;
        // The programme year is the survey's year, not today's — a panel
        // prepared in December still belongs to the following year's round.
        $year = !empty($pt['distribution_date']) ? date('Y', strtotime((string) $pt['distribution_date'])) : '';

        return [
            ['##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##', '##SHIPDATE##', '##YEAR##', '##COUNTRY##'],
            [
                $pt['name'] ?? '',
                $pt['shipment_code'] ?? '',
                $pt['SCHEME'] ?? '',
                $pt['distribution_code'] ?? '',
                $surveyDate,
                $shipDate,
                $year,
                $pt['country'] ?? '',
            ],
        ];
    }

    /**
     * Email lists for the Manage Enrollment copy-to-clipboard buttons:
     * participant.email and data_manager.primary_email for a shipment audience.
     *
     * $scope mirrors the three tabs on that page:
     *   'enrolled'      — everyone in shipment_participant_map for the shipment
     *   'not-responded' — enrolled but without a submitted response (same test
     *                     as getShipmentNotRespondedParticipants)
     *   'not-enrolled'  — active participants NOT in the shipment
     *   'ptcc'          — MTBEPT only: the PTCC logins mapped to the enrolled
     *                     participants (no participant emails in this list)
     *
     * Same deliverability convention as getAllPTDetails() — 'valid' and
     * 'unknown' stay eligible, flagged-bad statuses are dropped — then each
     * cell is split, syntax-checked and de-duplicated in PHP. Deactivated
     * participants and data-manager / PTCC logins are left out of every scope.
     */
    public function getShipmentEmailsByScope($shipmentId, $scope = 'enrolled')
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $shipmentId = (int) $shipmentId;
        $badStatuses = ['hard_bounce', 'invalid_domain', 'invalid_syntax'];
        $isPtccScope = ($scope === 'ptcc');

        $participantSql = $db->select()->distinct()->from(['p' => 'participant'], ['p.email'])
            ->where("p.email IS NOT NULL AND p.email <> ''")
            ->where('p.email_status NOT IN (?)', $badStatuses)
            ->where("p.status = 'active'");

        // data_manager_type is the reliable discriminator here; the legacy
        // data_manager.ptcc enum has drifted out of sync on both sides.
        $dataManagerSql = $db->select()->distinct()->from(['dm' => 'data_manager'], ['dm.primary_email'])
            ->join(['pmm' => 'participant_manager_map'], 'dm.dm_id = pmm.dm_id', [])
            ->join(['p' => 'participant'], 'p.participant_id = pmm.participant_id', [])
            ->where('dm.data_manager_type LIKE ?', $isPtccScope ? 'ptcc' : 'manager')
            ->where("dm.primary_email IS NOT NULL AND dm.primary_email <> ''")
            ->where('dm.primary_email_status NOT IN (?)', $badStatuses)
            // Retired logins/participants stay in the table; don't mail them.
            ->where("dm.status = 'active'")
            ->where("p.status = 'active'");

        if ($scope === 'not-enrolled') {
            $enrolledSub = $db->select()
                ->from(['spm' => 'shipment_participant_map'], ['spm.participant_id'])
                ->where('spm.shipment_id = ?', $shipmentId);
            foreach ([$participantSql, $dataManagerSql] as $sql) {
                $sql->where('p.participant_id NOT IN ?', $enrolledSub);
            }
        } else {
            foreach ([$participantSql, $dataManagerSql] as $sql) {
                $sql->join(['spm' => 'shipment_participant_map'], 'spm.participant_id = p.participant_id', [])
                    ->where('spm.shipment_id = ?', $shipmentId);
                if ($scope === 'not-responded') {
                    $sql->where("(spm.shipment_test_report_date IS NULL OR DATE(spm.shipment_test_report_date) = '0000-00-00' OR spm.response_status LIKE 'noresponse')");
                }
            }
        }

        return [
            'participantEmails' => $isPtccScope ? [] : $this->extractValidEmails($db->fetchCol($participantSql)),
            'dataManagerEmails' => $this->extractValidEmails($db->fetchCol($dataManagerSql)),
        ];
    }

    /**
     * A single email column sometimes carries several addresses separated by
     * commas/semicolons; split those out, keep only syntactically valid
     * addresses and de-duplicate case-insensitively.
     */
    private function extractValidEmails(array $rawValues): array
    {
        $unique = [];
        foreach ($rawValues as $raw) {
            foreach (preg_split('/[,;\s]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }
                $key = strtolower($email);
                if (!isset($unique[$key])) {
                    $unique[$key] = $email;
                }
            }
        }
        ksort($unique);
        return array_values($unique);
    }

    public function sendParticipantEmail($data)
    {
        $commonServices = new Application_Service_Common();
        $alertMsg = new Zend_Session_Namespace('alertSpace');

        $mail = json_decode($commonServices->getConfig('mail'));
        // Validated, domain-skipped and de-duplicated — same call the preview
        // screen makes, so what was previewed is what gets queued.
        $resolved = $this->resolveMailRecipients($data);

        // Persist what was sent (for history)
        $emailParticipantDb = new Application_Model_DbTable_EmailParticipants();
        $emailParticipantDb->saveEmailParticipants([
            'subject' => $data['subject'],
            'message' => $data['message'],
            'email' => implode(',', (array) $data['sendMail']),
            'scode' => implode(',', (array) $data['shipments']),
        ]);

        if (!empty($resolved['invalid'])) {
            $alertMsg->message = implode(', ', $resolved['invalid']) . ' — not valid email(s), skipped';
        }

        $fromEmail = $mail->fromEmail;
        $fromFullName = $mail->fromName;
        $configCc = trim((string) $mail->cc);
        $bcc = $mail->bcc;

        $status = false;
        foreach ($resolved['recipients'] as $pt) {
            // Personalize subject/message
            [$search, $replace] = self::mailMergeFields($pt);

            $message = str_replace($search, $replace, (string) $data['message']);
            $subject = str_replace($search, $replace, (string) $data['subject']);

            // The standing config Cc rides along with this row's data managers
            $ccList = $pt['cc'] ?? [];
            if ($configCc !== '') {
                array_unshift($ccList, $configCc);
            }
            $cc = implode(',', $ccList);

            // Queue email
            $status = $commonServices->insertTempMail($pt['email'], $cc, $bcc, $subject, $message, $fromEmail, $fromFullName) || $status;
        }
        if ($status) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Emails queued for sending';
        }
    }

    public function exportParticipantMapDetails()
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->exportParticipantMapDetails();
    }

    public function getParticipantList($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchParticipantList($params);
    }

    public function excludeParticipantById($params)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        $result = $participantDb->excludeUnrollParticipantById($params);
        if ($result) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'Participant was excluded from the shipment';
        }
        return $result;
    }

    public function uploadBulkEnrollment($params)
    {
        $enrollments = new Application_Model_DbTable_Enrollments();
        return $enrollments->uploadBulkEnrollmentDetails($params);
    }
}
