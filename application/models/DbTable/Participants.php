<?php

use Application_Service_Common as Common;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Pt_Commons_MiscUtility as MiscUtility;

class Application_Model_DbTable_Participants extends Zend_Db_Table_Abstract
{
    protected $_name = 'participant';
    protected $_primary = 'participant_id';
    // Initial onboarding password used when bulk-importing data managers from
    // a spreadsheet whose password column is blank. Stored only as a bcrypt
    // hash; users are expected to change it on first login. NOSONAR
    protected $_defaultPassword = 'ept1@)(*&^'; // NOSONAR
    protected $_defaultPasswordHash = null;

    public function __construct()
    {
        parent::__construct();
        $this->_defaultPasswordHash = Common::passwordHash($this->_defaultPassword);
    }

    /**
     * SQL expression for a participant's display name: lab_name when the
     * participant is an organisation (individual='no'), else first+last.
     */
    public static function participantNameExpr(string $alias = 'p'): string
    {
        // individual='yes' → first+last; everything else (including NULL/empty)
        // is treated as a lab participant. Lab participants canonically live in
        // lab_name; fall back to first_name for legacy rows that pre-date the
        // lab_name column and haven't been backfilled yet.
        return "TRIM(CASE WHEN {$alias}.individual = 'yes' "
            . "THEN CONCAT(COALESCE({$alias}.first_name, ''), ' ', COALESCE({$alias}.last_name, '')) "
            . "ELSE COALESCE(NULLIF(TRIM({$alias}.lab_name), ''), {$alias}.first_name, '') END)";
    }

    /**
     * GROUP_CONCAT-aggregated participant display name; honours the same
     * lab_name vs first+last rule as participantNameExpr().
     */
    public static function participantNameGroupConcatExpr(string $alias = 'p'): string
    {
        $name = self::participantNameExpr($alias);
        return "GROUP_CONCAT(DISTINCT $name ORDER BY {$alias}.first_name SEPARATOR ', ')";
    }

    /**
     * PHP-side mirror of participantNameExpr(): given a participant row
     * (associative array with individual / lab_name / first_name / last_name),
     * return the display name. Use this instead of hand-rolling the
     * lab_name-vs-first/last fallback at every call site.
     */
    public static function formatParticipantName($row): string
    {
        if (!is_array($row) && !($row instanceof ArrayAccess)) {
            return '';
        }
        $individual = isset($row['individual']) ? strtolower(trim((string) $row['individual'])) : '';
        if ($individual === 'yes') {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            return $name;
        }
        $lab = trim((string) ($row['lab_name'] ?? ''));
        if ($lab !== '') {
            return $lab;
        }
        // Legacy rows: lab name was stored in first_name before lab_name existed.
        return trim((string) ($row['first_name'] ?? ''));
    }

    public function getParticipantsByUserSystemId($userSystemId)
    {
        $sql = $this->getAdapter()->select()->from(['p' => $this->_name], [
            '*',
            'participant_name' => new Zend_Db_Expr(self::participantNameExpr('p')),
        ])
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")])
            ->joinLeft(['c' => 'countries'], 'p.country=c.id', ['country_name' => 'iso_name'])
            ->where('pmm.dm_id = ?', $userSystemId)
            //->where("p.status = 'active'")
            ->group('p.participant_id')
            ->order('participant_name ASC');
        return $this->getAdapter()->fetchAll($sql);
    }

    public function checkParticipantAccess($participantId, $dmId = '', $comingFrom = '')
    {
        if ($comingFrom != 'API') {
            $authNameSpace = new Zend_Session_Namespace('datamanagers');
            $dmId = $authNameSpace->dm_id;
        }

        $sql = $this->getAdapter()->select()
            ->from(['pmm' => 'participant_manager_map'])
            ->where('pmm.participant_id = ?', $participantId);

        if (isset($dmDb) && !empty($dmDb) && $dmDb != '') {
            $sql = $sql->where('pmm.dm_id = ?', $dmId);
        }
        $row = $this->getAdapter()->fetchRow($sql);
        return $row !== false;
    }

    public function getParticipant($partSysId)
    {
        $sQuery = $this->getAdapter()->select()->from(['p' => $this->_name])
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")])
            ->joinLeft(['pe' => 'participant_enrolled_programs_map'], 'pe.participant_id=p.participant_id', ['enrolled_prog' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pe.ep_id SEPARATOR ', ')")])
            ->joinLeft(['site' => 'r_site_type'], 'site.r_stid=p.site_type', ['siteType' => 'site_type'])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['iso_name'])
            ->where('p.participant_id = ?', $partSysId)
            ->group('p.participant_id');
        // $authNameSpace = new Zend_Session_Namespace('datamanagers');
        // if (isset($authNameSpace->mappedParticipants) && !empty($authNameSpace->mappedParticipants) && empty($partSysId)) {
        //     $sQuery = $sQuery
        //         ->where("pmm.participant_id IN(" . $authNameSpace->mappedParticipants . ")");
        // }
        return $this->getAdapter()->fetchRow($sQuery);
    }

    public function getAllParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['unique_identifier', new Zend_Db_Expr(self::participantNameExpr('p')), 'iso_name', 'p.state', 'p.district', 'email', 'status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'participant_id';

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                // Special handling for columns index 7 (Data Manager search) and index 8 (Shipments search)
                if ($i == 7 || $i == 8) {
                    // Skip here — handled separately after query is built
                    continue;
                }
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['p' => $this->_name], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id'), 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.state', 'p.district', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr(self::participantNameExpr('p')), 'mapCount' => new Zend_Db_Expr('COUNT(spm.map_id)')])
            ->join(['c' => 'countries'], 'c.id=p.country')
            ->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=p.participant_id', [])
            ->group('p.participant_id');

        if (isset($parameters['withStatus']) && $parameters['withStatus'] != '') {
            $sQuery = $sQuery->where('p.status = ? ', $parameters['withStatus']);
        }
        // PTCC participant-side: scope the list to participants mapped to the
        // logged-in DM. Suppress this scope when an admin is also logged in
        // (active session or mid-impersonation) — /admin/participants must
        // show every participant, and a stale `datamanagers` namespace from
        // an impersonation that didn't fully tear down was previously dropping
        // the admin's view to whatever DM was last impersonated.
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        $adminAuthNameSpace = new Zend_Session_Namespace('administrators');
        if (!empty($authNameSpace->dm_id) && empty($adminAuthNameSpace->admin_id)) {
            $sQuery = $sQuery->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }

        if (isset($parameters['pid']) && !empty($parameters['pid'])) {
            $pid = (is_array($parameters['pid'])) ? implode(',', $parameters['pid']) : $parameters['pid'];
            $sQuery = $sQuery->where('p.institute_name IN (?)', $pid);
        }
        if (isset($parameters['country']) && !empty($parameters['country'])) {
            $cid = (is_array($parameters['country'])) ? $parameters['country'] : explode(',', $parameters['country']);
            $sQuery = $sQuery->where('p.country IN (?)', $cid);
        }
        if (isset($parameters['pstatus']) && !empty($parameters['pstatus'])) {
            $sQuery = $sQuery->where('p.status LIKE ?', $parameters['pstatus']);
        }
        if (isset($parameters['mappingFilter']) && $parameters['mappingFilter'] !== '') {
            if ($parameters['mappingFilter'] === 'unmapped') {
                // Inactive + unmapped participants aren't interesting — only flag active ones
                $sQuery = $sQuery->where("p.status = 'active'")
                    ->where('NOT EXISTS (SELECT 1 FROM participant_manager_map pmm2 WHERE pmm2.participant_id = p.participant_id)');
            } elseif ($parameters['mappingFilter'] === 'mapped') {
                $sQuery = $sQuery->where('EXISTS (SELECT 1 FROM participant_manager_map pmm2 WHERE pmm2.participant_id = p.participant_id)');
            }
        }
        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        // Apply Data Manager filter via subquery using shipment_participant_map
        if (isset($parameters['bSearchable_7']) && $parameters['bSearchable_7'] == 'true' && $parameters['sSearch_7'] != '') {
            $searchValue = $parameters['sSearch_7'];

            // Subquery: get participant_ids linked to matching data managers
            $dmSubquery = $this->getAdapter()->select()
                ->from(['pmm' => 'participant_manager_map'], ['pmm.participant_id'])
                ->join(['d' => 'data_manager'], 'd.dm_id = pmm.dm_id', [])
                ->where(
                    "d.primary_email LIKE ? OR d.first_name LIKE ? OR d.last_name LIKE ? OR CONCAT(d.first_name, ' ', d.last_name) LIKE ?",
                    '%' . $searchValue . '%'
                );

            $sQuery = $sQuery->where('p.participant_id IN (?)', new Zend_Db_Expr('(' . $dmSubquery->assemble() . ')'));
        }

        // Apply Shipment filter via subquery using shipment_participant_map
        if (isset($parameters['bSearchable_8']) && $parameters['bSearchable_8'] == 'true' && $parameters['sSearch_8'] != '') {
            $searchValue = $parameters['sSearch_8'];

            // Subquery: get participant_ids linked to matching shipments
            $shipmentSubquery = $this->getAdapter()->select()
                ->from(['spm3' => 'shipment_participant_map'], ['spm3.participant_id'])
                ->join(['s' => 'shipment'], 's.shipment_id = spm3.shipment_id', [])
                ->where("s.shipment_code LIKE ?", '%' . $searchValue . '%');

            $sQuery = $sQuery->where('p.participant_id IN (?)', new Zend_Db_Expr('(' . $shipmentSubquery->assemble() . ')'));
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
        $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;

        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /* Lightweight count of mapped DMs per participant on this page — the full list
           is loaded on demand via AJAX when the user expands a row. */
        $dmCountByParticipant = [];
        $shipmentCountByParticipant = [];
        $pageParticipantIds = array_column($rResult, 'participant_id');
        if (!empty($pageParticipantIds)) {
            $countSelect = $this->getAdapter()->select()
                ->from('participant_manager_map', ['participant_id', 'cnt' => new Zend_Db_Expr('COUNT(*)')])
                ->where('participant_id IN (?)', $pageParticipantIds)
                ->group('participant_id');
            foreach ($this->getAdapter()->fetchAll($countSelect) as $cntRow) {
                $dmCountByParticipant[$cntRow['participant_id']] = (int) $cntRow['cnt'];
            }
            $shipCountSelect = $this->getAdapter()->select()
                ->from('shipment_participant_map', ['participant_id', 'cnt' => new Zend_Db_Expr('COUNT(*)')])
                ->where('participant_id IN (?)', $pageParticipantIds)
                ->group('participant_id');
            foreach ($this->getAdapter()->fetchAll($shipCountSelect) as $cntRow) {
                $shipmentCountByParticipant[$cntRow['participant_id']] = (int) $cntRow['cnt'];
            }
        }

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];
        $adminSession = new Zend_Session_Namespace('administrators');
        if ($adminSession->privileges != '') {
            $pstatus = false;
            $privileges = explode(',', $adminSession->privileges);
        } else {
            $pstatus = true;
            $privileges = [];
        }
        $deleteStatus = false;
        if (!$pstatus && in_array('delete-participants', $privileges)) {
            $deleteStatus = true;
        }
        $translator = Zend_Registry::get('translate');
        foreach ($rResult as $aRow) {
            $edit = '';
            $delete = '';
            $row = [];
            $row[] = $aRow['unique_identifier'];
            $row[] = $aRow['participantName'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['state'];
            $row[] = $aRow['district'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['status']);

            /* Data Managers column — compact button only. The actual DM list (which can
               be large — up to ~100 per participant) is loaded on demand via AJAX and
               expanded as a DataTables child row. */
            $dmCount = isset($dmCountByParticipant[$aRow['participant_id']]) ? $dmCountByParticipant[$aRow['participant_id']] : 0;
            if ($dmCount === 0) {
                // Only flag the row as "unmapped" if the participant is active —
                // inactive + unmapped isn't actionable, so don't highlight it.
                $unmappedClass = (strtolower($aRow['status'] ?? '') === 'active') ? ' unmapped-tag' : '';
                $row[] = '<em class="text-muted' . $unmappedClass . '" style="font-size:11px;">' . $translator->_('None mapped') . '</em>';
            } else {
                $label = sprintf($translator->_('View (%d)'), $dmCount);
                $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs toggle-dm-row" data-participant-id="' . (int) $aRow['participant_id'] . '"><i class="icon-user"></i> ' . $label . '</a>';
            }

            /* Shipments column — same on-demand pattern as Data Managers. */
            $shipCount = isset($shipmentCountByParticipant[$aRow['participant_id']]) ? $shipmentCountByParticipant[$aRow['participant_id']] : 0;
            if ($shipCount === 0) {
                $row[] = '<em class="text-muted" style="font-size:11px;">' . $translator->_('None') . '</em>';
            } else {
                $label = sprintf($translator->_('View (%d)'), $shipCount);
                $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs toggle-shipments-row" data-participant-id="' . (int) $aRow['participant_id'] . '"><i class="icon-truck"></i> ' . $label . '</a>';
            }

            if (isset($parameters['from']) && $parameters['from'] == 'participant') {
                $edit = '<a href="/participant/edit-participant/id/' . $aRow['participant_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            } else {
                $edit = '<a href="/admin/participants/edit/id/' . $aRow['participant_id'] . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';
            }
            if ($aRow['mapCount'] == 0 && $deleteStatus) {
                //$delete = '<a href="javascript:void(0);" onclick="deleteParticipant(' . $aRow['participant_id'] . ');" class="btn btn-danger btn-xs" style="margin-right: 2px;"><i class="icon-trash"></i> Delete</a>';
            }
            $row[] = $edit . $delete;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function updateParticipant($params)
    {
        $normalizedPid = MiscUtility::normalizeUniqueId($params['pid'] ?? null);
        if ($normalizedPid === null) {
            throw new InvalidArgumentException("Participant ID '" . ($params['pid'] ?? '') . "' is invalid — must contain at least 3 letters or numbers.");
        }
        $currentParticipantId = (int) ($params['participantId'] ?? 0);
        $existing = $this->fetchRow(
            $this->select()
                ->where('unique_identifier = ?', $normalizedPid)
                ->where('participant_id <> ?', $currentParticipantId)
        );
        if ($existing) {
            throw new InvalidArgumentException("Participant ID '{$normalizedPid}' already exists for another participant.");
        }
        $params['pid'] = $normalizedPid;

        $firstName = isset($params['pfname']) && $params['pfname'] != '' ? $params['pfname'] : null;
        $lastName = isset($params['plname']) && $params['plname'] != '' ? $params['plname'] : null;
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        $data = [
            'unique_identifier' => $params['pid'],
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'country' => $params['country'],
            'region' => $params['region'],
            'state' => $params['state'],
            'district' => $params['district'],
            'city' => $params['city'],
            'zip' => $params['zip'],
            'long' => $params['long'],
            'lat' => $params['lat'],
            'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'additional_email' => $params['additionalEmail'],
            'contact_name' => $params['contactname'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'testing_volume' => $params['testingVolume'],
            'funding_source' => $params['fundingSource'],
            'site_type' => $params['siteType'],
            'anc' => $params['anc'],
            'pepfar_id' => $params['pepfarID'],
            'updated_on' => new Zend_Db_Expr('now()'),
        ];
        if (isset($params['comingFrom']) && $params['comingFrom'] == 'participant') {
            $data['force_profile_updation'] = 0;
        }

        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }

        if (isset($params['status']) && $params['status'] != '' && $params['status'] != null) {
            $data['status'] = $params['status'];
        }

        if (isset($authNameSpace->dm_id) && $authNameSpace->dm_id != '') {
            $data['updated_by'] = $authNameSpace->dm_id;
        } else {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            if (isset($authNameSpace->primary_email) && $authNameSpace->primary_email != '') {
                $data['updated_by'] = $authNameSpace->primary_email;
            }
        }
        /* get previous unique id for changes */
        $exist = $this->fetchRow($this->select()->where('participant_id = ?', $params['participantId']));
        $noOfRows = $this->update($data, $this->getAdapter()->quoteInto('participant_id = ?', $params['participantId']));
        //Check profile update
        if (isset($authNameSpace->force_profile_updation) && trim($authNameSpace->force_profile_updation) > 0) {
            $profileUpdate = $this->checkParticipantsProfileUpdateByUserSystemId($authNameSpace->dm_id);
            if (count($profileUpdate) > 0) {
                $authNameSpace->profile_updation_pid = $profileUpdate[0]['participant_id'];
            } else {
                $authNameSpace->force_profile_updation = 0;
                $authNameSpace->profile_updation_pid = '';
            }
        }

        $db = Zend_Db_Table_Abstract::getAdapter();

        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != '') {
            $db->delete('participant_enrolled_programs_map', $db->quoteInto('participant_id = ?', $params['participantId']));
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', ['ep_id' => $epId, 'participant_id' => $params['participantId']]);
            }
        }
        $dmDb = new Application_Model_DbTable_DataManagers();
        $configDb = new Application_Model_DbTable_GlobalConfig();

        if ((isset($params['dmPassword']) && !empty($params['dmPassword'])) && isset($params['pemail']) && !empty($params['pemail'])) {
            $globalDb = new Application_Model_DbTable_GlobalConfig();
            $prefix = $globalDb->getValue('participant_login_prefix');

            $dmData = [
                'data_manager_type' => 'participant',
                'primary_email' => $params['pemail'],
                'first_name' => $params['pfname'],
                'last_name' => $params['plname'],
                'institute' => $params['instituteName'],
                'phone' => $params['pphone2'],
                'country_id' => $params['country'],
                'mobile' => $params['pphone1'],
                'updated_on' => new Zend_Db_Expr('now()'),
                'updated_by' => $authNameSpace->admin_id,
            ];
            if (isset($params['dmPassword']) && !empty($params['dmPassword'])) {
                $dmData['password'] = Common::passwordHash($params['dmPassword']);
            }
            $dmDb->update($dmData, $db->quoteInto('participant_ulid = ?', $exist['ulid']));
        }
        if (isset($params['dataManager']) && $params['dataManager'] != '') {
            $params['participantsList'][] = $params['participantId'];
            $dmDb->dmParticipantMap($params, $params['dataManager'], false, true);
        }

        if (isset($params['scheme']) && $params['scheme'] != '') {
            $enrollDb = new Application_Model_DbTable_Enrollments();
            $enrollDb->enrollParticipantToSchemes($params['participantId'], $params['scheme']);
        }

        if ($noOfRows > 0) {
            $name = "$firstName $lastName";
            $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Updated participant - $userName", 'participants');
        }

        return $noOfRows;
    }

    public function addParticipant($params)
    {

        $normalizedPid = MiscUtility::normalizeUniqueId($params['pid'] ?? null);
        if ($normalizedPid === null) {
            throw new InvalidArgumentException("Participant ID '" . ($params['pid'] ?? '') . "' is invalid — must contain at least 3 letters or numbers.");
        }
        $existing = $this->fetchRow($this->select()->where('unique_identifier = ?', $normalizedPid));
        if ($existing) {
            throw new InvalidArgumentException("Participant ID '{$normalizedPid}' already exists for another participant.");
        }
        $params['pid'] = $normalizedPid;

        $globalDb = new Application_Model_DbTable_GlobalConfig();
        $prefix = $globalDb->getValue('participant_login_prefix');
        $ulid = Pt_Commons_General::generateULID();
        $firstName = isset($params['pfname']) && $params['pfname'] != '' ? $params['pfname'] : null;
        $lastName = isset($params['plname']) && $params['plname'] != '' ? $params['plname'] : null;
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = [
            'unique_identifier' => $params['pid'],
            'ulid' => $ulid,
            'institute_name' => $params['instituteName'],
            'department_name' => $params['departmentName'],
            'address' => $params['address'],
            'country' => $params['country'],
            'region' => $params['region'],
            'state' => $params['state'],
            'district' => $params['district'],
            'city' => $params['city'],
            'zip' => $params['zip'],
            'long' => $params['long'],
            'lat' => $params['lat'],
            'shipping_address' => $params['shippingAddress'],
            'first_name' => $params['pfname'],
            'last_name' => $params['plname'],
            'mobile' => $params['pphone2'],
            'phone' => $params['pphone1'],
            'email' => $params['pemail'],
            'additional_email' => $params['additionalEmail'],
            'contact_name' => $params['contactname'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'testing_volume' => $params['testingVolume'],
            'funding_source' => $params['fundingSource'],
            'site_type' => $params['siteType'],
            'anc' => $params['anc'],
            'pepfar_id' => $params['pepfarID'],
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->primary_email,
            'status' => $params['status'],
        ];
        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }

        $participantId = $this->insert($data);

        $dmDb = new Application_Model_DbTable_DataManagers();
        $db = Zend_Db_Table_Abstract::getAdapter();
        $configDb = new Application_Model_DbTable_GlobalConfig();
        if ((isset($params['dmPassword']) && !empty($params['dmPassword'])) && isset($params['pemail']) && !empty($params['pemail'])) {
            $newDmId = $dmDb->insert([
                'primary_email' => $params['pemail'] ?? $prefix . $params['pid'],
                'participant_ulid' => $ulid,
                'data_manager_type' => 'participant',
                'password' => Common::passwordHash($params['dmPassword'] ?? 'ept1@)(*&^'),
                'first_name' => $params['pfname'],
                'last_name' => $params['plname'],
                'institute' => $params['instituteName'],
                'phone' => $params['pphone2'],
                'country_id' => $params['country'],
                'mobile' => $params['pphone1'],
                'force_password_reset' => 1,
                'status' => 'active',
                'created_on' => new Zend_Db_Expr('now()'),
                'created_by' => $authNameSpace->admin_id,
            ]);
            if ($newDmId) {
                $db = Zend_Db_Table_Abstract::getAdapter();
                $db->insert('participant_manager_map', ['dm_id' => $newDmId, 'participant_id' => $participantId]);
            }
        } else {
            if (isset($params['dataManager']) && $params['dataManager'] != '') {
                $params['participantsList'][] = $participantId;
                $dmDb->dmParticipantMap($params, $params['dataManager'], false, true);
                /* $db->delete('participant_manager_map', "participant_id = " . $participantId);
                foreach ($params['dataManager'] as $dataManager) {
                    $db->insert('participant_manager_map', array('dm_id' => $dataManager, 'participant_id' => $participantId));
                } */
            } else {
                if (isset($params['dmPassword']) && $params['dmPassword'] != '') {
                    $dmDb = new Application_Model_DbTable_DataManagers();
                    $dmDb->addQuickDm($params, $participantId);
                }
            }
        }

        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != '') {
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', ['ep_id' => $epId, 'participant_id' => $participantId]);
            }
        }

        if ($participantId > 0) {
            $name = $firstName . ' ' . $lastName;
            $userName = isset($name) != '' ? $name : $authNameSpace->primary_email;
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Added a new participant - ' . $userName, 'participants');
        }
        return $participantId;
    }

    public function addParticipantForDataManager($params)
    {
        $authNameSpace = new Zend_Session_Namespace('datamanagers');

        $data = [
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
            'contact_name' => $params['contactname'],
            'email' => $params['pemail'],
            'affiliation' => $params['partAff'],
            'network_tier' => $params['network'],
            'status' => 'pending',
            'testing_volume' => $params['testingVolume'],
            'funding_source' => $params['fundingSource'],
            'region' => $params['region'],
            'site_type' => $params['siteType'],
            'created_on' => new Zend_Db_Expr('now()'),
            'created_by' => $authNameSpace->UserID,
        ];

        if (isset($params['individualParticipant']) && $params['individualParticipant'] == 'on') {
            $data['individual'] = 'yes';
        } else {
            $data['individual'] = 'no';
        }

        $participantId = $this->insert($data);

        if (isset($params['enrolledProgram']) && $params['enrolledProgram'] != '') {
            $db = Zend_Db_Table_Abstract::getAdapter();
            $db->delete('participant_enrolled_programs_map', $db->quoteInto('participant_id = ?', $participantId));
            foreach ($params['enrolledProgram'] as $epId) {
                $db->insert('participant_enrolled_programs_map', ['ep_id' => $epId, 'participant_id' => $participantId]);
            }
        }

        if (isset($params['scheme']) && $params['scheme'] != '') {
            $enrollDb = new Application_Model_DbTable_Enrollments();
            $enrollDb->enrollParticipantToSchemes($participantId, $params['scheme']);
        }

        $db = Zend_Db_Table_Abstract::getAdapter();
        $db->insert('participant_manager_map', ['dm_id' => $authNameSpace->dm_id, 'participant_id' => $participantId]);

        $participantName = $params['pfname'] . ' ' . $params['plname'];
        $dataManager = $authNameSpace->first_name . ' ' . $authNameSpace->last_name;
        $common = new Application_Service_Common();
        $message = "Hi,<br/>  A new participant ($participantName) was added by $dataManager <br/><small>This is a system generated email. Please do not reply.</small>";
        $toMail = Common::getConfig('admin_email');
        //$fromName = Common::getConfig('admin-name');
        $common->sendMail($toMail, null, null, "New Participant Registered  ($participantName)", $message, null, 'ePT Admin');

        return $participantId;
    }

    public function fetchAllActiveParticipants()
    {
        return $this->fetchAll($this->select()->where("status='active'")->order('first_name'));
    }

    public function getSchemeWiseParticipants($schemeType)
    {
        if ($schemeType != 'all') {
            $result = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name], ['p.address', 'p.long', 'p.lat', 'p.first_name', 'p.last_name'])
                ->join(['e' => 'enrollments'], 'e.participant_id=p.participant_id')
                ->where('e.scheme_id = ?', $schemeType)
                ->where("p.status='active'")
                ->group('p.participant_id'));
        } else {
            $result = $this->fetchAll($this->select()->where("status='active'"));
        }

        return $result;
    }

    public function getEnrolledByShipmentDetails($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['first_name', 'iso_name', 'mobile', 'email', 'p.status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'participant_id';

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'], new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.*'))
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', ['sp.map_id', 'sp.created_on_user', 'sp.attributes', 'sp.final_result', 'sp.shipment_test_date', 'RESPONSE' => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date not like '0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', ['shipmentStatus' => 's.status'])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['c.iso_name'])
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id = ? ', $parameters['shipmentId']);
        }

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        //error_log($sQuery);

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = ucwords($aRow['first_name'] . ' ' . $aRow['last_name']);
            $row[] = ucwords($aRow['iso_name']);
            $row[] = $aRow['mobile'];
            $row[] = $aRow['email'];
            $row[] = ucwords($aRow['RESPONSE']);

            $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\'' . base64_encode($aRow['shipment_id']) . '\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            if (trim($aRow['created_on_user']) == '' && trim($aRow['final_result']) == '' && $aRow['shipmentStatus'] != 'finalized') {
            } else {
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

        $aColumns = ['first_name', 'iso_name', 'mobile', 'email', 'p.status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'participant_id';

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $subQuery = $this->getAdapter()->select()->from(['p' => 'participant'], [new Zend_Db_Expr('p.participant_id')])
            ->join(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [])
            ->join(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', [])
            ->where("p.status='active'");

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $subQuery = $subQuery->where('s.shipment_id = ? ', $parameters['shipmentId']);
        }

        $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.*')])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['c.iso_name'])->where("p.status='active'")->where('p.participant_id NOT IN ?', $subQuery);

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = '<input type="checkbox" class="isRequired" name="participants[]" id="' . $aRow['participant_id'] . '" value="' . base64_encode($aRow['participant_id']) . '" onclick="checkParticipantName(' . $aRow['participant_id'] . ',this)" title="Select atleast one participant">';
            $row[] = ucwords($aRow['first_name'] . ' ' . $aRow['last_name']);
            $row[] = ucwords($aRow['iso_name']);
            $row[] = $aRow['mobile'];
            $row[] = $aRow['email'];
            // $row[] = ucwords($aRow['status']);
            $row[] = 'Unenrolled';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function addParticipantManager($params, $type = null)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        try {
            $db = Zend_Db_Table_Abstract::getAdapter();

            $dataForStatistics = [];
            if (isset($_FILES['bulkMap']['tmp_name']) && !empty($_FILES['bulkMap']['tmp_name'])) {
                $common = new Application_Service_Common();
                $allowedExtensions = ['xls', 'xlsx', 'csv'];
                $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['bulkMap']['name']);
                $fileName = str_replace(' ', '-', $fileName);
                $random = MiscUtility::generateRandomString(6);
                $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileName = "$random-$fileName";
                if (in_array($extension, $allowedExtensions)) {
                    $tempDirectory = realpath(TEMP_UPLOAD_PATH);
                    if (!file_exists($tempDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                        if (move_uploaded_file($_FILES['bulkMap']['tmp_name'], $tempDirectory . DIRECTORY_SEPARATOR . $fileName)) {
                            $objPHPExcel = IOFactory::load($tempDirectory . DIRECTORY_SEPARATOR . $fileName);

                            $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

                            unset($sheetData[1]);

                            $authNameSpace = new Zend_Session_Namespace('administrators');
                            $mappedCount = 0;

                            foreach ($sheetData as $row) {

                                $row['D'] = MiscUtility::sanitizeAndValidateEmail(trim($row['D']));
                                $row['E'] = MiscUtility::sanitizeAndValidateEmail(trim($row['E']));

                                if (empty($row['B']) || empty($row['D'])) {
                                    continue;
                                }

                                // Duplications check
                                $psql = $db->select()
                                    ->from('participant')
                                    ->where('unique_identifier LIKE ?', trim((string) $row['B']));
                                $participantRow = $db->fetchRow($psql);

                                if (empty($participantRow) || $participantRow === false) {
                                    continue;
                                }

                                /* To check the duplication in data manager table */
                                $dmsql = $db->select()->from('data_manager')
                                    ->where('primary_email LIKE ?', $row['D']);
                                $dmresult = $db->fetchRow($dmsql);
                                if (empty($dmresult) || $dmresult === false) {

                                    $dataManagerData = [
                                        'first_name' => MiscUtility::cleanString($row['C']),
                                        'primary_email' => $row['D'],
                                        'secondary_email' => $row['E'],
                                        'mobile' => MiscUtility::cleanString($row['F']),
                                        'password' => $this->_defaultPasswordHash,
                                        'force_password_reset' => 0,
                                        'force_profile_check' => 0,
                                        'data_manager_type' => 'manager',
                                        'created_by' => $authNameSpace->admin_id,
                                        'created_on' => new Zend_Db_Expr('now()'),
                                        'status' => 'active',
                                    ];

                                    $db->insert('data_manager', $dataManagerData);
                                    $dmId = $db->lastInsertId();
                                } else {
                                    $dmId = $dmresult['dm_id'];
                                }

                                $participantId = $participantRow['participant_id'];

                                if ($dmId > 0) {
                                    $dmData = ['dm_id' => $dmId, 'participant_id' => $participantId];
                                    $common->insertIgnore('participant_manager_map', $dmData);
                                    $mappedCount++;
                                }
                            }
                            $authNameSpace = new Zend_Session_Namespace('administrators');
                            $auditDb = new Application_Model_DbTable_AuditLog();
                            $auditDb->addNewAuditLog("Bulk imported {$mappedCount} participant–data manager mappings", 'data-managers');
                            $alertMsg->message = 'Mapping completed successfully';
                        } else {
                            $alertMsg->message = 'Mapping import failed';
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
            } else {
                if (isset($params['datamanagerId']) && $params['datamanagerId'] != '') {
                    $dm = new Application_Model_DbTable_DataManagers();
                    $decoded = json_decode((string) ($params['selectedForMapping'] ?? ''), true);
                    if (!is_array($decoded)) {
                        $alertMsg->message = 'Invalid selection payload. Please try again.';
                        return ['ok' => false, 'count' => 0, 'error' => 'invalid_payload'];
                    }
                    $params['participantsList'] = $decoded;
                    $result = $dm->dmParticipantMap($params, $params['datamanagerId'], false);

                    if (is_array($result) && empty($result['ok'])) {
                        $err = $result['error'] ?? 'save_failed';
                        $alertMsg->message = $err === 'empty_selection'
                            ? 'No participants selected — nothing was changed.'
                            : 'Save failed. Please try again.';
                        return $result;
                    }

                    $mappedCount = (int) ($result['count'] ?? count($decoded));
                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog(
                        "Mapped {$mappedCount} participants to data manager #{$params['datamanagerId']}",
                        'data-managers'
                    );

                    $alertMsg->message = 'Participants mapped successfully';
                    return ['ok' => true, 'count' => $mappedCount];
                }

                if (isset($params['participantId']) && $params['participantId'] != '') {
                    $dm = new Application_Model_DbTable_DataManagers();
                    $params['participantsList'][] = $params['participantId'];
                    $dm->dmParticipantMap($params, $params['dataManager'], false);

                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog(
                        "Mapped participant #{$params['participantId']} to data manager #{$params['dataManager']}",
                        'data-managers'
                    );

                    $alertMsg->message = 'Datamanager mapped successfully';
                }
            }
        } catch (Exception $exc) {
            $traceId = 'pmm-' . bin2hex(random_bytes(4));
            Pt_Commons_LoggerUtility::logError('addParticipantManager failed', [
                'trace_id' => $traceId,
                'file'     => $exc->getFile(),
                'line'     => $exc->getLine(),
                'message'  => $exc->getMessage(),
                'trace'    => $exc->getTraceAsString(),
            ]);
            $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return ['ok' => false, 'count' => 0, 'error' => 'save_failed', 'trace_id' => $traceId];
        }
    }

    public function getShipmentRespondedParticipants($parameters)
    {

        $aColumns = ['unique_identifier', new Zend_Db_Expr(self::participantNameExpr('p')), 'institute_name', 'state', 'district', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        //  $sIndexColumn = "participant_id";

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $sQuery = $this->getAdapter()->select()
            ->from(
                ['sp' => 'shipment_participant_map'],
                [
                    new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'),
                    'sp.shipment_id',
                    'sp.participant_id',
                    'sp.shipment_test_date',
                    'RESPONSE' => new Zend_Db_Expr("
                CASE 
                    WHEN sp.response_status like 'responded'
                    THEN 'Responded'
                    ELSE 'Not Responded'
                END
                "),
                ]
            )
            ->joinLeft(
                ['p' => 'participant'],
                'p.participant_id = sp.participant_id',
                [
                    'p.participant_id',
                    'p.unique_identifier',
                    'p.institute_name',
                    'p.country',
                    'p.state',
                    'p.district',
                    'p.mobile',
                    'p.phone',
                    'p.affiliation',
                    'p.email',
                    'p.status',
                    'participantName' => new Zend_Db_Expr(self::participantNameExpr('p')),
                ]
            )
            ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['iso_name'])
            ->where('sp.shipment_id = ?', $parameters['shipmentId'])
            ->where("sp.response_status like 'responded'")
            ->group('sp.participant_id');
        //  error_log($sQuery);
        if (isset($parameters['withStatus']) && $parameters['withStatus'] != '') {
            $sQuery = $sQuery->where('p.status = ? ', $parameters['withStatus']);
        }
        if (isset($sWhere) && $sWhere != '') {
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

        $sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;

        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

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
            $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\'' . base64_encode($aRow['shipment_id']) . '\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function getShipmentNotRespondedParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['unique_identifier', new Zend_Db_Expr(self::participantNameExpr('p')), 'p.institute_name', 'p.state', 'p.district', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        //  $sIndexColumn = "participant_id";

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['sp' => 'shipment_participant_map'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.participant_id', 'sp.shipment_test_date', 'shipment_id', 'RESPONSE' => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.state', 'p.district', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr(self::participantNameGroupConcatExpr('p'))])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country')
            // ->where("(sp.shipment_test_date = '0000-00-00' OR sp.shipment_test_date IS NULL)")
            ->where("(sp.shipment_test_report_date IS NULL OR DATE(sp.shipment_test_report_date) = '0000-00-00' OR response_status like 'noresponse')")
            ->where('sp.shipment_id = ?', $parameters['shipmentId'])
            ->group('sp.participant_id');

        if (isset($sWhere) && $sWhere != '') {
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

        $sQuerySession = new Zend_Session_Namespace('notRespondedParticipantsExcel');
        $sQuerySession->shipmentRespondedParticipantQuery = $sQuery;

        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

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
            $row[] = '<a href="javascript:void(0);" onclick="removeParticipants(\'' . base64_encode($aRow['map_id']) . '\',\'' . base64_encode($aRow['shipment_id']) . '\')" class="btn btn-primary btn-xs"><i class="icon-remove"></i> Delete</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function getShipmentNotEnrolledParticipants($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['first_name', 'unique_identifier', new Zend_Db_Expr(self::participantNameExpr('p')), 'institute_name', 'state', 'district', 'iso_name', 'mobile', 'phone', 'affiliation', 'email', 'p.status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        //  $sIndexColumn = "participant_id";

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $subSql = $this->getAdapter()->select()->from(['sp' => 'shipment_participant_map'], ['sp.participant_id'])
            ->where('sp.shipment_id = ?', $parameters['shipmentId'])
            ->group('sp.participant_id');

        $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id'), 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.state', 'p.district', 'p.mobile', 'p.phone', 'p.affiliation', 'p.email', 'p.status', 'participantName' => new Zend_Db_Expr(self::participantNameGroupConcatExpr('p'))])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country')
            ->where('p.participant_id NOT IN ?', $subSql)
            ->where("p.status='active'")
            // ->order('first_name')
            ->group('p.participant_id');

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $rResult = $this->getAdapter()->fetchAll($sQuery);

        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            $row = [];

            $row[] = '<input type="checkbox" class="checkParticipants" id="chk' . base64_encode($aRow['participant_id']) . '"  value="' . base64_encode($aRow['participant_id']) . '" onclick="toggleSelect(this);"  />';
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
            $row[] = '<a href="javascript:void(0);" onclick="enrollParticipants(this, \'' . base64_encode($aRow['participant_id']) . '\',\'' . base64_encode($parameters['shipmentId']) . '\')" class="btn btn-primary btn-xs"> Enroll</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function checkParticipantsProfileUpdateByUserSystemId($userSystemId)
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name])
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")])
            ->where('pmm.dm_id = ?', $userSystemId)
            // ->where("p.force_profile_updation = ?", 1)
            ->group('p.participant_id'));
    }

    public function fetchFilterValues()
    {
        $query = $this->getAdapter()->select()
            ->from(
                ['p' => $this->_name],
                [
                    'country_details' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT CONCAT(con.id, ':', con.iso_name) SEPARATOR ',')"),
                    'districts' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.district SEPARATOR ',')"),
                    'regions' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.region SEPARATOR ',')"),
                    'states' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.state SEPARATOR ',')"),
                    'cities' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.city SEPARATOR ',')"),
                ]
            )
            ->joinLeft(['con' => 'countries'], 'con.id = p.country', [])
            ->where("p.status = 'active'");

        $result = $this->getAdapter()->fetchRow($query);

        // Process country_details into an id => name array
        $countries = [];
        if (!empty($result['country_details'])) {
            $countryPairs = explode(',', $result['country_details']);
            foreach ($countryPairs as $pair) {
                list($id, $name) = explode(':', $pair);
                $countries[$id] = $name;
            }
        }

        return [
            'countries' => $countries,
            'districts' => $result['districts'] ? explode(',', $result['districts']) : [],
            'regions' => $result['regions'] ? explode(',', $result['regions']) : [],
            'states' => $result['states'] ? explode(',', $result['states']) : [],
            'cities' => $result['cities'] ? explode(',', $result['cities']) : [],
        ];
    }

    public function fetchUniqueCountry()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name], ['country' => new Zend_Db_Expr(' DISTINCT con.iso_name '), 'id' => 'con.id'])
            ->join(['con' => 'countries'], 'con.id=p.country')->where("p.status='active'")->where('p.country IS NOT NULL')->where("trim(p.country)!=''"));
    }

    public function fetchUniqueDistrict()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name], ['district' => new Zend_Db_Expr(' DISTINCT p.district ')])
            ->where("p.status='active'")->where('p.district IS NOT NULL')->where("trim(p.district)!=''"));
    }

    public function fetchUniqueRegion()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name], ['region' => new Zend_Db_Expr(' DISTINCT p.region ')])
            ->where("p.status='active'")->where('p.region IS NOT NULL')->where("trim(p.region)!=''"));
    }

    public function fetchUniqueState()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name], ['state' => new Zend_Db_Expr(' DISTINCT p.state ')])
            ->where("p.status='active'")->where('p.state IS NOT NULL')->where("trim(p.state)!=''"));
    }
    public function fetchUniqueCity()
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name], ['city' => new Zend_Db_Expr(' DISTINCT p.city ')])
            ->where("p.status='active'")->where('p.city IS NOT NULL')->where("trim(p.city)!=''"));
    }

    public function fetchParticipantSearch($search)
    {
        $sql = $this->select();
        $searchPattern = '%' . $search . '%';
        $db = $this->getAdapter();
        $sql = $sql->where(
            $db->quoteInto('first_name LIKE ?', $searchPattern) . ' OR ' .
                $db->quoteInto('last_name LIKE ?', $searchPattern) . ' OR ' .
                $db->quoteInto('unique_identifier LIKE ?', $searchPattern) . ' OR ' .
                $db->quoteInto('institute_name LIKE ?', $searchPattern) . ' OR ' .
                $db->quoteInto('region LIKE ?', $searchPattern)
        )
            ->where("status like 'active'");
        return $this->fetchAll($sql);
    }

    public function fetchMapActiveParticipantDetails($userSystemId)
    {
        return $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name])
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")])
            ->where('pmm.dm_id = ?', $userSystemId)->group('p.participant_id'));
    }

    public function fetchFilterDetailsAPI($params)
    {
        /* Check the app versions & parameters */
        /* if (!isset($params['appVersion'])) {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!isset($params['authToken'])) {
            return ['status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again'];
        }

        /* Validate new auth token and app-version */
        $dmDb = new Application_Model_DbTable_DataManagers();
        $aResult = $dmDb->fetchAuthToken($params);
        /* if ($aResult == 'app-version-failed') {
            return array('status' => 'version-failed', 'message' => 'App version is not updated. Kindly go to the play store and update the app');
        } */
        if (!$aResult) {
            return ['status' => 'auth-fail', 'message' => 'Something went wrong. Please log in again', 'profileInfo' => $aResult['profileInfo']];
        }

        $result = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from(['p' => $this->_name])
            ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', ['data_manager' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT pmm.dm_id SEPARATOR ', ')")])
            ->where('pmm.dm_id = ?', $aResult['dm_id'])
            //->where("p.status = 'active'")
            ->group('p.participant_id'));
        if (!empty($result)) {
            $response['status'] = 'success';
            foreach ($result as $row) {
                $response['data']['participants'][] = [
                    'participant_id' => $row['participant_id'],
                    'unique_identifier' => $row['unique_identifier'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'mobile' => $row['mobile'],
                    'email' => $row['email'],
                    'status' => $row['status'],
                    'individual' => $row['individual'],
                    'lab_name' => $row['lab_name'],
                    'institute_name' => $row['institute_name'],
                    'department_name' => $row['department_name'],
                    'region' => $row['region'],
                ];
            }
            $schemeDb = new Application_Model_DbTable_SchemeList();
            $schemeList = $schemeDb->getFullSchemeList();
            if (count($schemeList) > 0) {
                foreach ($schemeList as $scheme) {
                    if ($scheme['status'] == 'active') {
                        $response['data']['shipments'][] = [
                            'scheme_id' => $scheme['scheme_id'],
                            'scheme_name' => $scheme['scheme_name'],
                        ];
                    }
                }
            }
        } else {
            $response['status'] = 'fail';
            $response['message'] = 'No participant found.';
        }
        $response['profileInfo'] = $aResult['profileInfo'];
        return $response;
    }

    private function validateUploadedFile($fileName, $templateFilePath)
    {
        $uploadedSpreadsheet = IOFactory::load($fileName);
        $templateSpreadsheet = IOFactory::load($templateFilePath);

        $uploadedHeaders = $uploadedSpreadsheet->getSheet(0)->rangeToArray('A1:Z1')[0];
        $templateHeaders = $templateSpreadsheet->getSheet(0)->rangeToArray('A1:Z1')[0];

        $normalize = function ($header) {
            return strtolower(preg_replace('/\s+/', '', (string) $header));
        };

        $mismatches = [];
        foreach ($templateHeaders as $idx => $expected) {
            $actual = $uploadedHeaders[$idx] ?? null;
            if ($normalize($expected) === $normalize($actual)) {
                continue;
            }
            $colLetter = chr(ord('A') + $idx);
            $expectedLabel = trim(preg_replace('/\s+/', ' ', (string) $expected));
            $actualLabel = trim(preg_replace('/\s+/', ' ', (string) $actual));
            if ($expectedLabel === '' && $actualLabel === '') {
                continue;
            }
            $mismatches[] = [
                'column' => $colLetter,
                'expected' => $expectedLabel,
                'actual' => $actualLabel,
            ];
        }

        return $mismatches;
    }

    public function processBulkImport($fileName, $allFakeEmail = false, $params = null)
    {
        $response = ['data' => [], 'error-data' => []];
        $alertMsg = new Zend_Session_Namespace('alertSpace');
        $common = new Application_Service_Common();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $authNameSpace = new Zend_Session_Namespace('administrators');

        // Validate file format
        $templateFilePath = realpath(WEB_ROOT) . '/files/Participant-Bulk-Import-Excel-Format-v2.xlsx';
        $mismatches = $this->validateUploadedFile($fileName, $templateFilePath);
        if (!empty($mismatches)) {
            return [
                'data' => [],
                'error-data' => [],
                'validation_error' => true,
                'mismatches' => $mismatches,
            ];
        }

        // Load Excel data
        $objPHPExcel = IOFactory::load($fileName);
        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        $count = count($sheetData);

        // Get configuration
        $configDb = new Application_Model_DbTable_GlobalConfig();
        $directParticipantLogin = $configDb->getValue('direct_participant_login');
        $prefix = $configDb->getValue('participant_login_prefix');
        $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);

        // Build cache for performance
        $countryCache = $this->buildCountryCache();
        $duplicateChecks = $this->batchCheckDuplicates($sheetData);
        $duplicateChecks['fileParticipants'] = [];
        $duplicateChecks['fileDataManagers'] = [];

        // Process data in single transaction
        $db->beginTransaction();

        try {
            for ($i = 2; $i <= $count; ++$i) {
                $row = array_map('trim', $sheetData[$i]);

                // Skip empty rows
                if (empty($row['A']) && empty($row['C']) && empty($row['D'])) {
                    continue;
                }

                // Clean and prepare data
                $row['R'] = MiscUtility::sanitizeAndValidateEmail($row['R'] ?? '');
                $row['T'] = MiscUtility::sanitizeAndValidateEmail($row['T'] ?? '');

                if (empty($row['B'])) {
                    $row['B'] = 'PT-' . strtoupper(MiscUtility::generateRandomString(5));
                } else {
                    $rawUniqueId = $row['B'];
                    $normalized = MiscUtility::normalizeUniqueId($rawUniqueId);
                    if ($normalized === null) {
                        $this->addError($response, $row, $i, "Unique ID '{$rawUniqueId}' is invalid — must contain at least 3 letters or numbers.");
                        continue;
                    }
                    $row['B'] = $normalized;
                }

                // Handle email. Normalize to the same lowercased/trimmed form used by
                // the duplicate-checks cache (batchCheckDuplicates) so cache lookups hit.
                $rawEmail = $row['R'] ?? null;
                $originalEmail = $rawEmail ? MiscUtility::sanitizeAndValidateEmail($rawEmail) : null;
                $emailWasSynthesized = false;
                if (empty($originalEmail) || $allFakeEmail) {
                    $originalEmail = MiscUtility::generateFakeEmailId($row['B'], trim(($row['D'] ?? '') . ' ' . ($row['E'] ?? '')));
                    $originalEmail = strtolower(trim((string) $originalEmail));
                    $emailWasSynthesized = true;
                }

                // Validation checks
                if (empty($originalEmail)) {
                    $this->addError($response, $row, $i, "Email is empty for participant {$row['B']}.");
                    continue;
                }

                // Check duplicates
                if (isset($duplicateChecks['fileParticipants'][$row['B']])) {
                    $this->addError($response, $row, $i, "Unique ID {$row['B']} is duplicated in the upload file.");
                    continue;
                }
                $participantExists = $duplicateChecks['participants'][$row['B']] ?? null;
                if (isset($params['bulkUploadDuplicateSkip']) && $params['bulkUploadDuplicateSkip'] == 'skip-duplicates' && $participantExists) {
                    $this->addError($response, $row, $i, "Unique ID {$row['B']} already exists.");
                    continue;
                }

                // Email-only update mode: touch participant.email and DM primary_email, skip the rest
                if (isset($params['bulkUploadDuplicateSkip']) && $params['bulkUploadDuplicateSkip'] == 'update-email-only' && $participantExists) {
                    $participantId = $participantExists['participant_id'];

                    $db->update('participant', ['email' => $originalEmail], $db->quoteInto('participant_id = ?', $participantId));

                    $mappedDms = $db->fetchAll(
                        $db->select()
                            ->from(['pmm' => 'participant_manager_map'], ['dm_id'])
                            ->join(['dm' => 'data_manager'], 'dm.dm_id = pmm.dm_id', [])
                            ->where('pmm.participant_id = ?', $participantId)
                            ->where("IFNULL(dm.data_manager_type, 'manager') NOT IN ('ptcc', 'participant')")
                    );

                    $dmIdForResponse = 0;
                    $canBlindUpdate = false;
                    $sharedDmToUnmap = 0;
                    if (count($mappedDms) == 1) {
                        $singleDmId = (int) $mappedDms[0]['dm_id'];
                        $sharedCount = (int) $db->fetchOne(
                            $db->select()
                                ->from('participant_manager_map', new Zend_Db_Expr('COUNT(*)'))
                                ->where('dm_id = ?', $singleDmId)
                        );
                        if ($sharedCount <= 1) {
                            $canBlindUpdate = true;
                        } else {
                            $sharedDmToUnmap = $singleDmId;
                        }
                    }

                    $existingDmForEmail = $duplicateChecks['dataManagers'][$originalEmail] ?? null;
                    if (empty($existingDmForEmail)) {
                        // Cache miss safety net: hit the DB directly. The prefetch can miss
                        // if the stored primary_email has invisible chars / odd whitespace,
                        // or if this email never appeared in the upload file at all.
                        $dbDmId = $db->fetchOne(
                            $db->select()
                                ->from('data_manager', 'dm_id')
                                ->where('LOWER(TRIM(primary_email)) = ?', $originalEmail)
                        );
                        if ($dbDmId) {
                            $existingDmForEmail = ['dm_id' => (int) $dbDmId];
                            $duplicateChecks['dataManagers'][$originalEmail] = $existingDmForEmail;
                        }
                    }
                    $allowEmailReuse = isset($params['bulkUploadAllowEmailRepeat'])
                        && $params['bulkUploadAllowEmailRepeat'] == 'allow-existing-email';

                    if ($canBlindUpdate) {
                        $currentDmId = (int) $mappedDms[0]['dm_id'];
                        if ($existingDmForEmail && (int) $existingDmForEmail['dm_id'] !== $currentDmId) {
                            // Target email already belongs to a different DM — updating
                            // would hit the primary_email unique key. Re-map instead.
                            if (!$allowEmailReuse) {
                                $this->addError($response, $row, $i, "Email {$originalEmail} is already in use by another Data Manager. Skipping {$row['B']}.");
                                continue;
                            }
                            $dmIdForResponse = (int) $existingDmForEmail['dm_id'];
                            $common->insertIgnore('participant_manager_map', ['dm_id' => $dmIdForResponse, 'participant_id' => $participantId]);
                            $db->delete('participant_manager_map', [
                                $db->quoteInto('dm_id = ?', $currentDmId),
                                $db->quoteInto('participant_id = ?', $participantId),
                            ]);
                        } else {
                            $dmIdForResponse = $currentDmId;
                            $db->update('data_manager', ['primary_email' => $originalEmail], $db->quoteInto('dm_id = ?', $dmIdForResponse));
                        }
                    } else {
                        if ($existingDmForEmail) {
                            // A DM with this email already exists — reuse it instead of
                            // inserting (which would collide on primary_email).
                            if (!$allowEmailReuse) {
                                $this->addError($response, $row, $i, "Email {$originalEmail} is already in use by another Data Manager. Skipping {$row['B']}.");
                                continue;
                            }
                            $dmIdForResponse = (int) $existingDmForEmail['dm_id'];
                        } else {
                            $newDmData = [
                                'first_name' => MiscUtility::cleanString($row['D'] ?? ''),
                                'last_name' => MiscUtility::cleanString($row['E'] ?? ''),
                                'institute' => MiscUtility::cleanString($row['F'] ?? ''),
                                'mobile' => MiscUtility::cleanString($row['Q'] ?? ''),
                                'secondary_email' => MiscUtility::cleanString($row['T'] ?? ''),
                                'primary_email' => $originalEmail,
                                'force_password_reset' => 1,
                                'created_by' => $authNameSpace->admin_id,
                                'created_on' => new Zend_Db_Expr('now()'),
                                'status' => 'active',
                            ];
                            $db->insert('data_manager', $newDmData);
                            $dmIdForResponse = $db->lastInsertId();
                        }
                        $common->insertIgnore('participant_manager_map', ['dm_id' => $dmIdForResponse, 'participant_id' => $participantId]);

                        if ($sharedDmToUnmap > 0) {
                            $db->delete('participant_manager_map', [
                                $db->quoteInto('dm_id = ?', $sharedDmToUnmap),
                                $db->quoteInto('participant_id = ?', $participantId),
                            ]);
                        }
                    }

                    $response['data'][] = [
                        's_no' => $sheetData[$i]['A'] ?: ($i - 1),
                        'participant_id' => $sheetData[$i]['B'],
                        'individual' => $sheetData[$i]['C'] ?? 'no',
                        'participant_lab_name' => $sheetData[$i]['D'],
                        'participant_last_name' => $sheetData[$i]['E'],
                        'institute_name' => $sheetData[$i]['F'] ?? null,
                        'department' => $sheetData[$i]['G'] ?? null,
                        'address' => $sheetData[$i]['H'] ?? null,
                        'district' => $sheetData[$i]['J'] ?? null,
                        'country' => $sheetData[$i]['M'],
                        'zip' => $sheetData[$i]['N'] ?? null,
                        'longitude' => $sheetData[$i]['O'] ?? null,
                        'latitude' => $sheetData[$i]['P'] ?? null,
                        'mobile_number' => $sheetData[$i]['Q'] ?? null,
                        'participant_email' => $originalEmail,
                        'participant_password' => $sheetData[$i]['S'],
                        'additional_email' => $sheetData[$i]['T'] ?? null,
                        'filename' => $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName,
                        'updated_datetime' => Pt_Commons_DateUtility::getCurrentDateTime(),
                    ];

                    $duplicateChecks['fileParticipants'][$row['B']] = true;
                    $duplicateChecks['fileDataManagers'][$originalEmail] = true;
                    $duplicateChecks['dataManagers'][$originalEmail] = ['dm_id' => $dmIdForResponse];
                    continue;
                }

                // Check data manager email duplicates
                if (isset($duplicateChecks['fileDataManagers'][$originalEmail])) {
                    $this->addError($response, $row, $i, "Data Manager email $originalEmail is duplicated in the upload file.");
                    continue;
                }
                $dataManagerExists = $duplicateChecks['dataManagers'][$originalEmail] ?? null;
                if (empty($dataManagerExists)) {
                    // Cache miss safety net (mirror of the update-email-only branch): if the
                    // prefetch missed a DB row (whitespace/zero-width in stored email, or the
                    // email never appearing in the upload file), look it up directly so we
                    // reuse the existing DM instead of attempting a duplicate insert.
                    $dbDmId = $db->fetchOne(
                        $db->select()
                            ->from('data_manager', 'dm_id')
                            ->where('LOWER(TRIM(primary_email)) = ?', $originalEmail)
                    );
                    if ($dbDmId) {
                        $dataManagerExists = ['dm_id' => (int) $dbDmId];
                        $duplicateChecks['dataManagers'][$originalEmail] = $dataManagerExists;
                    }
                }
                if (isset($params['bulkUploadAllowEmailRepeat']) && $params['bulkUploadAllowEmailRepeat'] == 'do-not-allow-existing-email' && $dataManagerExists) {
                    // The "don't allow" rule is meant to prevent two different participants
                    // sharing an email — not to block a participant from updating its own DM.
                    // If the existing DM is already mapped to the participant we're updating,
                    // fall through and let the regular update path run.
                    $alreadyLinkedToThisParticipant = false;
                    if ($participantExists) {
                        $linkCount = (int) $db->fetchOne(
                            $db->select()
                                ->from('participant_manager_map', new Zend_Db_Expr('COUNT(*)'))
                                ->where('dm_id = ?', $dataManagerExists['dm_id'])
                                ->where('participant_id = ?', $participantExists['participant_id'])
                        );
                        $alreadyLinkedToThisParticipant = $linkCount > 0;
                    }
                    if (!$alreadyLinkedToThisParticipant) {
                        if ($emailWasSynthesized) {
                            $this->addError($response, $row, $i, "Auto-generated login email {$originalEmail} (derived from Unique ID '{$row['B']}') is already in use by another participant. Either provide a unique email in column R or change the Unique ID.");
                        } else {
                            $this->addError($response, $row, $i, "Data Manager email {$originalEmail} already exists for another participant. Skipping for participant {$row['B']}.");
                        }
                        continue;
                    }
                }

                // Prepare participant data
                $isIndividual = strtolower($row['C'] ?? 'yes');
                if (!in_array($isIndividual, ['yes', 'no'])) {
                    $isIndividual = 'yes';
                }

                $countryId = $this->getCountryIdFromCache($row['M'] ?? '', $countryCache);

                if ($countryId == null || $countryId == 0) {
                    $this->addError($response, $row, $i, "Invalid country: {$row['M']}");
                    continue;
                }

                $ulid = ($directParticipantLogin == 'yes') ? MiscUtility::generateULID() : null;

                $participantData = [
                    'unique_identifier' => MiscUtility::cleanString($row['B']),
                    'individual' => $isIndividual,
                    'first_name' => MiscUtility::cleanString($row['D'] ?? ''),
                    'last_name' => MiscUtility::cleanString($row['E'] ?? ''),
                    'institute_name' => MiscUtility::cleanString($row['F'] ?? ''),
                    'department_name' => MiscUtility::cleanString($row['G'] ?? ''),
                    'address' => MiscUtility::cleanString($row['H'] ?? ''),
                    'shipping_address' => MiscUtility::cleanString($row['I'] ?? ''),
                    'district' => MiscUtility::cleanString($row['J'] ?? ''),
                    'state' => MiscUtility::cleanString($row['K'] ?? ''),
                    'region' => MiscUtility::cleanString($row['L'] ?? ''),
                    'country' => $countryId,
                    'zip' => MiscUtility::cleanString($row['N'] ?? ''),
                    'long' => MiscUtility::cleanString($row['O'] ?? ''),
                    'lat' => MiscUtility::cleanString($row['P'] ?? ''),
                    'mobile' => MiscUtility::cleanString($row['Q'] ?? ''),
                    'email' => $originalEmail,
                    'additional_email' => MiscUtility::cleanString($row['T'] ?? ''),
                    'force_profile_updation' => 0,
                    'created_by' => $authNameSpace->admin_id,
                    'created_on' => new Zend_Db_Expr('now()'),
                    'status' => 'active',
                ];

                if ($ulid) {
                    $participantData['ulid'] = $ulid;
                }

                // Prepare data manager data
                $dataManagerData = [
                    'first_name' => MiscUtility::cleanString($row['D'] ?? ''),
                    'last_name' => MiscUtility::cleanString($row['E'] ?? ''),
                    'institute' => MiscUtility::cleanString($row['F'] ?? ''),
                    'mobile' => MiscUtility::cleanString($row['Q'] ?? ''),
                    'secondary_email' => MiscUtility::cleanString($row['T'] ?? ''),
                    'primary_email' => $originalEmail,
                    'force_password_reset' => 1,
                    'created_by' => $authNameSpace->admin_id,
                    'created_on' => new Zend_Db_Expr('now()'),
                    'status' => 'active',
                ];

                // Handle password
                if (isset($params['resetPassword']) && $params['resetPassword'] == 'yes') {
                    $password = empty($row['S']) ? $this->_defaultPassword : trim($row['S']);
                    $dataManagerData['password'] = ($password == $this->_defaultPassword) ? $this->_defaultPasswordHash : Common::passwordHash($password);
                }

                // Insert/update data manager. Wrap in try/catch so a stray duplicate-
                // email collision (e.g. cache miss, or a DM that slipped past the
                // pre-check) becomes a per-row error instead of aborting the batch.
                $dmId = 0;
                try {
                    if (empty($dataManagerExists)) {
                        $db->insert('data_manager', $dataManagerData);
                        $dmId = $db->lastInsertId();
                    } else {
                        $dmId = $dataManagerExists['dm_id'];
                        $db->update('data_manager', $dataManagerData, $db->quoteInto('dm_id = ?', $dmId));
                    }
                } catch (Exception $e) {
                    error_log('Data Manager save error: ' . $e->getMessage());
                    $emailHint = $emailWasSynthesized
                        ? "Auto-generated login email {$originalEmail} (derived from Unique ID)"
                        : "Email {$originalEmail}";
                    $this->addError($response, $row, $i, "Could not save Data Manager for {$row['B']}. {$emailHint} may already be in use.");
                    continue;
                }

                // Handle direct participant login
                $dmId2 = 0;
                if ($directParticipantLogin == 'yes') {
                    $dataManagerData2 = $dataManagerData;
                    $dataManagerData2['data_manager_type'] = 'participant';
                    $dataManagerData2['primary_email'] = $prefix . $row['B'];
                    $dataManagerData2['participant_ulid'] = $ulid;

                    if (isset($params['resetPassword']) && $params['resetPassword'] == 'yes') {
                        $password = empty($row['S']) ? $this->_defaultPassword : trim($row['S']);
                        $dataManagerData2['password'] = ($password == $this->_defaultPassword) ? $this->_defaultPasswordHash : Common::passwordHash($password);
                    }

                    $dmExists2 = $duplicateChecks['dataManagers'][$dataManagerData2['primary_email']] ?? null;
                    if (empty($dmExists2)) {
                        $db->insert('data_manager', $dataManagerData2);
                        $dmId2 = $db->lastInsertId();
                    }
                }

                // Insert/update participant
                $lastInsertedId = 0;
                try {
                    if (empty($participantExists)) {
                        $db->insert('participant', $participantData);
                        $lastInsertedId = $db->lastInsertId();
                    } else {
                        $db->update('participant', $participantData, $db->quoteInto('unique_identifier = ?', $participantExists['unique_identifier']));
                        $lastInsertedId = $participantExists['participant_id'];
                    }
                } catch (Exception $e) {
                    error_log('Participant save error: ' . $e->getMessage());
                    continue;
                }

                // Handle mappings and finalize
                if ($lastInsertedId > 0) {
                    // Direct participant login mapping
                    if ($dmId2 > 0) {
                        $db->delete('participant_manager_map', $db->quoteInto('participant_id = ?', $lastInsertedId) . " AND dm_id NOT IN (SELECT dm_id FROM data_manager WHERE IFNULL(data_manager_type, 'manager') = 'ptcc')");
                        $db->insert('participant_manager_map', ['dm_id' => $dmId2, 'participant_id' => $lastInsertedId]);
                    }

                    // Regular data manager mapping
                    if ($dmId > 0) {
                        $common->insertIgnore('participant_manager_map', ['dm_id' => $dmId, 'participant_id' => $lastInsertedId]);

                        // Success - add to response
                        $response['data'][] = [
                            's_no' => $sheetData[$i]['A'] ?: ($i - 1),
                            'participant_id' => $sheetData[$i]['B'],
                            'individual' => $sheetData[$i]['C'] ?? 'no',
                            'participant_lab_name' => $sheetData[$i]['D'],
                            'participant_last_name' => $sheetData[$i]['E'],
                            'institute_name' => $sheetData[$i]['F'] ?? null,
                            'department' => $sheetData[$i]['G'] ?? null,
                            'address' => $sheetData[$i]['H'] ?? null,
                            'district' => $sheetData[$i]['J'] ?? null,
                            'country' => $sheetData[$i]['M'],
                            'zip' => $sheetData[$i]['N'] ?? null,
                            'longitude' => $sheetData[$i]['O'] ?? null,
                            'latitude' => $sheetData[$i]['P'] ?? null,
                            'mobile_number' => $sheetData[$i]['Q'] ?? null,
                            'participant_email' => $originalEmail,
                            'participant_password' => $sheetData[$i]['S'],
                            'additional_email' => $sheetData[$i]['T'] ?? null,
                            'filename' => $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName,
                            'updated_datetime' => Pt_Commons_DateUtility::getCurrentDateTime(),
                        ];
                    } else {
                        $this->addError($response, $row, $i, 'Could not add Participant Login');
                    }

                    // Track seen records to catch duplicates within the same upload
                    $duplicateChecks['fileParticipants'][$row['B']] = true;
                    $duplicateChecks['participants'][$row['B']] = [
                        'unique_identifier' => $row['B'],
                        'participant_id' => $lastInsertedId,
                    ];
                    $duplicateChecks['fileDataManagers'][$originalEmail] = true;
                    $duplicateChecks['dataManagers'][$originalEmail] = ['dm_id' => $dmId];
                    if ($dmId2 > 0) {
                        $duplicateChecks['fileDataManagers'][$dataManagerData2['primary_email']] = true;
                        $duplicateChecks['dataManagers'][$dataManagerData2['primary_email']] = ['dm_id' => $dmId2];
                    }
                } else {
                    $this->addError($response, $row, $i, 'Could not add Participant');
                }
            }

            $db->commit();

            // Log audit
            $importedCount = count($response['data'] ?? []);
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog("Bulk imported {$importedCount} participants", 'participants');

            $alertMsg->message = 'Your file was imported successfully';
        } catch (Exception $e) {
            $db->rollBack();
            error_log('BULK IMPORT ERROR: ' . $e->getFile() . ":{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            $alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return false;
        }

        return $response;
    }

    private function addError(&$response, $row, $rowIndex, $errorMessage)
    {
        $dbData = [
            'participant_id' => $row['B'] ?? 'Unknown',
            'error' => $errorMessage,
            'updated_datetime' => Pt_Commons_DateUtility::getCurrentDateTime(),
        ];

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $db->insert('participants_not_uploaded', $dbData);

        $response['error-data'][] = $dbData + [
            's_no' => $row['A'] ?: ($rowIndex - 1),
            'participant_lab_name' => $row['D'] ?? '',
            'participant_last_name' => $row['E'] ?? '',
            'institute_name' => $row['F'] ?? '',
            'mobile_number' => $row['Q'] ?? '',
            'district' => $row['J'] ?? '',
            'country' => $row['M'] ?? '',
            'participant_email' => $row['R'] ?? '',
        ];
    }
    // Helper methods for optimization
    private function buildCountryCache()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from('countries', ['iso_name', 'iso2', 'iso3', 'id']);
        $results = $db->fetchAll($sql);

        $cache = [];
        foreach ($results as $row) {
            $cache[strtolower($row['iso_name'])] = $row['id'];
            if (!empty($row['iso2'])) {
                $cache[strtolower($row['iso2'])] = $row['id'];
            }
            if (!empty($row['iso3'])) {
                $cache[strtolower($row['iso3'])] = $row['id'];
            }
        }

        return $cache;
    }

    private function batchCheckDuplicates($sheetData)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $uniqueIds = [];
        $emails = [];

        for ($i = 2; $i <= count($sheetData); $i++) {
            if (!empty($sheetData[$i]['B'])) {
                $cleanId = MiscUtility::slugify($sheetData[$i]['B']);
                if (!empty($cleanId)) {
                    $uniqueIds[] = $cleanId;
                }
            }

            $email = MiscUtility::sanitizeAndValidateEmail($sheetData[$i]['R']);
            if ($email) {
                $emails[] = $email;
            } elseif (!empty($sheetData[$i]['B'])) {
                // Blank email → import will synthesize <uniqueId>@<host>; pre-check that too
                // so the per-row duplicate-email guard catches it before the INSERT.
                try {
                    $fakeEmail = MiscUtility::generateFakeEmailId(
                        $sheetData[$i]['B'],
                        trim(($sheetData[$i]['D'] ?? '') . ' ' . ($sheetData[$i]['E'] ?? ''))
                    );
                    $fakeEmail = strtolower(trim((string) $fakeEmail));
                    if ($fakeEmail !== '') {
                        $emails[] = $fakeEmail;
                    }
                } catch (Throwable $ignore) {
                    // Synthesis failed (no usable id/name) — the per-row code will report it.
                }
            }

            // Also check for direct participant login emails
            $configDb = new Application_Model_DbTable_GlobalConfig();
            $prefix = $configDb->getValue('participant_login_prefix');
            if ($prefix && !empty($sheetData[$i]['B'])) {
                $directEmail = $prefix . MiscUtility::slugify($sheetData[$i]['B']);
                if ($directEmail) {
                    $emails[] = $directEmail;
                }
            }
        }

        // Get existing participants with full row data for update logic
        $existingParticipants = [];
        if (!empty($uniqueIds)) {
            $sql = $db->select()
                ->from('participant', ['unique_identifier', 'participant_id'])
                ->where('unique_identifier IN (?)', $uniqueIds);
            $results = $db->fetchAll($sql);
            foreach ($results as $row) {
                $existingParticipants[$row['unique_identifier']] = $row;
            }
        }

        // Get existing data managers. Per-row lookups use a lowercased email
        // (sanitizeAndValidateEmail), so key the cache the same way — DB collation
        // is case-insensitive but the array key isn't, and a mixed-case row in
        // data_manager would otherwise miss the cache and crash on INSERT.
        $existingDataManagers = [];
        if (!empty($emails)) {
            $sql = $db->select()
                ->from('data_manager', ['primary_email', 'dm_id'])
                ->where('primary_email IN (?)', $emails);
            $results = $db->fetchAll($sql);
            foreach ($results as $row) {
                $existingDataManagers[strtolower(trim((string) $row['primary_email']))] = $row;
            }
        }

        return [
            'participants' => $existingParticipants,
            'dataManagers' => $existingDataManagers,
        ];
    }

    private function getCountryIdFromCache($countryInput, $countryCache)
    {
        $countryId = null; // Default country ID if not found

        if (!empty($countryInput)) {
            $key = strtolower(trim($countryInput));
            $countryId = $countryCache[$key] ?? null;
        }

        return $countryId;
    }

    public function deleteParticipantBId($participantId)
    {
        try {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            if ($participantId > 0 && is_numeric($participantId)) {
                $sQuery = $this->getAdapter()->select()->from(['p' => $this->_name], ['mapCount' => new Zend_Db_Expr('COUNT(pmm.dm_id)'), 'pmm.dm_id'])
                    ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                    ->where('pmm.participant_id = ?', $participantId)
                    ->group('pmm.participant_id');
                $pmmCheck = $this->getAdapter()->fetchRow($sQuery);
                // $id = $db->query("SET FOREIGN_KEY_CHECKS=0");
                if ($pmmCheck['mapCount'] <= 1) {
                    $id = $db->delete('shipment_participant_map', $db->quoteInto('participant_id = ?', $participantId));
                    $id = $db->delete('participant_manager_map', $db->quoteInto('participant_id = ?', $participantId));
                    if ($pmmCheck['mapCount'] == 1 && $pmmCheck['dm_id'] > 0) {
                        $id = $db->delete('data_manager', ['dm_id' => $pmmCheck['dm_id']]);
                    }
                }
                $partcipant = $this->fetchRow($db->quoteInto('participant_id = ?', $participantId));
                $id = $db->delete('enrollments', $db->quoteInto('participant_id = ?', $participantId));
                $id = $db->delete('participant', $db->quoteInto('participant_id = ?', $participantId));
                // $id = $db->query("SET FOREIGN_KEY_CHECKS=1");
                if ($participantId > 0) {
                    $auditDb = new Application_Model_DbTable_AuditLog();
                    $auditDb->addNewAuditLog('Deleted participant - ' . $partcipant['unique_identifier'], 'participants');
                }

                return ($id);
            }
        } catch (Exception $e) {
            echo ($e->getMessage()) . PHP_EOL;
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function fetchShipmentResponseReport($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = [new Zend_Db_Expr(self::participantNameExpr('p')), 'institute_name', 'iso_name', 'state', 'district', 'shipment_code', 'response_status', 'shipment_test_report_date', 'final_result'];

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = [];
        if (isset($parameters['iSortCol_0'])) {
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $sortDir = strtolower($parameters['sSortDir_' . $i]) === 'desc' ? 'desc' : 'asc';
                    $sOrder[] = new Zend_Db_Expr(((string) $aColumns[intval($parameters['iSortCol_' . $i])]) . ' ' . $sortDir);
                }
            }
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS p.participant_id'), 'p.unique_identifier', 'p.institute_name', 'p.country', 'p.state', 'p.district', 'p.status', 'participantName' => new Zend_Db_Expr(self::participantNameGroupConcatExpr('p'))])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country')
            ->joinLeft(['sp' => 'shipment_participant_map'], 'p.participant_id=sp.participant_id', ['shipment_test_report_date', 'final_result', 'RESPONSE' => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")])
            ->joinLeft(['s' => 'shipment'], 's.shipment_id=sp.shipment_id', ['shipment_code', 'scheme_type', 'lastdate_response', 'status'])
            ->group('p.participant_id');

        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_type IN (?)', (array) $parameters['scheme']);
        }
        $authNameSpace = new Zend_Session_Namespace('datamanagers');
        if (!empty($authNameSpace->dm_id)) {
            $sQuery = $sQuery
                ->joinLeft(['pmm' => 'participant_manager_map'], 'pmm.participant_id=p.participant_id', [])
                ->where('pmm.dm_id = ?', $authNameSpace->dm_id);
        }
        if (empty($parameters['shipmentId']) && isset($parameters['startDate']) && $parameters['startDate'] != '' && isset($parameters['endDate']) && $parameters['endDate'] != '') {
            $sQuery = $sQuery->where('DATE(s.shipment_date) >= ?', Common::isoDateFormat($parameters['startDate']));
            $sQuery = $sQuery->where('DATE(s.shipment_date) <= ?', Common::isoDateFormat($parameters['endDate']));
        }

        if (isset($parameters['shipmentId']) && $parameters['shipmentId'] != '') {
            $sQuery = $sQuery->where('s.shipment_id IN (?)', (array) $parameters['shipmentId']);
        }

        if (isset($parameters['country']) && $parameters['country'] != '') {
            $sQuery = $sQuery->where('p.country = ?', $parameters['country']);
        }

        if (isset($parameters['region']) && $parameters['region'] != '') {
            $sQuery = $sQuery->where('p.region = ?', $parameters['region']);
        }

        if (isset($parameters['state']) && $parameters['state'] != '') {
            $sQuery = $sQuery->where('p.state = ?', $parameters['state']);
        }

        if (isset($parameters['district']) && $parameters['district'] != '') {
            $sQuery = $sQuery->where('p.district = ?', $parameters['district']);
        }

        if (isset($sWhere) && $sWhere != '') {
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
        $sQuerySession = new Zend_Session_Namespace('participantResponseReportQuerySession');
        $sQuerySession->participantResponseReportQuerySession = $sQuery;
        $iTotal = $iFilteredTotal = $this->getAdapter()->fetchOne('SELECT FOUND_ROWS()');

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        $finalResult = [1 => 'Pass', 2 => 'Fail', 3 => 'Excluded'];
        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = [];

            $row[] = ($aRow['participantName']);
            $row[] = ($aRow['institute_name']);
            $row[] = ($aRow['iso_name']);
            $row[] = ($aRow['state']);
            $row[] = ($aRow['district']);
            $row[] = $aRow['shipment_code'];
            $row[] = ucwords($aRow['RESPONSE']);
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['shipment_test_report_date'] ?? '');
            $row[] = (isset($finalResult[$aRow['final_result']]) && !empty($finalResult[$aRow['final_result']])) ? ucwords($finalResult[$aRow['final_result']]) : null;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function fetchParticipantsByLocations($locationValue, $locationField = 'country', $returnFields = ['participant_id'], $group = ['participant_id'])
    {
        $db = $this->getAdapter();
        $allowedFields = ['country', 'state', 'district', 'region', 'city'];
        if (!in_array($locationField, $allowedFields)) {
            $locationField = 'country';
        }
        return $db->fetchAll($sQuery = $db->select()
            ->from(['p' => $this->_name], $returnFields)
            ->where($locationField . ' LIKE ?', $locationValue)
            ->group($group));
    }

    public function exportParticipantMapDetails()
    {
        $headings = ['Participant ID', 'Lab Name/Participant Name', 'Cell/Mobile', 'Email', 'Country'];
        try {
            $excel = new Spreadsheet();

            $output = [];
            $sheet = $excel->getActiveSheet();
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

            $colNo = 0;
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $pQuery = $this->getAdapter()->select()
                ->from(['p' => $this->_name], ['unique_identifier', 'labName' => new Zend_Db_Expr(self::participantNameExpr('p')), 'pmobile' => 'mobile', 'email'])
                ->joinLeft(['c' => 'countries'], 'c.id=p.country', ['c.iso_name'])
                ->where("participant_id NOT IN(SELECT DISTINCT participant_id FROM participant_manager_map WHERE dm_id in (SELECT dm_id FROM data_manager WHERE data_manager_type like 'manager' or data_manager_type = '' or data_manager_type is null))")
                ->group(['p.participant_id']);
            $totalResult = $db->fetchAll($pQuery);

            foreach ($headings as $field => $value) {
                $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . 1)
                    ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . 1, null, null)->getFont()->setBold(true);
                $colNo++;
            }
            if (isset($totalResult) && !empty($totalResult)) {
                foreach ($totalResult as $aRow) {
                    $row = [];
                    $row[] = $aRow['unique_identifier'] ?? null;
                    $row[] = ucwords($aRow['labName']) ?? null;
                    $row[] = $aRow['pmobile'] ?? null;
                    $row[] = $aRow['email'] ?? null;
                    $row[] = ucwords($aRow['iso_name']) ?? null;
                    $output[] = $row;
                }
            } else {
                $row = [];
                $row[] = 'No result found';
                $output[] = $row;
            }

            foreach ($output as $rowNo => $rowData) {
                $colNo = 0;
                foreach ($rowData as $field => $value) {
                    if (!isset($value)) {
                        $value = '';
                    }
                    $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 2)
                        ->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if ($colNo == (sizeof($headings) - 1)) {
                        $sheet->getColumnDimensionByColumn($colNo)->setWidth(100);
                        $sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 2, null, null)->getAlignment()->setWrapText(true);
                    }
                    $colNo++;
                }
            }
            foreach (range('A', 'Z') as $columnID) {
                $sheet->getColumnDimension($columnID)->setAutoSize(true);
            }
            $tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
            if (!file_exists($tempUploadDirectory) && !is_dir($tempUploadDirectory)) {
                mkdir($tempUploadDirectory);
            }

            $writer = IOFactory::createWriter($excel, 'Xlsx');
            $filename = 'UNMAPPED-PARTICIPANT-LIST-' . date('d-M-Y-H-i-s') . '.xlsx';
            $writer->save($tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
            return $filename;
        } catch (Exception $exc) {
            error_log('UNMAPPED-PARTICIPANT-LIST--REPORT-EXCEL--' . $exc->getMessage());
            error_log($exc->getTraceAsString());

            return '';
        }
    }

    public function excludeUnrollParticipantById($params)
    {
        try {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
            $pQuery = $this->getAdapter()->select()
                ->from('system_admin', ['primary_email'])
                ->where('primary_email = ?', $authNameSpace->primary_email)
                ->where('password = ?', $params['password'])->limit(1);
            $verify = $db->fetchRow($pQuery);
            if ($verify) {
                $db->query('SET FOREIGN_KEY_CHECKS = 0;'); // Disable foreign key checks
                $allowedTestTypes = ['dts', 'dbs', 'vl', 'eid', 'recency', 'generic', 'covid19'];
                $testType = in_array($params['testType'], $allowedTestTypes) ? $params['testType'] : 'dts';
                $db->delete('response_result_' . $testType, $db->quoteInto('shipment_map_id = ?', $params['smid']));
                $db->delete('shipment_participant_map', $db->quoteInto('map_id = ?', $params['smid']));
                $db->query('SET FOREIGN_KEY_CHECKS = 1;'); // Enable foreign key checks
                return true;
            }
            return false;
        } catch (Exception $exc) {
            error_log('EXCLUDED-PARTICIPANT-ERROR-' . $exc->getMessage());
            error_log($exc->getTraceAsString());
            return false;
        }
    }

    public function notRespondedParticipants($shipmentId)
    {
        $sQuery = $this->getAdapter()->select()->from(['sp' => 'shipment_participant_map'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.participant_id', 'sp.shipment_test_date', 'shipment_id', 'RESPONSE' => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")])
            ->joinLeft(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.state', 'p.district', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr(self::participantNameGroupConcatExpr('p'))])
            ->joinLeft(['c' => 'countries'], 'c.id=p.country')
            ->where("(sp.shipment_test_report_date IS NULL OR DATE(sp.shipment_test_report_date) = '0000-00-00' OR response_status like 'noresponse')")
            ->where('sp.shipment_id = ?', $shipmentId)
            ->group('sp.participant_id');
        return $this->getAdapter()->fetchAll($sQuery);
    }

    public function fetchNotRespondedParticipantsByDmId($dmId)
    {
        $sQuery = $this->getAdapter()->select()->from(['sp' => 'shipment_participant_map'], [new Zend_Db_Expr('SQL_CALC_FOUND_ROWS sp.map_id'), 'sp.participant_id', 'sp.shipment_test_date', 'shipment_id', 'RESPONSE' => new Zend_Db_Expr("CASE WHEN (sp.is_excluded ='yes') THEN 'Excluded'  WHEN (sp.shipment_test_date not like '' AND sp.shipment_test_date!='0000-00-00' AND sp.shipment_test_date not like 'NULL') THEN 'Responded' ELSE 'Not Responded' END")])
            ->join(['p' => 'participant'], 'p.participant_id=sp.participant_id', ['p.participant_id', 'p.unique_identifier', 'p.institute_name', 'p.department_name', 'p.city', 'p.state', 'p.district', 'p.country', 'p.mobile', 'p.state', 'p.phone', 'p.affiliation', 'p.email', 'p.phone', 'p.status', 'participantName' => new Zend_Db_Expr(self::participantNameGroupConcatExpr('p'))])
            ->join(['pmm' => 'participant_manager_map'], 'p.participant_id=pmm.participant_id', [])
            ->where("(sp.shipment_test_report_date IS NULL OR DATE(sp.shipment_test_report_date) = '0000-00-00' OR response_status like 'noresponse')")
            ->where('pmm.dm_id = ?', $dmId)
            ->group('sp.participant_id');
        return $this->getAdapter()->fetchRow($sQuery);
    }

    public function fetchParticipantList($params)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['p' => 'participant'], ['participant_id', 'unique_identifier', 'first_name', 'last_name'])
            ->where('p.status= ?', 'active');
        if (!empty($params['country'])) {
            $sql->where('country IN (?)', (array) $params['country']);
        }
        if (!empty($params['state'])) {
            $sql->where('state IN (?)', (array) $params['state']);
        }
        if (!empty($params['region'])) {
            $sql->where('region IN (?)', (array) $params['region']);
        }
        if (!empty($params['district'])) {
            $sql->where('district IN (?)', (array) $params['district']);
        }
        if (!empty($params['city'])) {
            $sql->where('city IN (?)', (array) $params['city']);
        }
        if (!empty($params['network'])) {
            $sql->where('network_tier IN (?)', (array) $params['network']);
        }
        if (!empty($params['affiliation'])) {
            $sql->where('affiliation IN (?)', (array) $params['affiliation']);
        }
        if (!empty($params['institute'])) {
            $sql->where('institute_name IN (?)', (array) $params['institute']);
        }
        if (!empty($params['siteType'])) {
            $sql->where('site_type IN (?)', (array) $params['siteType']);
        }
        if (!empty($params['enrolledPrograms'])) {
            $sql->where('enrolled_programs IN (?)', (array) $params['enrolledPrograms']);
        }
        if (isset($params['schemeId']) && !empty($params['schemeId'])) {
            $subSql = $db->select()->from(['e' => 'enrollments'], 'participant_id')
                ->where('scheme_id = ?', $params['schemeId']);
            $sql = $sql->where('participant_id NOT IN ?', $subSql);
        }
        return $db->fetchAll($sql);
    }
}
