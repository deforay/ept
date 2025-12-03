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
            $impShipmentId = implode(",", $params['shipmentId']);
            $query = $query->where("s.shipment_id IN ($impShipmentId)");
        }
        $shipmentResult = $db->fetchAll($query);

        $shipmentId = [];
        foreach ($shipmentResult as $shipment) {
            $shipmentId[] = $shipment['shipment_id'];
            if (!file_exists(SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates' . DIRECTORY_SEPARATOR . $shipment['scheme_type'] . "-e.docx")) {
                $directory[] = $shipment['scheme_type'];
                $resp = 9999999;
            }
        }


        if (!empty($shipmentId) && isset($params['certificateName']) && $params['certificateName'] != "") {
            return $this->insert([
                "job" => "generate-certificates.php -s " . implode(",", $shipmentId) . " -c " . $params['certificateName'],
                "requested_on" => new Zend_Db_Expr('now()'),
                "requested_by" => $authNameSpace->admin_id,
            ]);
        } else {
            $resp = 0;
        }
        return $resp;
    }
    public function scheduleEvaluation($shipmentId)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        // Update status to 'queued' and set previous_status to the original status value
        $db->query("UPDATE shipment SET previous_status = `status`, `status` = 'queued', updated_on_admin = ? WHERE shipment_id = ?", [Pt_Commons_General::getDateTime(), $shipmentId]);

        if (isset($shipmentId) && !empty($shipmentId)) {
            return $this->insert([
                "job" => "evaluate-shipments.php -s $shipmentId",
                "requested_on" => new Zend_Db_Expr('now()'),
                "requested_by" => $authNameSpace->admin_id,
            ]);
        } else {
            return 0;
        }
    }
}
