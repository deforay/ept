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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . '
				 	' . ($parameters['sSortDir_' . $i]) . ', ';
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
            $row = [];
            $row[] = '<a class="btn btn-primary btn-xs" data-toggle="modal" data-target="#myModal" href="/admin/distributions/view-shipment/id/' . $aRow['distribution_id'] . '"><span><i class="icon-search"></i></span></a>';
            $row[] = ($aRow['scheme_name'] ?: '<span style="color:#ccc;">' . Pt_Commons_TranslateUtility::htmlTranslate('No Shipment/Panel Added') . '</span>');
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['distribution_date']);
            $row[] = '<a href="/admin/shipment/index/searchString/' . $aRow['distribution_code'] . '">' . $aRow['distribution_code'] . '</a>';
            $row[] = $aRow['shipments'] ?: '<span style="color:#ccc;">' . Pt_Commons_TranslateUtility::htmlTranslate('No Shipment/Panel Added') . '</span>';
            $row[] = ucwords($aRow['status']);
            $edit = '<a class="btn btn-primary btn-xs" href="/admin/distributions/edit/d8s5_8d/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-pencil"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Edit') . '</span></a>';
            $actionHtml = '';
            if (isset($aRow['status']) && $aRow['status'] == 'configured') {
                if ($shipNowStatus) {
                    $actionHtml = $edit . ' ' . '<a class="btn btn-primary btn-xs" href="javascript:void(0);" onclick="shipDistribution(\'' . base64_encode($aRow['distribution_id']) . '\')"><span><i class="icon-ambulance"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Ship Now') . '</span></a> &nbsp;&nbsp;';
                } else {
                    $actionHtml = $edit . ' ' . '<a class="btn btn-primary btn-xs" href="/admin/shipment/index/did/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-user"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Add Participants') . '</span></a>';
                }
            } elseif (isset($aRow['status']) && $aRow['status'] == 'shipped') {
                $actionHtml = '<a class="btn btn-primary btn-xs" href="/admin/distributions/edit/d8s5_8d/' . base64_encode($aRow['distribution_id']) . '/5h8pp3t/shipped"><span><i class="icon-pencil"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Edit') . '</span></a>' . ' ' . '<a class="btn btn-primary btn-xs disabled" href="javascript:void(0);"><span><i class="icon-ambulance"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Shipped') . '</span></a>
                <a class="btn btn-warning btn-xs" href="/admin/email-participants/index/id/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-envelope"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Send Email to Participants') . '</span></a>';
            } else {
                $actionHtml = $edit . ' ' . '<a class="btn btn-primary btn-xs" href="/admin/shipment/index/did/' . base64_encode($aRow['distribution_id']) . '"><span><i class="icon-plus"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Add Shipment') . '</span></a>';
            }
            // Delete only allowed when there are no shipments under this PT survey.
            if (empty($aRow['shipments']) && (!isset($aRow['status']) || $aRow['status'] !== 'shipped')) {
                $codeAttr = htmlspecialchars((string) $aRow['distribution_code'], ENT_QUOTES);
                $actionHtml .= ' <a class="btn btn-danger btn-xs" href="javascript:void(0);" onclick="confirmDeleteDistribution(\'' . base64_encode($aRow['distribution_id']) . '\', \'' . $codeAttr . '\')"><span><i class="icon-trash"></i> ' . Pt_Commons_TranslateUtility::htmlTranslate('Delete') . '</span></a>';
            }
            $row[] = $actionHtml;
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

    public function updateDistribution($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $data = [
            'distribution_code' => $params['distributionCode'],
            'distribution_date' => Pt_Commons_DateUtility::isoDateFormat($params['distributionDate']),
            'updated_by' => $authNameSpace->admin_id,
            'updated_on' => new Zend_Db_Expr('now()'),
        ];
        $distributionId = $this->update($data, 'distribution_id=' . base64_decode($params['distributionId']));
        if ($distributionId > 0) {
            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog('Updated PT Survey - ' . $params['distributionCode'], 'shipment');
        }
        return $distributionId;
    }
    public function getUnshippedDistributions()
    {
        return $this->fetchAll($this->select()->where("status != 'shipped'"));
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
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . '
				 	' . ($parameters['sSortDir_' . $i]) . ', ';
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
