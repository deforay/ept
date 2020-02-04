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
			$sheet->setCellValue('A4', html_entity_decode("Participant Id", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('B4', html_entity_decode("Lab Name/Participant Name", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('C4', html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('D4', html_entity_decode("Cell/Mobile", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('E4', html_entity_decode("Phone", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('F4', html_entity_decode("Affiliation", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('G4', html_entity_decode("Email", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('H4', html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

			$sheet->getStyle('A4')->applyFromArray($styleArray);
			$sheet->getStyle('B4')->applyFromArray($styleArray);
			$sheet->getStyle('C4')->applyFromArray($styleArray);
			$sheet->getStyle('D4')->applyFromArray($styleArray);
			$sheet->getStyle('E4')->applyFromArray($styleArray);
			$sheet->getStyle('F4')->applyFromArray($styleArray);
			$sheet->getStyle('G4')->applyFromArray($styleArray);
			$sheet->getStyle('H4')->applyFromArray($styleArray);

			$sQuerySession = new Zend_Session_Namespace('respondedParticipantsExcel');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$rResult = $db->fetchAll($sQuerySession->shipmentRespondedParticipantQuery);
			//  Zend_Debug::dump($rResult);die;

			foreach ($rResult as $aRow) {
				$row = array();
				$row[] = $aRow['unique_identifier'];
				$row[] = $aRow['participantName'];
				$row[] = $aRow['iso_name'];
				$row[] = $aRow['mobile'];
				$row[] = $aRow['phone'];
				$row[] = $aRow['affiliation'];
				$row[] = $aRow['email'];
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
			$filename = $params['shipmentCode'] . '-responded-participant-report-' . date('d-M-Y-H-i-s') . '.xls';
			$writer->save(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $filename);
			return $filename;
		} catch (Exception $exc) {
			return "";
			$sQuerySession->correctiveActionsQuery = '';
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
			$sheet->setCellValue('C4', html_entity_decode("Country", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('D4', html_entity_decode("Cell/Mobile", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('E4', html_entity_decode("Phone", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('F4', html_entity_decode("Affiliation", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('G4', html_entity_decode("Email", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);
			$sheet->setCellValue('H4', html_entity_decode("Response Status", ENT_QUOTES, 'UTF-8'), PHPExcel_Cell_DataType::TYPE_STRING);

			$sheet->getStyle('A4')->applyFromArray($styleArray);
			$sheet->getStyle('B4')->applyFromArray($styleArray);
			$sheet->getStyle('C4')->applyFromArray($styleArray);
			$sheet->getStyle('D4')->applyFromArray($styleArray);
			$sheet->getStyle('E4')->applyFromArray($styleArray);
			$sheet->getStyle('F4')->applyFromArray($styleArray);
			$sheet->getStyle('G4')->applyFromArray($styleArray);
			$sheet->getStyle('H4')->applyFromArray($styleArray);

			$sQuerySession = new Zend_Session_Namespace('notRespondedParticipantsExcel');
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$rResult = $db->fetchAll($sQuerySession->shipmentRespondedParticipantQuery);
			//  Zend_Debug::dump($rResult);die;

			foreach ($rResult as $aRow) {
				$row = array();
				$row[] = $aRow['unique_identifier'];
				$row[] = $aRow['participantName'];
				$row[] = $aRow['iso_name'];
				$row[] = $aRow['mobile'];
				$row[] = $aRow['phone'];
				$row[] = $aRow['affiliation'];
				$row[] = $aRow['email'];
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
		try{
			$alertMsg = new Zend_Session_Namespace('alertSpace');
			$adminSession = new Zend_Session_Namespace('administrators');
			$participantDb = new Application_Model_DbTable_Participants();
			$userDb = new Application_Model_DbTable_DataManagers();

			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			// $rResult = $db->fetchAll();
            $allowedExtensions = array('xls', 'xlsx', 'csv');
            $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['fileName']['name']);
            $fileName = str_replace(" ", "-", $fileName);
            $ranNumber1 = str_pad(rand(0, pow(10, 6)-1), 6, '0', STR_PAD_LEFT);
            $ranNumber2 = str_pad(rand(0, pow(10, 6)-1), 6, '0', STR_PAD_LEFT);
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
			$fileName =$ranNumber1.$ranNumber2.".".$extension;
			$response = array();
            
            if (in_array($extension, $allowedExtensions)) {
           
                if (!file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)) {   
                    
                    if (move_uploaded_file($_FILES['fileName']['tmp_name'], TEMP_UPLOAD_PATH.DIRECTORY_SEPARATOR. $fileName)) {
                    
                        $objPHPExcel = \PHPExcel_IOFactory::load(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName);
                        $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
						// Zend_Debug::dump($sheetData);die;
						$authNameSpace = new Zend_Session_Namespace('administrators');
                        $count = count($sheetData);
                        for ($i = 2; $i <= $count; ++$i) 
                        {
							if((isset($sheetData[$i]['P']) && trim($sheetData[$i]['P']) != "") && (isset($sheetData[$i]['Q']) && trim($sheetData[$i]['Q']) != "")){
								$lastInsertedId = 0;$dmId= 0;
								/* To check the dublication in participant table */
								$psql = $db->select()->from('participant')
								->where("email LIKE '" . $sheetData[$i]['P']."'")
								->orWhere("unique_identifier LIKE '".$sheetData[$i]['B']."'");
								$presult = $db->fetchRow($psql);
								/* To check the dublication in data manager table */
								$dmsql = $db->select()->from('data_manager')
								->where("primary_email LIKE '" . $sheetData[$i]['P']."'");
								$dmresult = $db->fetchRow($dmsql);

								/* To find the country id */
								$cmsql = $db->select()->from('countries')
								->where("iso_name LIKE '%" . $sheetData[$i]['K']."%'")
								->orWhere("iso2 LIKE '%".$sheetData[$i]['K']."%'")
								->orWhere("iso3 LIKE '%".$sheetData[$i]['K']."%'");
								$cresult = $db->fetchRow($cmsql);
								
								if(!$presult && !$dmresult){
									$lastInsertedId = $db->insert('participant', array(
										'unique_identifier' => $sheetData[$i]['B'],
										'individual' 		=> $sheetData[$i]['C'],
										'first_name' 		=> $sheetData[$i]['D'],
										'last_name' 		=> $sheetData[$i]['E'],
										'institute_name' 	=> $sheetData[$i]['F'],
										'department_name' 	=> $sheetData[$i]['G'],
										'address' 			=> $sheetData[$i]['H'],
										'city' 				=> $sheetData[$i]['I'],
										'state' 			=> $sheetData[$i]['J'],
										'country' 			=> (isset($cresult['id']) && $cresult['id'] != "")?$cresult['id']:0,
										'zip' 				=> $sheetData[$i]['L'],
										'long' 				=> $sheetData[$i]['M'],
										'lat' 				=> $sheetData[$i]['N'],
										'mobile' 			=> $sheetData[$i]['O'],
										'email' 			=> $sheetData[$i]['P'],
										'additional_email' 	=> $sheetData[$i]['R'],
										'created_by' 		=> $authNameSpace->admin_id,
										'created_on' 		=> new Zend_Db_Expr('now()'),
										'status'			=> 'active'
									));

									$pasql = $db->select()->from('participant')
									->where("email LIKE '" . $sheetData[$i]['P']."'")
									->orWhere("unique_identifier LIKE '".$sheetData[$i]['B']."'");
									$paresult = $db->fetchRow($pasql);
									$lastInsertedId = $paresult['participant_id'];
									if($lastInsertedId > 0){
										$dmId = $db->insert('data_manager', array(
											'first_name' 		=> $sheetData[$i]['D'],
											'last_name' 		=> $sheetData[$i]['E'],
											'institute' 		=> $sheetData[$i]['F'],
											'mobile' 			=> $sheetData[$i]['O'],
											'secondary_email' 	=> $sheetData[$i]['R'],
											'primary_email' 	=> $sheetData[$i]['P'], 
											'password' 			=> $sheetData[$i]['Q'],
											'created_by' 		=> $authNameSpace->admin_id,
											'created_on' 		=> new Zend_Db_Expr('now()'),
											'status'			=> 'active'
										));

										$dmasql = $db->select()->from('data_manager')
										->where("primary_email LIKE '" . $sheetData[$i]['P']."'");
										$dmaresult = $db->fetchRow($dmasql);
										$dmId = $dmaresult['dm_id'];
										if($dmId > 0){
											$db->insert('participant_manager_map', array('dm_id' => $dmId, 'participant_id' => $lastInsertedId));
										}
										$response['data'][] = array(
											'serialNo' 	=> $sheetData[$i]['A'],
											'identifier'=> $sheetData[$i]['B'], 
											'email' 	=> $sheetData[$i]['P'], 
											'mobile' 	=> $sheetData[$i]['O'],
											'first_name'=> $sheetData[$i]['D'],
											'last_name' => $sheetData[$i]['E'],
											'city' 		=> $sheetData[$i]['I'],
											'institute' => $sheetData[$i]['F']
										);
									}
								}else{
									$response['error-data'][] = array(
										'serialNo' 	=> $sheetData[$i]['A'],
										'identifier'=> $sheetData[$i]['B'], 
										'email' 	=> $sheetData[$i]['P'], 
										'mobile' 	=> $sheetData[$i]['O'],
										'first_name'=> $sheetData[$i]['D'],
										'last_name' => $sheetData[$i]['E'],
										'city' 		=> $sheetData[$i]['I'],
										'institute' => $sheetData[$i]['F']
									);
								}
								if($lastInsertedId > 0 || $dmId > 0){
									if(file_exists(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName)){
										unlink(TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $fileName);
									}
									$response['message'] = "File has expired please re-import again!";
								}
							}
                        }
                    }else{
						$alertMsg->message = 'Data imported failed';
						return false;
                    }
                }else{
					$alertMsg->message = 'File not uploaded. Please rename and upload it again!';
					return false;	
				}
			}else{
				$alertMsg->message = 'File format not supported';
				return false;
			}
            if($lastInsertedId > 0){
				$alertMsg->message = 'Your file was imported successfully';
            }
        }
        catch (Exception $exc) {
            error_log("IMPORT-PARTICIPANTS-DATA-EXCEL--" . $exc->getMessage());die;
			error_log($exc->getTraceAsString());
			$alertMsg->message = 'File not uploaded. Something went wrong please try again later!';
            return false;
		}
		return $response;
	}
}
