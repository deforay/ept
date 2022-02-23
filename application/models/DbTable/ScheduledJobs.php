<?php

class Application_Model_DbTable_ScheduledJobs extends Zend_Db_Table_Abstract
{
    protected $_name = 'scheduled_jobs';
    protected $_primary = 'job_id';

    public function saveScheduledJobsDetails($params)
    {
        $authNameSpace = new Zend_Session_Namespace('administrators');
        return $this->insert(array(
            "job" => $params['certificateName'],
            "requested_on" => new Zend_Db_Expr('now()'),
            "requested_by" => $authNameSpace->primary_email,
            "completed_on" => new Zend_Db_Expr('now()')
        ));
    }
}
