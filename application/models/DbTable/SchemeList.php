<?php

class Application_Model_DbTable_SchemeList extends Zend_Db_Table_Abstract
{

    protected $_name = 'scheme_list';
    protected $_primary = 'scheme_id';

    public function getAllSchemes(){
        $authNameSpace = new Zend_Session_Namespace('administrators');
        $schemes = [];
        foreach(explode(",", $authNameSpace->activeScheme) as $scheme){
            $schemes[] = sprintf("'%s'", $scheme);;
        }
        $sQuery = $this->getAdapter()->select()->from(array("s" => $this->_name), array('*'))->where("status='active'")->order("scheme_name");
        if(isset($authNameSpace->activeScheme) && !empty($authNameSpace->activeScheme)){
            $sQuery = $sQuery->where("scheme_id IN(".implode(",", $schemes).")");
        }
        return $this->getAdapter()->fetchAll($sQuery);
    }
    public function getFullSchemeList(){
        return $this->fetchAll($this->select())->toArray();
    }

    public function countEnrollmentSchemes(){
        $result=array();;
        $sql=$this->fetchAll($this->select()->where("status='active'"));
        
        foreach($sql as $scheme){
            $sQuery = $this->getAdapter()->select()->from(array('p' => 'participant'),array())
                        ->join(array('e'=>'enrollments'),'p.participant_id = e.participant_id',new Zend_Db_Expr("COUNT('e.participant_id')"))
                        ->where("p.status='active'")
                        ->where("e.scheme_id=?",$scheme['scheme_id']);
            $aResult= $this->getAdapter()->fetchCol($sQuery);
            $result[strtoupper($scheme['scheme_name'])]=  $aResult[0];
            
        }
        
        return $result;
    }
}

