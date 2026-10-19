<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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
                ->where('eln.list_name IN (?)', $ids);
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

    /**
     * PT Survey ("PT Survey Code") list for the Individual Reports filter on
     * /participant/report. Pulls the distributions that have at least one
     * finalized shipment the current participant/DM is mapped to, so the
     * dropdown only ever offers surveys that can actually return a report.
     */
    public function getParticipantSurveyList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['d' => 'distributions'], ['d.distribution_id', 'd.distribution_code'])
            ->join(['s' => 'shipment'], 's.distribution_id = d.distribution_id', [])
            ->join(['spm' => 'shipment_participant_map'], 'spm.shipment_id = s.shipment_id', [])
            ->join(['p' => 'participant'], 'p.participant_id = spm.participant_id', [])
            ->where("s.status = 'finalized'")
            ->where('s.cancelled_at IS NULL')
            ->where('d.distribution_code IS NOT NULL')
            ->where("d.distribution_code != ''")
            ->group('d.distribution_id')
            ->order('d.distribution_code');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }

    /**
     * Participant list for the "Participants" filter on /participant/corrective.
     * Scoped exactly like the corrective-action report itself (finalized
     * shipment, has a corrective-action file, unsatisfactory result) so the
     * dropdown only ever offers participants who can actually return a row.
     */
    public function getCorrectiveActionParticipantList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['p' => 'participant'], ['p.participant_id', 'p.unique_identifier', 'p.first_name', 'p.last_name'])
            ->join(['spm' => 'shipment_participant_map'], 'spm.participant_id = p.participant_id', [])
            ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', [])
            ->where("s.status = 'finalized'")
            ->where("s.corrective_action_file NOT LIKE ''")
            ->where('spm.final_result = 2')
            ->group('p.participant_id')
            ->order('p.first_name');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }

    /**
     * PT Survey ("PT Survey Code") list for the filter on
     * /participant/current-schemes. Scoped the same way as that page's own
     * grid (shipped/evaluated, not cancelled) rather than
     * getParticipantSurveyList's finalized-only scope, so the dropdown only
     * offers surveys that can actually return a row there.
     */
    public function getCurrentSchemeSurveyList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['d' => 'distributions'], ['d.distribution_id', 'd.distribution_code'])
            ->join(['s' => 'shipment'], 's.distribution_id = d.distribution_id', [])
            ->join(['spm' => 'shipment_participant_map'], 'spm.shipment_id = s.shipment_id', [])
            ->join(['p' => 'participant'], 'p.participant_id = spm.participant_id', [])
            ->where("(s.status = 'shipped' OR s.status = 'evaluated')")
            ->where('s.cancelled_at IS NULL')
            ->where('d.distribution_code IS NOT NULL')
            ->where("d.distribution_code != ''")
            ->group('d.distribution_id')
            ->order('d.distribution_code');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        return $db->fetchAll($sql);
    }

    /**
     * Participant list for the "Participant" filter on
     * /participant/current-schemes. Scoped the same way as that page's own
     * grid (shipped/evaluated, not cancelled), so the dropdown only offers
     * participants who can actually return a row there.
     */
    public function getCurrentSchemeParticipantList()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()
            ->from(['p' => 'participant'], ['p.participant_id', 'p.unique_identifier', 'p.first_name', 'p.last_name'])
            ->join(['spm' => 'shipment_participant_map'], 'spm.participant_id = p.participant_id', [])
            ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', [])
            ->where("(s.status = 'shipped' OR s.status = 'evaluated')")
            ->where('s.cancelled_at IS NULL')
            ->group('p.participant_id')
            ->order('p.first_name');
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sql = $sql
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id = p.participant_id', [])
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

    /**
     * Workbook of every participant and data manager with an email that mail cannot reach:
     * missing, made up at import (login_only), bounced, or failing the syntax/MX check.
     * Returns the file name under TEMP_UPLOAD_PATH, or '' on failure.
     */
    public function exportUnusableEmails(): string
    {
        ini_set('memory_limit', -1);
        set_time_limit(0);
        $badStatuses = ['hard_bounce', 'invalid_domain', 'invalid_syntax', 'login_only'];
        $labels = [
            'missing'        => 'No email',
            'login_only'     => 'Login-only address (made up at import)',
            'hard_bounce'    => 'Bounced',
            'invalid_domain' => 'Domain does not accept mail',
            'invalid_syntax' => 'Not a valid email address',
        ];
        $problem = function (?string $email, ?string $status) use ($badStatuses): ?string {
            if (trim((string) $email) === '') {
                return 'missing';
            }
            return in_array($status, $badStatuses, true) ? $status : null;
        };
        $label = fn (?string $key): string => $key === null ? '' : $labels[$key];

        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $quotedBad = $db->quote($badStatuses);
            // A manager's Lab IDs and a PTCC's Areas run past MySQL's 1,024-byte
            // GROUP_CONCAT default, which would cut them off without a word
            $db->query('SET SESSION group_concat_max_len = 1048576');

            $participants = $db->fetchAll(
                $db->select()
                    ->from(['p' => 'participant'], [
                        'p.unique_identifier',
                        'participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
                        'p.status', 'p.email', 'p.email_status', 'p.additional_email', 'p.additional_email_status',
                        'p.last_bounce_at', 'p.last_bounce_reason',
                    ])
                    ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['iso_name'])
                    ->where("p.email IS NULL OR TRIM(p.email) = '' OR p.email_status IN ($quotedBad)"
                        . " OR (TRIM(COALESCE(p.additional_email, '')) <> '' AND p.additional_email_status IN ($quotedBad))")
                    ->order(['p.status ASC', 'c.iso_name ASC', 'p.unique_identifier ASC'])
            );

            $dataManagers = $db->fetchAll(
                $db->select()
                    ->from(['dm' => 'data_manager'], [
                        'dm.dm_id',
                        'name' => new Zend_Db_Expr("TRIM(CONCAT(COALESCE(dm.first_name, ''), ' ', COALESCE(dm.last_name, '')))"),
                        'dm.institute', 'dm.data_manager_type', 'dm.status',
                        'dm.primary_email', 'dm.primary_email_status', 'dm.secondary_email', 'dm.secondary_email_status',
                        'dm.last_bounce_at', 'dm.last_bounce_reason',
                        'labIds' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.unique_identifier ORDER BY p.unique_identifier SEPARATOR ', ')"),
                        'labCount' => new Zend_Db_Expr('COUNT(DISTINCT p.participant_id)'),
                    ])
                    ->joinLeft(['c' => 'countries'], 'c.id = dm.country_id', ['iso_name'])
                    ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.dm_id = dm.dm_id', [])
                    ->joinLeft(['p' => 'participant'], 'p.participant_id = pmm.participant_id', [])
                    ->where("dm.primary_email IS NULL OR TRIM(dm.primary_email) = '' OR dm.primary_email_status IN ($quotedBad)"
                        . " OR (TRIM(COALESCE(dm.secondary_email, '')) <> '' AND dm.secondary_email_status IN ($quotedBad))")
                    ->group('dm.dm_id')
                    ->order(['dm.status ASC', 'dm.data_manager_type ASC', 'c.iso_name ASC', 'dm.primary_email ASC'])
            );

            $excel = new Spreadsheet();
            // $textColumns hold IDs: written as text so 19012 stays left-aligned beside
            // 01033 and a leading zero survives, without Excel's "number stored as
            // text" flag on every cell
            $writeSheet = function ($sheet, string $title, array $headings, array $rows, array $textColumns = []): void {
                $sheet->setTitle($title);
                $sheet->fromArray($headings, null, 'A1');
                if (!empty($rows)) {
                    $sheet->fromArray($rows, null, 'A2', true);
                }
                foreach ($textColumns as $col) {
                    $sheet->getStyle("{$col}2:{$col}" . (count($rows) + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                    foreach ($rows as $i => $row) {
                        $cell = $sheet->getCell($col . ($i + 2));
                        $cell->setValueExplicit((string) $row[Coordinate::columnIndexFromString($col) - 1], DataType::TYPE_STRING);
                        $cell->getIgnoredErrors()->setNumberStoredAsText(true);
                    }
                }
                $lastCol = Coordinate::stringFromColumnIndex(count($headings));
                $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                $sheet->setAutoFilter("A1:{$lastCol}" . max(1, count($rows) + 1));
                $sheet->freezePane('A2');
                $this->common->setAllColumnWidthsInSheet($sheet, 24);
            };

            $participantRows = [];
            foreach ($participants as $r) {
                $primary = $problem($r['email'], $r['email_status']);
                $additional = trim((string) $r['additional_email']) === '' ? null : $problem($r['additional_email'], $r['additional_email_status']);
                $reachable = $primary === null || (trim((string) $r['additional_email']) !== '' && $additional === null);
                $participantRows[] = [
                    $r['unique_identifier'], $r['participantName'], $r['iso_name'], ucfirst((string) $r['status']),
                    $r['email'], $label($primary), $r['additional_email'], $label($additional),
                    $reachable ? 'Yes' : 'No', $r['last_bounce_at'], $r['last_bounce_reason'],
                ];
            }
            $writeSheet($excel->getActiveSheet(), 'Participants', [
                'Lab ID', 'Participant Name', 'Country', 'Status', 'Email', 'Email Problem',
                'Additional Email', 'Additional Email Problem', 'Reachable By Email', 'Last Bounce', 'Bounce Reason',
            ], $participantRows, ['A']);

            $types = ['manager' => 'Data Manager', 'participant' => 'Participant Login'];
            $dmRows = [];
            $ptccs = [];
            foreach ($dataManagers as $r) {
                // PTCCs get a sheet of their own, below
                if ($r['data_manager_type'] === 'ptcc') {
                    $ptccs[] = $r;
                    continue;
                }
                $primary = $problem($r['primary_email'], $r['primary_email_status']);
                $secondary = trim((string) $r['secondary_email']) === '' ? null : $problem($r['secondary_email'], $r['secondary_email_status']);
                $reachable = $primary === null || (trim((string) $r['secondary_email']) !== '' && $secondary === null);
                $dmRows[] = [
                    $r['name'], $r['institute'], $types[$r['data_manager_type']] ?? $r['data_manager_type'], $r['iso_name'],
                    ucfirst((string) $r['status']), $r['labIds'], $r['primary_email'], $label($primary),
                    $r['secondary_email'], $label($secondary), $reachable ? 'Yes' : 'No', $r['last_bounce_at'], $r['last_bounce_reason'],
                ];
            }
            $writeSheet($excel->createSheet(), 'Data Managers', [
                'Name', 'Institute', 'Type', 'Country', 'Status', 'Lab IDs', 'Login Email', 'Login Email Problem',
                'Secondary Email', 'Secondary Email Problem', 'Reachable By Email', 'Last Bounce', 'Bounce Reason',
            ], $dmRows, ['F']);

            // A PTCC covers whole countries, provinces or districts, often hundreds of
            // labs, so its sheet names the areas and counts the labs instead of
            // listing their IDs
            if (!empty($ptccs)) {
                $areas = $db->fetchPairs(
                    $db->select()
                        ->from(['pcm' => 'ptcc_countries_map'], [
                            'pcm.ptcc_id',
                            'areas' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT CONCAT_WS(' / ', c.iso_name, NULLIF(pcm.state, ''), NULLIF(pcm.district, '')) ORDER BY c.iso_name, pcm.state, pcm.district SEPARATOR '; ')"),
                        ])
                        ->joinLeft(['c' => 'countries'], 'c.id = pcm.country_id', [])
                        ->where('pcm.ptcc_id IN (?)', array_column($ptccs, 'dm_id'))
                        ->group('pcm.ptcc_id')
                );
                $ptccRows = [];
                foreach ($ptccs as $r) {
                    $primary = $problem($r['primary_email'], $r['primary_email_status']);
                    $secondary = trim((string) $r['secondary_email']) === '' ? null : $problem($r['secondary_email'], $r['secondary_email_status']);
                    $reachable = $primary === null || (trim((string) $r['secondary_email']) !== '' && $secondary === null);
                    $ptccRows[] = [
                        $r['name'], ucfirst((string) $r['status']), $areas[$r['dm_id']] ?? '', (int) $r['labCount'],
                        $r['primary_email'], $label($primary), $r['secondary_email'], $label($secondary),
                        $reachable ? 'Yes' : 'No', $r['last_bounce_at'], $r['last_bounce_reason'],
                    ];
                }
                $writeSheet($excel->createSheet(), 'PTCCs', [
                    'Name', 'Status', 'Areas', 'Labs', 'Login Email', 'Login Email Problem',
                    'Secondary Email', 'Secondary Email Problem', 'Reachable By Email', 'Last Bounce', 'Bounce Reason',
                ], $ptccRows);
            }
            $excel->setActiveSheetIndex(0);

            $filename = 'UNUSABLE-EMAILS-' . date('d-M-Y-H-i-s') . '.xlsx';
            IOFactory::createWriter($excel, 'Xlsx')->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(sprintf('Downloaded unusable email list (%d participants, %d data managers)', count($participantRows), count($dmRows)), 'participants');
            return $filename;
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError('Failed to generate unusable email export: ' . $exc->getMessage(), [
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
                foreach ($rowData as $value) {
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

    /** Import options carried from the upload form to the confirmed import. */
    private const BULK_IMPORT_OPTIONS = ['bulkUploadDuplicateSkip', 'bulkUploadAllowEmailRepeat', 'resetPassword'];

    /**
     * Upload step: store the file and dry-run the import. The returned response
     * carries a review_token that importReviewedBulkParticipants() redeems.
     */
    public function previewBulkParticipants(array $params)
    {
        $path = $this->storeBulkImportUpload();
        if ($path === null) {
            return false;
        }
        $options = array_intersect_key($params, array_flip(self::BULK_IMPORT_OPTIONS));
        $response = $this->runBulkImport($path, $options, true);
        if (is_array($response) && empty($response['validation_error'])) {
            $response['review_token'] = Pt_Commons_ImportReview::stash('participants', $path, $options);
            $response['options'] = $options;
        }
        return $response;
    }

    /** Import step: run the reviewed file for real with the options it was reviewed under. */
    public function importReviewedBulkParticipants(string $token)
    {
        $pending = Pt_Commons_ImportReview::claim('participants', $token);
        if ($pending === null) {
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = 'This review has expired or was already imported. Please upload the file again.';
            return false;
        }
        return $this->runBulkImport($pending['path'], $pending['params'], false);
    }

    private function storeBulkImportUpload(): ?string
    {
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        if (empty($_FILES['fileName']['name']) || !is_uploaded_file($_FILES['fileName']['tmp_name'] ?? '')) {
            $alertMsg->message = 'File not uploaded. Please try again.';
            return null;
        }
        $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
            $alertMsg->message = 'File format not supported';
            return null;
        }
        $path = realpath(UPLOAD_PATH) . DIRECTORY_SEPARATOR . Pt_Commons_MiscUtility::generateRandomString(6) . '-' . $fileName;
        if (file_exists($path) || !move_uploaded_file($_FILES['fileName']['tmp_name'], $path)) {
            $alertMsg->message = 'Data import failed';
            return null;
        }
        return $path;
    }

    private function runBulkImport(string $path, array $params, bool $dryRun)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        try {
            $participantDb = new Application_Model_DbTable_Participants();
            return $participantDb->processBulkImport($path, false, $params, $dryRun);
        } catch (Throwable $exc) {
            Pt_Commons_LoggerUtility::logError($exc->getMessage(), [
                'file'  => $exc->getFile(),
                'line'  => $exc->getLine(),
                'trace' => $exc->getTraceAsString(),
            ]);
            $alertMsg = new Zend_Session_Namespace('alertSpace');
            $alertMsg->message = $this->describeBulkImportFailure($exc);
            return false;
        }
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
        $host = Pt_Commons_MiscUtility::generatedEmailHost();

        // Status values that mean "do not deliver" — populated by
        // bin/check-participant-emails.php (syntax+MX), bin/process-bounces.php
        // (hard bounces) and bulk import (login_only: a made-up login address).
        // 'valid' and 'unknown' both stay eligible: unknown rows haven't been
        // classified yet, and we'd rather attempt-and-bounce than silently skip
        // everything on a fresh install.
        $badStatuses = "'hard_bounce','invalid_domain','invalid_syntax','login_only'";

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
        // Made-up login addresses are skipped even before their status is stamped
        // login_only; '@ept' is the host used before a domain was configured.
        // One row per address, lab and shipment: a manager or PTCC covering
        // 200 labs gets 200 emails, each about one lab in one shipment, rather
        // than one email with every lab's details run together.
        $groupAndFilter = function (Zend_Db_Select $sql) use ($host) {
            return $sql->group(['mailTo', 'p.participant_id', 's.shipment_id'])
                ->having('mailTo IS NOT NULL')
                ->having('LOWER(mailTo) NOT LIKE ?', '%@' . $host)
                ->having('LOWER(mailTo) NOT LIKE ?', '%@%.' . $host)
                ->having('LOWER(mailTo) NOT LIKE ?', '%@ept');
        };

        $result = [];

        // A reminder goes only to labs still to respond, and a manager's
        // email covers only those of their labs
        $pendingOnly = self::normalizeLabFilter($data['labFilter'] ?? null) === self::LAB_FILTER_PENDING;
        $applyLabFilter = function (Zend_Db_Select $sql) use ($pendingOnly): Zend_Db_Select {
            if ($pendingOnly) {
                $sql->where("IFNULL(spm.response_status, 'noresponse') NOT IN (?)", self::RESPONSE_STATUSES_DONE);
            }
            return $sql;
        };

        // Each row is one lab in one shipment, so the lab's own columns are
        // single values. Never GROUP_CONCAT them: a manager's labs reach the
        // email as {{lab_table}}, not as a comma-separated list.
        $labNameExpr = new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p'));
        $labColumns = [
            'lab_id' => 'p.participant_id',
            'participant_ids' => 'p.unique_identifier',
            'participant_names' => $labNameExpr,
        ];
        // Two manager logins can share one address; address them by one name
        $dmNameExpr = new Zend_Db_Expr("MIN(TRIM(CONCAT(COALESCE(dm.first_name, ''), ' ', COALESCE(dm.last_name, ''))))");

        if (in_array('participant', (array) $data['sendMail'], true)) {
            $emailExpr = $emailPicker('p.email', 'p.email_status', 'p.additional_email', 'p.additional_email_status');
            $sql = $db->select()->from(['p' => 'participant'], [
                'email'  => new Zend_Db_Expr($emailExpr),
                'mailTo' => new Zend_Db_Expr($emailExpr),
                'name'  => $labNameExpr,
                'role'  => new Zend_Db_Expr("'participant'"),
            ] + $labColumns)
                ->joinLeft(['spm' => 'shipment_participant_map'], 'p.participant_id=spm.participant_id', ['spm.response_status', 'spm.shipment_test_report_date'])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_id', 's.shipment_code', 's.shipment_date', 's.response_deadline'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country' => 'c.iso_name'])
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

            $result[] = $db->fetchAll($groupAndFilter($applyLabFilter($sql)));
        }

        $dmEmailExpr = $emailPicker('dm.primary_email', 'dm.primary_email_status', 'dm.secondary_email', 'dm.secondary_email_status');

        if (in_array('datamanager', (array) $data['sendMail'], true)) {
            $sql = $db->select()->from(['dm' => 'data_manager'], [
                'email'  => new Zend_Db_Expr($dmEmailExpr),
                'mailTo' => new Zend_Db_Expr($dmEmailExpr),
                'name'  => $dmNameExpr,
                'role'  => new Zend_Db_Expr("'datamanager'"),
            ] + $labColumns)
                ->joinLeft(['pmm' => 'participant_manager_map'], 'dm.dm_id=pmm.dm_id', [])
                ->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=pmm.participant_id', ['spm.response_status', 'spm.shipment_test_report_date'])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_id', 's.shipment_code', 's.shipment_date', 's.response_deadline'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
                ->joinLeft(['p' => 'participant'], 'p.participant_id=pmm.participant_id', [])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country' => 'c.iso_name'])
                ->where('s.shipment_id IN(?)', (array) $data['shipments'])
                ->where('data_manager_type LIKE "manager"')
                ->where("dm.status = 'active'")
                ->where("p.status = 'active'");

            $result[] = $db->fetchAll($groupAndFilter($applyLabFilter($sql)));
        }

        if (in_array('ptcc', (array) $data['sendMail'], true)) {
            $sql = $db->select()->from(['dm' => 'data_manager'], [
                'email'  => new Zend_Db_Expr($dmEmailExpr),
                'mailTo' => new Zend_Db_Expr($dmEmailExpr),
                'name'  => $dmNameExpr,
                'role'  => new Zend_Db_Expr("'ptcc'"),
            ] + $labColumns)
                ->joinLeft(['pmm' => 'participant_manager_map'], 'dm.dm_id=pmm.dm_id', [])
                ->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=pmm.participant_id', ['spm.response_status', 'spm.shipment_test_report_date'])
                ->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_id', 's.shipment_code', 's.shipment_date', 's.response_deadline'])
                ->joinLeft(['d' => 'distributions'], 'd.distribution_id=s.distribution_id', ['distribution_code', 'distribution_date'])
                ->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
                ->joinLeft(['p' => 'participant'], 'p.participant_id=pmm.participant_id', [])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['country' => 'c.iso_name'])
                ->where('s.shipment_id IN(?)', (array) $data['shipments'])
                ->where('data_manager_type LIKE "ptcc"')
                ->where("dm.status = 'active'")
                ->where("p.status = 'active'");

            $result[] = $db->fetchAll($groupAndFilter($applyLabFilter($sql)));
        }

        return $result;
    }

    /** How managers and PTCCs get an email that covers several of their labs */
    public const MANAGER_DELIVERY_COMBINED = 'combined';
    public const MANAGER_DELIVERY_PER_LAB = 'per_lab';

    /** Which labs an email is about: all in the shipment, or those still to respond */
    public const LAB_FILTER_ALL = 'all';
    public const LAB_FILTER_PENDING = 'pending';

    /**
     * Response statuses that mean the lab has dealt with the shipment, one way
     * or another. Anything else — no response, or a draft not yet
     * submitted — still owes a response.
     */
    private const RESPONSE_STATUSES_DONE = ['responded', 'late', 'nottested', 'late_submitted', 'late_rejected'];

    /** Audiences that look after labs rather than being one */
    private const MANAGER_ROLES = ['datamanager', 'ptcc'];

    /**
     * The single source of truth for "who actually gets this email".
     *
     * Runs getAllPTDetails(), then applies exactly the filters the send loop
     * used to apply inline — syntax validation, the @domain skip, and the
     * cross-role de-dupe — so the preview screen and the real send can never
     * disagree. Rows come in one per address, lab and shipment; the first role
     * to claim that combination keeps it, so nobody hears about the same lab
     * in the same shipment twice.
     *
     * $data['managerDelivery'] decides what a data manager or PTCC gets:
     *
     * - combined (the default): one email per shipment, with all their labs in
     *   it as {{lab_table}}. Labs still get their own email; managers are not
     *   Cc'd on it, since the summary already covers that lab.
     * - per_lab: one email per lab per shipment.
     *
     * $data['ccDataManagers'] on a participants email Cc's each lab's data
     * managers on that lab's own email (a manager who shares the lab's address
     * is dropped from Cc). Emails saved before it existed asked for this by
     * selecting participants and data managers together with per_lab; then a
     * manager only gets a To of their own for a lab with no deliverable
     * address — otherwise that lab would hear nothing at all.
     *
     * Every recipient carries 'labs': the labs its email is about.
     *
     * @return array{recipients: array<string, array>, invalid: string[]}
     */
    public function resolveMailRecipients($data)
    {
        $host = Pt_Commons_MiscUtility::generatedEmailHost();

        $roles = (array) ($data['sendMail'] ?? []);
        $combined = self::normalizeManagerDelivery($data['managerDelivery'] ?? null) === self::MANAGER_DELIVERY_COMBINED;
        $ccDataManagers = in_array('participant', $roles, true)
            && (!empty($data['ccDataManagers']) || (!$combined && in_array('datamanager', $roles, true)));
        $data['ccDataManagers'] = $ccDataManagers;

        $recipients = [];
        $invalid = [];
        // Manager rows whose lab already went to this same address as the
        // lab's own email. No separate email for them, but a manager who also
        // looks after other labs still sees that lab in their table.
        $claimedLabs = [];
        // Every address already on a lab's message for a shipment, as a To or a
        // Cc. In paired mode this is what stops a Cc'd manager also getting
        // their own copy of that lab's email.
        $covered = [];

        $isSkipped = function (string $email) use ($host): bool {
            $domain = strtolower(substr(strrchr($email, '@'), 1));
            return Pt_Commons_MiscUtility::isGeneratedEmail($email) || substr($domain, -strlen('.' . $host)) === '.' . $host;
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

                $lab = '|' . ($pt['lab_id'] ?? '') . '|' . ($pt['shipment_id'] ?? '');
                $key = strtolower($toEmail) . $lab;
                if (isset($recipients[$key])) {
                    if (
                        $combined
                        && in_array($pt['role'] ?? '', self::MANAGER_ROLES, true)
                        && ($recipients[$key]['role'] ?? '') === 'participant'
                    ) {
                        $claimedLabs[strtolower($toEmail) . '|' . ($pt['shipment_id'] ?? '')][] = self::labSummary($pt);
                    }
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
                    $ccKey = strtolower($ccEmail) . $lab;
                    if ($ccKey === $key || isset($cc[$ccKey])) {
                        continue;
                    }
                    $cc[$ccKey] = $ccEmail;
                }

                $pt['email'] = $toEmail;
                $pt['cc'] = array_values($cc);
                $pt['name'] = self::tidyName($pt['name'] ?? '');
                $pt['participant_names'] = self::tidyName($pt['participant_names'] ?? '');
                $pt['labs'] = [self::labSummary($pt)];
                $recipients[$key] = $pt;

                $covered[$key] = true;
                foreach (array_keys($cc) as $ccKey) {
                    $covered[$ccKey] = true;
                }
            }
        }

        if ($combined) {
            $recipients = self::combineManagerRecipients($recipients, $claimedLabs);
        }

        return ['recipients' => $recipients, 'invalid' => array_values($invalid)];
    }

    /** Anything but pending is all, which is what older saved emails meant */
    public static function normalizeLabFilter($value): string
    {
        return $value === self::LAB_FILTER_PENDING ? self::LAB_FILTER_PENDING : self::LAB_FILTER_ALL;
    }

    /** Anything but per_lab is combined, so older saved emails keep one email per manager */
    public static function normalizeManagerDelivery($value): string
    {
        return $value === self::MANAGER_DELIVERY_PER_LAB ? self::MANAGER_DELIVERY_PER_LAB : self::MANAGER_DELIVERY_COMBINED;
    }

    /** One row of {{lab_table}} */
    private static function labSummary(array $pt): array
    {
        return [
            'key'     => (string) ($pt['lab_id'] ?? ''),
            'id'      => (string) ($pt['participant_ids'] ?? ''),
            'name'    => self::tidyName($pt['participant_names'] ?? ''),
            'country' => (string) ($pt['country'] ?? ''),
            'status'  => (string) ($pt['response_status'] ?? ''),
            'responded_on' => (string) ($pt['shipment_test_report_date'] ?? ''),
        ];
    }

    /**
     * Names as people read them. Lab names are often typed as
     * "Central Hospital ,Benin,Edo State"; an email shows
     * "Central Hospital, Benin, Edo State". The stored name is unchanged.
     */
    public static function tidyName($name): string
    {
        // A comma between digits is a decimal ("km 12,5"), not a separator
        $name = preg_replace('/(?<!\d)\s*,\s*|\s*,\s*(?!\d)/u', ', ', (string) $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name, " \t\n\r\0\x0B,");
    }

    /**
     * Folds each manager's or PTCC's per-lab rows into one recipient per
     * address and shipment, adding the $claimedLabs that already went to the
     * same address as a lab's own email. A manager left with one lab gets that
     * lab's fields, so their email reads like the lab's own; with several,
     * mailMerge() words the lab fields for the table it appends.
     */
    private static function combineManagerRecipients(array $recipients, array $claimedLabs): array
    {
        $combined = [];
        foreach ($recipients as $key => $pt) {
            if (!in_array($pt['role'] ?? '', self::MANAGER_ROLES, true)) {
                $combined[$key] = $pt;
                continue;
            }
            $groupKey = strtolower($pt['email']) . '|' . ($pt['shipment_id'] ?? '');
            if (!isset($combined[$groupKey])) {
                $pt['combined'] = true;
                $combined[$groupKey] = $pt;
                continue;
            }
            $combined[$groupKey]['labs'][] = $pt['labs'][0];
        }

        foreach ($combined as $groupKey => &$pt) {
            if (empty($pt['combined'])) {
                continue;
            }
            foreach ($claimedLabs[$groupKey] ?? [] as $lab) {
                $pt['labs'][] = $lab;
            }
            // The same lab can arrive through several roles at one address
            // (data manager and PTCC both); it is still one row in the table
            $unique = [];
            foreach ($pt['labs'] as $lab) {
                $unique[$lab['key'] !== '' ? $lab['key'] : $lab['id']] ??= $lab;
            }
            $pt['labs'] = array_values($unique);
            // A manager with one lab in the shipment keeps that lab's fields
            if (count($pt['labs']) === 1) {
                $pt['participant_ids'] = $pt['labs'][0]['id'];
                $pt['participant_names'] = $pt['labs'][0]['name'];
                $pt['country'] = $pt['labs'][0]['country'];
            }
        }
        unset($pt);

        return $combined;
    }

    /**
     * The merge fields Email Participants offers, as {{key}} => legacy ##TOKEN##.
     * The {{key}} form is what the page suggests. The ##TOKEN## form keeps
     * working because saved mail templates and older drafts still use it.
     * Labels live in the view so the translation extractor finds them.
     */
    public const MAIL_MERGE_FIELDS = [
        'recipient_name'    => '##NAME##',
        'participant_name'  => null,
        'participant_id'    => null,
        'country'           => '##COUNTRY##',
        'shipment_code'     => '##SHIPCODE##',
        'scheme'            => '##SHIPTYPE##',
        'shipment_date'     => '##SHIPDATE##',
        'response_deadline' => null,
        'pt_survey_code'    => '##SURVEYCODE##',
        'pt_survey_date'    => '##SURVEYDATE##',
        'year'              => '##YEAR##',
        'support_email'     => null,
        'lab_table'         => null,
    ];

    /**
     * Merge-field values for one recipient row, keyed like MAIL_MERGE_FIELDS.
     *
     * An email about several labs (a manager's, combined) never lists them in
     * a field: the lab fields point at the table mailMerge() adds instead —
     * "the 12 labs listed below" in the message, "12 labs" in the subject.
     * Country is only spelled out when all the labs share it.
     *
     * @return array<string, string>
     */
    public static function mailMergeValues(array $pt, bool $isSubject = false): array
    {
        // Same for every recipient, so look it up once per request
        static $supportEmail = null;
        $supportEmail ??= Application_Service_Common::getSupportEmail();

        $surveyDate = Pt_Commons_DateUtility::humanReadableDateFormat($pt['distribution_date'] ?? null);
        $shipDate = !empty($pt['shipment_date'])
            ? Pt_Commons_DateUtility::humanReadableDateFormat($pt['shipment_date'])
            : $surveyDate;
        // The programme year is the survey's year, not today's — a panel
        // prepared in December still belongs to the following year's round.
        $year = !empty($pt['distribution_date']) ? date('Y', strtotime((string) $pt['distribution_date'])) : '';

        // An end-of-day cutoff reads better as a plain date; any other time
        // of day is part of the deadline and has to be shown.
        $deadline = (string) ($pt['response_deadline'] ?? '');
        $time = strlen($deadline) > 10 ? substr($deadline, 11, 5) : '';
        $deadline = Pt_Commons_DateUtility::humanReadableDateFormat(
            $deadline,
            $time !== '' && !in_array($time, ['00:00', '23:59'], true)
        );

        $labName = self::tidyName($pt['participant_names'] ?? '');
        $labId = (string) ($pt['participant_ids'] ?? '');
        $country = (string) ($pt['country'] ?? '');
        $labCount = self::isMultiLab($pt) ? count($pt['labs']) : 0;
        if ($labCount > 0) {
            $countries = array_unique(array_column($pt['labs'], 'country'));
            $labsPhrase = sprintf($isSubject ? self::translate('%s labs') : self::translate('the %s labs listed below'), $labCount);
            $labName = $labsPhrase;
            $labId = $isSubject ? $labsPhrase : self::translate('listed below');
            $country = count($countries) === 1
                ? (string) reset($countries)
                : ($isSubject
                    ? sprintf(self::translate('%s countries'), count($countries))
                    : self::translate('the countries listed below'));
        }

        return [
            // A manager saved without a name is greeted by their lab's name,
            // or by their address when the email covers several labs
            'recipient_name'    => ($pt['name'] ?? '') !== '' ? self::tidyName($pt['name']) : (string) ($labCount > 0 ? ($pt['email'] ?? '') : $labName),
            'participant_name'  => $labName,
            'participant_id'    => $labId,
            'country'           => $country,
            'shipment_code'     => (string) ($pt['shipment_code'] ?? ''),
            'scheme'            => (string) ($pt['SCHEME'] ?? ''),
            'shipment_date'     => (string) $shipDate,
            'response_deadline' => (string) $deadline,
            'pt_survey_code'    => (string) ($pt['distribution_code'] ?? ''),
            'pt_survey_date'    => (string) $surveyDate,
            'year'              => $year,
            'support_email'     => $supportEmail,
            // Filled by mailMerge(), which knows where the table goes
            'lab_table'         => $isSubject ? $labName : '',
        ];
    }

    /** Words that open a greeting, in the languages ePT ships with */
    private const GREETINGS = 'Dear|Hello|Hi|Greetings|Cher|Chère|Chers|Chères|Bonjour|Kính gửi|Xin chào|Thân gửi';

    /**
     * Puts {{lab_table}} after the block holding the first lab field, unless
     * the admin placed it. A field inside a list waits for the list to end.
     */
    private static function placeLabTable(string $text): string
    {
        if (preg_match('/\{\{\s*lab_table\s*\}\}/i', $text)) {
            return $text;
        }
        if (!preg_match('/\{\{\s*(participant_name|participant_id|country)\s*\}\}|##COUNTRY##/i', $text, $m, PREG_OFFSET_CAPTURE)) {
            return $text;
        }
        $from = $m[0][1];
        if (!preg_match('#</(p|div|ul|ol|h[1-6]|blockquote|table)>#i', $text, $close, PREG_OFFSET_CAPTURE, $from)) {
            return $text;
        }
        $at = $close[0][1] + strlen($close[0][0]);
        return substr($text, 0, $at) . '{{lab_table}}' . substr($text, $at);
    }

    /** Formatting a field can be wrapped in without leaving its sentence */
    private const INLINE_TAG = '<\/?(?:b|strong|i|em|u|span|font)\b[^>]*>';

    /** A manager's combined email that covers more than one lab */
    private static function isMultiLab(array $pt): bool
    {
        return !empty($pt['combined']) && count($pt['labs'] ?? []) > 1;
    }

    private static function translate(string $text): string
    {
        static $translate = false;
        if ($translate === false) {
            $translate = Zend_Registry::isRegistered('translate') ? Zend_Registry::get('translate') : null;
        }
        return $translate ? $translate->_($text) : $text;
    }

    /**
     * {{lab_table}}: the labs an email is about, one row each, with how far
     * each has got with its response. Labs still to respond come first, as
     * they are what the email asks about, then the labs that have responded.
     * Each group is in name order. A header row names each group when both
     * are present. Inline styles only, since mail clients drop <style> blocks.
     */
    public static function labTableHtml(array $pt): string
    {
        $labs = $pt['labs'] ?? [self::labSummary($pt)];

        // Each literal sits inside self::translate() so the extractor finds it
        $e = fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $statuses = [
            'responded'      => $e(self::translate('Responded')),
            'late'           => $e(self::translate('Responded late')),
            'late_submitted' => $e(self::translate('Submitted late, awaiting approval')),
            'late_rejected'  => $e(self::translate('Late, not accepted')),
            'nottested'      => $e(self::translate('Could not test')),
            'draft'          => $e(self::translate('Draft, not submitted')),
        ];
        $notYet = $e(self::translate('Not yet'));

        $byName = fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']) ?: strnatcasecmp($a['id'], $b['id']);
        $groups = ['pending' => [], 'done' => []];
        foreach ($labs as $lab) {
            $groups[in_array($lab['status'], self::RESPONSE_STATUSES_DONE, true) ? 'done' : 'pending'][] = $lab;
        }
        usort($groups['pending'], $byName);
        usort($groups['done'], $byName);

        // A draft can carry a date too, but only a submitted response has a "responded on"
        $respondedOn = fn (array $lab): string => in_array($lab['status'], self::RESPONSE_STATUSES_DONE, true)
            ? (string) Pt_Commons_DateUtility::humanReadableDateFormat($lab['responded_on'] ?? '')
            : '';
        $showDate = false;
        foreach ($groups['done'] as $lab) {
            $showDate = $showDate || $respondedOn($lab) !== '';
        }
        $columns = $showDate ? 5 : 4;

        $cell = 'padding:6px 10px;border:1px solid #d9dee5;text-align:left;vertical-align:top;';
        $head = $cell . 'background:#f4f6f9;font-weight:bold;';
        $groupHead = $cell . 'background:#eef1f5;font-weight:bold;';

        $html = '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin:8px 0;">'
            . '<thead><tr>'
            . "<th style=\"$head\">" . $e(self::translate('Participant ID')) . '</th>'
            . "<th style=\"$head\">" . $e(self::translate('Participant name')) . '</th>'
            . "<th style=\"$head\">" . $e(self::translate('Country')) . '</th>'
            . "<th style=\"$head\">" . $e(self::translate('Response')) . '</th>'
            . ($showDate ? "<th style=\"$head\">" . $e(self::translate('Responded on')) . '</th>' : '')
            . '</tr></thead><tbody>';

        $titles = [
            'pending' => self::translate('Still to respond (%s)'),
            'done'    => self::translate('Responded (%s)'),
        ];
        $labelGroups = $groups['pending'] !== [] && $groups['done'] !== [];
        foreach ($groups as $group => $rows) {
            if ($labelGroups) {
                $html .= "<tr><td colspan=\"$columns\" style=\"$groupHead\">"
                    . $e(sprintf($titles[$group], count($rows))) . '</td></tr>';
            }
            foreach ($rows as $lab) {
                $html .= '<tr>'
                    . "<td style=\"$cell\">" . $e($lab['id']) . '</td>'
                    . "<td style=\"$cell\">" . $e($lab['name']) . '</td>'
                    . "<td style=\"$cell\">" . $e($lab['country']) . '</td>'
                    . "<td style=\"$cell\">" . ($statuses[$lab['status']] ?? $notYet) . '</td>'
                    . ($showDate ? "<td style=\"$cell\">" . $e($respondedOn($lab)) . '</td>' : '')
                    . '</tr>';
            }
        }
        return $html . '</tbody></table>';
    }

    /**
     * Fill one recipient's values into a subject or body. Shared by the send
     * and the preview so a previewed message is byte-identical to what goes
     * out. {{ key }} is matched case- and space-insensitively; an unknown key
     * is left as typed so the preview shows the typo instead of hiding it.
     *
     * One pass over the text, both {{key}} and ##TOKEN## forms together, so a
     * value that happens to contain a token (a lab named "##NAME##") is never
     * filled in a second time. The message is HTML, so values are escaped
     * there ("GSMC & KEMH"); the subject is plain text and takes them as is.
     *
     * A manager's email about several labs always carries the table: where
     * {{lab_table}} is, else right after the paragraph that first mentions the
     * labs ("listed below" has to be true), else at the end.
     * "({{participant_id}})" would only say "(listed below)" again there, so
     * it goes, brackets and all.
     *
     * A greeting is to a person, so in a manager's or PTCC's email
     * "Dear {{participant_name}}" greets them by name, not their lab's.
     */
    public static function mailMerge(string $text, array $pt, bool $isSubject = false): string
    {
        $values = self::mailMergeValues($pt, $isSubject);
        $multiLab = self::isMultiLab($pt);
        if (!$isSubject && ($multiLab || stripos($text, 'lab_table') !== false)) {
            $values['lab_table'] = self::labTableHtml($pt);
        }
        if (in_array($pt['role'] ?? '', self::MANAGER_ROLES, true)) {
            $text = preg_replace(
                // Formatting may sit between them: "Dear <b>{{participant_name}}</b>"
                '/(?<!\p{L})((?:' . self::GREETINGS . ')(?:\s|&nbsp;|' . self::INLINE_TAG . ')+)\{\{\s*participant_name\s*\}\}/iu',
                '$1{{recipient_name}}',
                $text
            );
        }

        if ($multiLab) {
            // Any formatting tags inside the brackets stay, so the HTML stays balanced
            $text = preg_replace('/\s*\((\s*(?:' . self::INLINE_TAG . '\s*)*)\{\{\s*participant_id\s*\}\}((?:\s*' . self::INLINE_TAG . ')*)\s*\)/i', '$1$2', $text);
            if (!$isSubject) {
                $text = self::placeLabTable($text);
            }
        }

        $legacy = [];
        foreach (self::MAIL_MERGE_FIELDS as $key => $token) {
            if ($token !== null) {
                $legacy[$token] = $key;
            }
        }

        $usedTable = false;
        $text = preg_replace_callback('/\{\{\s*([A-Za-z_]+)\s*\}\}|##[A-Z_]+##/', function ($m) use ($values, $legacy, $isSubject, &$usedTable) {
            $key = isset($m[1]) ? strtolower($m[1]) : ($legacy[$m[0]] ?? null);
            if ($key === null || !array_key_exists($key, $values)) {
                return $m[0];
            }
            if ($key === 'lab_table') {
                $usedTable = true;
                return $values[$key];
            }
            return $isSubject ? $values[$key] : htmlspecialchars($values[$key], ENT_QUOTES, 'UTF-8');
        }, $text);

        if ($multiLab && !$isSubject && !$usedTable) {
            $text .= $values['lab_table'];
        }
        return $text;
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
     *   'passed'        — enrolled responders with final_result Pass
     *   'failed'        — enrolled responders with final_result Fail
     *                     (unsatisfactory performance)
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
        $badStatuses = ['hard_bounce', 'invalid_domain', 'invalid_syntax', 'login_only'];
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
                if ($scope === 'passed' || $scope === 'failed') {
                    // final_result=2 was historically stamped on non-responders,
                    // so only count a Fail that came from a submitted response.
                    $sql->where('spm.final_result = ?', $scope === 'passed' ? 1 : 2)
                        ->where("spm.response_status = 'responded'")
                        ->where("(spm.is_excluded IS NULL OR spm.is_excluded <> 'yes')");
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

    /**
     * Saves an email from Email Participants. With $scheduledAt it waits for
     * the dispatch-scheduled-emails job; without it, it is queued now.
     *
     * @return array{id: int, queued: int, invalid: string[]}
     */
    public function saveParticipantEmail(array $data, ?string $scheduledAt, string $adminEmail): array
    {
        $emailParticipantDb = new Application_Model_DbTable_EmailParticipants();
        $id = $emailParticipantDb->createEmail($data, $scheduledAt, $adminEmail);
        if ($scheduledAt !== null) {
            return ['id' => $id, 'queued' => 0, 'invalid' => []];
        }
        return ['id' => $id] + $this->queueParticipantEmail($id);
    }

    /**
     * Works out the recipients of a saved email and queues one message each,
     * linked back to it. The caller must first have moved the email to
     * 'dispatching', which is what stops it being queued twice. Reads no
     * session, so the dispatch job can call it.
     *
     * @return array{queued: int, invalid: string[]}
     */
    public function queueParticipantEmail(int $id): array
    {
        $emailParticipantDb = new Application_Model_DbTable_EmailParticipants();
        $email = $emailParticipantDb->fetchEmail($id);
        if ($email === null || $email['status'] !== 'dispatching') {
            return ['queued' => 0, 'invalid' => []];
        }

        $data = [
            'subject'   => (string) $email['subject'],
            'message'   => (string) $email['content'],
            'sendMail'  => array_filter(explode(',', (string) $email['receivers'])),
            'shipments' => array_filter(explode(',', (string) $email['shipment_code'])),
            'managerDelivery' => $email['manager_delivery'] ?? null,
            'labFilter' => $email['lab_filter'] ?? null,
            'ccDataManagers' => !empty($email['cc_data_managers']),
        ];

        try {
            // Validated, domain-skipped and de-duplicated — same call the preview
            // screen makes, so what was previewed is what gets queued.
            $resolved = $this->resolveMailRecipients($data);

            $commonServices = new Application_Service_Common();
            $mail = Application_Service_Common::getMailSettings();
            $queued = 0;
            foreach ($resolved['recipients'] as $pt) {
                // The standing config Cc rides along with this row's data managers
                $ccList = $pt['cc'] ?? [];
                if ($mail['cc'] !== '') {
                    array_unshift($ccList, $mail['cc']);
                }

                $tempId = $commonServices->insertTempMail(
                    $pt['email'],
                    implode(',', $ccList),
                    $mail['bcc'],
                    self::mailMerge($data['subject'], $pt, true),
                    self::mailMerge($data['message'], $pt),
                    $mail['fromEmail'],
                    $mail['fromName'],
                    null,
                    null,
                    $id
                );
                if ($tempId) {
                    $queued++;
                }
            }
        } catch (Throwable $e) {
            Pt_Commons_LoggerUtility::logError("Email Participants: queuing email {$id} failed: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $emailParticipantDb->changeStatus($id, ['dispatching'], 'failed', null, [
                'dispatched_at'  => new Zend_Db_Expr('NOW()'),
                'failure_reason' => 'Could not work out the recipients: ' . $e->getMessage(),
            ]);
            return ['queued' => 0, 'invalid' => []];
        }

        $emailParticipantDb->changeStatus($id, ['dispatching'], $queued > 0 ? 'queued' : 'failed', null, [
            'dispatched_at'  => new Zend_Db_Expr('NOW()'),
            'queued_count'   => $queued,
            'failure_reason' => $queued > 0 ? null : 'No recipients: the selected shipments have no one in the chosen audiences with a usable email address.',
        ]);

        return ['queued' => $queued, 'invalid' => $resolved['invalid']];
    }

    /**
     * Pause, resume, cancel or send now. Each works on an email still waiting
     * for its time, and pause, resume and cancel also work on one partway out.
     * Pausing that one holds its unsent messages in temp_mail; cancelling
     * cancels them. A message the mail job has already picked up still goes.
     *
     * @return array{ok: bool, code: string, count?: int} code names the outcome
     *         for the controller to word; count is the number of messages moved
     */
    public function changeParticipantEmailState(int $id, string $action, string $adminEmail): array
    {
        $emailParticipantDb = new Application_Model_DbTable_EmailParticipants();
        $email = $emailParticipantDb->fetchEmail($id);
        if ($email === null) {
            return ['ok' => false, 'code' => 'missing'];
        }
        $inFlight = !empty($email['dispatched_at']);
        $stale = ['ok' => false, 'code' => 'stale'];

        switch ($action) {
            case 'pause':
                if (!$emailParticipantDb->changeStatus($id, $inFlight ? ['queued'] : ['scheduled'], 'paused', $adminEmail)) {
                    return $stale;
                }
                if ($inFlight) {
                    $held = $emailParticipantDb->moveQueuedMessages($id, ['pending'], 'held');
                    return ['ok' => true, 'code' => 'paused_held', 'count' => $held];
                }
                return ['ok' => true, 'code' => 'paused'];

            case 'resume':
                if ($inFlight) {
                    if (!$emailParticipantDb->changeStatus($id, ['paused'], 'queued', $adminEmail)) {
                        return $stale;
                    }
                    $released = $emailParticipantDb->moveQueuedMessages($id, ['held'], 'pending');
                    return ['ok' => true, 'code' => 'resumed_released', 'count' => $released];
                }
                if (!$emailParticipantDb->changeStatus($id, ['paused'], 'scheduled', $adminEmail)) {
                    return $stale;
                }
                return ['ok' => true, 'code' => 'resumed'];

            case 'cancel':
                $from = $inFlight ? ['queued', 'paused'] : ['scheduled', 'paused'];
                if (!$emailParticipantDb->changeStatus($id, $from, 'cancelled', $adminEmail)) {
                    return $stale;
                }
                if ($inFlight) {
                    $cancelled = $emailParticipantDb->moveQueuedMessages($id, ['pending', 'held'], 'cancelled');
                    return ['ok' => true, 'code' => 'cancelled_messages', 'count' => $cancelled];
                }
                return ['ok' => true, 'code' => 'cancelled'];

            case 'send-now':
                if ($inFlight || !$emailParticipantDb->changeStatus($id, ['scheduled', 'paused'], 'dispatching', $adminEmail)) {
                    return $stale;
                }
                $result = $this->queueParticipantEmail($id);
                return $result['queued'] > 0
                    ? ['ok' => true, 'code' => 'queued', 'count' => $result['queued']]
                    : ['ok' => false, 'code' => 'no_recipients'];
        }
        return ['ok' => false, 'code' => 'unknown_action'];
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

    /**
     * File types an admin may distribute to participants. Anything else —
     * notably scripts — is rejected server-side regardless of what the
     * browser's accept filter allowed through.
     */
    public const PARTICIPANT_FILE_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx',
        'png', 'jpg', 'jpeg', 'gif', 'txt', 'zip',
    ];

    /**
     * Resolves a temp-upload batch id to its directory under DOWNLOADS_FOLDER
     * (outside the webroot). Returns null for ids that fail the character
     * whitelist, so a crafted temp_id can never escape the temp root.
     */
    public static function filesForParticipantsTempPath($tempId): ?string
    {
        $tempId = basename(trim((string) $tempId));
        if ($tempId === '' || !preg_match('/^[a-zA-Z0-9_.\-]+$/', $tempId)) {
            return null;
        }
        return DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR
            . 'temp-participant-files' . DIRECTORY_SEPARATOR . $tempId;
    }

    private static function purgeStaleParticipantTempDirs(): void
    {
        $root = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'temp-participant-files';
        if (!is_dir($root)) {
            return;
        }
        foreach (scandir($root) as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($path) && filemtime($path) < (time() - 86400)) {
                Pt_Commons_MiscUtility::removeDirectory($path);
            }
        }
    }

    public function uploadFilesForParticipants(array $files, $tempId)
    {
        self::purgeStaleParticipantTempDirs();

        $tempDir = self::filesForParticipantsTempPath($tempId);
        if ($tempDir === null) {
            throw new Exception('Invalid upload reference.');
        }

        if (!is_dir($tempDir) && !Pt_Commons_MiscUtility::makeDirectory($tempDir)) {
            throw new Exception('Unable to create temporary upload directory.');
        }

        $uploadedFiles = [];
        $rejectedFiles = [];

        foreach ((array) $files['name'] as $index => $originalName) {

            if ($files['error'][$index] !== UPLOAD_ERR_OK) {
                $rejectedFiles[] = $originalName;
                continue;
            }

            $extension = Pt_Commons_MiscUtility::getFileExtension($originalName);
            if (!in_array($extension, self::PARTICIPANT_FILE_EXTENSIONS, true)) {
                $rejectedFiles[] = $originalName;
                continue;
            }

            // Keep the original name (participants see it as-is), reduced to a
            // safe character set, deduplicated within the batch.
            $baseName = Pt_Commons_MiscUtility::sanitizeFilename(
                pathinfo($originalName, PATHINFO_FILENAME),
                '/[^a-zA-Z0-9_\- ]/'
            );
            $baseName = trim(preg_replace('/[\s\-]+/', '-', $baseName), '-_');
            if ($baseName === '') {
                $baseName = uniqid('file-');
            }

            $newFileName = $baseName . '.' . $extension;
            $counter = 1;
            while (file_exists($tempDir . DIRECTORY_SEPARATOR . $newFileName)) {
                $newFileName = $baseName . '-' . $counter++ . '.' . $extension;
            }

            $destination = $tempDir . DIRECTORY_SEPARATOR . $newFileName;
            if (!move_uploaded_file($files['tmp_name'][$index], $destination)) {
                throw new Exception('Unable to save uploaded file: ' . $originalName);
            }

            $uploadedFiles[] = [
                'original_name' => $originalName,
                'file_name'     => $newFileName,
                'temp_id'       => basename($tempDir),
                'extension'     => $extension,
                'size'          => $files['size'][$index],
            ];
        }

        return [
            'files'    => $uploadedFiles,
            'rejected' => $rejectedFiles,
        ];
    }

    /**
     * Copies a confirmed temp batch into downloads/<unique_identifier>/ for
     * every selected participant — the folder that /participant/file-downloads
     * and the mobile API list from. The temp batch is removed only when every
     * copy succeeded.
     */
    public function confirmTemporaryFiles($tempId, $participantIds)
    {
        $result = [
            'status'       => false,
            'participants' => 0,
            'copied'       => 0,
            'failed'       => 0,
            'message'      => '',
        ];

        $tempDir = self::filesForParticipantsTempPath($tempId);
        if ($tempDir === null || !is_dir($tempDir)) {
            $result['message'] = 'The uploaded files could not be found. Please upload them again.';
            return $result;
        }

        $fileNames = [];
        foreach (scandir($tempDir) as $fileName) {
            if (is_file($tempDir . DIRECTORY_SEPARATOR . $fileName)) {
                $fileNames[] = $fileName;
            }
        }
        if (empty($fileNames)) {
            $result['message'] = 'No files were uploaded. Please upload files before submitting.';
            return $result;
        }

        $ids = array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $participantIds)),
            'ctype_digit'
        )));
        if (empty($ids)) {
            $result['message'] = 'No participants were selected.';
            return $result;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from('participant', ['participant_id', 'unique_identifier'])
                ->where('participant_id IN (?)', $ids)
        );

        foreach ($rows as $row) {
            $uniqueIdentifier = basename(trim((string) $row['unique_identifier']));
            if ($uniqueIdentifier === '' || !preg_match('/^[a-zA-Z0-9_.\-]+$/', $uniqueIdentifier)) {
                $result['failed'] += count($fileNames);
                continue;
            }

            $participantDir = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . $uniqueIdentifier;
            if (!is_dir($participantDir) && !Pt_Commons_MiscUtility::makeDirectory($participantDir)) {
                $result['failed'] += count($fileNames);
                continue;
            }

            $result['participants']++;
            foreach ($fileNames as $fileName) {
                $copied = copy(
                    $tempDir . DIRECTORY_SEPARATOR . $fileName,
                    $participantDir . DIRECTORY_SEPARATOR . $fileName
                );
                if ($copied) {
                    $result['copied']++;
                } else {
                    $result['failed']++;
                }
            }
        }

        $missing = count($ids) - count($rows);
        $result['status'] = $result['copied'] > 0 && $result['failed'] === 0 && $missing === 0;

        if ($result['status']) {
            Pt_Commons_MiscUtility::removeDirectory($tempDir);
            $result['message'] = count($fileNames) . ' file(s) distributed to '
                . $result['participants'] . ' participant(s) successfully';
        } else {
            $result['message'] = 'Distributed ' . $result['copied'] . ' file cop(ies); '
                . ($result['failed'] + ($missing * count($fileNames)))
                . ' failed. Please review and try again.';
        }

        return $result;
    }

    public function getTemporaryFiles($tempId)
    {
        $tempDir = self::filesForParticipantsTempPath($tempId);
        if ($tempDir === null || !is_dir($tempDir)) {
            return [];
        }

        $files = [];
        foreach (scandir($tempDir) as $fileName) {
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
            if ($fileName === '.' || $fileName === '..' || !is_file($filePath)) {
                continue;
            }
            $files[] = [
                'temp_id'       => basename($tempDir),
                'file_name'     => $fileName,
                'original_name' => $fileName,
                'size'          => filesize($filePath),
            ];
        }

        return $files;
    }

    public function getParticipantsForFiles($parameters)
    {
        $participantDb = new Application_Model_DbTable_Participants();
        return $participantDb->fetchParticipantsForFiles($parameters);
    }

    public function removeTemporaryFiles($tempId, $fileName)
    {
        $tempDir = self::filesForParticipantsTempPath($tempId);
        if ($tempDir === null) {
            return false;
        }
        if (!is_dir($tempDir)) {
            return true;
        }

        $fileName = basename(trim((string) $fileName));
        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            return false;
        }

        $filePath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
        if (is_file($filePath)) {
            unlink($filePath);
        }

        return true;
    }
}
