<?php

class Application_Model_DbTable_CertificateBatches extends Zend_Db_Table_Abstract
{
    protected $_name = 'certificate_batches';
    protected $_primary = 'batch_id';

    /**
     * Create a new certificate batch record
     *
     * @param array $data Batch data including batch_name, shipment_ids, created_by
     * @return int The new batch_id
     */
    public function createBatch($data)
    {
        $insertData = [
            'batch_name' => $data['batch_name'],
            'shipment_ids' => $data['shipment_ids'],
            'created_by' => $data['created_by'],
            'created_on' => new Zend_Db_Expr('NOW()')
        ];

        if (isset($data['status'])) {
            $insertData['status'] = $data['status'];
        }

        $this->insert($insertData);
        return $this->getAdapter()->lastInsertId();
    }

    /**
     * Update batch status and any additional fields
     *
     * @param int $batchId The batch ID to update
     * @param string $status The new status value
     * @param array $data Additional fields to update
     * @return int Number of rows affected
     */
    public function updateStatus($batchId, $status, $data = [])
    {
        $updateData = array_merge($data, ['status' => $status]);
        return $this->update($updateData, ['batch_id = ?' => (int) $batchId]);
    }

    /**
     * Get a single batch by its ID
     *
     * @param int $batchId The batch ID
     * @return array|null The batch record or null if not found
     */
    public function getBatch($batchId)
    {
        $select = $this->select()
            ->from($this->_name)
            ->where('batch_id = ?', (int) $batchId);

        return $this->getAdapter()->fetchRow($select);
    }

    /**
     * Get the most recent batch that matches the given shipment IDs
     *
     * @param string $shipmentIds Comma-separated shipment IDs
     * @return array|null The batch record or null if not found
     */
    public function getLatestBatchForShipments($shipmentIds)
    {
        $select = $this->select()
            ->from($this->_name)
            ->where('shipment_ids = ?', $shipmentIds)
            ->order('created_on DESC')
            ->limit(1);

        return $this->getAdapter()->fetchRow($select);
    }

    /**
     * Get all batches for DataTables server-side processing
     *
     * @param array $parameters DataTables request parameters
     * @return array DataTables response with aaData, iTotalRecords, iTotalDisplayRecords
     */
    public function getAllBatchesByGrid($parameters)
    {
        $aColumns = ['cb.batch_name', 'cb.status', 'cb.excellence_count', 'cb.participation_count', 'cb.skipped_count', 'a.first_name', 'cb.created_on'];
        $orderColumns = ['batch_name', 'status', 'excellence_count', 'participation_count', 'skipped_count', 'created_by_name', 'created_on'];

        $dbAdapter = $this->getAdapter();

        // Base query
        $sQuery = $dbAdapter->select()
            ->from(['cb' => $this->_name], [
                'batch_id',
                'batch_name',
                'shipment_ids',
                'status',
                'excellence_count',
                'participation_count',
                'skipped_count',
                'download_url',
                'error_message',
                'created_by',
                'created_on',
                'approved_by',
                'approved_on'
            ])
            ->joinLeft(
                ['a' => 'system_admin'],
                'a.admin_id = cb.created_by',
                ['created_by_name' => new Zend_Db_Expr("CONCAT(a.first_name, ' ', a.last_name)")]
            )
            ->joinLeft(
                ['a2' => 'system_admin'],
                'a2.admin_id = cb.approved_by',
                ['approved_by_name' => new Zend_Db_Expr("CONCAT(a2.first_name, ' ', a2.last_name)")]
            );

        // Global search
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
                        $sWhereSub .= $aColumns[$i] . " LIKE " . $dbAdapter->quote('%' . $search . '%') . " OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE " . $dbAdapter->quote('%' . $search . '%') . " ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sQuery = $sQuery->where($sWhereSub);
        }

        // Status filter
        if (!empty($parameters['status'])) {
            $sQuery = $sQuery->where('cb.status = ?', $parameters['status']);
        }

        // Date range filter
        if (!empty($parameters['dateFrom'])) {
            $dateFrom = date('Y-m-d', strtotime($parameters['dateFrom']));
            $sQuery = $sQuery->where('DATE(cb.created_on) >= ?', $dateFrom);
        }
        if (!empty($parameters['dateTo'])) {
            $dateTo = date('Y-m-d', strtotime($parameters['dateTo']));
            $sQuery = $sQuery->where('DATE(cb.created_on) <= ?', $dateTo);
        }

        // Get total count before pagination
        $countQuery = clone $sQuery;
        $countQuery = $countQuery->reset(Zend_Db_Select::COLUMNS)
            ->columns(new Zend_Db_Expr('COUNT(*) as total'));
        $countResult = $dbAdapter->fetchRow($countQuery);
        $iFilteredTotal = (int) ($countResult['total'] ?? 0);

        // Sorting
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $orderColumns[intval($parameters['iSortCol_' . $i])] . " " . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }
            $sOrder = substr_replace($sOrder, "", -2);
            if (!empty($sOrder)) {
                $sQuery = $sQuery->order($sOrder);
            }
        } else {
            $sQuery = $sQuery->order('cb.created_on DESC');
        }

        // Pagination
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sQuery = $sQuery->limit((int) $parameters['iDisplayLength'], (int) $parameters['iDisplayStart']);
        }

        $rResult = $dbAdapter->fetchAll($sQuery);

        // Get total records (without filters)
        $totalQuery = $dbAdapter->select()->from($this->_name, [new Zend_Db_Expr('COUNT(*) as total')]);
        $totalResult = $dbAdapter->fetchRow($totalQuery);
        $iTotal = (int) ($totalResult['total'] ?? 0);

        // Fetch shipment codes for each batch
        foreach ($rResult as &$row) {
            if (!empty($row['shipment_ids'])) {
                $shipmentIds = array_map('intval', explode(',', $row['shipment_ids']));
                if (!empty($shipmentIds)) {
                    $shipmentQuery = $dbAdapter->select()
                        ->from('shipment', ['shipment_code'])
                        ->where('shipment_id IN (?)', $shipmentIds);
                    $shipments = $dbAdapter->fetchCol($shipmentQuery);
                    $row['shipment_codes'] = implode(', ', $shipments);
                }
            }
        }
        unset($row); // break reference

        // Build output
        $output = [
            "sEcho" => isset($parameters['sEcho']) ? intval($parameters['sEcho']) : 0,
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => $rResult
        ];

        return $output;
    }

    /**
     * Cancel a batch by marking it as cancelled
     *
     * @param int $batchId The batch ID to cancel
     * @param int $cancelledBy Admin ID who cancelled the batch
     * @return bool True if successfully cancelled, false otherwise
     */
    public function cancelBatch($batchId, $cancelledBy = null)
    {
        $batch = $this->getBatch($batchId);

        if (!$batch) {
            return false;
        }

        // Only allow cancelling pending or generating batches
        $cancellableStatuses = ['pending', 'generating'];
        if (!in_array($batch['status'], $cancellableStatuses)) {
            return false;
        }

        $updateData = [
            'status' => 'cancelled'
        ];

        if ($cancelledBy) {
            $updateData['approved_by'] = $cancelledBy; // Reusing approved_by for cancelled_by
            $updateData['approved_on'] = new Zend_Db_Expr('NOW()');
        }

        return $this->update($updateData, ['batch_id = ?' => (int) $batchId]) > 0;
    }

    /**
     * Reject a generated batch (don't distribute certificates)
     *
     * @param int $batchId The batch ID to reject
     * @param int $rejectedBy Admin ID who rejected the batch
     * @return bool True if successfully rejected, false otherwise
     */
    public function rejectBatch($batchId, $rejectedBy = null)
    {
        $batch = $this->getBatch($batchId);

        if (!$batch) {
            return false;
        }

        // Only allow rejecting generated batches
        if ($batch['status'] !== 'generated') {
            return false;
        }

        $updateData = [
            'status' => 'rejected'
        ];

        if ($rejectedBy) {
            $updateData['approved_by'] = $rejectedBy; // Reusing approved_by for rejected_by
            $updateData['approved_on'] = new Zend_Db_Expr('NOW()');
        }

        return $this->update($updateData, ['batch_id = ?' => (int) $batchId]) > 0;
    }
}
