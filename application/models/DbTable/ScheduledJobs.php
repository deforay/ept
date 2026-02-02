<?php

class Application_Model_DbTable_ScheduledJobs extends Zend_Db_Table_Abstract
{
    protected $_name = 'scheduled_jobs';
    protected $_primary = 'job_id';

    public function scheduleCertificationGeneration($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        $query = $db->select()
            ->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response'])
            ->where("s.status = 'finalized'");

        if (isset($params['shipmentId']) && !empty($params['shipmentId'])) {
            // Sanitize shipmentId array - ensure all values are integers
            $safeShipmentIds = array_map('intval', $params['shipmentId']);
            $impShipmentId = implode(",", $safeShipmentIds);
            $query = $query->where("s.shipment_id IN ($impShipmentId)");
        }
        $shipmentResult = $db->fetchAll($query);

        $shipmentId = [];
        foreach ($shipmentResult as $shipment) {
            $shipmentId[] = intval($shipment['shipment_id']);
            if (!file_exists(SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates' . DIRECTORY_SEPARATOR . $shipment['scheme_type'] . "-e.docx")) {
                $directory[] = $shipment['scheme_type'];
                $resp = 9999999;
            }
        }


        if (!empty($shipmentId) && isset($params['certificateName']) && $params['certificateName'] != "") {
            // Validate certificateName - only allow alphanumeric characters and hyphens
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $params['certificateName'])) {
                throw new Exception('Invalid certificate name: only alphanumeric characters and hyphens are allowed');
            }

            // Create a batch record first
            $certificateBatchesDb = new Application_Model_DbTable_CertificateBatches();
            $shipmentIdsStr = implode(",", $shipmentId);
            $batchId = $certificateBatchesDb->createBatch([
                'batch_name' => $params['certificateName'],
                'shipment_ids' => $shipmentIdsStr,
                'created_by' => $authNameSpace->admin_id,
                'status' => 'pending'
            ]);

            $safeCertName = escapeshellarg($params['certificateName']);
            $safeShipmentIdStr = escapeshellarg($shipmentIdsStr);
            $safeBatchId = intval($batchId);

            $this->insert([
                "job" => "generate-certificates.php -s $safeShipmentIdStr -c $safeCertName -b $safeBatchId",
                "requested_on" => new Zend_Db_Expr('now()'),
                "requested_by" => $authNameSpace->admin_id,
            ]);

            // Return batch_id instead of job_id for status polling
            return $batchId;
        } else {
            $resp = 0;
        }
        return $resp;
    }
    public function scheduleEvaluation($shipmentId)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();

        // Sanitize shipmentId - ensure it's an integer
        $safeShipmentId = intval($shipmentId);
        if ($safeShipmentId <= 0) {
            return 0;
        }

        // Update status to 'queued' and set previous_status to the original status value
        $db->query(
            "UPDATE shipment SET previous_status = `status`, `status` = 'queued', updated_on_admin = ? WHERE shipment_id = ?",
            [Pt_Commons_DateUtility::getCurrentDateTime(), $safeShipmentId]
        );

        return $this->insert([
            "job" => "evaluate-shipments.php -s " . escapeshellarg($safeShipmentId),
            "requested_on" => new Zend_Db_Expr('now()'),
            "requested_by" => $authNameSpace->admin_id,
        ]);
    }

    /**
     * Schedule certificate distribution job for a batch
     *
     * @param int $batchId The batch ID to distribute
     * @return int The job_id of the created job
     */
    public function scheduleCertificateDistribution($batchId)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $safeBatchId = intval($batchId);

        if ($safeBatchId <= 0) {
            return 0;
        }

        return $this->insert([
            "job" => "distribute-certificates.php -b " . escapeshellarg($safeBatchId),
            "requested_on" => new Zend_Db_Expr('now()'),
            "requested_by" => $authNameSpace->admin_id,
        ]);
    }
}
