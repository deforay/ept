<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use Pt_Commons_MiscUtility as MiscUtility;

class Application_Model_DbTable_Enrollments extends Zend_Db_Table_Abstract
{
    protected $_name = 'enrollments';
    protected $_primary = ['list_name', 'participant_id'];

    public function getAllEnrollments($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['p.unique_identifier', 'p.first_name', 'iso_name', 's.scheme_name', 'e.enrolled_on'];
        $aSortColumns = ['p.unique_identifier', 'p.first_name', 'iso_name', 'MIN(s.scheme_name)', 'first_enrolled_on', 'list_count'];

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
                    if (!isset($aSortColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aSortColumns[$colIdx] . '
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
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' OR ';
                    } else {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($search) . ' ';
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
                    $sWhere .= $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . ' LIKE ' . Pt_Commons_General::sqlLikeContains($parameters['sSearch_' . $i]) . ' ';
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'])
            ->join(['c' => 'countries'], 'c.id=p.country')
            ->joinLeft(['e' => 'enrollments'], 'p.participant_id = e.participant_id')
            ->joinLeft(['s' => 'scheme_list'], 'e.scheme_id = s.scheme_id', [
                'scheme_name' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT s.scheme_name ORDER BY s.scheme_name SEPARATOR ', ')"),
                // The first enrollment, not whichever row the GROUP BY happened to keep.
                'first_enrolled_on' => new Zend_Db_Expr('MIN(e.enrolled_on)'),
                'list_count' => new Zend_Db_Expr('COUNT(DISTINCT e.scheme_id, e.list_name)'),
                // scheme id, scheme name and list name per list, for the row's Lists panel
                'list_names' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT CONCAT_WS(CHAR(9), e.scheme_id, IFNULL(s.scheme_name, ''), e.list_name) ORDER BY s.scheme_name, e.list_name SEPARATOR '\n')"),
            ])
            ->where("p.status='active'")
            ->group('p.participant_id');

        if (isset($sWhere) && $sWhere != '') {
            $sQuery = $sQuery->where($sWhere);
        }
        if (isset($parameters['scheme']) && $parameters['scheme'] != '') {
            $sQuery = $sQuery->where('s.scheme_id = ? ', $parameters['scheme']);
        }

        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        $rResult = $this->getAdapter()->fetchAll($sQuery);
        $translator = Zend_Registry::get('translate');

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
            ->group('p.participant_id');

        $aResultTotal = $this->getAdapter()->fetchAll($sQuery);
        $iTotal = sizeof($aResultTotal);

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
            $row[] = $aRow['first_name'] . ' ' . $aRow['last_name'];
            $row[] = $aRow['iso_name'];
            $row[] = $aRow['scheme_name'];
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['first_enrolled_on']);
            // Lists: a button that opens the participant's lists in a row underneath.
            $lists = [];
            foreach (array_filter(explode("\n", (string) $aRow['list_names'])) as $line) {
                [$schemeId, $schemeName, $listName] = array_pad(explode("\t", $line), 3, '');
                $lists[] = ['scheme' => $schemeId, 'schemeName' => $schemeName, 'list' => $listName];
            }
            $row[] = empty($lists) ? '' : '<button type="button" class="btn btn-default btn-xs toggle-lists" data-lists="'
                . htmlspecialchars(json_encode($lists), ENT_QUOTES, 'UTF-8') . '"><i class="icon-list"></i> '
                . sprintf($translator->_('View (%d)'), count($lists)) . '</button>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function enrollParticipants($params)
    {
        if (empty($params['schemeId'])) {
            return ['ok' => false, 'count' => 0, 'error' => 'missing_scheme'];
        }

        $alertMsg = new Zend_Session_Namespace('alertSpace');
        try {
            return $this->doEnrollParticipants($params, $alertMsg);
        } catch (Throwable $e) {
            $traceId = 'enr-' . bin2hex(random_bytes(4));
            Pt_Commons_LoggerUtility::logError('enrollParticipants failed', [
                'trace_id' => $traceId,
                'scheme' => $params['schemeId'] ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $alertMsg->message = 'Enrolment failed. Please try again.';
            return ['ok' => false, 'count' => 0, 'error' => 'save_failed', 'trace_id' => $traceId];
        }
    }

    // Saving a list makes it hold exactly the participants that were on the right-hand
    // side of the editor. The editor only loads active participants, so rows for
    // inactive or pending ones were never on screen and are left alone.
    private function doEnrollParticipants($params, $alertMsg)
    {
        $decoded = json_decode((string) ($params['selectedForEnrollment'] ?? ''), true);
        if (!is_array($decoded)) {
            $alertMsg->message = 'Invalid enrolment payload. Please reload the page and try again.';
            return ['ok' => false, 'count' => 0, 'error' => 'invalid_payload'];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $decoded))));
        if (empty($ids) && empty($params['allowEmptyList'])) {
            $alertMsg->message = 'No participants selected for enrolment.';
            return ['ok' => false, 'count' => 0, 'error' => 'empty_selection'];
        }

        $schemeId = (string) $params['schemeId'];
        $listName = self::normalizeListName($params['listName'] ?? '');
        $db = $this->getAdapter();
        $common = new Application_Service_Common();
        $enrollmentListId = Pt_Commons_General::generateULID();

        $db->beginTransaction();
        try {
            $remove = [
                'list_name = ?' => $listName,
                'scheme_id = ?' => $schemeId,
                "participant_id IN (SELECT participant_id FROM participant WHERE status = 'active')",
            ];
            if (!empty($ids)) {
                $remove[] = 'participant_id NOT IN (' . implode(',', $ids) . ')';
            }
            $removed = $db->delete($this->_name, $remove);

            $added = 0;
            foreach ($ids as $participant) {
                $stmt = $common->insertIgnore($this->_name, [
                    'enrollment_id' => $enrollmentListId,
                    'list_name' => $listName,
                    'participant_id' => $participant,
                    'scheme_id' => $schemeId,
                    'status' => 'enrolled',
                    'enrolled_on' => new Zend_Db_Expr('now()'),
                ]);
                $added += $stmt ? $stmt->rowCount() : 0;
            }
            $db->commit();
        } catch (Throwable $e) {
            try {
                $db->rollBack();
            } catch (Throwable $ignored) {
                // best-effort rollback
            }
            throw $e;
        }

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog(
            "Saved enrollment list {$listName} for scheme {$schemeId}: {$added} added, {$removed} removed",
            'enrollment'
        );
        $alertMsg->message = 'Enrollment list saved';
        return ['ok' => true, 'count' => count($ids), 'added' => $added, 'removed' => $removed];
    }

    /**
     * Blank means the scheme's Default list, the one the participant form also writes to.
     * The pages show that list as "Default" in the admin's language, so that label maps
     * back to it too.
     *
     * Any other name is kept exactly, spaces included: older lists were saved with
     * trailing spaces, and the column's collation does not ignore them, so trimming
     * would point at a different, empty list.
     */
    public static function normalizeListName($name): string
    {
        $name = (string) $name;
        $defaultLabel = Zend_Registry::isRegistered('translate') ? Zend_Registry::get('translate')->_('Default') : 'Default';
        if (trim($name) === '' || mb_strtolower(trim($name)) === mb_strtolower($defaultLabel)) {
            return 'default';
        }
        return mb_substr($name, 0, 128);
    }

    // Called by the participant add/approve form with the schemes ticked there. It used
    // to delete every enrollment the participant had, on every list, and then fail on
    // the second scheme. It now only removes schemes that were unticked, and adds a
    // ticked scheme to the Default list unless some list already enrolls them in it.
    public function enrollParticipantToSchemes($participantId, $schemes, $listName = 'default')
    {
        $participantId = (int) $participantId;
        $schemes = array_values(array_filter(array_map('strval', (array) $schemes), static fn ($s) => $s !== ''));
        $db = $this->getAdapter();

        // scheme_id <> '' keeps legacy lists saved without a scheme, which the form cannot show.
        $unticked = ['participant_id = ?' => $participantId, "scheme_id <> ''"];
        if (!empty($schemes)) {
            $unticked['scheme_id NOT IN (?)'] = $schemes;
        }
        $this->delete($unticked);

        $already = $db->fetchCol($db->select()->from($this->_name, ['scheme_id'])->where('participant_id = ?', $participantId));
        $common = new Application_Service_Common();
        foreach (array_diff($schemes, $already) as $scheme) {
            $common->insertIgnore($this->_name, [
                'enrollment_id' => Pt_Commons_General::generateULID(),
                'list_name' => self::normalizeListName($listName),
                'participant_id' => $participantId,
                'scheme_id' => $scheme,
                'status' => 'enrolled',
                'enrolled_on' => new Zend_Db_Expr('now()'),
            ]);
        }
    }

    /**
     * Gives an old list saved without a scheme (early Ship It presets) a scheme, so it
     * can be edited like any other list. If the scheme already has a list of that name,
     * the two are merged.
     */
    public function assignSchemeToList(string $listName, string $schemeId): int
    {
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $db->query(
                "INSERT IGNORE INTO {$this->_name} (enrollment_id, list_name, scheme_id, participant_id, enrolled_on, status)
                 SELECT enrollment_id, list_name, ?, participant_id, enrolled_on, IF(status = '', 'enrolled', status)
                 FROM {$this->_name} WHERE list_name = ? AND scheme_id = ''",
                [$schemeId, $listName]
            );
            $moved = $db->delete($this->_name, ['list_name = ?' => $listName, "scheme_id = ''"]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Assigned scheme {$schemeId} to enrollment list {$listName} ({$moved} rows)", 'enrollment');
        return $moved;
    }

    // --- Managing a list -------------------------------------------------------------
    // The Default list is never renamed, merged away, archived or deleted: the
    // participant form adds to it, so these actions refuse it.

    /** Archived lists stay enrolled but are hidden from Ship It and grouped apart. */
    public function setListArchived(string $schemeId, string $listName, bool $archived): void
    {
        $this->refuseDefault($listName);
        $db = $this->getAdapter();
        if ($archived) {
            $admin = new Zend_Session_Namespace('administrators');
            $common = new Application_Service_Common();
            $common->insertIgnore('enrollment_list_archive', [
                'scheme_id' => $schemeId,
                'list_name' => $listName,
                'archived_by' => $admin->admin_id ?? null,
            ]);
        } else {
            $db->delete('enrollment_list_archive', ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
        }
        $this->audit(($archived ? 'Archived' : 'Reactivated') . " enrollment list {$listName} for scheme {$schemeId}");
    }

    /** Returns false when the scheme already has a list with the new name (merge instead). */
    public function renameList(string $schemeId, string $listName, string $newName): bool
    {
        $this->refuseDefault($listName);
        $newName = self::normalizeListName(trim($newName));
        $db = $this->getAdapter();
        if ($newName === 'default' || $this->listExists($schemeId, $newName)) {
            return false;
        }
        $db->beginTransaction();
        try {
            $db->update($this->_name, ['list_name' => $newName], ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
            $db->update('enrollment_list_archive', ['list_name' => $newName], ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->audit("Renamed enrollment list {$listName} to {$newName} for scheme {$schemeId}");
        return true;
    }

    /** Moves everyone on $listName onto $intoList (same scheme), then removes $listName. */
    public function mergeList(string $schemeId, string $listName, string $intoList): int
    {
        $this->refuseDefault($listName);
        $intoList = self::normalizeListName($intoList);
        if ($intoList === $listName || !$this->listExists($schemeId, $intoList)) {
            return 0;
        }
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $db->query(
                "INSERT IGNORE INTO {$this->_name} (enrollment_id, list_name, scheme_id, participant_id, enrolled_on, status)
                 SELECT enrollment_id, ?, scheme_id, participant_id, enrolled_on, status
                 FROM {$this->_name} WHERE scheme_id = ? AND list_name = ?",
                [$intoList, $schemeId, $listName]
            );
            $moved = $db->delete($this->_name, ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
            $db->delete('enrollment_list_archive', ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->audit("Merged enrollment list {$listName} into {$intoList} for scheme {$schemeId} ({$moved} rows)");
        return $moved;
    }

    /** Participants stay enrolled through any other list they are on. */
    public function deleteList(string $schemeId, string $listName): int
    {
        $this->refuseDefault($listName);
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $removed = $db->delete($this->_name, ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
            $db->delete('enrollment_list_archive', ['scheme_id = ?' => $schemeId, 'list_name = ?' => $listName]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $this->audit("Deleted enrollment list {$listName} for scheme {$schemeId} ({$removed} rows)");
        return $removed;
    }

    private function listExists(string $schemeId, string $listName): bool
    {
        $db = $this->getAdapter();
        return (bool) $db->fetchOne(
            $db->select()->from($this->_name, [new Zend_Db_Expr('1')])
                ->where('scheme_id = ?', $schemeId)->where('list_name = ?', $listName)->limit(1)
        );
    }

    private function refuseDefault(string $listName): void
    {
        if (self::normalizeListName($listName) === 'default') {
            throw new InvalidArgumentException('The Default enrollment list cannot be changed this way.');
        }
    }

    private function audit(string $message): void
    {
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog($message, 'enrollment');
    }

    /** One row per scheme and list, counting active participants only. */
    public function getEnrollmentLists(): array
    {
        $db = $this->getAdapter();
        $sql = $db->select()->from(['e' => $this->_name], [
            'scheme_id',
            'list_name',
            'participants' => new Zend_Db_Expr("COUNT(DISTINCT CASE WHEN p.status = 'active' THEN e.participant_id END)"),
            'last_changed' => new Zend_Db_Expr('MAX(e.enrolled_on)'),
        ])
            ->joinLeft(['s' => 'scheme_list'], 's.scheme_id = e.scheme_id', ['scheme_name'])
            ->joinLeft(['p' => 'participant'], 'p.participant_id = e.participant_id', [])
            ->joinLeft(['a' => 'enrollment_list_archive'], 'a.scheme_id = e.scheme_id AND a.list_name = e.list_name', [
                'archived' => new Zend_Db_Expr('MAX(a.list_name IS NOT NULL)'),
            ])
            ->group(['e.scheme_id', 'e.list_name'])
            // Within a scheme: Default first, then the most recently changed lists.
            ->order(['s.scheme_name', new Zend_Db_Expr("e.list_name <> 'default'"), new Zend_Db_Expr('MAX(e.enrolled_on) DESC'), 'e.list_name']);
        return $db->fetchAll($sql);
    }

    /**
     * Rows for the list editor: active participants matching the filters, plus
     * everyone already on the list (who always stay visible on the right).
     */
    public function getListEditorItems(string $schemeId, string $listName, array $filters): array
    {
        $db = $this->getAdapter();
        $listName = self::normalizeListName($listName);
        $nameExpr = Application_Model_DbTable_Participants::participantNameExpr('p');
        $columns = ['participant_id', 'unique_identifier', 'name' => new Zend_Db_Expr($nameExpr)];

        $candidates = $db->select()->from(['p' => 'participant'], $columns)->where("p.status = 'active'");
        $filterColumns = [
            'country' => 'p.country',
            'province' => 'p.state',
            'district' => 'p.district',
            'institute' => 'p.institute_name',
            'network' => 'p.network_tier',
            'affiliation' => 'p.affiliation',
        ];
        foreach ($filterColumns as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $candidates->where("{$column} = ?", $filters[$key]);
            }
        }

        $members = $db->select()->from(['e' => $this->_name], [])
            ->join(['p' => 'participant'], 'p.participant_id = e.participant_id', $columns)
            ->where("p.status = 'active'")
            ->where('e.scheme_id = ?', $schemeId)
            ->where('e.list_name = ?', $listName);
        $onList = array_flip($db->fetchCol(
            $db->select()->from(['e' => $this->_name], ['participant_id'])
                ->where('e.scheme_id = ?', $schemeId)->where('e.list_name = ?', $listName)
        ));

        $rows = $db->fetchAll($db->select()->union([$candidates, $members])->order('name'));
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row['participant_id'],
                'code' => (string) $row['unique_identifier'],
                'name' => (string) $row['name'],
                'label' => $row['name'] . ' (' . $row['unique_identifier'] . ')',
                'selected' => isset($onList[$row['participant_id']]),
            ];
        }
        return $items;
    }

    /** The list as an Excel file in the bulk-import layout (Participant ID in column A). */
    public function exportList(string $schemeId, string $listName): string
    {
        $db = $this->getAdapter();
        $listName = self::normalizeListName($listName);
        $rows = $db->fetchAll($db->select()->from(['e' => $this->_name], [])
            ->join(['p' => 'participant'], 'p.participant_id = e.participant_id', [
                'unique_identifier',
                'name' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
            ])
            ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['iso_name'])
            ->where("p.status = 'active'")
            ->where('e.scheme_id = ?', $schemeId)
            ->where('e.list_name = ?', $listName)
            ->order('p.unique_identifier'));

        $translator = Zend_Registry::get('translate');
        $excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $excel->getActiveSheet();
        $sheet->fromArray([[$translator->_('Participant ID'), $translator->_('Name'), $translator->_('Country')]], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $line = 2;
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit('A' . $line, (string) $row['unique_identifier'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('B' . $line, html_entity_decode((string) $row['name'], ENT_QUOTES, 'UTF-8'));
            $sheet->setCellValue('C' . $line, (string) $row['iso_name']);
            $line++;
        }
        foreach (['A', 'B', 'C'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $safe = preg_replace('/[^A-Za-z0-9]+/', '-', "{$schemeId}-{$listName}");
        $filename = 'ENROLLMENT-' . strtoupper(trim($safe, '-')) . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        IOFactory::createWriter($excel, 'Xlsx')->save(realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . $filename);
        return $filename;
    }

    /**
     * Works out what importing a file into one scheme's list will do, without saving.
     * Column A holds the Participant ID (Lab ID). With $removeMissing, participants on
     * the list but not in the file are removed, so the list ends up matching the file.
     *
     * Returns rows (one per file row, with status add / same / skip / err and a reason
     * code: not_found, duplicate, inactive, leading_zeros) and remove (participants that
     * will leave the list).
     */
    public function planBulkEnrollment(string $path, string $schemeId, string $listName, bool $removeMissing): array
    {
        $db = $this->getAdapter();
        $listName = self::normalizeListName($listName);
        $nameExpr = new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p'));

        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
        unset($sheet[1]);

        $onList = [];
        foreach ($db->fetchAll($db->select()->from(['e' => $this->_name], [])
            ->join(['p' => 'participant'], 'p.participant_id = e.participant_id', ['participant_id', 'unique_identifier', 'name' => $nameExpr, 'status'])
            ->where('e.scheme_id = ?', $schemeId)
            ->where('e.list_name = ?', $listName)) as $member) {
            $onList[(int) $member['participant_id']] = $member;
        }

        $rows = [];
        $inFile = [];
        foreach ($sheet as $rowNo => $cells) {
            $raw = trim(MiscUtility::normalizeImportText((string) ($cells['A'] ?? '')));
            if ($raw === '') {
                continue;
            }
            $row = ['row' => $rowNo, 'id' => $raw, 'participant_id' => null, 'code' => '', 'name' => '', 'status' => 'err', 'reason' => ''];

            $match = $db->fetchRow($db->select()->from(['p' => 'participant'], ['participant_id', 'unique_identifier', 'name' => $nameExpr, 'status'])
                ->where('p.unique_identifier = ?', $raw)->limit(1));
            if (!$match && ctype_digit($raw)) {
                // Excel turns 06157 into 6157. Accept the match only when exactly one
                // participant has that ID once its leading zeros are dropped.
                $candidates = $db->fetchAll($db->select()->from(['p' => 'participant'], ['participant_id', 'unique_identifier', 'name' => $nameExpr, 'status'])
                    ->where("TRIM(LEADING '0' FROM p.unique_identifier) = ?", ltrim($raw, '0'))->limit(2));
                if (count($candidates) === 1) {
                    $match = $candidates[0];
                }
            }

            if (!$match) {
                $row['reason'] = 'not_found';
            } else {
                $pid = (int) $match['participant_id'];
                $row['participant_id'] = $pid;
                $row['code'] = (string) $match['unique_identifier'];
                $row['name'] = (string) $match['name'];
                if (isset($inFile[$pid])) {
                    $row['status'] = 'skip';
                    $row['reason'] = 'duplicate';
                } elseif ($match['status'] !== 'active') {
                    $row['status'] = 'skip';
                    $row['reason'] = 'inactive';
                } else {
                    $row['status'] = isset($onList[$pid]) ? 'same' : 'add';
                    if ($row['code'] !== $raw) {
                        $row['reason'] = 'leading_zeros';
                    }
                }
                $inFile[$pid] = true;
            }
            $rows[] = $row;
        }

        $remove = [];
        if ($removeMissing) {
            foreach ($onList as $pid => $member) {
                // Inactive members are not shown anywhere, so the file cannot be expected to list them.
                if (!isset($inFile[$pid]) && $member['status'] === 'active') {
                    $remove[] = ['participant_id' => $pid, 'code' => (string) $member['unique_identifier'], 'name' => (string) $member['name']];
                }
            }
        }

        return ['rows' => $rows, 'remove' => $remove];
    }

    /** Saves a plan from planBulkEnrollment(). Returns the added and removed counts. */
    public function applyBulkEnrollment(array $plan, string $schemeId, string $listName): array
    {
        $db = $this->getAdapter();
        $listName = self::normalizeListName($listName);
        $common = new Application_Service_Common();
        $enrollmentListId = Pt_Commons_General::generateULID();
        $added = 0;

        $db->beginTransaction();
        try {
            $removeIds = array_column($plan['remove'], 'participant_id');
            if (!empty($removeIds)) {
                $db->delete($this->_name, [
                    'list_name = ?' => $listName,
                    'scheme_id = ?' => $schemeId,
                    'participant_id IN (?)' => $removeIds,
                ]);
            }
            foreach ($plan['rows'] as $row) {
                if ($row['status'] !== 'add') {
                    continue;
                }
                $stmt = $common->insertIgnore($this->_name, [
                    'enrollment_id' => $enrollmentListId,
                    'list_name' => $listName,
                    'scheme_id' => $schemeId,
                    'participant_id' => $row['participant_id'],
                    'status' => 'enrolled',
                    'enrolled_on' => new Zend_Db_Expr('now()'),
                ]);
                $added += $stmt ? $stmt->rowCount() : 0;
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $removed = count($plan['remove']);
        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog("Bulk imported enrollment list {$listName} for scheme {$schemeId}: {$added} added, {$removed} removed", 'enrollment');
        return ['added' => $added, 'removed' => $removed];
    }
}
