<?php

class Application_Model_DbTable_Distribution extends Zend_Db_Table_Abstract
{
    protected $_name = 'distributions';
    protected $_primary = 'distribution_id';

    public function getAllDistributions($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['d.distribution_id', 'scheme_name', "DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code', 'd.status'];
        $orderColumns = ['d.distribution_id', 'scheme_name', 'distribution_date', 'distribution_code', 's.shipment_code', 'd.status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($orderColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $orderColumns[$colIdx] . '
				 	' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
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
                    if ($aColumns[$i] == '' || $aColumns[$i] == null) {
                        continue;
                    }
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

        $sQuery = $this->getAdapter()->select()->from(['d' => $this->_name])
            ->joinLeft(['s' => 'shipment'], 's.distribution_id=d.distribution_id', ['shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")])
            ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
            ->group('d.distribution_id');

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
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

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
            $shipNowStatus = false;
            $shipNowStatus = $this->checkShipmentStatus($aRow['distribution_id']);
            $shipmentCounts = $this->getShipmentStateCounts($aRow['distribution_id']);
            $row = [];
            $row[] = '<a class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal" href="/admin/distributions/view-shipment/id/' . $aRow['distribution_id'] . '"><span><i class="icon-search"></i></span></a>';
            $row[] = ($aRow['scheme_name'] ?: '<span style="color:#ccc;">' . Pt_Commons_TranslateUtility::htmlTranslate('No Shipment/Panel Added') . '</span>');
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = '<a href="/admin/shipment/index/searchString/' . $aRow['distribution_code'] . '">' . $aRow['distribution_code'] . '</a>';
            $row[] = $aRow['shipments'] ?: '<span style="color:#ccc;">' . Pt_Commons_TranslateUtility::htmlTranslate('No Shipment/Panel Added') . '</span>';
            $row[] = $this->getDisplayStatus($aRow['distribution_id'], $aRow['status'], $shipmentCounts);
            $edit = '<a class="btn btn-primary btn-xs" href="/admin/distributions/edit/d8s5_8d/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-pencil"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Edit') . '</span></a>';
            // A survey is cancelled when its status says so, or every shipment under it is cancelled.
            $isSurveyCancelled = (isset($aRow['status']) && $aRow['status'] == 'cancelled')
                || ($shipmentCounts['total'] > 0 && $shipmentCounts['cancelled'] === $shipmentCounts['total']);
            // Row actions are grouped onto lines: what you do to the survey itself, then
            // mail, then the destructive ones. See .row-action-lines in admin.css.
            $primaryLine = [];
            $mailLine = [];
            $destructiveLine = [];

            if ($isSurveyCancelled) {
                $primaryLine[] = '<a class="btn btn-danger btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ban-circle"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Cancelled') . '</span></a>';
            } elseif (isset($aRow['status']) && $aRow['status'] == 'configured') {
                $primaryLine[] = $edit;
                if ($shipNowStatus) {
                    $primaryLine[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="shipDistribution(\'' . base64_encode($aRow['distribution_id']) . '\')"><span><i class="icon-ambulance"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Ship Now') . '</span></a>';
                } else {
                    $primaryLine[] = '<a class="btn btn-primary btn-xs" href="/admin/shipment/index/did/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-user"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Add Participants') . '</span></a>';
                }
            } elseif (isset($aRow['status']) && $aRow['status'] == 'shipped') {
                $primaryLine[] = '<a class="btn btn-primary btn-xs" href="/admin/distributions/edit/d8s5_8d/' . base64_encode($aRow['distribution_id']) . '/5h8pp3t/shipped"><span><i class="icon-pencil"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Edit') . '</span></a>';
                $primaryLine[] = '<a class="btn btn-primary btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ambulance"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Shipped') . '</span></a>';
                // Shortened from "Send Email to Participants" — the full wording is the tooltip.
                $mailLine[] = '<a class="btn btn-warning btn-xs" href="/admin/email-participants/index/id/' . base64_encode($aRow['distribution_id']) . '" title="' . Pt_Commons_TranslateUtility::htmlTranslate('Send Email to Participants') . '"><span><i class="icon-envelope"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Email') . '</span></a>';
            } else {
                $primaryLine[] = $edit;
                $primaryLine[] = '<a class="btn btn-primary btn-xs" href="/admin/shipment/index/did/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-plus"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Add Shipment') . '</span></a>';
            }
            // Survey-level cancel: only offered when there are active shipments and NONE is
            // finalized (a finalized shipment can never be cancelled, so neither can its survey).
            if (!$isSurveyCancelled && $shipmentCounts['active'] > 0 && $shipmentCounts['finalized'] === 0) {
                $codeAttr = htmlspecialchars((string) $aRow['distribution_code'], ENT_QUOTES);
                $jsCodes = htmlspecialchars(json_encode(array_values($this->getCancellableShipmentCodes($aRow['distribution_id']))), ENT_QUOTES);
                $destructiveLine[] = '<a class="btn btn-danger btn-xs" href="javascript:void(0);" title="' . Pt_Commons_TranslateUtility::htmlTranslate('Cancel PT Survey') . '" onclick="cancelDistribution(\'' . base64_encode($aRow['distribution_id']) . '\', \'' . $codeAttr . '\', ' . $jsCodes . ')"><span><i class="icon-ban-circle"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Cancel') . '</span></a>';
            }
            // Delete only allowed when there are no shipments under this PT survey.
            if (empty($aRow['shipments']) && (!isset($aRow['status']) || $aRow['status'] !== 'shipped')) {
                $codeAttr = htmlspecialchars((string) $aRow['distribution_code'], ENT_QUOTES);
                $destructiveLine[] = '<a class="btn btn-danger btn-xs" href="javascript:void(0);" onclick="confirmDeleteDistribution(\'' . base64_encode($aRow['distribution_id']) . '\', \'' . $codeAttr . '\')"><span><i class="icon-trash"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Delete') . '</span></a>';
            }

            $actionHtml = '';
            foreach ([$primaryLine, $mailLine, $destructiveLine] as $line) {
                if (empty($line)) {
                    continue;
                }
                $actionHtml .= '<div class="row-action-line">' . implode('', $line) . '</div>';
            }
            $row[] = '<div class="row-action-lines">' . $actionHtml . '</div>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function checkShipmentStatus($enId)
    {
        return $this->getAdapter()
            ->fetchRow($this->getAdapter()->select()->from(['d' => 'distributions'])
                ->join(['s' => 'shipment'], 's.distribution_id=d.distribution_id', ['shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')")])
                ->join(['spm' => 'shipment_participant_map'], 's.shipment_id=spm.shipment_id')
                ->join(['sl' => 'scheme_list'], 's.scheme_type=sl.scheme_id', ['scheme_name'])
                ->group('d.distribution_id')
                ->where('d.distribution_id = ?', $enId));
    }

    public function addDistribution($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = [
            'distribution_code' => $params['distributionCode'],
            'distribution_date' => Pt_Commons_DateUtility::isoDateFormat($params['distributionDate']),
            'status' => 'created',
            'created_by' => $authNameSpace->admin_id,
            'created_on' => new Zend_Db_Expr('now()'),
        ];
        $distributionId = $this->insert($data);
        if ($distributionId > 0) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Added a new PT Survey - ' . $params['distributionCode'], 'shipment');
        }
        return $distributionId;
    }

    public function shipDistribution($params)
    {
    }

    public function getDistributionDates()
    {
        return $this->getAdapter()->fetchCol($this->select()->from($this->_name, new Zend_Db_Expr("DATE_FORMAT(distribution_date,'%d-%b-%Y')")));
    }

    public function getDistribution($did)
    {
        return $this->fetchRow('distribution_id = ' . $did);
    }

    /**
     * PT Surveys already scheduled on a given date, each with the shipments under
     * it. Feeds the clash warning on the Add PT Survey screen: picking a date that
     * is already taken is now allowed, but the admin is shown what they collide
     * with before saving.
     *
     * @param string $date any parseable date (the picker's display format)
     * @param int|null $excludeDistributionId a survey to leave out (its own row, on the edit screen)
     * @return array<int,array{distribution_code:string,shipments:array<int,array{shipment_code:string,scheme_name:?string}>}>
     */
    public function getSurveysOnDate($date, $excludeDistributionId = null)
    {
        $iso = Pt_Commons_DateUtility::isoDateFormat($date);
        if (empty($iso)) {
            return [];
        }
        $db = $this->getAdapter();
        $select = $db->select()
            ->from(['d' => 'distributions'], ['distribution_id', 'distribution_code'])
            ->joinLeft(['s' => 'shipment'], 's.distribution_id = d.distribution_id', ['shipment_code'])
            ->joinLeft(['sl' => 'scheme_list'], 's.scheme_type = sl.scheme_id', ['scheme_name'])
            ->where('DATE(d.distribution_date) = ?', $iso)
            ->order(['d.distribution_code', 's.shipment_code']);
        if ((int) $excludeDistributionId > 0) {
            $select->where('d.distribution_id != ?', (int) $excludeDistributionId);
        }
        $rows = $db->fetchAll($select);
        $surveys = [];
        foreach ($rows as $row) {
            $id = (int) $row['distribution_id'];
            if (!isset($surveys[$id])) {
                $surveys[$id] = [
                    'distribution_code' => $row['distribution_code'],
                    'shipments' => [],
                ];
            }
            if (!empty($row['shipment_code'])) {
                $surveys[$id]['shipments'][] = [
                    'shipment_code' => $row['shipment_code'],
                    'scheme_name' => $row['scheme_name'],
                ];
            }
        }
        return array_values($surveys);
    }

    public function deleteDistribution($id)
    {
        $id = (int) $id;
        if ($id <= 0) {
            return 'Invalid PT Survey.';
        }
        $shipmentCount = (int) $this->getAdapter()->fetchOne(
            $this->getAdapter()->select()
                ->from('shipment', new Zend_Db_Expr('COUNT(*)'))
                ->where('distribution_id = ?', $id)
        );
        if ($shipmentCount > 0) {
            return 'Cannot delete a PT Survey that has shipments under it.';
        }
        $row = $this->fetchRow("distribution_id = $id");
        if (!$row) {
            return 'PT Survey not found.';
        }
        $code = $row['distribution_code'];
        $this->delete("distribution_id = $id");
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog('Deleted PT Survey - ' . $code, 'shipment');
        return 'OK';
    }

    /**
     * True if any shipment under this PT Survey has been finalized. Once that
     * happens the survey code is frozen: participants already hold reports/emails
     * referencing it, so a rename would desync what they have from the system.
     */
    public function hasFinalizedShipment($distributionId)
    {
        $db = $this->getAdapter();
        return (int) $db->fetchOne(
            $db->select()
                ->from('shipment', new Zend_Db_Expr('COUNT(*)'))
                ->where('distribution_id = ?', (int) $distributionId)
                ->where("status = 'finalized'")
        ) > 0;
    }

    /**
     * Shipment tallies for a survey, used for both the display status and the
     * cancel-eligibility check. 'active' = not-cancelled; 'finalized' counts only
     * active shipments (a finalized shipment can never be cancelled).
     *
     * @return array{total:int,cancelled:int,finalized:int,active:int}
     */
    public function getShipmentStateCounts($distributionId)
    {
        $db = $this->getAdapter();
        $counts = $db->fetchRow(
            $db->select()
                ->from('shipment', [
                    'total' => new Zend_Db_Expr('COUNT(*)'),
                    'cancelled' => new Zend_Db_Expr('SUM(cancelled_at IS NOT NULL)'),
                    'finalized' => new Zend_Db_Expr("SUM(cancelled_at IS NULL AND status = 'finalized')"),
                ])
                ->where('distribution_id = ?', (int) $distributionId)
        );
        $total = (int) $counts['total'];
        $cancelled = (int) $counts['cancelled'];
        return [
            'total' => $total,
            'cancelled' => $cancelled,
            'finalized' => (int) $counts['finalized'],
            'active' => $total - $cancelled,
        ];
    }

    /**
     * Finalization-aware display status for a survey. `distributions.status` never
     * advances past 'shipped', so we derive the label from the shipments underneath.
     * Cancelled shipments (cancelled_at set) are "dead" and dropped from the count;
     * finalization is judged against the surviving/active shipments:
     *   - every shipment cancelled          => 'Cancelled'
     *   - all active shipments finalized      => 'Finalized'
     *   - some active shipments finalized      => 'Partially Finalized'
     *   - otherwise                            => the stored status, title-cased.
     * Finalization only applies once shipped; cancellation overrides any status.
     */
    public function getDisplayStatus($distributionId, $storedStatus, ?array $counts = null)
    {
        $counts = $counts ?? $this->getShipmentStateCounts($distributionId);

        if ($counts['total'] > 0 && $counts['cancelled'] === $counts['total']) {
            return 'Cancelled';
        }
        if ($storedStatus === 'shipped' && $counts['active'] > 0) {
            if ($counts['finalized'] === $counts['active']) {
                return 'Finalized';
            }
            if ($counts['finalized'] > 0) {
                return 'Partially Finalized';
            }
        }
        return ucwords((string) $storedStatus);
    }

    /** Codes of the shipments a survey-level cancel would actually cancel (active, non-finalized). */
    public function getCancellableShipmentCodes($distributionId)
    {
        $db = $this->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from('shipment', ['shipment_code'])
                ->where('distribution_id = ?', (int) $distributionId)
                ->where('cancelled_at IS NULL')
                ->where("status != 'finalized'")
                ->order('shipment_code')
        );
    }

    /** Codes of the finalized shipments under this survey — used to explain the code lock. */
    public function getFinalizedShipmentCodes($distributionId)
    {
        $db = $this->getAdapter();
        return $db->fetchCol(
            $db->select()
                ->from('shipment', ['shipment_code'])
                ->where('distribution_id = ?', (int) $distributionId)
                ->where("status = 'finalized'")
                ->order('shipment_code')
        );
    }

    /** Case-insensitive: is this survey code already used by a different PT Survey? */
    public function isCodeTaken($code, $excludeDistributionId)
    {
        $db = $this->getAdapter();
        return (int) $db->fetchOne(
            $db->select()
                ->from($this->_name, new Zend_Db_Expr('COUNT(*)'))
                ->where('LOWER(distribution_code) = ?', strtolower(trim((string) $code)))
                ->where('distribution_id != ?', (int) $excludeDistributionId)
        ) > 0;
    }

    public function updateDistribution($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $distributionId = (int) base64_decode($params['distributionId']);

        $data = [
            'distribution_date' => Pt_Commons_DateUtility::isoDateFormat($params['distributionDate']),
            'updated_by' => $authNameSpace->admin_id,
            'updated_on' => new Zend_Db_Expr('now()'),
        ];

        // The survey code may only change while NO shipment under it is finalized,
        // and only to a value not already used by another survey. Both guards are
        // re-checked here; the form's readonly attribute and JS dup-check are advisory.
        $newCode = trim((string) ($params['distributionCode'] ?? ''));
        if (
            $newCode !== ''
            && !$this->hasFinalizedShipment($distributionId)
            && !$this->isCodeTaken($newCode, $distributionId)
        ) {
            $data['distribution_code'] = $newCode;
        }

        $affected = $this->update($data, 'distribution_id=' . $distributionId);
        if ($affected > 0 && isset($data['distribution_code'])) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Updated PT Survey - ' . $data['distribution_code'], 'shipment');
        }
        return $affected;
    }
    public function getUnshippedDistributions()
    {
        // Exclude shipped and cancelled surveys — you can't add shipments to either.
        return $this->fetchAll($this->select()->where("status NOT IN ('shipped', 'cancelled')")->order('distribution_date DESC'));
    }

    public function updateDistributionStatus($distributionId, $status)
    {
        if (!empty($status) && $status != '') {
            return $this->update(['status' => $status], "distribution_id=$distributionId");
        } else {
            return 0;
        }
    }

    public function getAllDistributionReports($parameters)
    {
        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ["DATE_FORMAT(distribution_date,'%d-%b-%Y')", 'distribution_code', 's.shipment_code', 'd.status'];
        $orderColumns = ['distribution_date', 'distribution_code', 's.shipment_code', 'd.status'];

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = 'distribution_id';

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($orderColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $orderColumns[$colIdx] . '
				 	' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
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
                    if ($aColumns[$i] == '' || $aColumns[$i] == null) {
                        continue;
                    }
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

        $dbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sQuery = $dbAdapter->select()->from(['d' => 'distributions'])
            ->joinLeft(['s' => 'shipment'], 's.distribution_id=d.distribution_id', ['shipments' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.shipment_code SEPARATOR ', ')"), 'not_finalized_count' => new Zend_Db_Expr("SUM(IF(s.status!='finalized',1,0))")])
            ->where("s.status!='finalized'")
            ->where('s.cancelled_at IS NULL')
            ->group('d.distribution_id');

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }

        // Scheme-type filter (dropdown above the table). Narrows the listing to
        // distributions that contain a shipment of this scheme, and narrows the
        // GROUP_CONCAT of shipment codes to that scheme.
        $schemeType = isset($parameters['schemeType']) ? trim((string) $parameters['schemeType']) : '';
        if ($schemeType !== '') {
            $sQuery = $sQuery->where('s.scheme_type = ?', $schemeType);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }

        $sQuery = $dbAdapter->select()->from(['temp' => $sQuery])->where('not_finalized_count>0');

        $rResult = $dbAdapter->fetchAll($sQuery);

        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $dbAdapter->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        //$sQuery = $dbAdapter->select()->from('distributions', new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"))->where("status='shipped'");
        $aResultTotal = $dbAdapter->fetchAll($sQuery);
        $iTotal = count($aResultTotal);

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        // $shipmentDb = new Application_Model_DbTable_Shipments();
        foreach ($rResult as $aRow) {
            // $shipmentResults = $shipmentDb->getPendingShipmentsByDistribution($aRow['distribution_id']);
            $row = [];
            $row['DT_RowId'] = 'dist' . $aRow['distribution_id'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = $aRow['distribution_code'];
            $row[] = $aRow['shipments'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="getShipmentInReports(\'' . ($aRow['distribution_id']) . '\')"><span><i class="icon-search"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('View') . '</span></a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
    public function getAllDistributionStatusDetails()
    {
        return $this->fetchAll($this->select());
    }
}
