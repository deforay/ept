<?php
class Application_Model_UsersProfile{
	
	public function getUsersParticipant($sysUID){
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$stmt = $db->prepare("call USERS_PARTICIPANT(?)");
		$stmt->execute(array($sysUID));
		$rs = $stmt->fetchall();
		return $rs;
	}
	
	public function getParticipant($pSysId)
	{
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$stmt = $db->prepare("call PARTICIPANT_ONE(?)");
		$stmt->execute(array($pSysId));
		$rs = $stmt->fetch();
		return $rs;
	}
	public function saveParticipant($data){
		
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		
		$pSysId = $data['PartSysID'];
		$pid = $data['pid'];
		$uSysId =$data['UsrSysID'];
		 
		$fName = $data['pfname'];
		$lName = $data['plname'];
		$pemail = $data['pemail'];
		
		$phone = $data['pphone1'];
		$cellPhone = $data['pphone2'];
		$pAff = $data['UserFld1'];
		

		$authNameSpace = new Zend_Session_Namespace('datamanagers');
		$user = $authNameSpace->UserID;
		
		
			
		/*
		IN PSysId varchar(45), 

		IN pId varchar(45),
		
		IN uSysId varchar(45),
		
		
		
		IN fName varchar(45),
		
		IN lName varchar(45),
		
		IN pemail varchar(45),
		
		
		
		IN phone varchar(45),
		
		IN cellPhone varchar(45),
		
		IN pAff varchar(45),
		
		
		IN user varchar(45)
		*/
		
		try{
		
			$stmt = $db->prepare("call PARTICIPANT_ONE_UPDATE(?,?,?,  ?,?,?,  ?,?,?, ?)");
			
			$resp = $stmt->execute(array($pSysId,$pid,$uSysId,  $fName,$lName,$pemail,   $phone,$cellPhone,$pAff,  $user));
			
		}
		catch (exception $e) {$resp = "Error";
			Zend_Debug::dump($e) ;
		die;
		return false;}
		return true;
			
		}
		
		public function getUserInfo($userId)
		{
			
			$db = Zend_Db_Table_Abstract::getDefaultAdapter();
			$stmt = $db->prepare("call USER_ONE(?)");
			$stmt->execute(array($userId));
			$rs = $stmt->fetch();
			//Zend_Debug::dump($rs);
			return $rs;
		}
	}

	