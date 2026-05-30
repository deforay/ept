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
