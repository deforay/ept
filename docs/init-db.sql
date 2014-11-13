-- phpMyAdmin SQL Dump
-- version 3.4.10.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Sep 14, 2013 at 12:38 PM
-- Server version: 5.5.32
-- PHP Version: 5.3.10-1ubuntu3.8

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `eanalyze`
--

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `current_scheme`$$
CREATE PROCEDURE `current_scheme`(IN UsrSysId varchar(45))
BEGIN
-- Create similar tabel for all Scheme - union it and the send for display
-- 1. DTS
SELECT a.ShipmentDate, 'DTS' as Scheme,  b.ParticipantFname,b.ParticipantFname,a.LastDateResponse ,
EvaluationStatus, ResponseDate
FROM shipment_dts as a left join participant as b 
on a.ParticipantID = b.ParticipantID 
where LastDateResponse <= CURDATE() and b.UserSystemId = UsrSysId

union 
-- 2. VL
SELECT a.ShipmentDate, 'VL' as Scheme,  b.ParticipantFname,b.ParticipantFname,a.LastDateResponse ,
EvaluationStatus, ResponseDate
FROM shipment_vl as a left join participant as b 
on a.ParticipantID = b.ParticipantID 
where LastDateResponse <= CURDATE() and b.UserSystemId = UsrSysId

--
-- Now order by
order by LastDateResponse desc , Scheme asc; 

END$$

DROP PROCEDURE IF EXISTS `getUserInfo`$$
CREATE PROCEDURE `getUserInfo`(IN UsrID varchar(45))
BEGIN
select UserID, UserSystemId, UserFname, UserLName, UserPhoneNumber,UserEmail,UserFld1,UserFld2,UserFld3 from users where userID = UsrID ;

END$$

DROP PROCEDURE IF EXISTS `PARTICIPANT_ONE`$$
CREATE PROCEDURE `PARTICIPANT_ONE`(IN PSysId varchar(45))
BEGIN

SELECT
	ParticipantAffiliation,
	ParticipantFName,
	ParticipantID,
	ParticipantLName,
	ParticipantMobile,
	ParticipantPhone,
	UserSystemID,
	ParticipantSystemID,
	ParticipanteMail
FROM participant where
	ParticipantSystemID = PSysId ;
END$$

DROP PROCEDURE IF EXISTS `PARTICIPANT_ONE_UPDATE`$$
CREATE PROCEDURE `PARTICIPANT_ONE_UPDATE`(
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
)
BEGIN
Declare pCount INT default 0;
 
select count(*) into  pCount from participant where 
ParticipantSystemID = PSysId ;

IF (pCount > 0) THEN
-- Use update 
-- select * from response_result_dts;
	update participant set 


	ParticipantAffiliation = pAff,
	ParticipanteMail = pemail,
	ParticipantFName = fName,
	ParticipantID = pId,
	ParticipantLName = lName,
	ParticipantMobile = cellPhone,
	ParticipantPhone = phone,
	Updated_on = now(),
	Updated_by = user
WHERE
	ParticipantSystemID = PSysId;
ELSE

INSERT INTO participant
(
		ParticipantSystemID,
		ParticipantID,
		UserSystemID,

		ParticipantAffiliation,
		ParticipanteMail,
		ParticipantFName,

		ParticipantLName,
		ParticipantMobile,
		ParticipantPhone,

		Created_on,
		Updated_on,
		Updated_by,
		Created_by
)
	VALUES
	(
		PSysId , 
		pId, 
		uSysId,

		pAff,
		pemail,
		fName,

		lName,
		cellPhone,
		phone ,

		now(),
		now(),
		user,
		user
	);


END IF;

END$$

DROP PROCEDURE IF EXISTS `POSSIBLE_TESTRESULT`$$
CREATE PROCEDURE `POSSIBLE_TESTRESULT`(IN SchCode varchar(45),IN SchSubGrp varchar(45))
BEGIN
SELECT
Ucase(`r_possibleresult`.`Response`) AS RESULT,
Ucase(`r_possibleresult`.`Response`) AS RES_VALUE
FROM `r_possibleresult`
where SchemeCode = SchCode and SchemeSubgroup = SchSubGrp;

END$$

DROP PROCEDURE IF EXISTS `RESPONSE_DTS_ONE`$$
CREATE PROCEDURE `RESPONSE_DTS_ONE`(IN PartId varchar(45),IN ShipId varchar(45))
BEGIN
/*
SELECT
`response_result_dts`.`CalculatedScore`,
`response_result_dts`.`DTSSampleID`,
`response_result_dts`.`ExpDate1`,
`response_result_dts`.`ExpDate2`,
`response_result_dts`.`ExpDate3`,
`response_result_dts`.`LotNo1`,
`response_result_dts`.`LotNo2`,
`response_result_dts`.`LotNo3`,
`response_result_dts`.`ParticipantID`,
`response_result_dts`.`ReportedResult`,

`response_result_dts`.`ShipmentID`,
`response_result_dts`.`TestKitName1`,
`response_result_dts`.`TestKitName2`,
`response_result_dts`.`TestKitName3`,

`response_result_dts`.`TestResult1`,
`response_result_dts`.`TestResult2`,
`response_result_dts`.`TestResult3`,

`reference_result_dts`.`DTSSampleLabel`

FROM `eanalyze`.`response_result_dts` left join
reference_result_dts on
`response_result_dts`.`DTSSampleID` = `reference_result_dts`.`DTSSampleID` and
`response_result_dts`.`ShipmentID` = `reference_result_dts`.`DTSShipmentID`
where 
`response_result_dts`.`ShipmentID` = ShipId and
`response_result_dts`.`ParticipantID` = PartId 
order by 
`reference_result_dts`.`DTSSampleLabel`;
*/
SELECT
b.CalculatedScore,
a.DTSSampleID,
date_format(b.ExpDate1,'%d-%b-%Y') as ExpDate1,
date_format(b.ExpDate2,'%d-%b-%Y') as ExpDate2,
date_format(b.ExpDate3,'%d-%b-%Y') as ExpDate3,
b.LotNo1,
b.LotNo2,
b.LotNo3,
b.ParticipantID,
b.ReportedResult,
b.ShipmentID,
b.TestKitName1,
b.TestKitName2,
b.TestKitName3,
b.TestResult1,
b.TestResult2,
b.TestResult3,
a.DTSSampleLabel
FROM 
(select * from reference_result_dts where DTSShipmentID = ShipId) as a left join 
(Select * from response_result_dts where ShipmentID = ShipId and ParticipantID = PartId) as b
 on 
a.DTSSampleID = b.DTSSampleID and
b.ShipmentID = a.DTSShipmentID


order by 
a.DTSSampleLabel;

END$$

DROP PROCEDURE IF EXISTS `RESPONSE_RESULT_DTS_UPDATE`$$
CREATE PROCEDURE `RESPONSE_RESULT_DTS_UPDATE`(
IN PartId varchar(45), 
IN ShipID varchar(45),
IN SampID varchar(45),

IN KITName1 varchar(45),
IN Lot1 varchar(45),
IN ExpDt1 date,
IN TResult1 varchar(45),

IN KITName2 varchar(45),
IN Lot2 varchar(45),
IN ExpDt2 date,
IN TResult2 varchar(45),


IN KITName3 varchar(45),
IN Lot3 varchar(45),
IN ExpDt3 date,
IN TResult3 varchar(45),

IN RptResult varchar(45),

IN user varchar(45)
)
BEGIN
Declare SampleCount INT default 0;
 
select count(*) into  SampleCount from response_result_dts where 
ShipmentID = ShipId and
DTSSampleID = SampID and
ParticipantID = PartId;

IF (SampleCount > 0) THEN
-- Use update 
-- select * from response_result_dts;
	update response_result_dts set 

		TestKitName1 = KITName1,
		LotNo1 = Lot1,
		TestResult1 = TResult1,
		ExpDate1 = ExpDt1,

		TestKitName2 = KITName2,
		LotNo2 = Lot2,
		TestResult2 = TResult2,
		ExpDate2 = ExpDt2,

		TestKitName3 =KITName3,
		LotNo3 = Lot3,
		TestResult3 = TResult3,
		ExpDate3 = ExpDt3,

		ReportedResult = RptResult,
		Updated_on = now(),
		Updated_by = user
		
	Where 
	ShipmentID = ShipId and
	ParticipantID = PartId and 
	DTSSampleID = SampID;
ELSE
INSERT INTO response_result_dts
	(
		ParticipantID,
		ShipmentID,
		DTSSampleID,

		TestKitName1,
		LotNo1, 
		TestResult1,
		ExpDate1,

		TestKitName2,
		LotNo2,
		TestResult2,
		ExpDate2,

		TestKitName3,
		LotNo3,
		TestResult3,
		ExpDate3,

		ReportedResult,
		Created_on,
		Updated_on,
		Updated_by,
		Created_by
	)
	VALUES
	(
		PartId , 
		ShipID, 
		SampID,

		KITName1,
		Lot1,
		ExpDt1,
		TResult1,

		KITName2,
		Lot2 ,
		ExpDt2,
		TResult2,


		KITName3,
		Lot3,
		ExpDt3,
		TResult3,

		RptResult,

		now(),
		now(),
		user,
		user
	);


END IF;

END$$

DROP PROCEDURE IF EXISTS `SHIPMENT_ALL`$$
CREATE PROCEDURE `SHIPMENT_ALL`( 
   IN paramFrom INT,
   IN paramTo INT)
BEGIN
    DECLARE valFrom INT;
	DECLARE valTo   INT;

    SET valFrom = paramFrom;
    SET valTo = paramTo;

Select year(a.ShipmentDate) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.ParticipantFname as FNAME,b.ParticipantFname as LNAME,
a.PARTICIPANTID,
a.SHIPMENTDATE, 
-- a.ShipmentTestReportDate as RESPONSEDATE,
DATE_FORMAT(a.ShipmentTestReportDate,'%Y-%m-%d')  as RESPONSEDATE,
a.LASTDATERESPONSE, 
a.ParticipantID as PARTICIPANT_ID,
a.EvaluationStatus as EVALUATIONSTATUS, 
 a.DTSShipmentID as SHIPID,

	CASE  substr(a.EvaluationStatus,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.EvaluationStatus,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment_dts  as a 
left join participant as b on a.ParticipantID = b.ParticipantID where year(a.ShipmentDate)  + 5 > year(CURDATE())


union

Select year(a.ShipmentDate) as SHIP_YEAR,   
'VL' AS SCHEME,
b.ParticipantFname as FNAME,b.ParticipantFname as LNAME,
a.PARTICIPANTID,
a.SHIPMENTDATE, 
-- a.ShipmentTestReportDate as RESPONSEDATE,
DATE_FORMAT(a.ShipmentTestReportDate,'%Y-%m-%d')  as RESPONSEDATE,
a.LASTDATERESPONSE, 
a.ParticipantID as PARTICIPANT_ID,
a.EvaluationStatus as EVALUATIONSTATUS, 
 a.VLShipmentID as SHIPID,
	CASE  substr(a.EvaluationStatus,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.EvaluationStatus,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment_vl  as a 
left join participant as b on a.ParticipantID = b.ParticipantID where year(a.ShipmentDate)  + 5 > year(CURDATE())

order by SHIP_YEAR, ParticipantID ;
-- LIMIT valFrom, valTo;
END$$

DROP PROCEDURE IF EXISTS `SHIPMENT_CURRENT`$$
CREATE PROCEDURE `SHIPMENT_CURRENT`(IN uId varchar(45) )
BEGIN
Select year(a.ShipmentDate) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.ParticipantFname as FNAME,b.ParticipantFname as LNAME,
a.PARTICIPANTID,
a.SHIPMENTDATE, 
DATE_FORMAT(a.ShipmentTestReportDate,'%Y-%m-%d')  as RESPONSEDATE,
a.LASTDATERESPONSE, 
a.DTSShipmentID as SHIPID,
a.EvaluationStatus as EVALUATIONSTATUS, 

	CASE  substr(a.EvaluationStatus,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE'  ,


	CASE  substr(a.EvaluationStatus,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment_dts  as a 
left join participant as b on a.ParticipantID = b.ParticipantID where year(a.ShipmentDate)  + 5 > year(CURDATE()) 
and a.LASTDATERESPONSE >= CURDATE() 
-- and a.ParticipantID in (Select ParticipantID from participant where UserSystemId = '1')
union

Select year(a.ShipmentDate) as SHIP_YEAR,   
'VL' AS SCHEME,
b.ParticipantFname as FNAME,b.ParticipantFname as LNAME,
a.PARTICIPANTID,
a.SHIPMENTDATE, 
DATE_FORMAT(a.ShipmentTestReportDate, '%Y-%m-%d') as RESPONSEDATE,
a.LASTDATERESPONSE, 
a.VLShipmentID as SHIPID,
a.EvaluationStatus as EVALUATIONSTATUS, 

	CASE  substr(a.EvaluationStatus,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.EvaluationStatus,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment_vl  as a 
left join participant as b on a.ParticipantID = b.ParticipantID where year(a.ShipmentDate)  + 5 > year(CURDATE()) 
and a.LASTDATERESPONSE >= CURDATE() 
-- and a.ParticipantID in (Select ParticipantID from participant where UserSystemId = uId)
order by SHIP_YEAR, ParticipantID ;

END$$

DROP PROCEDURE IF EXISTS `SHIPMENT_DEFAULTED`$$
CREATE PROCEDURE `SHIPMENT_DEFAULTED`()
BEGIN
Select year(a.ShipmentDate) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.ParticipantFname as FNAME,b.ParticipantFname as LNAME,
a.PARTICIPANTID,
a.SHIPMENTDATE, 
a.ShipmentTestReportDate as RESPONSEDATE,
a.LASTDATERESPONSE, 
a.ParticipantID as PARTICIPANT_ID,
a.EvaluationStatus as EVALUATIONSTATUS, 

	CASE  substr(a.EvaluationStatus,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(a.EvaluationStatus,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment_dts  as a 
left join participant as b on a.ParticipantID = b.ParticipantID where year(a.ShipmentDate)  + 5 > year(CURDATE()) 
and a.LASTDATERESPONSE < CURDATE() and  substr(a.EvaluationStatus,3,1) <> '1'

union

Select year(a.ShipmentDate) as SHIP_YEAR,   
'VL' AS SCHEME,
b.ParticipantFname as FNAME,b.ParticipantFname as LNAME,
a.PARTICIPANTID,
a.SHIPMENTDATE, 
a.ShipmentTestReportDate as RESPONSEDATE,
a.LASTDATERESPONSE, 
a.ParticipantID as PARTICIPANT_ID,
a.EvaluationStatus as EVALUATIONSTATUS, 

	CASE  substr(a.EvaluationStatus,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(a.EvaluationStatus,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment_vl  as a 
left join participant as b on a.ParticipantID = b.ParticipantID where year(a.ShipmentDate)  + 5 > year(CURDATE()) 
and a.LASTDATERESPONSE < CURDATE() and  substr(a.EvaluationStatus,3,1) <> '1'

order by SHIP_YEAR, ParticipantID ;
END$$

DROP PROCEDURE IF EXISTS `SHIPMENT_ONE`$$
CREATE PROCEDURE `SHIPMENT_ONE`(IN PartId varchar(45), IN ShipId varchar(45))
BEGIN
SELECT

`shipment_dts`.`DTSShipmentID` as ShipmentID,
`shipment_dts`.`EvaluationStatus`,
date_format(`shipment_dts`.`LastDateResponse`,'%d-%b-%Y') as LastDateResponse,
`shipment_dts`.`ParticipantID`,
`shipment_dts`.`ParticipantSupervisor`,
date_format(`shipment_dts`.`ShipmentTestReportDate`,'%d-%b-%Y') as RESPONSEDATE,
date_format(`shipment_dts`.`ReviewDate`,'%d-%b-%Y') as ReviewDate ,
date_format(`shipment_dts`.`ShipmentDate`,'%d-%b-%Y') as ShipmentDate,
date_format(`shipment_dts`.`ShipmentReceiptDate`,'%d-%b-%Y') as ShipmentReceiptDate ,
`shipment_dts`.`ShipmentScore`,
date_format(`shipment_dts`.`ShipmentTestDate`,'%d-%b-%Y') as ShipmentTestDate,
date_format(`shipment_dts`.`ShipmentTestReportDate`,'%d-%b-%Y') as ShipmentTestReportDate,
date_format(`shipment_dts`.`SampleRehydrationDate`,'%d-%b-%Y') as SampleRehydrationDate,
`shipment_dts`.`UserComment`,
`shipment_dts`.`supervisorApproval`

FROM `shipment_dts`
Where 
ParticipantID = PartId and DTSShipmentID = ShipId ;
END$$

DROP PROCEDURE IF EXISTS `SHIPMENT_OVERVIEW`$$
CREATE PROCEDURE `SHIPMENT_OVERVIEW`()
BEGIN
-- Select shipment for last five year
Select year(ShipmentDate) as SHIP_YEAR,   
'DTS' AS SCHEME,
count(substr(EvaluationStatus,1,1)) as TOTALSHIPMEN,  
count(
	CASE  substr(EvaluationStatus,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
  
count(
	CASE  substr(EvaluationStatus,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(EvaluationStatus,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment_dts  
where year(ShipmentDate)  + 5 > year(CURDATE())
group by SHIP_YEAR 

union

Select year(ShipmentDate) as SHIP_YEAR,   
'VL' AS SCHEME,
count(substr(EvaluationStatus,1,1)) as TOTLASHIPPED,  
count(
	CASE  substr(EvaluationStatus,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
count(
	CASE  substr(EvaluationStatus,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(EvaluationStatus,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment_vl as a 
where year(ShipmentDate)  + 5 > year(CURDATE())
group by SHIP_YEAR ;

END$$

DROP PROCEDURE IF EXISTS `SHIPMENT_UPDATE_DTS`$$
CREATE PROCEDURE `SHIPMENT_UPDATE_DTS`(
IN PartId varchar(45), 
IN ShipId varchar(45),
IN EvaStatus varchar(45),
IN recpDate varchar(45),
IN testDate varchar(45),
IN rehydDate varchar(45),
IN supApproval varchar(45),
IN partSupervisor varchar(45),
In userCommnets varchar(90),
In UpdaterId varchar(45)
)
BEGIN
-- $shipId,$participantId, $receiptDate,$testDate,$rehydrationDate,$supervisorApproval,$participantSupervisor,$userCommnets

UPDATE `eanalyze`.`shipment_dts`
SET
Updated_by_user= UpdaterId,
EvaluationStatus = EvaStatus,
ParticipantSupervisor = partSupervisor,
SampleRehydrationDate = rehydDate,
ShipmentReceiptDate = recpDate,
ShipmentTestDate = testDate,
ShipmentTestReportDate = now(),
supervisorApproval = supApproval,
Update_on_user = now(),
UserComment = userCommnets
WHERE 
DTSShipmentID = ShipId and ParticipantID = PartId;

END$$

DROP PROCEDURE IF EXISTS `TESTKITNAME_ALL_DTS`$$
CREATE PROCEDURE `TESTKITNAME_ALL_DTS`()
BEGIN
SELECT TESTKITNAME_ID as TESTKITNAMEID, TESTKIT_NAME as TESTKITNAME FROM eanalyze.r_testkitname_dts
where COUNTRYADAPTED = 1;
END$$

DROP PROCEDURE IF EXISTS `USERS_PARTICIPANT`$$
CREATE PROCEDURE `USERS_PARTICIPANT`(IN uId varchar(45) )
BEGIN

select PARTICIPANTID, ParticipantSystemID, PARTICIPANTFNAME, PARTICIPANTLNAME,PARTICIPANTMOBILE,ParticipantAffiliation as TESTGROUP   from participant , users where 
participant.userSystemId = users.userSystemId and 
 users.userSystemId = uId;
-- --fdsfdsf

END$$

DROP PROCEDURE IF EXISTS `USER_ONE`$$
CREATE PROCEDURE `USER_ONE`(IN UsrID varchar(45))
BEGIN
select UserID, UserSystemId, UserFname, UserLName, UserPhoneNumber,UserEmail,UserFld1,UserFld2,UserFld3,UserCellNumber,UserSecondaryemail from users where userID = UsrID ;

END$$

DROP PROCEDURE IF EXISTS `_UserParticipant`$$
CREATE PROCEDURE `_UserParticipant`(IN SYSID varchar(45))
BEGIN
SELECT ParticipantFName,ParticipantLName,ParticipantID,ParticipantMobile, ParticipantPhone, UserSystemID  FROM eanalyze.participant where UserSystemId = SYSID;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `participant`
--

DROP TABLE IF EXISTS `participant`;
CREATE TABLE IF NOT EXISTS `participant` (
  `ParticipantID` varchar(45) NOT NULL COMMENT 'We need more information for this table but at this pointit should work ',
  `UserSystemID` varchar(45) NOT NULL COMMENT 'System Generated User Id for System user; One user can enter data for multiple participant ',
  `ParticipantFName` varchar(45) DEFAULT NULL,
  `ParticipantLName` varchar(45) DEFAULT NULL,
  `ParticipantMobile` varchar(45) DEFAULT NULL,
  `ParticipantPhone` varchar(45) DEFAULT NULL,
  `ParticipantAffiliation` varchar(45) DEFAULT NULL,
  `ParticipantSystemID` varchar(45) DEFAULT NULL,
  `ParticipanteMail` varchar(45) DEFAULT NULL,
  `Created_on` datetime DEFAULT NULL,
  `Updated_on` datetime DEFAULT NULL,
  `Updated_by` varchar(45) DEFAULT NULL,
  `Created_by` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`ParticipantID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `participant`
--

INSERT INTO `participant` (`ParticipantID`, `UserSystemID`, `ParticipantFName`, `ParticipantLName`, `ParticipantMobile`, `ParticipantPhone`, `ParticipantAffiliation`, `ParticipantSystemID`, `ParticipanteMail`, `Created_on`, `Updated_on`, `Updated_by`, `Created_by`) VALUES
('adhikari1', '2', 'amitabh1', 'adhikari1', '1234567', '1234567', NULL, 'adhikari1', 'ad@mail', NULL, NULL, NULL, NULL),
('adhikari2', '2', 'amitabh2', 'adhikari2', '23456', '23423', NULL, 'adhikari2', 'ad2@mail', NULL, NULL, NULL, NULL),
('amit1', '3', 'amith1', 'adhikairamit1', '24325', '4324', NULL, 'amit1', 'amit1@mail', NULL, NULL, NULL, NULL),
('app01', '1', 'amitapp01', 'adhikariapp01', 'CALL324', '234323', '', 'app01', 'app01@mail.com', NULL, '2013-08-14 12:51:22', 'app0@cdc.gov', NULL),
('app02', '1', 'amitapp02', 'adhikariapp03', '4324', '56436', NULL, 'app02', 'app02@mail', NULL, NULL, NULL, NULL),
('app03', '1', 'amitapp03', 'adhikariapp03', '4324', '45654', NULL, 'app03', 'app03@mail', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reference_result_dts`
--

DROP TABLE IF EXISTS `reference_result_dts`;
CREATE TABLE IF NOT EXISTS `reference_result_dts` (
  `DTSShipmentID` varchar(45) NOT NULL,
  `DTSSampleID` int(11) NOT NULL,
  `DTSRefranceResult` varchar(45) DEFAULT NULL,
  `DTSSampleLabel` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`DTSShipmentID`,`DTSSampleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Referance Result for DTS Shipment';

--
-- Dumping data for table `reference_result_dts`
--

INSERT INTO `reference_result_dts` (`DTSShipmentID`, `DTSSampleID`, `DTSRefranceResult`, `DTSSampleLabel`) VALUES
('1', 1, 'P', 'Sample 1'),
('1', 2, 'N', 'Sample 2'),
('1', 3, 'P', 'Sample 3'),
('2', 1, 'P', 'A'),
('3', 1, 'P', 'B'),
('3', 2, 'N', 'C'),
('3', 3, 'P', 'D'),
('3', 4, 'P', 'E'),
('3', 5, 'N', 'F'),
('3', 6, 'P', 'G');

-- --------------------------------------------------------

--
-- Table structure for table `response_result_dts`
--

DROP TABLE IF EXISTS `response_result_dts`;
CREATE TABLE IF NOT EXISTS `response_result_dts` (
  `ShipmentID` varchar(45) NOT NULL,
  `ParticipantID` varchar(45) NOT NULL,
  `DTSSampleID` varchar(45) NOT NULL,
  `TestKitName1` varchar(45) DEFAULT NULL,
  `LotNo1` varchar(45) DEFAULT NULL,
  `ExpDate1` date DEFAULT NULL,
  `TestResult1` varchar(45) DEFAULT NULL,
  `TestKitName2` varchar(45) DEFAULT NULL,
  `LotNo2` varchar(45) DEFAULT NULL,
  `ExpDate2` date DEFAULT NULL,
  `TestResult2` varchar(45) DEFAULT NULL,
  `TestKitName3` varchar(45) DEFAULT NULL,
  `LotNo3` varchar(45) DEFAULT NULL,
  `ExpDate3` date DEFAULT NULL,
  `TestResult3` varchar(45) DEFAULT NULL,
  `ReportedResult` varchar(45) DEFAULT NULL,
  `CalculatedScore` varchar(45) DEFAULT NULL,
  `Created_by` varchar(45) DEFAULT NULL,
  `Created_on` datetime DEFAULT NULL,
  `Updated_by` varchar(45) DEFAULT NULL,
  `Updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`ShipmentID`,`ParticipantID`,`DTSSampleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `response_result_dts`
--

INSERT INTO `response_result_dts` (`ShipmentID`, `ParticipantID`, `DTSSampleID`, `TestKitName1`, `LotNo1`, `ExpDate1`, `TestResult1`, `TestKitName2`, `LotNo2`, `ExpDate2`, `TestResult2`, `TestKitName3`, `LotNo3`, `ExpDate3`, `TestResult3`, `ReportedResult`, `CalculatedScore`, `Created_by`, `Created_on`, `Updated_by`, `Updated_on`) VALUES
('1', 'adhikari1', '1', 'tk50f41f66a238f', 'vcxv', '2013-07-01', 'NONREACTIVE', 'tk50f41f66a2399', 'fsdf', '2013-07-09', 'NONREACTIVE', 'tk50f41f66a23b1', 'qwq', '2013-07-15', 'REACTIVE', 'POSITIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:32'),
('1', 'adhikari1', '2', 'tk50f41f66a238f', 'vcxv', '2013-07-01', 'NONREACTIVE', 'tk50f41f66a2399', 'fsdf', '2013-07-09', 'REACTIVE', 'tk50f41f66a23b1', 'qwq', '2013-07-15', 'NONREACTIVE', 'POSITIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:32'),
('1', 'adhikari1', '3', 'tk50f41f66a238f', 'vcxv', '2013-07-01', 'REACTIVE', 'tk50f41f66a2399', 'fsdf', '2013-07-09', 'NONREACTIVE', 'tk50f41f66a23b1', 'qwq', '2013-07-15', 'NONREACTIVE', 'POSITIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:32'),
('1', 'adhikari2', '1', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'P', NULL, NULL, NULL, NULL, NULL),
('1', 'adhikari2', '2', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'N', NULL, NULL, NULL, NULL, NULL),
('1', 'adhikari2', '3', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'P', NULL, NULL, NULL, NULL, NULL),
('3', 'amit1', '1', 'tk50f41f66a238f', 'adfasd', '2013-07-09', 'INVALID', 'tk50f41f66a23a7', 'gdfg', '2013-07-15', 'REACTIVE', 'tk50f41f66a23b1', 'gdfg', '2013-07-15', '', 'POSITIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:50'),
('3', 'amit1', '2', 'tk50f41f66a238f', 'adfasd', '2013-07-09', 'NONREACTIVE', 'tk50f41f66a23a7', 'gdfg', '2013-07-15', 'REACTIVE', 'tk50f41f66a23b1', 'gdfg', '2013-07-15', '', 'NEGATIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:50'),
('3', 'amit1', '3', 'tk50f41f66a238f', 'adfasd', '2013-07-09', 'REACTIVE', 'tk50f41f66a23a7', 'gdfg', '2013-07-15', 'NONREACTIVE', 'tk50f41f66a23b1', 'gdfg', '2013-07-15', 'REACTIVE', 'POSITIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:50'),
('3', 'amit1', '4', 'tk50f41f66a238f', 'adfasd', '2013-07-09', 'REACTIVE', 'tk50f41f66a23a7', 'gdfg', '2013-07-15', 'REACTIVE', 'tk50f41f66a23b1', 'gdfg', '2013-07-15', 'REACTIVE', 'INDETERMINATE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:50'),
('3', 'amit1', '5', 'tk50f41f66a238f', 'adfasd', '2013-07-09', 'REACTIVE', 'tk50f41f66a23a7', 'gdfg', '2013-07-15', 'REACTIVE', 'tk50f41f66a23b1', 'gdfg', '2013-07-15', '', 'NEGATIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:50'),
('3', 'amit1', '6', 'tk50f41f66a238f', 'adfasd', '2013-07-09', 'NONREACTIVE', 'tk50f41f66a23a7', 'gdfg', '2013-07-15', 'REACTIVE', 'tk50f41f66a23b1', 'gdfg', '2013-07-15', '', 'POSITIVE', NULL, NULL, NULL, 'app0@cdc.gov', '2013-07-15 16:07:50');

-- --------------------------------------------------------

--
-- Table structure for table `r_possibleresult`
--

DROP TABLE IF EXISTS `r_possibleresult`;
CREATE TABLE IF NOT EXISTS `r_possibleresult` (
  `ID` int(11) NOT NULL,
  `SchemeCode` varchar(45) NOT NULL,
  `SchemeSubgroup` varchar(45) DEFAULT NULL,
  `Response` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `r_possibleresult`
--

INSERT INTO `r_possibleresult` (`ID`, `SchemeCode`, `SchemeSubgroup`, `Response`) VALUES
(1, 'DTS', 'DTS_TEST', 'REACTIVE'),
(2, 'DTS', 'DTS_TEST', 'NONREACTIVE'),
(3, 'DTS', 'DTS_TEST', 'INVALID'),
(4, 'DTS', 'DTS_FINAL', 'POSITIVE'),
(5, 'DTS', 'DTS_FINAL', 'NEGATIVE'),
(6, 'DTS', 'DTS_FINAL', 'INDETERMINATE');

-- --------------------------------------------------------

--
-- Table structure for table `r_testkitname_dts`
--

DROP TABLE IF EXISTS `r_testkitname_dts`;
CREATE TABLE IF NOT EXISTS `r_testkitname_dts` (
  `TestKitName_ID` varchar(50) NOT NULL,
  `TestKit_Name` varchar(100) DEFAULT NULL,
  `TestKit_Name_Short` varchar(50) DEFAULT NULL,
  `TestKit_Comments` varchar(50) DEFAULT NULL,
  `Updated_On` datetime DEFAULT NULL,
  `Updated_By` int(11) DEFAULT NULL,
  `Installation_id` varchar(50) DEFAULT NULL,
  `TestKit_Manufacturer` varchar(50) DEFAULT NULL,
  `Created_On` datetime DEFAULT NULL,
  `Created_By` int(11) DEFAULT NULL,
  `Approval` int(1) DEFAULT '1' COMMENT '1 = Approved , 0 not approved.',
  `TestKit_ApprovalAgency` varchar(20) DEFAULT NULL COMMENT 'USAID, FDA, LOCAL',
  `Source_referance` varchar(50) DEFAULT NULL,
  `CountryAdapted` int(11) DEFAULT NULL COMMENT '0= Not allowed in the country 1 = approved in country ',
  PRIMARY KEY (`TestKitName_ID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Dumping data for table `r_testkitname_dts`
--

INSERT INTO `r_testkitname_dts` (`TestKitName_ID`, `TestKit_Name`, `TestKit_Name_Short`, `TestKit_Comments`, `Updated_On`, `Updated_By`, `Installation_id`, `TestKit_Manufacturer`, `Created_On`, `Created_By`, `Approval`, `TestKit_ApprovalAgency`, `Source_referance`, `CountryAdapted`) VALUES
('tk50f41f66a2388', 'ACON HIV 1/2/0 Tri-line', 'ACON HIV 1/2/0 Tri', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a238f', 'Alere Determine HIV-1/2', 'Alere Determine HIV-1/2', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/Abbott Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2399', 'Aware HIV-1/2 BSP', 'Aware HIV-1/2 BSP', NULL, '2013-01-14 10:09:21', 0, '0', ' Calypte Biomedical ', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a239e', 'Bionor HIV-1&2', 'Bionor HIV-1&2', NULL, '2013-01-14 10:09:21', 0, '0', ' Bionor A/S ', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23a7', 'Calypte Aware HIV-1/2 OMT ', 'Calypte Aware HIV-', NULL, '2013-01-14 10:09:21', 0, '0', ' Calypte Biomedical Corp.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23b1', 'Care Start HIV 1-2-O', 'Care Start HIV 1-2', NULL, '2013-01-14 10:09:21', 0, '0', ' Access Bio, Inc.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23b5', 'ClearviewÃ‚Â® COMPLETE HIV1/2 (formerly SURE) CHECKÃ‚Â® HIV1/2)', 'ClearviewÃ‚Â® COMPLETE HIV1/2 Non - US Labeling', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23ba', 'ClearviewÃ‚Â® COMPLETE HIV1/2 - US labeling** (formerly SURE CHECKÃ‚Â® HIV1/2)', 'ClearviewÃ‚Â® COMPLETE HIV1/2 - US labeling ', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23bf', 'Clearview  HIV 1/2 STAT-PAK Assay', 'Clearview  HIV 1/2', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23c4', 'Combaids RS Advantage', 'Combaids RS Advant', NULL, '2013-01-14 10:09:21', 0, '0', ' Span Diagnostics Ltd.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23c8', 'DPP HIV 1/2 Screen ', 'DPP HIV 1/2 Screen', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23cd', 'DPP HIV 1 / 2 Screen Assay  Oral Fluid, Whole Blood,Serum & Plasma', 'DPP HIV 1 / 2 Scre', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23d1', 'Double Check HIV 1&2', 'Double Check HIV 1', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/ Orgenics, Ltd', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23d6', 'Double Check Gold HIV1&2', 'Double Check Gold ', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/ Orgenics, Ltd', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23db', 'EZ-TRUST Rapid Anti-HIV (1&2) Test', 'EZ-TRUST Rapid Ant', NULL, '2013-01-14 10:09:21', 0, '0', ' CS Innovation', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23df', 'First Response HIV 1-2.0', 'First Response HIV', NULL, '2013-01-14 10:09:21', 0, '0', ' Premier Medical Corporation', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23e3', 'Genie Fast HIV 1/2 ', 'Genie Fast HIV 1/2', NULL, '2013-01-14 10:09:21', 0, '0', ' Bio-Rad Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23e8', 'HIV 1/2 Gold Rapid Screen Test ', 'HIV 1/2 Gold Rapid', NULL, '2013-01-14 10:09:21', 0, '0', ' Medinostics IntÃ¢â‚¬â„¢l', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23ed', 'HIV 1/2 Rapid Test Kit', 'HIV 1/2 Rapid Test', NULL, '2013-01-14 10:09:21', 0, '0', ' Medinostics IntÃ¢â‚¬â„¢l', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23f1', 'HIV 1/ 2 STAT-PAK Assay', 'HIV 1/ 2 STAT-PAK ', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23f6', 'HIV 1/2 STAT-PAK Dipstick Assay', 'HIV 1/2 STAT-PAK D', NULL, '2013-01-14 10:09:21', 0, '0', ' Chembio Diagnostic Systems, Inc', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23fa', 'HIV(1+2) Rapid Test Strip', 'HIV(1+2) Rapid Tes', NULL, '2013-01-14 10:09:21', 0, '0', ' Shanghai Kehua Bio-engineering Co., Ltd (KHB)', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a23ff', 'HIVSav 1&2 Rapid SeroTest', 'HIVSav 1&2 Rapid S', NULL, '2013-01-14 10:09:21', 0, '0', ' Savyvon Diagnostics Ltd.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2404', 'iCARE Rapid Anti-HIV (1&2) ', 'iCARE Rapid Anti-H', NULL, '2013-01-14 10:09:21', 0, '0', ' JAL Innovation', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2408', 'ImmunoComb HIV 1&2', 'ImmunoComb HIV 1&2', NULL, '2013-01-14 10:09:21', 0, '0', ' Alere/ Orgenics, Ltd', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a240d', 'InstantCHEK HIV1+2', 'InstantCHEK HIV1+2', NULL, '2013-01-14 10:09:21', 0, '0', ' EY Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2411', 'KSII  HIV 1/2 Rapid Diagnostic Test Kit ', 'KSII  HIV 1/2 Rapi', NULL, '2013-01-14 10:09:21', 0, '0', ' K. Shorehill Int''l, Inc.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2415', 'MPI Diagnostics Anti-HIV (1&2) Test ', 'MPI Diagnostics An', NULL, '2013-01-14 10:09:21', 0, '0', ' MPI Diagnostics', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a241a', 'INSTI HIV Antibody', 'INSTI HIV Antibody', NULL, '2013-01-14 10:09:21', 0, '0', ' Biolytical Laboratories', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a241f', 'Multispot HIV-1/HIV-2', 'Multispot HIV-1/HI', NULL, '2013-01-14 10:09:21', 0, '0', ' Bio-Rad laboratories', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2423', 'OraQuick ADVANCE Rapid HIV-1/2', 'OraQuick ADVANCE R', NULL, '2013-01-14 10:09:21', 0, '0', ' OraSure Technologies', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2428', 'OraQuick HIV-1/2 Rapid Antibody Test', 'OraQuick HIV-1/2 R', NULL, '2013-01-14 10:09:21', 0, '0', ' OraSure Technologies', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a242c', 'RAPID 1-2-3 HEMA Dipstick', 'RAPID 1-2-3 HEMA D', NULL, '2013-01-14 10:09:21', 0, '0', ' Hema Diagnostics Systems', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2430', 'RAPID 1-2-3 HEMA EZ ', 'RAPID 1-2-3 HEMA E', NULL, '2013-01-14 10:09:21', 0, '0', ' Hema Diagnostics Systems', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2435', 'RAPID 1-2-3 HEMA EXPRESS', 'RAPID 1-2-3 HEMA E', NULL, '2013-01-14 10:09:21', 0, '0', ' Hema Diagnostics Systems', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2439', 'Reveal Rapid HIV Test', 'Reveal Rapid HIV T', NULL, '2013-01-14 10:09:21', 0, '0', ' MedMira', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a243e', 'Reveal G3 Rapid HIV-1 Antibody Test', 'Reveal G3 Rapid HI', NULL, '2013-01-14 10:09:21', 0, '0', ' MedMira', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2443', 'Signal HIV Rapid Test', 'Signal HIV Rapid T', NULL, '2013-01-14 10:09:21', 0, '0', ' Span Diagnostics Ltd.', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a2447', 'Uni-Gold HIV - USAID', 'Uni-Gold HIV -USAID', NULL, '2013-01-14 10:09:21', 0, '0', ' Trinity Biotech', '2012-06-06 11:53:26', 0, 1, 'USAID', 'USAID Approval List March 30, 2012', 1),
('tk50f41f66a244b', 'Uni-Gold Recombigen HIV - FDA', 'Uni-Gold Recombige - FDA', NULL, '2013-01-14 10:09:21', 0, '0', ' Trinity Biotech', '2012-06-06 11:53:26', 0, 1, 'FDA', 'USAID Approval List March 30, 2012', 1),
('tk5136b425387a4', 'First Own Test Kit', 'MyKitname', 'Comments', NULL, NULL, 'LOG4fabc8babf6eb', 'Oh', '2013-03-06 04:12:37', 0, 1, 'WHO and National', 'Yes', 1),
('tk5137b608ac1d9', 'Hexagon HIVI II', 'Hexagon', 'rwer', NULL, NULL, 'LOG4fabc8babf6eb', 'rewr', '2013-03-06 22:32:56', 0, 0, 'NA', 'Yes', 1),
('tk51435b69f3b7e', 'gdfg', 'gfdg', 'gfdg', NULL, NULL, '5132ceba8fafa', 'gfdg', '2013-03-15 18:33:29', 0, 1, 'NA', 'NA', 1),
('tk514b50a81832c', 'Test Kit New ', 'New ', 'dasd', NULL, NULL, '5132ceba8fafa', 'dsad', '2013-03-21 19:25:44', 0, 1, 'Other', 'Yes', 1);

-- --------------------------------------------------------

--
-- Table structure for table `schemelist`
--

DROP TABLE IF EXISTS `schemelist`;
CREATE TABLE IF NOT EXISTS `schemelist` (
  `schemeID` varchar(10) NOT NULL,
  `ShipmentTable` varchar(45) DEFAULT NULL,
  `ResponseTable` varchar(45) DEFAULT NULL,
  `ReferanceResultTable` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`schemeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `shipment_dts`
--

DROP TABLE IF EXISTS `shipment_dts`;
CREATE TABLE IF NOT EXISTS `shipment_dts` (
  `DTSShipmentID` varchar(45) NOT NULL,
  `ParticipantID` varchar(45) NOT NULL,
  `ShipmentDate` date DEFAULT NULL,
  `EvaluationStatus` varchar(10) DEFAULT NULL COMMENT 'Shipment Status					\\nUse this to flag - 					\\nABCDEFG					\\nA = 9 Not shipped 1 shipped					\\nB = 1 Sample Received 9 Not recieved					\\nC = 1 = Responded 9 = Not responded					\\nD = 1= Timeely response 2= Late					\\nE = 1 - via Web user 2 - via web Provider 3 - Scanning 					\\nF = 9 Not eligille for evaluation 1 eligible for evaluation					\\nG = 1 = Evaluated  9= not evaluated					\\n',
  `ShipmentScore` int(11) DEFAULT NULL,
  `LastDateResponse` date DEFAULT NULL,
  `ShipmentTestDate` date DEFAULT NULL,
  `ShipmentReceiptDate` date DEFAULT NULL,
  `ShipmentTestReportDate` datetime DEFAULT NULL,
  `ParticipantSupervisor` varchar(45) DEFAULT NULL,
  `supervisorApproval` varchar(45) DEFAULT NULL,
  `ReviewDate` date DEFAULT NULL,
  `SampleRehydrationDate` date DEFAULT NULL,
  `NumberOfSample` int(11) DEFAULT NULL,
  `UserComment` varchar(90) DEFAULT NULL,
  `Create_on_admin` datetime DEFAULT NULL,
  `Update_on_admin` datetime DEFAULT NULL,
  `Update_by_admin` varchar(45) DEFAULT NULL,
  `Update_on_user` datetime DEFAULT NULL,
  `Updated_by_user` varchar(45) DEFAULT NULL,
  `created_by_admin` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`DTSShipmentID`,`ParticipantID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Shipment for DTS Samples';

--
-- Dumping data for table `shipment_dts`
--

INSERT INTO `shipment_dts` (`DTSShipmentID`, `ParticipantID`, `ShipmentDate`, `EvaluationStatus`, `ShipmentScore`, `LastDateResponse`, `ShipmentTestDate`, `ShipmentReceiptDate`, `ShipmentTestReportDate`, `ParticipantSupervisor`, `supervisorApproval`, `ReviewDate`, `SampleRehydrationDate`, `NumberOfSample`, `UserComment`, `Create_on_admin`, `Update_on_admin`, `Update_by_admin`, `Update_on_user`, `Updated_by_user`, `created_by_admin`) VALUES
('1', 'adhikari1', '2013-01-01', '11111110', NULL, '2015-06-02', '2013-07-02', '2013-07-01', '2013-07-15 16:07:32', 'ghffhgf', 'yes', NULL, '2013-07-02', NULL, 'ghfh', NULL, NULL, NULL, '2013-07-15 16:07:32', 'app0@cdc.gov', NULL),
('1', 'amit1', '2013-01-01', '11900990', NULL, '2015-06-02', NULL, NULL, NULL, 'B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('1', 'app01', '2013-01-01', '19000990', NULL, '2015-06-02', NULL, NULL, NULL, 'C', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('1', 'app02', '2013-01-01', '11123110', NULL, '2015-06-02', NULL, NULL, NULL, 'D', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('1', 'app03', '2013-01-01', '11900990', NULL, '2015-06-02', NULL, NULL, NULL, 'E', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('2', 'adhikari1', '2013-02-01', '11111110', NULL, '2013-06-02', NULL, NULL, NULL, 'F', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('2', 'adhikari2', '2013-02-01', '11900990', NULL, '2014-06-02', NULL, NULL, NULL, 'G', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('2', 'app01', '2013-02-01', '19000990', NULL, '2013-06-02', NULL, NULL, NULL, 'H', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('2', 'app02', '2013-02-01', '11123110', NULL, '2013-06-02', NULL, NULL, NULL, 'I', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('3', 'amit1', '2013-03-01', '11110190', NULL, '2015-06-02', '2013-07-01', '2013-07-01', '2013-07-15 16:07:50', 'cxczxcx', 'yes', NULL, '2013-07-09', NULL, 'cxzcxz', NULL, NULL, NULL, '2013-07-15 16:07:50', 'app0@cdc.gov', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shipment_vl`
--

DROP TABLE IF EXISTS `shipment_vl`;
CREATE TABLE IF NOT EXISTS `shipment_vl` (
  `VLShipmentID` varchar(45) NOT NULL,
  `ParticipantID` varchar(45) NOT NULL,
  `ShipmentDate` date DEFAULT NULL,
  `EvaluationStatus` varchar(10) DEFAULT NULL COMMENT 'Shipment Status					\\nUse this to flag - 					\\nABCDEFG					\\nA = 9 Not shipped 1 shipped					\\nB = 1 Sample Received 9 Not recieved					\\nC = 1 = Responded 9 = Not responded					\\nD = 1= Timeely response 2= Late					\\nE = 1 - via Web user 2 - via web Provider 3 - Scanning 					\\nF = 9 Not eligille for evaluation 1 eligible for evaluation					\\nG = 1 = Evaluated  9= not evaluated					\\n',
  `ShipmentTestReportDate` datetime DEFAULT NULL,
  `ShipmentScore` int(11) DEFAULT NULL,
  `LastDateResponse` date DEFAULT NULL,
  `Create_on` datetime DEFAULT NULL,
  `Created_by` varchar(45) DEFAULT NULL,
  `Update_on` datetime DEFAULT NULL,
  `Update_by` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`VLShipmentID`,`ParticipantID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Shipment for DTS Samples';

--
-- Dumping data for table `shipment_vl`
--

INSERT INTO `shipment_vl` (`VLShipmentID`, `ParticipantID`, `ShipmentDate`, `EvaluationStatus`, `ShipmentTestReportDate`, `ShipmentScore`, `LastDateResponse`, `Create_on`, `Created_by`, `Update_on`, `Update_by`) VALUES
('1', 'adhikari1', '2013-01-01', '11111110', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('1', 'amit1', '2013-01-01', '11900990', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('1', 'app01', '2013-01-01', '19000990', NULL, NULL, '2014-06-02', NULL, NULL, NULL, NULL),
('1', 'app02', '2013-01-01', '11123110', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('1', 'app03', '2013-01-01', '11900990', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('2', 'adhikari1', '2013-02-01', '11111110', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('2', 'adhikari2', '2013-02-01', '11900990', NULL, NULL, '2014-06-02', NULL, NULL, NULL, NULL),
('2', 'app01', '2013-02-01', '19000990', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('2', 'app02', '2013-02-01', '11123110', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL),
('3', 'amit1', '2013-03-01', '11900990', NULL, NULL, '2013-06-02', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `UserID` varchar(45) NOT NULL,
  `UserSystemID` varchar(45) NOT NULL,
  `Password` varchar(45) DEFAULT NULL,
  `UserFName` varchar(45) DEFAULT NULL,
  `UserLName` varchar(45) DEFAULT NULL,
  `UserPhoneNumber` varchar(45) DEFAULT NULL,
  `UserEmail` varchar(45) DEFAULT NULL,
  `UserFld1` varchar(45) DEFAULT NULL,
  `UserFld2` varchar(45) DEFAULT NULL,
  `UserFld3` varchar(45) DEFAULT NULL,
  `UserCellNumber` varchar(45) DEFAULT NULL,
  `UserSecondaryemail` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`UserID`,`UserSystemID`),
  UNIQUE KEY `USERID_UNIQUE` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='A PT user Table for Data entry or report printing';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `UserSystemID`, `Password`, `UserFName`, `UserLName`, `UserPhoneNumber`, `UserEmail`, `UserFld1`, `UserFld2`, `UserFld3`, `UserCellNumber`, `UserSecondaryemail`) VALUES
('adhikariamitabh@gmail.com', '2', '123', 'Adhi-F', 'Adhi-L', '1', 'adhikariamitabh@gmail.com', NULL, NULL, NULL, NULL, NULL),
('amit@muontech.com', '3', '123', 'Amit-F', 'Amit-L', '2', 'amit@muontech.com', NULL, NULL, NULL, NULL, NULL),
('app0@cdc.gov', '1', '123', 'app0-F', 'app0-L', '3', 'app0@cdc.gov', 'Florida', 'USA', NULL, NULL, NULL);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
