<?php

class Application_Service_Distribution
{
    public function getAllDistributions($params)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getAllDistributions($params);
    }
    public function addDistribution($params)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->addDistribution($params);
    }
    public function getDistribution($did)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getDistribution($did);
    }
    public function updateDistribution($params)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->updateDistribution($params);
    }
    public function hasFinalizedShipment($distributionId)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->hasFinalizedShipment($distributionId);
    }
    public function getFinalizedShipmentCodes($distributionId)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getFinalizedShipmentCodes($distributionId);
    }

    /**
     * Cancel a PT Survey by cancelling every (active, non-finalized) shipment under
     * it. Uses the exact same rules and confirmation as a single shipment cancel:
     * the admin must type "CANCEL" and give a reason, and if ANY shipment is already
     * finalized the whole survey cannot be cancelled. When the last active shipment
     * is cancelled, cancelShipment() flips the survey's own status to 'cancelled'.
     *
     * @return array{success:bool,message:string}
     */
    public function cancelDistribution($distributionId, $reason, $confirmToken)
    {
        $distributionId = (int) $distributionId;
        if ($distributionId <= 0) {
            return ['success' => false, 'message' => 'Invalid PT Survey.'];
        }
        if (strtoupper(trim((string) $confirmToken)) !== 'CANCEL') {
            return ['success' => false, 'message' => 'Please type CANCEL to confirm.'];
        }
        $reason = trim((string) $reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'A reason for cancelling is required.'];
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $distro = $db->fetchRow($db->select()->from('distributions')->where('distribution_id = ?', $distributionId));
        if (empty($distro)) {
            return ['success' => false, 'message' => 'PT Survey not found.'];
        }

        $shipments = $db->fetchAll(
            $db->select()->from('shipment', ['shipment_id', 'shipment_code', 'status', 'cancelled_at'])
                ->where('distribution_id = ?', $distributionId)
        );
        if (empty($shipments)) {
            return ['success' => false, 'message' => 'This PT Survey has no shipments to cancel.'];
        }

        // Same rule as a single shipment cancel: a finalized shipment can never be
        // cancelled, so a survey holding one cannot be cancelled at all.
        $finalized = [];
        $active = [];
        foreach ($shipments as $s) {
            if (($s['status'] ?? '') === 'finalized') {
                $finalized[] = $s['shipment_code'];
            } elseif (empty($s['cancelled_at'])) {
                $active[] = $s;
            }
        }
        if (!empty($finalized)) {
            return ['success' => false, 'message' => 'This PT Survey cannot be cancelled because these shipments are finalized: ' . implode(', ', $finalized) . '.'];
        }
        if (empty($active)) {
            return ['success' => false, 'message' => 'This PT Survey is already cancelled.'];
        }

        $shipmentService = new Application_Service_Shipments();
        $cancelledCount = 0;
        $failures = [];
        foreach ($active as $s) {
            $res = $shipmentService->cancelShipment($s['shipment_id'], $reason, $confirmToken);
            if (!empty($res['success'])) {
                $cancelledCount++;
            } else {
                $failures[] = $s['shipment_code'] . ': ' . $res['message'];
            }
        }

        $auditDb = new Application_Model_DbTable_AuditLog();
        $auditDb->addNewAuditLog(
            'Cancelled PT Survey - ' . $distro['distribution_code'] . " ({$cancelledCount} shipments, reason: {$reason})",
            'shipment'
        );

        if (!empty($failures)) {
            return ['success' => false, 'message' => 'Some shipments could not be cancelled: ' . implode('; ', $failures)];
        }
        return ['success' => true, 'message' => 'PT Survey ' . $distro['distribution_code'] . ' and its ' . $cancelledCount . ' shipment(s) have been cancelled.'];
    }
    public function deleteDistribution($distributionId)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->deleteDistribution($distributionId);
    }
    public function getDistributionDates()
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getDistributionDates();
    }
    public function getShipments($distroId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['s' => 'shipment'])
            ->where('distribution_id = ?', $distroId);

        return $db->fetchAll($sql);
    }

    public function getUnshippedDistributions()
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getUnshippedDistributions();
    }

    public function updateDistributionStatus($distributionId, $status)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->updateDistributionStatus($distributionId, $status);
    }

    public function shipDistribution($distributionId)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $distroCode = $db->fetchOne(
            $db->select()
                ->from('distributions', ['distribution_code'])
                ->where('distribution_id = ?', $distributionId)
        );
        $shipmentCount = (int) $db->fetchOne(
            $db->select()
                ->from('shipment', new Zend_Db_Expr('COUNT(*)'))
                ->where('distribution_id = ?', $distributionId)
        );
        $db->beginTransaction();
        try {
            $shipmentDb = new Application_Model_DbTable_Shipments();
            $shipmentDb->updateShipmentStatusByDistribution($distributionId, 'shipped');
            $disrtibutionDb = new Application_Model_DbTable_Distribution();
            $disrtibutionDb->updateDistributionStatus($distributionId, 'shipped');
            $db->commit();

            $auditDb = new Application_Model_DbTable_AuditLog();
            $auditDb->addNewAuditLog(
                'Shipped PT Survey - ' . ($distroCode ?: "#$distributionId") . " ({$shipmentCount} shipments)",
                'shipment'
            );

            return 'PT Event shipped!';
        } catch (Throwable $e) {
            $db->rollBack();
            Pt_Commons_LoggerUtility::logError($e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Unable to ship. Please try again later or contact system admin for help';
        }
    }

    public function getAllDistributionReports($parameters)
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getAllDistributionReports($parameters);
    }
    public function getAllDistributionStatus()
    {
        $disrtibutionDb = new Application_Model_DbTable_Distribution();
        return $disrtibutionDb->getAllDistributionStatusDetails();
    }

    public function generateSurveyCode($ptDate = null)
    {
        $ptDate = !empty($ptDate) ? date('Y-m', strtotime($ptDate)) : date('Y-m');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $sql = $db->select()->from(['d' => 'distributions'], ['count' => new Zend_Db_Expr('COUNT(distribution_id)')])
            ->where("DATE_FORMAT(distribution_date, '%Y-%m') = ?", $ptDate)
            ->order('distribution_id desc');
        $result = $db->fetchRow($sql);
        $count = sprintf('%02d', ((int) ($result['count'] ?? 0)) + 1);
        return "PT-$ptDate-$count";
    }
}
