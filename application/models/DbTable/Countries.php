<?php

class Application_Model_DbTable_Countries extends Zend_Db_Table_Abstract
{
	protected $_name = 'countries';

	public function getAllCountries()
	{
		$sql = $this->select();
		return $this->fetchAll($sql);
	}

	public function fetchParticipantCountriesList()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array("c" => $this->_name))->columns(array('id', 'iso_name'))
			->join(array('p' => 'participant'), 'c.id=p.country', array(''));
		return $db->fetchAll($sql);
	}
}
