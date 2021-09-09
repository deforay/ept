<?php
include_once "PHPExcel.php";

class Application_Service_Participants
{

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

	public function requestParticipant($params)
	{
		$participantDb = new Application_Model_DbTable_Participants();
		return $participantDb->saveRequestParticipant($params);
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

	public function getParticipantsListNames($id = "")
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('eln' => 'enrollment_lists_names'), array('*'));
		if (trim($id) != '') {
			$sql = $sql->where("eln.eln_unique_id IN (?)", base64_decode($id));
		} else {
			$sql = $sql->group(array('eln_unique_id'));
		}
		return $db->fetchAll($sql);
	}
	public function getParticipantSchemes($dmId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'))
			->joinLeft(array('pmm' => 'participant_manager_map'), 'p.participant_id=pmm.participant_id')
			->joinLeft(array('sp' => 'shipment_participant_map'), 'p.participant_id=sp.participant_id')
			->joinLeft(array('s' => 'shipment'), 's.shipment_id=sp.shipment_id')
			->joinLeft(array('sl' => 'scheme_list'), 'sl.scheme_id=s.scheme_type')
			->where("pmm.dm_id= ?", $dmId)
			->group(array("sp.participant_id", "s.scheme_type"))
			->order("p.first_name");
		return $db->fetchAll($sql);
	}

	public function getPendingParticipants()
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'), array('p.participant_id'))
			->where("p.status= ?", "pending");
		return $db->fetchAll($sql);
	}

	public function getUnEnrolled($scheme, $stateId = '', $cityId = '')
	{

		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$subSql = $db->select()->from(array('e' => 'enrollments'), 'participant_id')->where("scheme_id = ?", $scheme);
		$sql = $db->select()->from(array('p' => 'participant'))
			->where("participant_id NOT IN ?", $subSql)
			->where("p.status='active'")
			->order('first_name');
		if (trim($stateId) != '') {
			$stateId = explode(',', $stateId);
			$sql = $sql->where("p.state IN (?)", $stateId);
		}

		if (trim($cityId) != '') {
			$cityId = explode(',', $cityId);
			$sql = $sql->where("p.city IN (?)", $cityId);
		}

		//echo $sql;die;
		return $db->fetchAll($sql);
	}
	public function getEnrolledBySchemeCode($scheme)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('e' => 'enrollments'), array())
			->join(array('p' => 'participant'), "p.participant_id=e.participant_id")->where("scheme_id = ?", $scheme)->where("p.status='active'")->order('first_name');
		return $db->fetchAll($sql);
	}

	public function getEnrolledByShipmentId($shipmentId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$sql = $db->select()->from(array('p' => 'participant'))
			->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
			->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
			->where("s.shipment_id = ?", $shipmentId)
			->where("p.status='active'")
			->order('p.first_name');

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

		return $db->fetchCol($sql);
	}
	public function getUnEnrolledByShipmentId($shipmentId, $stateId = '', $cityId = '')
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$subSql = $db->select()->from(array('p' => 'participant'), array('participant_id'))
			->join(array('sp' => 'shipment_participant_map'), 'sp.participant_id=p.participant_id', array())
			->join(array('s' => 'shipment'), 'sp.shipment_id=s.shipment_id', array())
			->where("s.shipment_id = ?", $shipmentId)
			->where("p.status='active'");
		$sql = $db->select()->from(array('p' => 'participant'))->where("participant_id NOT IN ?", $subSql)
			->order('p.first_name');
		if (trim($stateId) != '') {
			$stateId = explode(',', $stateId);
			$sql = $sql->where("p.state IN (?)", $stateId);
		}

		if (trim($cityId) != '') {
			$cityId = explode(',', $cityId);
			$sql = $sql->where("p.city IN (?)", $cityId);
		}
		//echo $sql;
		return $db->fetchAll($sql);
	}

	public function enrollParticipants($params)
	{
		$enrollments = new Application_Model_DbTable_Enrollments();
		return $enrollments->enrollParticipants($params);
	}
	public function addParticipantManagerMap($params)
	{
		$db = new Application_Model_DbTable_Participants();
		return $db->addParticipantManager($params);
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
			->group('p.region')->where("p.region IS NOT NULL")->where("p.region != ''")->order("p.region");
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
			$excel = new PHPExcel();
			$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
			$cacheSettings = array('memoryCacheSize' => '256MB');
			PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
			$output = array();
			$sheet = $excel->getActiveSheet();
			$colNo = 0;

			$styleArray = array(
				'font' => array(
					'bold' => true,
				),
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
					'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
				),
				'borders' => array(
					'outline' => array(
						'style' => PHPExcel_Style_Border::BORDER_THIN,
					),
				)
			);
			$styleInboldArray = array(
				'font' => array(
					'bold' => true,
				),
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
					'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
				)
			);
			$borderStyle = array(
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
				),
				'borders' => array(
					'outline' => array(
						'style' => PHPExcel_Style_Border::BORDER_THIN,
					),
				)
			);
			if ($params['type'] == 'from-participant') {
				$sheet->mergeCells('A1:E1');
				$sheet->setCellValue('A1', html_entity_decode("Shipment Participant List", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->getStyle('A1')->applyFromArray($styleInboldArray);
			} else {
				$sheet->mergeCells('A1:E1');
				$sheet->setCellValue('A1', html_entity_decode("Responded Shipment Participant List", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->getStyle('A1')->applyFromArray($styleInboldArray);
				if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
					$sheet->setCellValue('A2', html_entity_decode("Shipment Code", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
					$sheet->setCellValue('B2', html_entity_decode($params['shipmentCode'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				}
				if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
					$sheet->setCellValue('A3', html_entity_decode("Shipment Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
					$sheet->setCellValue('B3', html_entity_decode($params['shipmentDate'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				}
			}
			$sheet->setCellValue('A4', html_entity_decode("Participant Id", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('B4', html_entity_decode("Lab Name/Participant Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('C4', html_entity_decode("Institute Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('D4', html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('E4', html_entity_decode("Cell/Mobile", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('F4', html_entity_decode("Phone", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('G4', html_entity_decode("Affiliation", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('H4', html_entity_decode("Email", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('I4', html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

			$sheet->getStyle('A4')->applyFromArray($styleArray);
			$sheet->getStyle('B4')->applyFromArray($styleArray);
			$sheet->getStyle('C4')->applyFromArray($styleArray);
			$sheet->getStyle('D4')->applyFromArray($styleArray);
			$sheet->getStyle('E4')->applyFromArray($styleArray);
			$sheet->getStyle('F4')->applyFromArray($styleArray);
			$sheet->getStyle('G4')->applyFromArray($styleArray);
			$sheet->getStyle('H4')->applyFromArray($styleArray);
			$sheet->getStyle('I4')->applyFromArray($styleArray);

			$sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$sQuery = $sQuerySession->shipmentRespondedParticipantQuery;
			if ($params['type'] == 'from-participant') {
				// $sQuery = $sQuery->where("p.status = ? ", 'active');
			}
			$rResult = $db->fetchAll($sQuery);
			// Zend_Debug::dump($rResult);die;

			foreach ($rResult as $aRow) {
				$row = array();
				$row[] = $aRow['unique_identifier'];
				$row[] = $aRow['participantName'];
				$row[] = $aRow['institute_name'];
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
			//Zend_Debug::dump($output);die;

			foreach ($output as $rowNo => $rowData) {
				$colNo = 0;
				foreach ($rowData as $field => $value) {
					if (!isset($value)) {
						$value = "";
					}
					$sheet->getCellByColumnAndRow($colNo, $rowNo + 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
					$rRowCount = $rowNo + 5;
					$cellName = $sheet->getCellByColumnAndRow($colNo, $rowNo + 5)->getColumn();
					$sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
					$sheet->getDefaultRowDimension()->setRowHeight(18);
					$sheet->getColumnDimensionByColumn($colNo)->setWidth(22);
					$sheet->getStyleByColumnAndRow($colNo, $rowNo + 5)->getAlignment()->setWrapText(true);
					$colNo++;
				}
			}

			$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
			if ($params['type'] == 'from-participant') {
				$filename = 'Shipment-Participant-Report-(' . date('d-M-Y-H-i-s') . ').xls';
			} else {
				$filename = $params['shipmentCode'] . '-responded-participant-report-' . date('d-M-Y-H-i-s') . '.xls';
			}
			$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
			return $filename;
		} catch (Exception $exc) {
			return "";
			$sQuerySession->shipmentRespondedParticipantQuery = '';
			error_log("GENERATE-SHIPMENT-RESPONDED-PARTICIPANT-REPORT-EXCEL--" . $exc->getMessage());
			error_log($exc->getTraceAsString());
		}
	}

	public function exportShipmentNotRespondedParticipantsDetails($params)
	{
		try {
			$excel = new PHPExcel();
			$cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
			$cacheSettings = array('memoryCacheSize' => '80MB');
			PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
			$output = array();
			$sheet = $excel->getActiveSheet();
			$colNo = 0;

			$styleArray = array(
				'font' => array(
					'bold' => true,
				),
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
					'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
				),
				'borders' => array(
					'outline' => array(
						'style' => PHPExcel_Style_Border::BORDER_THIN,
					),
				)
			);
			$styleInboldArray = array(
				'font' => array(
					'bold' => true,
				),
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_LEFT,
					'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
				)
			);
			$borderStyle = array(
				'alignment' => array(
					'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
				),
				'borders' => array(
					'outline' => array(
						'style' => PHPExcel_Style_Border::BORDER_THIN,
					),
				)
			);
			$sheet->mergeCells('A1:E1');
			$sheet->setCellValue('A1', html_entity_decode("Not Responded Shipment Participant List", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->getStyle('A1')->applyFromArray($styleInboldArray);

			if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
				$sheet->setCellValue('A2', html_entity_decode("Shipment Code", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->setCellValue('B2', html_entity_decode($params['shipmentCode'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			}
			if (isset($params['shipmentCode']) && trim($params['shipmentCode']) != "") {
				$sheet->setCellValue('A3', html_entity_decode("Shipment Date", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
				$sheet->setCellValue('B3', html_entity_decode($params['shipmentDate'], ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			}

			$sheet->setCellValue('A4', html_entity_decode("Participant Id", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('B4', html_entity_decode("Lab Name/Participant Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('C4', html_entity_decode("Department Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('D4', html_entity_decode("Email", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('E4', html_entity_decode("Cell/Mobile", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('F4', html_entity_decode("City", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('G4', html_entity_decode("State", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('H4', html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('I4', html_entity_decode("Phone", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('J4', html_entity_decode("Affiliation", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('K4', html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

			$sheet->getStyle('A4')->applyFromArray($styleArray);
			$sheet->getStyle('B4')->applyFromArray($styleArray);
			$sheet->getStyle('C4')->applyFromArray($styleArray);
			$sheet->getStyle('D4')->applyFromArray($styleArray);
			$sheet->getStyle('E4')->applyFromArray($styleArray);
			$sheet->getStyle('F4')->applyFromArray($styleArray);
			$sheet->getStyle('G4')->applyFromArray($styleArray);
			$sheet->getStyle('H4')->applyFromArray($styleArray);
			$sheet->getStyle('I4')->applyFromArray($styleArray);
			$sheet->getStyle('J4')->applyFromArray($styleArray);
			$sheet->getStyle('K4')->applyFromArray($styleArray);

			$sQuerySession = new Zend_Session_Namespace('notRespondedParticipantsExcel');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$rResult = $db->fetchAll($sQuerySession->shipmentRespondedParticipantQuery);
			//  Zend_Debug::dump($rResult);die;

			foreach ($rResult as $aRow) {
				$row = array();
				$row[] = $aRow['unique_identifier'];
				$row[] = $aRow['participantName'];
				$row[] = $aRow['department_name'];
				$row[] = $aRow['email'];
				$row[] = $aRow['mobile'];
				$row[] = $aRow['city'];
				$row[] = $aRow['state'];
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
					$sheet->getCellByColumnAndRow($colNo, $rowNo + 5)->setValueExplicit(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
					$rRowCount = $rowNo + 5;
					$cellName = $sheet->getCellByColumnAndRow($colNo, $rowNo + 5)->getColumn();
					$sheet->getStyle($cellName . $rRowCount)->applyFromArray($borderStyle);
					$sheet->getDefaultRowDimension()->setRowHeight(18);
					$sheet->getColumnDimensionByColumn($colNo)->setWidth(22);
					$sheet->getStyleByColumnAndRow($colNo, $rowNo + 5)->getAlignment()->setWrapText(true);
					$colNo++;
				}
			}

			$writer = PHPExcel_IOFactory::createWriter($excel, 'Excel5');
			$filename = $params['shipmentCode'] . '-not-responded-participant-report-' . date('d-M-Y-H-i-s') . '.xls';
			$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
			return $filename;
		} catch (Exception $exc) {
			return "";
			$sQuerySession->correctiveActionsQuery = '';
			error_log("GENERATE-SHIPMENT-NOT-RESPONDED-PARTICIPANT-REPORT-EXCEL--" . $exc->getMessage());
			error_log($exc->getTraceAsString());
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

	public function addBulkParticipant()
	{
		ini_set('memory_limit', -1);
		ini_set('max_execution_time', -1);
		try {
			$alertMsg = new Zend_Session_Namespace('alertSpace');
			$adminSession = new Zend_Session_Namespace('administrators');
			// $participantDb = new Application_Model_DbTable_Participants();
			// $userDb = new Application_Model_DbTable_DataManagers();
			// $common = new Application_Service_Common();
			$schemeDb = new Application_Model_DbTable_SchemeList();
			$activeSchemes = $schemeDb->getAllSchemes();
			// var_dump($activeSchemes);die;
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			// $rResult = $db->fetchAll();
			$allowedExtensions = array('xls', 'xlsx', 'csv');
			$fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
			$fileName = str_replace(" ", "-", $fileName);
			$ranNumber1 = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
			$ranNumber2 = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
			$extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			$fileName = $ranNumber1 . $ranNumber2 . "." . $extension;
			$response = array();

			if (in_array($extension, $allowedExtensions)) {

				if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {

					if (move_uploaded_file($_FILES['fileName']['tmp_name'], TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {

						$objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName);
						$sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);

						$authNameSpace = new Zend_Session_Namespace('administrators');
						$count = count($sheetData);

						for ($i = 2; $i <= $count; ++$i) {

							$lastInsertedId = 0;

							if (empty($sheetData[$i]['A']) && empty($sheetData[$i]['B']) && empty($sheetData[$i]['C']) && empty($sheetData[$i]['D'])) {
								continue;
							}


							$sheetData[$i]['A'] = filter_var(trim($sheetData[$i]['A']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['B'] = filter_var(trim($sheetData[$i]['B']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['C'] = filter_var(trim($sheetData[$i]['C']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['D'] = filter_var(trim($sheetData[$i]['D']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['E'] = filter_var(trim($sheetData[$i]['E']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['F'] = filter_var(trim($sheetData[$i]['F']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['G'] = filter_var(trim($sheetData[$i]['G']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['H'] = filter_var(trim($sheetData[$i]['H']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['I'] = filter_var(trim($sheetData[$i]['I']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['J'] = filter_var(trim($sheetData[$i]['J']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['K'] = filter_var(trim($sheetData[$i]['K']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['L'] = filter_var(trim($sheetData[$i]['L']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['M'] = filter_var(trim($sheetData[$i]['M']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['N'] = filter_var(trim($sheetData[$i]['N']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['O'] = filter_var(trim($sheetData[$i]['O']), FILTER_SANITIZE_STRING);
							$sheetData[$i]['Q'] = filter_var(trim($sheetData[$i]['Q']), FILTER_SANITIZE_STRING);


							$sheetData[$i]['P'] = filter_var(trim($sheetData[$i]['P']), FILTER_SANITIZE_EMAIL);
							$sheetData[$i]['R'] = filter_var(trim($sheetData[$i]['R']), FILTER_SANITIZE_EMAIL);

							// if the unique_identifier is blank, we generate a new one
							$useUniqueIDForDuplicateCheck = true;
							if (empty($sheetData[$i]['B'])) {
								$useUniqueIDForDuplicateCheck = false;
								$sheetData[$i]['B'] = "PT-" . strtoupper(bin2hex(random_bytes(3)));
							}

							// if the email is blank, we generate a new one
							$useEmailForDuplicateCheck = true;
							if (empty($sheetData[$i]['P'])) {
								$useEmailForDuplicateCheck = false;
								$sheetData[$i]['P'] = $this->generateFakeEmailId($sheetData[$i]['B'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
							}

							$dataForStatistics = array(
								'serialNo' 	=> $sheetData[$i]['A'],
								'identifier' => $sheetData[$i]['B'],
								'email' 	=> $sheetData[$i]['P'],
								'mobile' 	=> $sheetData[$i]['O'],
								'first_name' => $sheetData[$i]['D'],
								'last_name' => $sheetData[$i]['E'],
								'city' 		=> $sheetData[$i]['I'],
								'institute' => $sheetData[$i]['F'],
								'country' => $sheetData[$i]['K']
							);

							if (!empty($sheetData[$i]['P']) && $sheetData[$i]['P'] != false) {

								$dmId = 0;
								$isIndividual = strtolower($sheetData[$i]['C']);
								if (!in_array($isIndividual, array('yes', 'no'))) {
									$isIndividual = 'yes'; // Default we treat testers as individuals
								}

								$presult = $dmresult = false;
								if ($useUniqueIDForDuplicateCheck && $useEmailForDuplicateCheck) {
									/* To check the duplication in participant table */
									$psql = $db->select()->from('participant')
										->where("unique_identifier LIKE ?", $sheetData[$i]['B'])
										->orWhere("email LIKE ?", $sheetData[$i]['P']);
									$presult = $db->fetchRow($psql);
								} else if ($useUniqueIDForDuplicateCheck) {
									/* To check the duplication in participant table */
									$psql = $db->select()->from('participant')
										->where("unique_identifier LIKE ?", $sheetData[$i]['B']);
									$presult = $db->fetchRow($psql);
								} else if ($useEmailForDuplicateCheck) {
									/* To check the duplication in participant table */
									$psql = $db->select()->from('participant')
										->where("email LIKE ?", $sheetData[$i]['P']);
									$presult = $db->fetchRow($psql);
								} else {
									$psql = $db->select()->from('participant')
										->where("first_name LIKE ?", $sheetData[$i]['D'])
										->where("last_name LIKE ?", $sheetData[$i]['E'])
										->where("mobile LIKE ?", $sheetData[$i]['O'])
										->where("city LIKE ?", $sheetData[$i]['I']);
									$presult = $db->fetchRow($psql);
								}

								/* To check the duplication in data manager table */
								$dmsql = $db->select()->from('data_manager')
									->where("primary_email LIKE ?", $sheetData[$i]['P']);
								$dmresult = $db->fetchRow($dmsql);


								// if($dmresult !== false){
								// 	$sheetData[$i]['P'] = $this->generateFakeEmailId($sheetData[$i]['B'], $sheetData[$i]['D'] . " " . $sheetData[$i]['E']);
								// 	$dmresult = false;
								// }

								/* To find the country id */
								$cmsql = $db->select()->from('countries')
									->where("iso_name LIKE ?", $sheetData[$i]['K'])
									->orWhere("iso2 LIKE  ?", $sheetData[$i]['K'])
									->orWhere("iso3 LIKE  ?", $sheetData[$i]['K']);

								//echo $cmsql;	
								$cresult = $db->fetchRow($cmsql);

								if (!$cresult) {
									$dataForStatistics['error'] = 'Could not add find country named ' . $sheetData[$i]['K'];
									$response['error-data'][] = $dataForStatistics;
									continue;
								}

								if (!$presult && !$dmresult) {

									$db->beginTransaction();
									try {
										$lastInsertedId = $db->insert('participant', array(
											'unique_identifier' => ($sheetData[$i]['B']),
											'individual' 		=> $isIndividual,
											'first_name' 		=> ($sheetData[$i]['D']),
											'last_name' 		=> ($sheetData[$i]['E']),
											'institute_name' 	=> ($sheetData[$i]['F']),
											'department_name' 	=> ($sheetData[$i]['G']),
											'address' 			=> ($sheetData[$i]['H']),
											'city' 				=> ($sheetData[$i]['I']),
											'state' 			=> ($sheetData[$i]['J']),
											'country' 			=> (isset($cresult['id']) && $cresult['id'] != "") ? $cresult['id'] : 0,
											'zip' 				=> ($sheetData[$i]['L']),
											'long' 				=> ($sheetData[$i]['M']),
											'lat' 				=> ($sheetData[$i]['N']),
											'mobile' 			=> ($sheetData[$i]['O']),
											'email' 			=> ($sheetData[$i]['P']),
											'additional_email' 	=> ($sheetData[$i]['R']),
											'created_by' 		=> $authNameSpace->admin_id,
											'created_on' 		=> new Zend_Db_Expr('now()'),
											'status'			=> 'active'
										));

										$pasql = $db->select()->from('participant')
											->where("email LIKE ?", trim($sheetData[$i]['P']))
											->orWhere("unique_identifier LIKE ?", trim($sheetData[$i]['B']));
										$paresult = $db->fetchRow($pasql);
										$lastInsertedId = $paresult['participant_id'];
										if ($lastInsertedId > 0) {
											$db->insert('data_manager', array(
												'first_name' 		=> ($sheetData[$i]['D']),
												'last_name' 		=> ($sheetData[$i]['E']),
												'institute' 		=> ($sheetData[$i]['F']),
												'mobile' 			=> ($sheetData[$i]['O']),
												'secondary_email' 	=> ($sheetData[$i]['R']),
												'primary_email' 	=> ($sheetData[$i]['P']),
												'password' 			=> (!isset($sheetData[$i]['Q']) || empty($sheetData[$i]['Q'])) ? 'ept1@)(*&^' : trim($sheetData[$i]['Q']),
												'created_by' 		=> $authNameSpace->admin_id,
												'created_on' 		=> new Zend_Db_Expr('now()'),
												'status'			=> 'active'
											));

											$dmId = $db->lastInsertId();

											if ($dmId != null && $dmId > 0) {
												$db->insert('participant_manager_map', array('dm_id' => $dmId, 'participant_id' => $lastInsertedId));
												$response['data'][] = $dataForStatistics;
											} else {
												$dataForStatistics['error'] = 'Could not add Participant Login';
												$response['error-data'][] = $dataForStatistics;
												throw new Zend_Exception('Could not add Participant Login');
											}
										} else {
											$dataForStatistics['error'] = 'Could not add Participant';
											$response['error-data'][] = $dataForStatistics;
											throw new Zend_Exception('Could not add Participant');
										}
										$db->commit();
									} catch (Exception $e) {
										// If any of the queries failed and threw an exception,
										// we want to roll back the whole transaction, reversing
										// changes made in the transaction, even those that succeeded.
										// Thus all changes are committed together, or none are.
										$db->rollBack();
										error_log($e->getMessage());
										error_log($e->getTraceAsString());
										continue;
									}
								} else {
									if ($useUniqueIDForDuplicateCheck || $useEmailForDuplicateCheck) {
										$dataForStatistics['error'] = 'Possible duplicate of Participant Email or Unique ID.';
									} else {
										$dataForStatistics['error'] = 'Possible duplicate of Name, Location, Mobile combination';
									}

									$response['error-data'][] = $dataForStatistics;
								}
								// if ($lastInsertedId > 0 || $dmId > 0) {
								// 	if (file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {
								// 		unlink(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName);
								// 	}
								// 	$response['message'] = "File has expired please re-import again!";
								// }
							} else {
								$dataForStatistics['error'] = 'Primary Email Missing';
								$response['error-data'][] = $dataForStatistics;
							}
						}
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
			if ($lastInsertedId > 0) {
				$alertMsg->message = 'Your file was imported successfully';
			}
		} catch (Exception $exc) {
			error_log("IMPORT-PARTICIPANTS-DATA-EXCEL--" . $exc->getMessage());
			error_log($exc->getTraceAsString());
			$alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
			return false;
		}
		return $response;
	}

	public function generateFakeEmailId($uniqueId, $participantName)
	{
		$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
		$eptDomain = !empty($conf->domain) ? rtrim($conf->domain, "/") : 'ept';
		$uniqueId = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $uniqueId));
		$participantName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $participantName));

		$fakeEmail = $uniqueId . "_" . $participantName . "@" . parse_url($eptDomain, PHP_URL_HOST);
		return $fakeEmail;
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
}
