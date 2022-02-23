<?php

class Application_Model_DbTable_ScheduledJobs extends Zend_Db_Table_Abstract
{
    protected $_name = 'scheduled_jobs';
    protected $_primary = 'job_id';

    public function saveScheduledJobsDetails($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $startDate = $params['startDate'];
        $endDate = $params['endDate'];

        $query = $db->select()
            ->from(array('s' => 'shipment'), array('s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date', 's.lastdate_response'))
            ->where("DATE(s.shipment_date) >=?", $startDate)
            ->where("DATE(s.shipment_date) <=?", $endDate)
            ->where("s.status <= ?", 'finalized')
            ->order("s.scheme_type");

        if (isset($params['scheme']) && !empty($params['scheme']) && count($params['scheme']) > 0) {
            $sWhere = "";
            foreach ($params['scheme'] as $val) {
                if ($sWhere != "") {
                    $sWhere .= " OR ";
                }
                $sWhere .= "s.scheme_type='" . $val . "'";
            }
            if (!empty($sWhere)) {
                $query = $query->where($sWhere);
            }
        }
        $shipmentResult = $db->fetchAll($query);
        foreach ($shipmentResult as $shipment) {
            $shipmentId[] = $shipment['shipment_id'];
        }
        return $this->insert(array(
            "job" => "generate-certificates.php -s " . implode(",", $shipmentId) . " -c " . $params['certificateName'],
            "requested_on" => new Zend_Db_Expr('now()'),
            "requested_by" => $authNameSpace->primary_email,
            "completed_on" => new Zend_Db_Expr('now()')
        ));
    }
}
