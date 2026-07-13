<?php

class Application_Model_DbTable_SchemeList extends Zend_Db_Table_Abstract
{
    protected $_name = 'scheme_list';
    protected $_primary = 'scheme_id';

    public function getAllSchemes()
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $schemes = [];
        if (isset($authNameSpace->activeScheme) && !empty($authNameSpace->activeScheme)) {
            foreach (explode(',', $authNameSpace->activeScheme) as $scheme) {
                $schemes[] = sprintf("'%s'", $scheme);
            }
        }
        $sQuery = $this->getAdapter()->select()->from(['s' => $this->_name], ['*'])->where("status='active'")->order('scheme_name');
        if (isset($authNameSpace->activeScheme) && !empty($authNameSpace->activeScheme)) {
            $sQuery = $sQuery->where('scheme_id IN(' . implode(',', $schemes) . ')');
        }
        return $this->getAdapter()->fetchAll($sQuery);
    }
    public function getFullSchemeList($toBind = false)
    {
        $result =  $this->fetchAll($this->select())->toArray();
        if ($toBind) {
            $response = [];
            foreach ($result as $row) {
                $response[$row['scheme_id']] = ucwords($row['scheme_name']);
            }
            return $response;
        }
        return $result;
    }

    public function countEnrollmentSchemes()
    {
        $result = [];
        $sql = $this->fetchAll($this->select()->where("status='active'"));

        foreach ($sql as $scheme) {
            $sQuery = $this->getAdapter()->select()->from(['p' => 'participant'], [])
                ->join(['e' => 'enrollments'], 'p.participant_id = e.participant_id', new Zend_Db_Expr("COUNT('e.participant_id')"))
                ->where("p.status='active'")
                ->where('e.scheme_id=?', $scheme['scheme_id']);
            $aResult = $this->getAdapter()->fetchCol($sQuery);
            $result[strtoupper($scheme['scheme_name'])] =  $aResult[0];
        }

        return $result;
    }

    public function fetchAllGenericTestInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['scheme_name', 'scheme_id', 'status'];

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
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
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

        $sQuery = $this->getAdapter()->select()->from(['s' => $this->_name])
            ->where('is_user_configured = "yes"')->group('scheme_id');

        /* Status filter: active / inactive / all (empty = all) */
        if (isset($parameters['status']) && in_array($parameters['status'], ['active', 'inactive'], true)) {
            $sQuery = $sQuery->where('status = ?', $parameters['status']);
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
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length (custom tests only) */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('*')"))
            ->where('is_user_configured = "yes"');
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
            $row = [];
            $row[] = ucwords($aRow['scheme_name']);
            $row[] = $aRow['scheme_id'];
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/custom-test/edit/id/' . base64_encode($aRow['scheme_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>'
                . '<a href="/admin/custom-test/clone/id/' . base64_encode($aRow['scheme_id']) . '" class="btn btn-info btn-xs" style="margin-right: 2px;"><i class="icon-copy"></i> Clone</a>'
                . '<a href="/admin/custom-test/export/id/' . base64_encode($aRow['scheme_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;"><i class="icon-download-alt"></i> Export</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function saveGenericTestDetails($params)
    {
        try {

            // Set default values if not provided
            if (!isset($params['genericConfig']['numberOfTests']) || empty($params['genericConfig']['numberOfTests'])) {
                $params['genericConfig']['numberOfTests'] = '1';
            }
            if (!isset($params['genericConfig']['captureAdditionalDetails']) || empty($params['genericConfig']['captureAdditionalDetails'])) {
                $params['genericConfig']['captureAdditionalDetails'] = 'no';
            }
            if (!isset($params['genericConfig']['passingScore']) || $params['genericConfig']['passingScore'] === '') {
                $params['genericConfig']['passingScore'] = '100';
            }

            if (isset($params['testType']) && !empty($params['testType'])) {
                $params['genericConfig']['testType'] = reset($params['testType']);
            }
            if (isset($params['quantitative']['minNumberOfResponses']) && !empty($params['quantitative']['minNumberOfResponses'])) {
                $params['genericConfig']['minNumberOfResponses'] = reset($params['quantitative']['minNumberOfResponses']);
            }
            $data = [
                'scheme_id' => $params['schemeCode'],
                'scheme_name' => $params['schemeName'],
                'is_user_configured' => 'yes',
                'user_test_config' => Zend_Json_Encoder::encode($params['genericConfig']),
                'status' => $params['status'],
            ];
            $inUseCodeSet = [];
            if (isset($params['schemeId']) && !empty($params['schemeId'])) {
                $db = $this->getAdapter();
                $schemeCode = base64_decode($params['schemeId']);
                $this->update($data, $db->quoteInto('scheme_id = ?', $schemeCode));

                // The custom-test form rebuilds r_possibleresult from scratch on every save. Any
                // result already referenced by a shipment must survive that rebuild UNCHANGED --
                // deleting or renaming its code orphans historical results (their code vanishes
                // from r_possibleresult). So: preserve the in-use rows (never delete them) and
                // skip re-inserting them below (would duplicate). Non-in-use rows are rebuilt as
                // before. With no in-use rows this behaves exactly like the old delete-all.
                $usedIds = $this->usedPossibleResultIds($schemeCode, true);
                if (!empty($usedIds)) {
                    $inUseCodes = $db->fetchCol(
                        $db->select()->from('r_possibleresult', ['result_code'])
                            ->where('id IN (?)', $usedIds)
                            ->where('result_code IS NOT NULL')
                            ->where("result_code <> ''")
                    );
                    $inUseCodeSet = array_flip(array_map('strval', $inUseCodes));
                }

                if (!empty($inUseCodeSet)) {
                    $db->delete('r_possibleresult', [
                        $db->quoteInto('scheme_id = ?', $schemeCode),
                        $db->quoteInto('(result_code IS NULL OR result_code NOT IN (?))', array_keys($inUseCodeSet)),
                    ]);
                } else {
                    $db->delete('r_possibleresult', $db->quoteInto('scheme_id = ?', $schemeCode));
                }
            } else {
                $this->insert($data);
            }
            if (isset($params['testType']) && !empty($params['testType'])) {
                $sortOrder = 1;
                foreach ($params['testType'] as $key => $test) {
                    if (isset($params[$test]['expectedResult']) && isset($params[$test]['expectedResult'][$key][1]) && $test == 'qualitative' && count($params[$test]['expectedResult'][$key]) > 0) {
                        foreach ($params[$test]['expectedResult'][$key] as $ikey => $val) {
                            if (isset($val) && !empty($val)) {
                                if (isset($params[$test]['resultType'][$key][$ikey]) && !empty($params[$test]['resultType'][$key][$ikey])) {
                                    $subGrp = ($params[$test]['resultType'][$key][$ikey] == 'test-result') ? 'TEST' : 'FINAL';
                                } else {
                                    $subGrp = null;
                                }
                                // In-use codes were preserved above (not deleted). Their code /
                                // label / grouping stay frozen, but sort order and "displayed to"
                                // may still be edited. Anything else is a fresh insert.
                                $code = (string) ($params[$test]['resultCode'][$key][$ikey] ?? '');
                                $status = (($params[$test]['status'][$key][$ikey] ?? '') === 'inactive') ? 'inactive' : 'active';
                                if ($code !== '' && isset($inUseCodeSet[$code])) {
                                    // Frozen code/label/grouping, but sort order, "displayed to"
                                    // and active/inactive may still change (retiring an in-use
                                    // result just hides it from new entry; history keeps it).
                                    $upd = [
                                        'status'     => $status,
                                        'sort_order' => (($params[$test]['sortOrder'][$key][$ikey] ?? '') === '')
                                            ? null : $params[$test]['sortOrder'][$key][$ikey],
                                    ];
                                    $ctx = $params[$test]['displayContext'][$key][$ikey] ?? '';
                                    if (in_array($ctx, ['participant', 'admin', 'all', 'none'], true)) {
                                        $upd['display_context'] = $ctx;
                                    }
                                    $this->getAdapter()->update('r_possibleresult', $upd, [
                                        'scheme_id = ?'   => $params['schemeCode'],
                                        'result_code = ?' => $code,
                                    ]);
                                } else {
                                    $this->getAdapter()->insert('r_possibleresult', [
                                        'scheme_id'         => $params['schemeCode'],
                                        'sub_scheme'        => $params['resultSubGroup'][$key],
                                        'scheme_sub_group'  => $subGrp,
                                        'result_type'       => $test,
                                        'response'          => $params[$test]['expectedResult'][$key][$ikey],
                                        'result_code'       => $params[$test]['resultCode'][$key][$ikey],
                                        'display_context'   => $params[$test]['displayContext'][$key][$ikey],
                                        'sort_order'        => $params[$test]['sortOrder'][$key][$ikey],
                                        'status'            => $status,
                                    ]);
                                }
                            }
                            $sortOrder = $params[$test]['sortOrder'][$key][$ikey];
                        }
                    } elseif ($test == 'quantitative') {
                        $sortOrder++;
                        $this->getAdapter()->insert('r_possibleresult', [
                            'scheme_id'         => $params['schemeCode'],
                            'sub_scheme'        => $params['resultSubGroup'][$key],
                            'result_type'       => $test,
                            'high_range'        => $params[$test]['highValue'][$key],
                            'threshold_range'   => $params[$test]['thresholdValue'][$key],
                            'low_range'         => $params[$test]['lowValue'][$key],
                            'sd_scaling_factor'         => $params[$test]['SDScalingFactor'][$key],
                            'uncertainy_scaling_factor' => $params[$test]['uncertainyScalingFactor'][$key],
                            'uncertainy_threshold'      => $params[$test]['uncertainyThreshold'][$key],
                            'minimum_number_of_responses' => $params[$test]['minNumberOfResponses'][$key],
                            'sort_order'        => $sortOrder,
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function fetchGenericTest($id)
    {
        $response = [];
        if (!empty($id)) {
            $response['schemeResult'] = $this->fetchRow($this->select()->where('scheme_id = "' . $id . '"'))->toArray();
            $possibleResults = $this->getAdapter()->fetchAll($this->getAdapter()->select()->from('r_possibleresult', ['*'])->where('scheme_id = "' . $id . '"')->order('sort_order asc'));

            // Flag results already referenced by a shipment so the editor can lock them: their
            // code / label must not change or be removed, else historical results orphan (the
            // custom-test save rebuilds r_possibleresult from the form -- see saveGenericTestDetails).
            $usedSet = array_flip($this->usedPossibleResultIds($id, true));
            foreach ($possibleResults as &$pr) {
                $pr['is_matched'] = isset($usedSet[(int) $pr['id']]) ? 1 : 0;
            }
            unset($pr);

            $response['possibleResult'] = $possibleResults;
        }
        return $response;
    }

    public function checkUserConfig($id)
    {
        $scheme = $this->fetchRow($this->select()->where('scheme_id = "' . $id . '"'))->toArray();
        return $scheme['is_user_configured'];
    }

    public function fetchGenericSchemeLists()
    {
        $sql = $this->getAdapter()->select()->from(['s' => $this->_name], ['scheme_id', 'scheme_name'])->where('is_user_configured = "yes"')->group('scheme_id');
        return $this->getAdapter()->fetchAll($sql);
    }

    public function fetchSchemeById($id)
    {
        return $this->fetchRow($this->select()->where('scheme_id = "' . $id . '"'))->toArray();
    }

    public function fetchAllPossibleResultsInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['scheme_name', 'scheme_id', 'status'];

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
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
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

        $sQuery = $this->getAdapter()->select()->from(['sl' => 'scheme_list']);

        // Only qualitative schemes belong on this screen. Exclude explicit quantitative
        // schemes (e.g. Viral Load); NULL/unclassified is treated as qualitative so newly
        // added schemes still appear.
        $sQuery->where("sl.test_format IS NULL OR sl.test_format != 'quantitative'");
        // Only active schemes are shown.
        $sQuery->where('sl.status = ?', 'active');

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
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('*')"))
            ->where("test_format IS NULL OR test_format != 'quantitative'")
            ->where('status = ?', 'active');
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
            $row = [];
            $row[] = ucwords($aRow['scheme_name']);
            $row[] = strtoupper($aRow['scheme_id']);
            $row[] = ucwords($aRow['status']);
            $row[] = '<a href="/admin/schemes/manage-test-results/id/' . base64_encode($aRow['scheme_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Test Results</a>';
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    public function savePossibleResultsTestDetails($params)
    {
        if (empty($params['schemeId'])) {
            return false;
        }
        $db = $this->getAdapter();

        $schemeId = base64_decode($params['schemeId']);

        // scheme_sub_group must round-trip and stay consistent with the rest of the system.
        // The whole codebase keys off two conventions (never a hyphen):
        //   * built-in schemes    -> "{SCHEME}_{TYPE}"  e.g. DTS_TEST, COVID19_FINAL (hardcoded
        //                            evaluators in Shipments.php / response.phtml compare these)
        //   * user-configured     -> bare "TEST" / "FINAL" (what custom-test add/edit writes for
        //                            these very rows; the format lives in result_type instead)
        $isUserConfigured = ((string) $db->fetchOne(
            'SELECT is_user_configured FROM scheme_list WHERE scheme_id = ?',
            [$schemeId]
        )) === 'yes';

        // Results already referenced by a shipment are FROZEN: their code / response / grouping
        // must never change, and the row must never be deleted, else historical results orphan.
        // This is the server-side counterpart to the editor's lock (manage-test-results.phtml)
        // and holds even against a stale or crafted POST that routes an in-use row through the
        // editable fields (disabled inputs don't post, so a well-behaved form never hits this).
        $usedIds = array_flip($this->usedPossibleResultIds($schemeId, $isUserConfigured));

        // Remove results (never an in-use one)
        if (isset($params['removedRow']) && !empty($params['removedRow'])) {
            foreach (explode(',', $params['removedRow']) as $id) {
                $rid = (int) base64_decode($id);
                if (isset($usedIds[$rid])) {
                    continue;
                }
                $db->delete('r_possibleresult', $db->quoteInto('id = ?', $rid));
            }
        }

        $allowedContexts = ['participant', 'admin', 'all', 'none'];

        // Pre-defined sub-tests (scheme_sub_group richer than the plain "{SCHEME}_TEST/FINAL" or
        // bare "TEST/FINAL" forms -- e.g. DTS_SYP_TEST, RECENCY_FINAL) cannot be represented by
        // this flat Test/Final form, so they must never be flattened here. The UI already locks
        // them; this is server-side defense-in-depth in case a crafted POST slips one through.
        // Detection is structural (no hardcoded list) so future sub-tests are protected too.
        $plainGroups = $isUserConfigured
            ? ['TEST', 'FINAL']
            : [strtoupper($schemeId) . '_TEST', strtoupper($schemeId) . '_FINAL'];
        $existingSubGroups = $db->fetchPairs(
            'SELECT id, scheme_sub_group FROM r_possibleresult WHERE scheme_id = ?',
            [$schemeId]
        );

        foreach (($params['resultType'] ?? []) as $key => $resultType) {
            $rowId = !empty($params['rId'][$key]) ? base64_decode($params['rId'][$key]) : null;

            // Refuse to rewrite an existing pre-defined sub-test row through the flat form.
            if ($rowId !== null && isset($existingSubGroups[$rowId])) {
                $existing = strtoupper(trim((string) $existingSubGroups[$rowId]));
                if ($existing !== '' && !in_array($existing, $plainGroups, true)) {
                    continue;
                }
            }

            // Refuse to rewrite an in-use row's code / response / grouping. Only its sort order
            // and display context may change (same two columns the locked* path below allows).
            if ($rowId !== null && isset($usedIds[(int) $rowId])) {
                $frozen = [];
                if (array_key_exists($key, $params['sortOrder'] ?? [])) {
                    $frozen['sort_order'] = ($params['sortOrder'][$key] === '') ? null : $params['sortOrder'][$key];
                }
                if (isset($params['displayContext'][$key])) {
                    $ctx = $params['displayContext'][$key];
                    $frozen['display_context'] = in_array($ctx, $allowedContexts, true) ? $ctx : 'all';
                }
                if (!empty($frozen)) {
                    $db->update('r_possibleresult', $frozen, ['id = ?' => $rowId]);
                }
                continue;
            }

            $type = $params['resultType'][$key]; // TEST | FINAL
            $resultTypeCode = $isUserConfigured ? $type : (strtoupper($schemeId) . '_' . $type);

            $displayContext = $params['displayContext'][$key] ?? 'all';
            if (!in_array($displayContext, $allowedContexts, true)) {
                $displayContext = 'all';
            }

            $data = [
                'scheme_id'       => $schemeId,
                'scheme_sub_group' => $resultTypeCode,
                'response'        => $params['response'][$key],
                'result_code'     => $params['resultCode'][$key],
                'display_context' => $displayContext,
                'sort_order'      => $params['sortOrder'][$key],
            ];

            if ($rowId !== null) {
                $db->update('r_possibleresult', $data, ['id = ?' => $rowId]);
            } else {
                $db->insert('r_possibleresult', $data);
            }
        }

        // In-use rows (results belonging to a not-yet-finalized shipment) are locked in the
        // form except for sort order and "displayed to". They are submitted under separate
        // `locked*` arrays keyed by the base64 row id, and ONLY those two columns are updated
        // here -- never scheme_sub_group / response / result_code. Routing them through the
        // main loop above would rebuild scheme_sub_group from the TEST/FINAL select and clobber
        // multi-subgroup grouping (e.g. DTS_SYP_TEST -> dts-TEST).
        $lockedSort = $params['lockedSortOrder'] ?? [];
        $lockedCtx  = $params['lockedDisplayContext'] ?? [];
        $lockedIds  = array_unique(array_merge(array_keys($lockedSort), array_keys($lockedCtx)));

        foreach ($lockedIds as $encId) {
            $rowId = base64_decode((string) $encId);
            if (!ctype_digit((string) $rowId)) {
                continue;
            }

            $update = [];
            if (array_key_exists($encId, $lockedSort)) {
                $update['sort_order'] = ($lockedSort[$encId] === '') ? null : $lockedSort[$encId];
            }
            if (array_key_exists($encId, $lockedCtx)) {
                $ctx = $lockedCtx[$encId];
                $update['display_context'] = in_array($ctx, $allowedContexts, true) ? $ctx : 'all';
            }

            if (!empty($update)) {
                $db->update('r_possibleresult', $update, ['id = ?' => $rowId]);
            }
        }

        return true;
    }

    public function fetchPossibleResultById($id)
    {
        $db = $this->getAdapter();

        $scheme = $db->fetchRow(
            $db->select()
                ->from(['sl' => 'scheme_list'], ['scheme_id', 'scheme_name', 'is_user_configured'])
                ->where('sl.scheme_id = ?', $id)
        );

        if (empty($scheme)) {
            return [];
        }

        $isUserConfigured = strtolower((string) $scheme['is_user_configured']) === 'yes';

        // Fetch all possible results for this scheme
        $possibleResults = $db->fetchAll(
            $db->select()
                ->from(['sl' => 'scheme_list'], ['sl.scheme_id', 'scheme_name', 'is_user_configured'])
                ->joinLeft(
                    ['rp' => 'r_possibleresult'],
                    'rp.scheme_id = sl.scheme_id',
                    ['rp.id', 'rp.scheme_sub_group', 'rp.result_type', 'rp.response', 'rp.result_code', 'rp.display_context', 'rp.sort_order']
                )
                ->where('sl.scheme_id = ?', $id)
                // Group Test Results first, then Final Interpretations; within each group order
                // by sort_order asc (unset/NULL last). The type is the last '_'-delimited token
                // of scheme_sub_group (e.g. DTS_TEST / DTS_SYP_FINAL).
                ->order(new Zend_Db_Expr("CASE UPPER(SUBSTRING_INDEX(rp.scheme_sub_group, '_', -1)) WHEN 'TEST' THEN 0 WHEN 'FINAL' THEN 1 ELSE 2 END"))
                ->order(new Zend_Db_Expr('rp.sort_order IS NULL'))
                ->order('rp.sort_order ASC')
                ->order('rp.id ASC')
        );

        if (empty($possibleResults)) {
            return [];
        }

        // Flag every result that ANY shipment has used, so the editor can lock its code /
        // response (see manage-test-results.phtml). This intentionally ignores shipment
        // status: a finalized shipment's stored results still render from these codes forever,
        // so an in-use code must stay frozen even after finalization. `shipment_status` is
        // retained (null) only for backward compatibility with the view.
        //
        // NOTE: this replaces an older check that compared the response table's stored value
        // against rp.id for EVERY scheme. Built-in schemes store the id, but user-configured
        // schemes store the result_code -- so that check never matched a generic result and
        // every generic code read as "not in use", which is exactly what let in-use custom
        // codes be renamed and orphan historical results. usedPossibleResultIds() resolves
        // per-scheme.
        $usedSet = array_flip($this->usedPossibleResultIds($id, $isUserConfigured));
        foreach ($possibleResults as &$row) {
            $row['is_matched']      = isset($usedSet[(int) $row['id']]) ? 1 : 0;
            $row['shipment_status'] = null;
        }
        unset($row);

        return $possibleResults;
    }

    /**
     * The response_result_* table and the columns within it that hold r_possibleresult
     * references, for a given scheme. User-configured (generic) schemes store the
     * result_code in these columns; built-in schemes store the r_possibleresult.id.
     *
     * @return array{0: ?string, 1: string[]} [table, columns]; [null, []] when unknown.
     */
    private function responseTableAndColumns($schemeId, $isUserConfigured)
    {
        if ($isUserConfigured) {
            return ['response_result_generic_test', ['reported_result', 'result_1', 'result_2', 'result_3']];
        }
        switch ($schemeId) {
            case 'covid19':
                return ['response_result_covid19', ['test_result_1', 'test_result_2', 'test_result_3', 'reported_result']];
            case 'dts':
                return ['response_result_dts', ['test_result_1', 'test_result_2', 'test_result_3', 'syphilis_result', 'syphilis_final', 'reported_result']];
            case 'dbs':
                return ['response_result_dbs', ['reported_result']];
            case 'eid':
                return ['response_result_eid', ['reported_result']];
            case 'recency':
                return ['response_result_recency', ['reported_result']];
            case 'tb':
                return ['response_result_tb', ['mtb_detected', 'rif_resistance']];
            default:
                return [null, []];
        }
    }

    /**
     * Set of r_possibleresult.id values that any shipment's response has referenced for this
     * scheme (across every result-bearing column, regardless of shipment status). Used to lock
     * a result's code/response in the editor and, server-side, to refuse edits/deletes of an
     * in-use result -- which is what keeps a rename from orphaning historical results.
     *
     * Generic schemes store the result_code in the response columns, so matched codes are
     * resolved back to ids within the scheme; built-in schemes already store the id.
     *
     * @return int[]
     */
    private function usedPossibleResultIds($schemeId, $isUserConfigured)
    {
        $db = $this->getAdapter();
        [$table, $columns] = $this->responseTableAndColumns($schemeId, $isUserConfigured);
        if ($table === null || empty($columns)) {
            return [];
        }

        $values = [];

        // Participant responses.
        $rows = $db->fetchAll(
            $db->select()
                ->from(['rr' => $table], $columns)
                ->join(['spm' => 'shipment_participant_map'], 'spm.map_id = rr.shipment_map_id', [])
                ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', [])
                ->where('s.scheme_type = ?', $schemeId)
        );
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                if (isset($row[$col]) && $row[$col] !== '') {
                    $values[(string) $row[$col]] = true;
                }
            }
        }

        // Reference (expected) results for user-configured schemes: a code used only as an
        // expected answer -- never reported by anyone -- must still lock, else renaming it
        // orphans the reference row. reference_result_generic_test holds the Final code.
        if ($isUserConfigured) {
            $refRows = $db->fetchCol(
                $db->select()
                    ->from(['ref' => 'reference_result_generic_test'], ['reference_result'])
                    ->join(['s' => 'shipment'], 's.shipment_id = ref.shipment_id', [])
                    ->where('s.scheme_type = ?', $schemeId)
                    ->where("ref.reference_result <> ''")
            );
            foreach ($refRows as $code) {
                $values[(string) $code] = true;
            }
        }

        if (empty($values)) {
            return [];
        }

        if ($isUserConfigured) {
            // Stored values are result_codes -> resolve to ids within this scheme.
            $ids = $db->fetchCol(
                $db->select()
                    ->from('r_possibleresult', ['id'])
                    ->where('scheme_id = ?', $schemeId)
                    ->where('result_code IN (?)', array_keys($values))
            );
            return array_map('intval', $ids);
        }

        // Stored values are already r_possibleresult ids.
        return array_values(array_filter(array_map('intval', array_keys($values))));
    }
}
