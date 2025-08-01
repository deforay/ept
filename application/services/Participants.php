<?php

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class Application_Service_Participants
{

	private $db = null;
	private $common = null;

	public function __construct()
	{
		$this->db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$this->common = new Application_Service_Common();
	}

	public function getUsersParticipants($userSystemId = null)
	{
		if ($userSystemId == null) {
			$authNameSpace = new Zend_Session_Namespace('datamanagers');
			$userSystemId = $authNameSpace->dm_id;
		}

		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getParticipantsByUserSystemId($userSystemId);
	}

	public function getParticipantDetails($partSysId)
	{

		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getParticipant($partSysId);
	}

	public function addParticipant($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->addParticipant($params);
	}

	public function addParticipantForDataManager($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->addParticipantForDataManager($params);
	}

	public function updateParticipant($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->updateParticipant($params);
	}
	public function getAllParticipants($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getAllParticipants($params);
	}

	public function getAllEnrollments($params)
	{
		$enrollments = new Application_Model_DbTable_Enrollments();
		return $enrollments->getAllEnrollments($params);
	}
	public function getEnrollmentDetails($pid, $sid)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'))
			->joinLeft(array('sp' => 'shipment_participant_map'), 'p.participant_id=sp.participant_id')
			->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id')
			->where("p.participant_id=" . $pid);
		return $db->fetchAll($sql);
	}

	public function getParticipantsListNames()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('eln' => 'enrollments'), array('*'))->group(array('list_name'));
		return $db->fetchAll($sql);
	}

	public function getParticipantsListNamesByUniqueId($id)
	{
		if (isset($id) && sizeof($id) > 0) {
			$ids = [];
			foreach ($id as $d) {
				$ids[] = base64_decode($d);
			}
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$sql = $db->select()->from(['eln' => 'enrollments'], ['*'])
				->where("eln.list_name IN ('" . implode("', '", $ids) . "')");
			return $db->fetchAll($sql);
		}
	}
	public function getParticipantSchemes($dmId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'))
			->joinLeft(array('sp' => 'shipment_participant_map'), 'p.participant_id=sp.participant_id')
			->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id')
			->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
			->where("pmm.dm_id= ?", $dmId)
			->group(array("sp.participant_id", "s.scheme_type"))
			->order("p.first_name");
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $db->fetchAll($sql);
	}

	public function getPendingParticipants()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'), array('p.participant_id'))
			->where("p.status= ?", "pending");
		return $db->fetchAll($sql);
	}

	public function getUnEnrolled($scheme, $params = '')
	{

		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$subSql = $db->select()->from(array('e' => 'enrollments'), 'participant_id')->where("scheme_id = ?", $scheme);
		$sql = $db->select()->from(array('p' => 'participant'))
			->where("participant_id NOT IN ?", $subSql)
			->where("p.status='active'")
			->order('first_name')
			->group('p.participant_id');
		if (isset($params['choosenPid']) && trim($params['choosenPid']) != '') {
			$pId = explode(',', $params['choosenPid']);
			$sql = $sql->where("p.institute_name IN (?)", $pId);
		}
		if (isset($params['selectedCountries']) && trim($params['selectedCountries']) != '') {
			$countryId = explode(',', $params['selectedCountries']);
			$sql = $sql->where("p.country IN (?)", $countryId);
		}
		if (isset($params['selectedRegions']) && trim($params['selectedRegions']) != '') {
			$regionId = explode(',', $params['selectedRegions']);
			$sql = $sql->where("p.region IN (?)", $regionId);
		}
		if (isset($params['selectedDistricts']) && trim($params['selectedDistricts']) != '') {
			$districtId = explode(',', $params['selectedDistricts']);
			$sql = $sql->where("p.district IN (?)", $districtId);
		}
		if (isset($params['selectedStates']) && trim($params['selectedStates']) != '') {
			$stateId = explode(',', $params['selectedStates']);
			$sql = $sql->where("p.state IN (?)", $stateId);
		}

		if (isset($params['selectedCities']) && trim($params['selectedCities']) != '') {
			$cityId = explode(',', $params['selectedCities']);
			$sql = $sql->where("p.city IN (?)", $cityId);
		}
		return $db->fetchAll($sql);
	}
	public function getEnrolledBySchemeCode($scheme, $schemeName = "")
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(['e' => 'enrollments'], [])
			->joinLeft(['p' => 'participant'], "p.participant_id=e.participant_id")
			->where("scheme_id = ?", $scheme)
			->where("p.status='active'")
			->order('first_name')
			->group('p.participant_id');
		if (isset($schemeName) && !empty($schemeName)) {
			$sql = $sql->where("IFNULL(list_name, 'default') = ?", $schemeName);
		}
		return $db->fetchAll($sql);
	}

	public function getEnrolledByShipmentId($shipmentId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(['p' => 'participant'])
			->joinLeft(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
			->joinLeft(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
			->where("s.shipment_id = ?", $shipmentId)
			->where("p.status='active'")
			->order('p.first_name')
			->group('p.participant_id');

		return $db->fetchAll($sql);
	}
	public function getSchemesByParticipantId($pid)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'), array())
			->joinLeft(array('e' => 'enrollments'), 'e.participant_id=p.participant_id', array())
			->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=e.scheme_id', array('scheme_id'))
			->where("p.participant_id = ?", $pid)
			->order('p.first_name');
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $db->fetchCol($sql);
	}
	public function getUnEnrolledByShipmentId($shipmentId, $params = [])
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$subSql = $db->select()->from(['p' => 'participant'], ['participant_id'])
			->joinLeft(['sp' => 'shipment_participant_map'], 'sp.participant_id=p.participant_id', [])
			->joinLeft(['s' => 'shipment'], 'sp.shipment_id=s.shipment_id', [])
			->where("s.shipment_id = ?", $shipmentId)
			->where("p.status='active'")
			->group('p.participant_id');
		$sql = $db->select()->from(['p' => 'participant'])->where("participant_id NOT IN ?", $subSql)
			->order('p.first_name');
		if (isset($params['choosenPid']) && trim($params['choosenPid']) != '') {
			$pId = explode(',', $params['choosenPid']);
			$sql = $sql->where("p.institute_name IN (?)", $pId);
		}
		if (isset($params['selectedCountries']) && trim($params['selectedCountries']) != '') {
			$countryId = explode(',', $params['selectedCountries']);
			$sql = $sql->where("p.country IN (?)", $countryId);
		}
		if (isset($params['selectedRegions']) && trim($params['selectedRegions']) != '') {
			$regionId = explode(',', $params['selectedRegions']);
			$sql = $sql->where("p.region IN (?)", $regionId);
		}
		if (isset($params['selectedDistricts']) && trim($params['selectedDistricts']) != '') {
			$districtId = explode(',', $params['selectedDistricts']);
			$sql = $sql->where("p.district IN (?)", $districtId);
		}
		if (isset($params['selectedStates']) && trim($params['selectedStates']) != '') {
			$stateId = explode(',', $params['selectedStates']);
			$sql = $sql->where("p.state IN (?)", $stateId);
		}

		if (isset($params['selectedCities']) && trim($params['selectedCities']) != '') {
			$cityId = explode(',', $params['selectedCities']);
			$sql = $sql->where("p.city IN (?)", $cityId);
		}
		return $db->fetchAll($sql);
	}

	public function enrollParticipants($params)
	{
		$enrollments = new Application_Model_DbTable_Enrollments();
		return $enrollments->enrollParticipants($params);
	}
	public function addParticipantManagerMap($params, $type = null)
	{
		$db = new Application_Model_DbTable_Participants();
		return $db->addParticipantManager($params, $type);
	}
	public function getAffiliateList()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_participant_affiliates')->order('affiliate ASC'));
	}
	public function getEnrolledProgramsList()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_enrolled_programs')->order('enrolled_programs ASC'));
	}
	public function getSiteTypeList()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_site_type')->order('site_type ASC'));
	}
	public function getNetworkTierList()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		return $db->fetchAll($db->select()->from('r_network_tiers')->order('network_name ASC'));
	}
	public function getAllParticipantRegion()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'), array('p.region'))
			->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")
			->order("p.region");
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $db->fetchAll($sql);
	}
	public function getAllParticipantStates()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'), array('p.state'))
			->group('p.state')->where("p.state IS NOT NULL")->where("p.state != ''")
			->order("p.state");
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $db->fetchAll($sql);
	}
	public function getAllParticipantDistricts()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'), array('p.district'))
			->group('p.district')->where("p.district IS NOT NULL")->where("p.district != ''")
			->order("p.district");
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		if (!empty($authNameSpace->dm_id)) {
			$sql = $sql
				->joinLeft(array('pmm' => 'participant_manager_map'), 'pmm.participant_id=p.participant_id', array())
				->where("pmm.dm_id = ?", $authNameSpace->dm_id);
		}
		return $db->fetchAll($sql);
	}

	public function getAllParticipantDetails($dmId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'))
			->join(array('c' => 'countries'), 'c.id=p.country')
			->joinLeft(array('pmm' => 'participant_manager_map'), 'p.participant_id=pmm.participant_id')
			->where("pmm.dm_id= ?", $dmId)
			->group(array("p.participant_id"))
			->order("p.first_name");
		return $db->fetchAll($sql);
	}


	public function getAllActiveParticipants()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchAllActiveParticipants();
	}

	public function getSchemeWiseParticipants($schemeType)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getSchemeWiseParticipants($schemeType);
	}

	public function getShipmentEnrollement($parameters)
	{
		$db = new Application_Model_DbTable_Participants();
		$db->getEnrolledByShipmentDetails($parameters);
	}

	public function getShipmentUnEnrollements($parameters)
	{
		$db = new Application_Model_DbTable_Participants();
		$db->getUnEnrolledByShipments($parameters);
	}
	public function getShipmentRespondedParticipants($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getShipmentRespondedParticipants($params);
	}
	public function getShipmentNotRespondedParticipants($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getShipmentNotRespondedParticipants($params);
	}
	public function getShipmentNotEnrolledParticipants($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getShipmentNotEnrolledParticipants($params);
	}

	public function getParticipantSchemesBySchemeId($parameters)
	{
		$shipmentDb = new Application_Model_DbTable_Shipments();
		return $shipmentDb->fetchParticipantSchemesBySchemeId($parameters);
	}

	public function exportShipmentRespondedParticipantsDetails($params)
	{
		try {
			$excel = new Spreadsheet();

			$output = [];
			$sheet = $excel->getActiveSheet();
			$colNo = 0;

			if ($params['type'] == 'from-participant') {
				$sheet->mergeCells('A1:E1');
				$sheet->getCell('A1')->setValue(html_entity_decode("Shipment Participant List", ENT_QUOTES, 'UTF-8'));
			} else {
				$sheet->mergeCells('A1:E1');
				$sheet->getCell('A1')->setValue(html_entity_decode("Responded Shipment Participant List", ENT_QUOTES, 'UTF-8'));

				if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
					$sheet->getCell('A2')->setValue(html_entity_decode("Shipment Code", ENT_QUOTES, 'UTF-8'));
					$sheet->getCell('B2')->setValue(html_entity_decode($params['shipmentCode'], ENT_QUOTES, 'UTF-8'));
				}
				if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
					$sheet->getCell('A3')->setValue(html_entity_decode("Shipment Date", ENT_QUOTES, 'UTF-8'));
					$sheet->getCell('B3')->setValue(html_entity_decode($params['shipmentDate'], ENT_QUOTES, 'UTF-8'));
				}
			}

			$headings = [
				"Lab/Participant ID",
				"Lab/Participant Name",
				"Institute Name",
				"State/Province/Region",
				"District/County",
				"Country",
				"Cell/Mobile",
				"Phone",
				"Affiliation",
				"Email"
			];

			if ($params['type'] == 'from-participant') {
				$headings[] = "Participant Status";
			} else {
				$headings[] = "Response Status";
			}
			$sheet->fromArray($headings, null, 'A3');

			$sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$sQuery = $sQuerySession->shipmentRespondedParticipantQuery;
			if ($params['type'] == 'from-participant') {
				// $sQuery = $sQuery->where("p.status = ? ", 'active');
			}
			$rResult = $db->fetchAll($sQuery);

			foreach ($rResult as $aRow) {
				$row = [];
				$row[] = $aRow['unique_identifier'];
				$row[] = $aRow['participantName'];
				$row[] = $aRow['institute_name'];
				$row[] = $aRow['state'];
				$row[] = $aRow['district'];
				$row[] = $aRow['iso_name'];
				$row[] = $aRow['mobile'];
				$row[] = $aRow['phone'];
				$row[] = $aRow['affiliation'];
				$row[] = $aRow['email'];
				if ($params['type'] == 'from-participant') {
					$row[] = ucwords($aRow['status']);
				} else {
					$row[] = ucwords($aRow['RESPONSE']);
				}

				$output[] = $row;
			}

			foreach ($output as $rowNo => $rowData) {
				$rRowCount = $rowNo + 4;
				$sheet->fromArray($rowData, null, 'A' . $rRowCount);
			}

			$sheet = $this->common->centerAndBoldRowInSheet($sheet, 'A3');
			$sheet = $this->common->applyBordersToSheet($sheet);
			$sheet = $this->common->setAllColumnWidthsInSheet($sheet, 20);

			$writer = IOFactory::createWriter($excel, 'Xlsx');
			if ($params['type'] == 'from-participant') {
				$filename = 'PARTICIPANT-LIST-' . date('d-M-Y-H-i-s') . '.xlsx';
			} else {
				$filename = strtoupper($params['shipmentCode']) . '-PARTICIPANT-RESPONSE-REPORT-' . date('d-M-Y-H-i-s') . '.xlsx';
			}
			$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
			$authNameSpace = new Zend_Session_Namespace('administrators');
			$auditDb = new Application_Model_DbTable_AuditLog();
			$auditDb->addNewAuditLog("Downloaded Participant Data", "participants");
			return $filename;
		} catch (Exception $exc) {

			$sQuerySession->shipmentRespondedParticipantQuery = '';
			error_log("PARTICIPANT-EXCEL-" . $exc->getMessage());
			error_log($exc->getTraceAsString());
			return "";
		}
	}

	public function exportShipmentNotRespondedParticipantsDetails($params)
	{
		try {
			$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

			$output = [];
			$sheet = $excel->getActiveSheet();
			$colNo = 0;

			$styleArray = array(
				'font' => array(
					'bold' => true,
				),
				'alignment' => array(
					'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
					'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
				),
				'borders' => array(
					'outline' => array(
						'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
					),
				)
			);
			$styleInboldArray = array(
				'font' => array(
					'bold' => true,
				),
				'alignment' => array(
					'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
					'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
				)
			);
			$borderStyle = array(
				'alignment' => array(
					'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
				),
				'borders' => array(
					'outline' => array(
						'style' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
					),
				)
			);
			$sheet->mergeCells('A1:E1');
			$sheet->getCell('A1')->setValue(html_entity_decode("Not Responded Shipment Participant List", ENT_QUOTES, 'UTF-8'));
			$sheet->getStyle('A1')->applyFromArray($styleInboldArray, true);

			if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
				$sheet->getCell('A2')->setValue(html_entity_decode("Shipment Code", ENT_QUOTES, 'UTF-8'));
				$sheet->getCell('B2')->setValue(html_entity_decode($params['shipmentCode'], ENT_QUOTES, 'UTF-8'));
			}
			if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
				$sheet->getCell('A3')->setValue(html_entity_decode("Shipment Date", ENT_QUOTES, 'UTF-8'));
				$sheet->getCell('B3')->setValue(html_entity_decode($params['shipmentDate'], ENT_QUOTES, 'UTF-8'));
			}

			$sheet->getCell('A4')->setValue(html_entity_decode("Participant Id", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('B4')->setValue(html_entity_decode("Lab Name/Participant Name", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('C4')->setValue(html_entity_decode("Institute/Hospital Name", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('D4')->setValue(html_entity_decode("Department Name", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('E4')->setValue(html_entity_decode("Email", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('F4')->setValue(html_entity_decode("Cell/Mobile", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('G4')->setValue(html_entity_decode("City", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('H4')->setValue(html_entity_decode("State", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('I4')->setValue(html_entity_decode("District", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('J4')->setValue(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('K4')->setValue(html_entity_decode("Phone", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('L4')->setValue(html_entity_decode("Affiliation", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('M4')->setValue(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'));

			$sheet->getStyle('A4')->applyFromArray($styleArray, true);
			$sheet->getStyle('B4')->applyFromArray($styleArray, true);
			$sheet->getStyle('C4')->applyFromArray($styleArray, true);
			$sheet->getStyle('D4')->applyFromArray($styleArray, true);
			$sheet->getStyle('E4')->applyFromArray($styleArray, true);
			$sheet->getStyle('F4')->applyFromArray($styleArray, true);
			$sheet->getStyle('G4')->applyFromArray($styleArray, true);
			$sheet->getStyle('H4')->applyFromArray($styleArray, true);
			$sheet->getStyle('I4')->applyFromArray($styleArray, true);
			$sheet->getStyle('J4')->applyFromArray($styleArray, true);
			$sheet->getStyle('K4')->applyFromArray($styleArray, true);
			$sheet->getStyle('L4')->applyFromArray($styleArray, true);
			$sheet->getStyle('M4')->applyFromArray($styleArray, true);

			$sQuerySession = new Zend_Session_Namespace('notRespondedParticipantsExcel');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$rResult = $db->fetchAll($sQuerySession->shipmentRespondedParticipantQuery);
			//  Zend_Debug::dump($rResult);die;

			foreach ($rResult as $aRow) {
				$row = [];
				$row[] = $aRow['unique_identifier'];
				$row[] = $aRow['participantName'];
				$row[] = $aRow['institute_name'];
				$row[] = $aRow['department_name'];
				$row[] = $aRow['email'];
				$row[] = $aRow['mobile'];
				$row[] = $aRow['city'];
				$row[] = $aRow['state'];
				$row[] = $aRow['district'];
				$row[] = $aRow['iso_name'];
				$row[] = $aRow['phone'];
				$row[] = $aRow['affiliation'];
				$row[] = ucwords($aRow['RESPONSE']);

				$output[] = $row;
			}
			//Zend_Debug::dump($output);die;

			foreach ($output as $rowNo => $rowData) {
				$colNo = 0;
				foreach ($rowData as $field => $value) {
					if (!isset($value)) {
						$value = "";
					}
					$sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 5)
						->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
					$rRowCount = $rowNo + 5;
					$cellName = $sheet->getCell(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 5)
						->getColumn();
					$sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle, true);
					$sheet->getDefaultRowDimension()->setRowHeight(18);
					$sheet->getColumnDimensionByColumn($colNo)->setWidth(22);
					$sheet->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colNo + 1) . $rowNo + 5, null, null)->getAlignment()->setWrapText(true);
					$colNo++;
				}
			}

			$writer = IOFactory::createWriter($excel, 'Xlsx');
			$filename = $params['shipmentCode'] . '-not-responded-participant-report-' . date('d-M-Y-H-i-s') . '.xlsx';
			$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
			return $filename;
		} catch (Exception $exc) {
			$sQuerySession->correctiveActionsQuery = '';
			error_log("GENERATE-SHIPMENT-NOT-RESPONDED-PARTICIPANT-REPORT-EXCEL--" . $exc->getMessage());
			error_log($exc->getTraceAsString());

			return "";
		}
	}
	public function checkParticipantsProfileUpdate($userSystemId)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->checkParticipantsProfileUpdateByUserSystemId($userSystemId);
	}
	public function getParticipantUniqueIdentifier()
	{
		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		$userSystemId = $authNameSpace->dm_id;
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->getParticipantsByUserSystemId($userSystemId);
	}

	public function getUniqueCountry()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchUniqueCountry();
	}
	public function fetchFilterValues()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchFilterValues();
	}

	public function getUniqueRegion()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchUniqueRegion();
	}

	public function getUniqueDistrict()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchUniqueDistrict();
	}

	public function getUniqueState()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchUniqueState();
	}

	public function getUniqueCity()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchUniqueCity();
	}

	public function getActiveParticipantDetails($userId)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchMapActiveParticipantDetails($userId);
	}

	public function getParticipantSearch($search)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchParticipantSearch($search);
	}

	public function uploadBulkParticipants($params = null)
	{
		ini_set('memory_limit', -1);
		ini_set('max_execution_time', -1);
		try {
			$alertMsg = new Zend_Session_Namespace('alertSpace');
			$participantDb = new Application_Model_DbTable_Participants();
			$allowedExtensions = ['xls', 'xlsx', 'csv'];
			$fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
			$fileName = str_replace(" ", "-", $fileName);
			$random = Pt_Commons_MiscUtility::generateRandomString(6);
			$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			$fileName = "$random-$fileName";
			$response = [];
			$lastInsertedId = 0;
			if (in_array($extension, $allowedExtensions)) {
				$tempUploadDirectory = realpath(UPLOAD_PATH);
				if (!file_exists($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
					if (move_uploaded_file($_FILES['fileName']['tmp_name'], $tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
						$response = $participantDb->processBulkImport($tempUploadDirectory . DIRECTORY_SEPARATOR . $fileName,  false, $params);
					} else {
						$alertMsg->message = 'Data import failed';
						return false;
					}
				} else {
					$alertMsg->message = 'File not uploaded. Please try again.';
					return false;
				}
			} else {
				$alertMsg->message = 'File format not supported';
				return false;
			}
		} catch (Throwable $exc) {
			error_log($exc->getFile() . ":" . $exc->getLine() . ":" . $exc->getMessage());
			error_log($exc->getTraceAsString());
			$alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
			return false;
		}
		return $response;
	}

	public function getFilterDetailsAPI($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchFilterDetailsAPI($params);
	}

	public function getProfileCheckDetailsAPI($params)
	{
		$dmDb = new Application_Model_DbTable_DataManagers();
		return $dmDb->fetchProfileCheckDetailsAPI($params);
	}

	public function saveProfileByAPI($params)
	{
		$dmDb = new Application_Model_DbTable_DataManagers();
		return $dmDb->saveProfileDetailsByAPI($params);
	}

	public function deleteParticipant($participantId)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->deleteParticipantBId($participantId);
	}

	public function getParticipantCountriesList()
	{
		$countriesDb = new Application_Model_DbTable_Countries();
		return $countriesDb->fetchParticipantCountriesList();
	}

	public function getResponseFilters($params)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'))
			->join(array('c' => 'countries'), 'c.id=p.country')
			->group(array("c.id"))
			->order("c.iso_name ASC");
		return $db->fetchAll($sql);
	}

	public function getShipmentResponseReport($parameters)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchShipmentResponseReport($parameters);
	}

	public function exportParticipantsResponseDetails($params)
	{
		try {
			$excel = new Spreadsheet();

			$output = [];
			$sheet = $excel->getActiveSheet();
			$colNo = 0;


			$sheet->getCell('A1')->setValue(html_entity_decode("Participant Name", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('B1')->setValue(html_entity_decode("Institute Name", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('C1')->setValue(html_entity_decode("Country", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('D1')->setValue(html_entity_decode("State/Province", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('E1')->setValue(html_entity_decode("District/County", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('F1')->setValue(html_entity_decode("Shipment Code", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('G1')->setValue(html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('H1')->setValue(html_entity_decode("Responded On", ENT_QUOTES, 'UTF-8'));
			$sheet->getCell('I1')->setValue(html_entity_decode("Evaluation Result", ENT_QUOTES, 'UTF-8'));


			$sQuerySession = new Zend_Session_Namespace('participantResponseReportQuerySession');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$sQuery = $sQuerySession->participantResponseReportQuerySession;
			$rResult = $db->fetchAll($sQuery);
			// Zend_Debug::dump($rResult);die;
			$finalResult = array(1 => 'Pass', 2 => 'Fail', 3 => 'Excluded');
			foreach ($rResult as $aRow) {
				$row = [];
				$row[] = ucwords($aRow['participantName']);
				$row[] = ucwords($aRow['institute_name']);
				$row[] = ucwords($aRow['iso_name']);
				$row[] = ucwords($aRow['state']);
				$row[] = ucwords($aRow['district']);
				$row[] = $aRow['shipment_code'];
				$row[] = ucwords($aRow['RESPONSE']);
				$row[] = Pt_Commons_General::humanReadableDateFormat($aRow['shipment_test_report_date'] ?? '');
				$row[] = (isset($finalResult[$aRow['final_result']]) && !empty($finalResult[$aRow['final_result']])) ? ucwords($finalResult[$aRow['final_result']]) : null;

				$output[] = $row;
			}

			foreach ($output as $rowNo => $rowData) {
				$rRowCount = $rowNo + 2;
				$sheet->fromArray($rowData, null, 'A' . $rRowCount);
			}

			$sheet = $this->common->centerAndBoldRowInSheet($sheet, 'A1');
			$sheet = $this->common->applyBordersToSheet($sheet);
			$sheet = $this->common->setAllColumnWidthsInSheet($sheet, 20);
			$tempUploadDirectory = realpath(TEMP_UPLOAD_PATH);
			$writer = IOFactory::createWriter($excel, 'Xlsx');
			$filename = 'Shipment-Participant-Response-Report-' . date('d-M-Y-H-i-s') . '.xlsx';
			$writer->save($tempUploadDirectory . DIRECTORY_SEPARATOR . $filename);
			$auditDb = new Application_Model_DbTable_AuditLog();
			$auditDb->addNewAuditLog("Downloaded a participant data", "participants");
			echo $filename;
		} catch (Exception $exc) {
			$sQuerySession->shipmentRespondedParticipantQuery = '';
			error_log("GENERATE-SHIPMENT-RESPONDED-PARTICIPANT-REPORT-EXCEL--" . $exc->getMessage());
			error_log($exc->getTraceAsString());
			echo "";
		}
	}

	public function getParticipantsCertificates($params)
	{
		$dmDb = new Application_Model_DbTable_DataManagers();
		$dmDetails = $dmDb->fetchAuthToken($params);
		/* Validate new auth token and app-version */
		if (!$dmDetails) {
			return array('status' => 'auth-fail', 'message' => 'Please check your credentials and try to log in again');
		}
		$participantDb = new Application_Model_DbTable_Participants();
		$downloads = $participantDb->getParticipantsByUserSystemId($dmDetails['dm_id']);

		$arrCount = count($downloads);
		$downloads[$arrCount]['unique_identifier'] = 'common';
		$response = [];

		$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

		$eptDomain = rtrim($conf->domain, "/");
		$common = new Application_Service_Common();

		if (!empty($downloads)) {
			foreach ($downloads as $uniqueId) {
				$path = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . $uniqueId['unique_identifier'];
				if (is_dir($path) && count(scandir($path)) > 2) {
					$lab = (isset($uniqueId['lab_name']) && $uniqueId['lab_name'] != '') ? $uniqueId['lab_name'] : $uniqueId['first_name'] . " " . $uniqueId['last_name'];


					$files = [];
					$nameOfTheFile = [];
					foreach (scandir($path) as $fileName) {
						if ($fileName != '.' && $fileName != '..') {
							$files[$fileName] = filemtime($path . "/" . $fileName);
							$nameOfTheFile[] = $fileName;
						}
					}
					if (!empty($files)) {
						arsort($files);
						$i = 0;
						foreach (array_keys($files) as $key => $descFile) {
							$response[$key]['unique'] = ucfirst($uniqueId['unique_identifier']);
							$response[$key]['lab'] = ucfirst($lab);
							$response[$key]['fileName'] = ucfirst($nameOfTheFile[$i]);
							$response[$key]['url'] = $eptDomain . "/participant/download-file?fileName=" . urlencode(base64_encode($descFile . '#######' . $uniqueId['unique_identifier'] . '#######' . $common->getCurrentDateTime()));
							$i++;
						}
					}
				}
			}
		}
		return $response;
	}

	public function getTbInstruments($mapId)
	{
		$instrumentDb = new Application_Model_DbTable_TBInstruments();
		return $instrumentDb->fetchTbInstruments($mapId);
	}
	public function getAllPTDetails($data)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

		$eptDomain = parse_url($conf->domain, PHP_URL_HOST);

		$skipEmail = false;
		if (isset($data['skipEmail']) && !empty($data['skipEmail']) && $data['skipEmail'] == 'on') {
			$skipEmail = true;
		}
		$result = [];
		if (in_array('participant', $data['sendMail'])) {
			$sql = $db->select()->from(['p' => 'participant'], ['p.email', 'name' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT p.first_name,\" \",p.last_name ORDER BY p.first_name SEPARATOR ', ')")])
				->joinLeft(['spm' => 'shipment_participant_map'], 'p.participant_id=spm.participant_id', [])
				->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.shipment_code'])
				->joinLeft(['d' => 'distributions'], 'd.distribution_id = s.distribution_id', ['distribution_code', 'distribution_date'])
				->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
				->where("s.shipment_id IN(" . implode(",", $data['shipments']) . ")")->group('p.email');
			if ($skipEmail && !empty($eptDomain)) {
				$sql = $sql->where("p.email not like '%$eptDomain'");
			}
			$result[] = $db->fetchAll($sql);
		}
		if (in_array('datamanager', $data['sendMail'])) {
			$sql = $db->select()->from(['dm' => 'data_manager'], ['email' => 'dm.primary_email', 'name' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT dm.first_name,\" \",dm.last_name ORDER BY dm.first_name SEPARATOR ', ')")])
				->joinLeft(['pmm' => 'participant_manager_map'], 'dm.dm_id=pmm.dm_id', [''])
				->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=pmm.participant_id', [])
				->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.shipment_code'])
				->joinLeft(['d' => 'distributions'], 'd.distribution_id = s.distribution_id', ['distribution_code', 'distribution_date'])
				->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
				->where("s.shipment_id IN(" . implode(",", $data['shipments']) . ")")
				->where('data_manager_type like "manager"')->group('dm.primary_email');
			if ($skipEmail && !empty($eptDomain)) {
				$sql = $sql->where("dm.primary_email not like '%$eptDomain'");
			}
			$result[] = $db->fetchAll($sql);
		}
		if (in_array('ptcc', $data['sendMail'])) {
			$sql = $db->select()->from(['dm' => 'data_manager'], ['email' => 'dm.primary_email', 'name' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT dm.first_name,\" \",dm.last_name ORDER BY dm.first_name SEPARATOR ', ')")])
				->joinLeft(['pmm' => 'participant_manager_map'], 'dm.dm_id=pmm.dm_id', [])
				->joinLeft(['spm' => 'shipment_participant_map'], 'spm.participant_id=pmm.participant_id', [''])
				->joinLeft(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.shipment_code'])
				->joinLeft(['d' => 'distributions'], 'd.distribution_id = s.distribution_id', ['distribution_code', 'distribution_date'])
				->joinLeft(['sl' => 'scheme_list'], 'sl.scheme_id=s.scheme_type', ['SCHEME' => 'sl.scheme_name'])
				->where("s.shipment_id IN(" . implode(",", $data['shipments']) . ")")
				->where('data_manager_type like "ptcc"')->group('dm.primary_email');
			if ($skipEmail && !empty($eptDomain)) {
				$sql = $sql->where("dm.primary_email not like '%$eptDomain'");
			}
			$result[] = $db->fetchAll($sql);
		}
		return $result;
	}

	public function sendParticipantEmail($data)
	{
		$commonServices = new Application_Service_Common();
		$file = APPLICATION_PATH . DIRECTORY_SEPARATOR . "configs" . DIRECTORY_SEPARATOR . "config.ini";
		$config = new Zend_Config_Ini($file, APPLICATION_ENV);
		$results = $this->getAllPTDetails($data);
		$status = false;
		$emailParticipantDb = new Application_Model_DbTable_EmailParticipants();

		$emailParticipantDb->saveEmailParticipants(array(
			'subject'	=> $data['subject'],
			'message'	=> $data['message'],
			'email'		=> implode(",", $data['sendMail']),
			'scode'		=> implode(",", $data['shipments'])
		));

		foreach ($results as $row) {
			foreach ($row as $pt) {
				if ($pt['email'] != '') {
					$surveyDate = Pt_Commons_General::humanReadableDateFormat($pt['distribution_date']);
					$search = ['##NAME##', '##SHIPCODE##', '##SHIPTYPE##', '##SURVEYCODE##', '##SURVEYDATE##',];
					/* Search and Replace for Message Content */
					$replaceMsg = [$pt['name'], $pt['shipment_code'], $pt['SCHEME'], $pt['distribution_code'], $surveyDate];
					$message = str_replace($search, $replaceMsg, $data['message']);
					/* Search and Replace for the Subject */
					$replaceSub = [$pt['name'], $pt['shipment_code'], $pt['SCHEME'], $pt['distribution_code'], $surveyDate];
					$subject = str_replace($search, $replaceSub, $data['subject']);

					$fromEmail = $config->email->participant->fromMail;
					$fromFullName = $config->email->participant->fromName;
					$toEmail = $pt['email'];
					$cc = $config->email->participant->cc;
					$bcc = $config->email->participant->bcc;
					$status = $commonServices->insertTempMail($toEmail, $cc, $bcc, $subject, $message, $fromEmail, $fromFullName);
				}
			}
		}
		if ($status) {
			$alertMsg = new Zend_Session_Namespace('alertSpace');
			$alertMsg->message = 'Emails queued for sending';
		}
	}

	public function exportParticipantMapDetails()
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->exportParticipantMapDetails();
	}

	public function getParticipantList($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->fetchParticipantList($params);
	}

	public function excludeParticipantById($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		$result =  $participantDb->excludeUnrollParticipantById($params);
		if ($result) {
			$alertMsg = new Zend_Session_Namespace('alertSpace');
			$alertMsg->message = 'Participant was excluded from the shipment';
		}
		return $result;
	}

	public function uploadBulkEnrollment($params)
	{
		$enrollments = new Application_Model_DbTable_Enrollments();
		return $enrollments->uploadBulkEnrollmentDetails($params);
	}
}
