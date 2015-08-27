<?php

class Application_Model_DbTable_SchemeList extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_list';
    protected $_primary = 'scheme_id';

    public function getAllSchemes(){
        return $this->fetchAll($this->select()->where("status='active'")->order("scheme_name"));
    }
    public function getFullSchemeList(){
        return $this->fetchAll($this->select())->toArray();
    }

    public function countEnrollmentSchemes(){
        $result="";
        $sql=$this->fetchAll($this->select()->where("status='active'"));
        
        foreach($sql as $scheme){
            $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'),array())
                        ->join(array('e'=>'enrollments'),'p.participant_id = e.participant_id',new Zend_Db_Expr("COUNT('e.participant_id')"))
                        ->where("p.status='active'")
                        ->where("e.scheme_id=?",$scheme['scheme_id']);
            $aResult= $this->getAdapter()->fetchCol($sQuery);
            $result[strtoupper($scheme['scheme_id'])]=  $aResult[0];
            
        }
        
        return $result;
    }
}

