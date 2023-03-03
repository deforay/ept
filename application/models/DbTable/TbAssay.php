<?php

class Application_Model_DbTable_TbAssay extends Zend_Db_Table_Abstract
{

    protected $_name = 'r_tb_assay';
    protected $_primary = 'id';

    public function fetchAllTbAssay()
    {
        return $this->fetchAll("status like 'active'")->toArray();
    }
    public function getTbAssayName($assayId)
    {
        $row = $this->fetchRow("id = $assayId")->toArray();
        return $row['name'];
    }
}
