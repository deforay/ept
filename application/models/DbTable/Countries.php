<?php

class Application_Model_DbTable_Countries extends Zend_Db_Table_Abstract
{
	protected $_name = 'countries';

	public function getAllCountries()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->distinct()->from($this->_name)->order('iso_name');
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
			$sql = $sql->where("id IN(".$authNameSpace->ptccMappedCountries.")");
		}
		return $db->fetchAll($sql);
	}

	public function fetchAllCountries($search)
	{
		$sql = $this->select();
		$sql =  $sql->where("iso_name LIKE '%" . $search . "%'")
            ->orWhere("iso2 LIKE '%" . $search . "%'")
            ->orWhere("iso3 LIKE '%" . $search . "%'");
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (isset($authNameSpace->ptcc) && $authNameSpace->ptcc == 1) {
			$sql = $sql->where("id IN(".$authNameSpace->ptccMappedCountries.")");
		}
		return $this->fetchAll($sql);
	}

	public function fetchParticipantCountriesList()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->distinct()->from(array("c" => $this->_name))->columns(array('id', 'iso_name'))
			->join(array('p' => 'participant'), 'c.id=p.country', array(''))
			->order('iso_name');
		return $db->fetchAll($sql);
	}
}
