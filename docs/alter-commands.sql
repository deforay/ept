By Amit on 15 Sep 2013
ALTER TABLE  `users` DROP PRIMARY KEY;
ALTER TABLE  `users` ADD PRIMARY KEY (  `UserSystemID` );
ALTER TABLE  `users` CHANGE  `UserSystemID`  `UserSystemID` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE  `users` CHANGE  `UserID`  `UserID` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

-- By Amit on 17 Sep 2013

CREATE TABLE IF NOT EXISTS `global_config` (
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB;

INSERT INTO `global_config` (`name`, `value`) VALUES ('admin-name', 'ePT Admin'), ('admin_email', 'admin@ept.com');
ALTER TABLE  `users` ADD  `force_password_reset` INT NOT NULL DEFAULT  '0';


-- By Amit on 18 Sep 2013

-- ALTER TABLE  `schemelist` CHANGE  `schemeID`  `SchemeID` VARCHAR( 10 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
-- ALTER TABLE  `schemelist` ADD  `SchemeName` VARCHAR( 255 ) NOT NULL AFTER  `SchemeID`;
-- INSERT INTO `schemelist` (`SchemeID`, `SchemeName`, `ShipmentTable`, `ResponseTable`, `ReferanceResultTable`) VALUES ('DTS', 'Dried Tube Specimen', NULL, NULL, NULL), ('VL', 'Viral Load', NULL, NULL, NULL);

-- By Amit on 19 Sep 2013


CREATE TABLE IF NOT EXISTS `shipment_eid` (
  `eid_shipment_id` varchar(255) NOT NULL,
  `participant_id` varchar(255) NOT NULL,
  `shipment_date` date DEFAULT NULL,
  `evaluation_status` varchar(10) DEFAULT NULL COMMENT 'Shipment Status	\\nUse this to flag -  \\nABCDEFG \\nA = 9 Not shipped 1 shipped \\nB = 1 Sample Received 9 Not recieved \\nC = 1 = Responded 9 = Not responded \\nD = 1= Timeely response 2= Late \\nE = 1 - via Web user 2 - via web Provider 3 - Scanning  \\nF = 9 Not eligille for evaluation 1 eligible for evaluation \\nG = 1 = Evaluated  9= not evaluated \\n',
  `lastdate_response` date DEFAULT NULL,
  `shipment_test_date` date DEFAULT NULL,
  `shipment_receipt_date` date DEFAULT NULL,
  `shipment_test_report_date` datetime DEFAULT NULL,
  `participant_supervisor` varchar(255) DEFAULT NULL,
  `supervisor_approval` varchar(255) DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `number_of_samples` int(11) DEFAULT NULL, 
  `user_comment` varchar(500) DEFAULT NULL,
  `created_on_admin` datetime DEFAULT NULL,
  `updated_on_admin` datetime DEFAULT NULL,
  `updated_by_admin` varchar(255) DEFAULT NULL,
  `updated_on_user` datetime DEFAULT NULL,
  `updated_by_user` varchar(255) DEFAULT NULL,
  `created_by_admin` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`eid_shipment_id`,`participant_id`)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS `eid_detection_assay` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=7 ;

--
-- Dumping data for table `eid_detection_assay`
--

INSERT INTO `eid_detection_assay` (`id`, `name`) VALUES
(1, 'COBAS Ampliprep/Taqman HIV-1 Qual Test'),
(2, 'Roche - Amplicor HIV-1 Monitor Test'),
(3, 'QIAamp Viral Mini Kit (DNA or RNA)'),
(4, 'Biocentric - Generic'),
(5, 'Chelex'),
(6, 'In House');



CREATE TABLE IF NOT EXISTS `eid_extraction_assay` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=7 ;

--
-- Dumping data for table `eid_extraction_assay`
--

INSERT INTO `eid_extraction_assay` (`id`, `name`) VALUES
(1, 'COBAS Ampliprep/Taqman HIV-1 Qual Test'),
(2, 'Roche - Amplicor HIV-1 Monitor Test'),
(3, 'QIAamp Viral Mini Kit (DNA or RNA)'),
(4, 'Biocentric - Generic'),
(5, 'Chelex'),
(6, 'In House');



CREATE TABLE IF NOT EXISTS `reference_result_eid` (
  `eid_shipment_id` varchar(255) NOT NULL,
  `eid_sample_id` int(11) NOT NULL,
  `eid_reference_result` varchar(255) DEFAULT NULL,
  `eid_sample_label` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`eid_shipment_id`,`eid_sample_id`)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS `r_vl_assay` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=6 ;

--
-- Dumping data for table `r_vl_assay`
--

INSERT INTO `r_vl_assay` (`id`, `name`) VALUES
(1, 'Abbott - RealTime '),
(2, 'Roche - COBAS Ampliprep/TaqMan'),
(3, 'Biocentric - Generic HIV Charge Virale'),
(4, 'Biomerieux - NucliSENS'),
(5, 'Roche - Amplicor');



-- By Amit on Sep 20 2013

CREATE  TABLE `r_control` (
  `control_id` INT NOT NULL AUTO_INCREMENT ,
  `control_name` VARCHAR(255) NULL ,
  `for_scheme` VARCHAR(255) NULL ,
  `is_active` VARCHAR(45) NULL ,
  PRIMARY KEY (`control_id`) )
DEFAULT CHARACTER SET = utf8;


INSERT INTO `r_control` (`control_id`, `control_name`, `for_scheme`, `is_active`) VALUES
(1, 'Kit Negative Control', 'eid', 'active'),
(2, 'Kit Positive Control', 'eid', 'active'),
(3, 'PT Provider Negative Control', 'eid', 'active'),
(4, 'PT Provider Positive Control', 'eid', 'active'),
(5, 'In-House Negative Control', 'eid', 'active'),
(6, 'In-House Positive Control	', 'eid', 'active'),
(7, 'Negative Control', 'vl', 'active'),
(8, 'Low Positive Control', 'vl', 'active'),
(9, 'High Positive Control', 'vl', 'active');


ALTER TABLE `reference_result_eid` ADD COLUMN `hiv_ct_od` VARCHAR(45) NULL  AFTER `reference_result` , ADD COLUMN `ic_qs` VARCHAR(45) NULL  AFTER `hiv_ct_od` , CHANGE COLUMN `eid_sample_label` `eid_sample_label` VARCHAR(255) NULL DEFAULT NULL  AFTER `eid_sample_id` , CHANGE COLUMN `eid_reference_result` `reference_result` VARCHAR(255) NULL DEFAULT NULL ;

ALTER TABLE `shipment_eid` ADD COLUMN `sample_rehydration_date` DATE NULL  AFTER `created_by_admin` ;

CREATE TABLE IF NOT EXISTS `response_result_eid` (
  `eid_shipment_id` varchar(45) NOT NULL,
  `participant_id` varchar(45) NOT NULL,
  `eid_sample_id` varchar(45) NOT NULL,
  `reported_result` varchar(45) DEFAULT NULL,
  `hiv_ct_od` varchar(45) DEFAULT NULL,
  `ic_qs` varchar(45) DEFAULT NULL,
  `calculated_score` varchar(45) DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_id`,`participant_id`,`eid_sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- By Amit on 23 Sep 2013

ALTER TABLE  `shipment_eid` ADD  `extraction_assay` INT NULL AFTER  `shipment_test_report_date` ,
ADD  `detection_assay` INT NULL AFTER  `extraction_assay`;


ALTER TABLE  `reference_result_eid` CHANGE  `hiv_ct_od`  `reference_hiv_ct_od` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL ,
CHANGE  `ic_qs`  `reference_ic_qs` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;


ALTER TABLE `shipment_vl` CHANGE `VLShipmentID` `vl_shipment_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ParticipantID` `participant_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ShipmentDate` `shipment_date` DATE NULL DEFAULT NULL, CHANGE `EvaluationStatus` `evaluation_status` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'Shipment Status					\\nUse this to flag - 					\\nABCDEFG					\\nA = 9 Not shipped 1 shipped					\\nB = 1 Sample Received 9 Not recieved					\\nC = 1 = Responded 9 = Not responded					\\nD = 1= Timeely response 2= Late					\\nE = 1 - via Web user 2 - via web Provider 3 - Scanning 					\\nF = 9 Not eligille for evaluation 1 eligible for evaluation					\\nG = 1 = Evaluated  9= not evaluated					\\n', CHANGE `ShipmentTestReportDate` `shipment_test_report_date` DATETIME NULL DEFAULT NULL, CHANGE `ShipmentScore` `shipment_score` INT(11) NULL DEFAULT NULL, CHANGE `LastDateResponse` `lastdate_response` DATE NULL DEFAULT NULL, CHANGE `Create_on` `created_on` DATETIME NULL DEFAULT NULL, CHANGE `Created_by` `created_by` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Update_on` `updated_on` DATETIME NULL DEFAULT NULL, CHANGE `Update_by` `updated_by` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

DELIMITER $$

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
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
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
left join participant as b on a.ParticipantID = b.ParticipantSystemID where year(a.ShipmentDate)  + 5 > year(CURDATE())


union

Select year(a.shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
a.participant_id,
a.shipment_date, 
-- a.shipment_test_report_date as RESPONSEDATE,
DATE_FORMAT(a.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
a.participant_id as PARTICIPANT_ID,
a.evaluation_status as EVALUATIONSTATUS, 
 a.vl_shipment_id as SHIPID,
	CASE  substr(a.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.evaluation_status,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment_vl  as a 
left join participant as b on a.participant_id = b.ParticipantSystemID where year(a.shipment_date)  + 5 > year(CURDATE())

union

Select year(a.shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
a.participant_id,
a.shipment_date, 
-- a.shipment_test_report_date as RESPONSEDATE,
DATE_FORMAT(a.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
a.participant_id as PARTICIPANT_ID,
a.evaluation_status as EVALUATIONSTATUS, 
 a.eid_shipment_id as SHIPID,
	CASE  substr(a.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.evaluation_status,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment_eid  as a 
left join participant as b on a.participant_id = b.ParticipantSystemID where year(a.shipment_date)  + 5 > year(CURDATE())

order by SHIP_YEAR, ParticipantID ;
-- LIMIT valFrom, valTo;
END$$


DELIMITER $$

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

Select year(shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
count(substr(evaluation_status,1,1)) as TOTLASHIPPED,  
count(
	CASE  substr(evaluation_status,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
count(
	CASE  substr(evaluation_status,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(evaluation_status,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment_vl as a 
where year(shipment_date)  + 5 > year(CURDATE())


union

Select year(shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
count(substr(evaluation_status,1,1)) as TOTLASHIPPED,  
count(
	CASE  substr(evaluation_status,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
count(
	CASE  substr(evaluation_status,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(evaluation_status,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment_eid as a 
where year(shipment_date)  + 5 > year(CURDATE())
group by SHIP_YEAR ;

END$$



DELIMITER $$

DROP PROCEDURE IF EXISTS `SHIPMENT_DEFAULTED` $$
CREATE PROCEDURE `SHIPMENT_DEFAULTED`()
BEGIN
Select year(a.ShipmentDate) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
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
left join participant as b on a.ParticipantID = b.ParticipantSystemID where year(a.ShipmentDate)  + 5 > year(CURDATE()) 
and a.LASTDATERESPONSE < CURDATE() and  substr(a.EvaluationStatus,3,1) <> '1'

union

Select year(a.shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
a.participant_id,
a.shipment_date, 
a.shipment_test_report_date as RESPONSEDATE,
a.lastdate_response, 
a.participant_id as PARTICIPANT_ID,
a.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(a.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(a.evaluation_status,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment_vl  as a 
left join participant as b on a.participant_id = b.ParticipantSystemID where year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response < CURDATE() and  substr(a.evaluation_status,3,1) <> '1'
union

Select year(a.shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
a.participant_id,
a.shipment_date, 
a.shipment_test_report_date as RESPONSEDATE,
a.lastdate_response, 
a.participant_id as PARTICIPANT_ID,
a.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(a.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(a.evaluation_status,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment_eid  as a 
left join participant as b on a.participant_id = b.ParticipantSystemID where year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response < CURDATE() and  substr(a.evaluation_status,3,1) <> '1'

order by SHIP_YEAR, ParticipantID ;
END $$



CREATE TABLE IF NOT EXISTS `reference_result_vl` (
  `vl_shipment_id` varchar(255) NOT NULL,
  `vl_sample_id` int(11) NOT NULL,
  `vl_sample_label` varchar(255) DEFAULT NULL,
  `reference_result` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`vl_shipment_id`,`vl_sample_id`)
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS `response_result_vl` (
  `vl_shipment_id` varchar(45) NOT NULL,
  `participant_id` varchar(45) NOT NULL,
  `vl_sample_id` varchar(45) NOT NULL,
  `reported_viral_load` varchar(255) DEFAULT NULL,
  `calculated_score` varchar(45) DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`vl_shipment_id`,`participant_id`,`vl_sample_id`)
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS `shipment_vl` (
  `vl_shipment_id` varchar(255) NOT NULL,
  `participant_id` varchar(255) NOT NULL,
  `shipment_date` date DEFAULT NULL,
  `evaluation_status` varchar(10) DEFAULT NULL COMMENT 'Shipment Status	\\nUse this to flag -  \\nABCDEFG \\nA = 9 Not shipped 1 shipped \\nB = 1 Sample Received 9 Not recieved \\nC = 1 = Responded 9 = Not responded \\nD = 1= Timeely response 2= Late \\nE = 1 - via Web user 2 - via web Provider 3 - Scanning  \\nF = 9 Not eligille for evaluation 1 eligible for evaluation \\nG = 1 = Evaluated  9= not evaluated \\n',
  `lastdate_response` date DEFAULT NULL,
  `shipment_test_date` date DEFAULT NULL,
  `shipment_receipt_date` date DEFAULT NULL,
  `shipment_test_report_date` datetime DEFAULT NULL,
  `vl_assay` int(11) DEFAULT NULL,
  `assay_lot_number` varchar(255) DEFAULT NULL,
  `assay_expiration_date` date DEFAULT NULL,
  `specimen_volume` varchar(255) DEFAULT NULL,
  `sample_rehydration_date` date DEFAULT NULL,
  `participant_supervisor` varchar(255) DEFAULT NULL,
  `supervisor_approval` varchar(255) DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `number_of_samples` int(11) DEFAULT NULL,
  `user_comment` varchar(500) DEFAULT NULL,
  `created_on_admin` datetime DEFAULT NULL,
  `updated_on_admin` datetime DEFAULT NULL,
  `updated_by_admin` varchar(255) DEFAULT NULL,
  `updated_on_user` datetime DEFAULT NULL,
  `updated_by_user` varchar(255) DEFAULT NULL,
  `created_by_admin` varchar(255) DEFAULT NULL,  
  PRIMARY KEY (`vl_shipment_id`,`participant_id`)
) ENGINE=InnoDB;


-- By Amit on Sep 25 2013

CREATE TABLE IF NOT EXISTS `admin` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `primary_email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `secondary_email` varchar(255) NOT NULL,
  `phone` varchar(255) NOT NULL,
  `force_password_reset` int(11) NOT NULL,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


-- By Amit on Sep 26 2013

ALTER TABLE  `users` ADD  `status` VARCHAR( 255 ) NOT NULL;
ALTER TABLE  `users` DROP PRIMARY KEY , ADD PRIMARY KEY (  `UserSystemID` );
ALTER TABLE  `users` CHANGE  `UserSystemID`  `UserSystemID` INT NOT NULL;
ALTER TABLE  `users` CHANGE  `UserSystemID`  `UserSystemID` INT( 11 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `users` CHANGE  `force_password_reset`  `force_password_reset` INT( 1 ) NOT NULL DEFAULT  '0';
ALTER TABLE  `users` CHANGE  `status`  `status` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT  'inactive';


UPDATE  `participant` SET  `ParticipantSystemID` =  '1' WHERE  `participant`.`ParticipantID` =  'adhikari1';
UPDATE  `participant` SET  `ParticipantSystemID` =  '2' WHERE  `participant`.`ParticipantID` =  'adhikari2';
UPDATE  `participant` SET  `ParticipantSystemID` =  '3' WHERE  `participant`.`ParticipantID` =  'amit1';
UPDATE  `participant` SET  `ParticipantSystemID` =  '4' WHERE  `participant`.`ParticipantID` =  'app012';
UPDATE  `participant` SET  `ParticipantSystemID` =  '5' WHERE  `participant`.`ParticipantID` =  'app02';
UPDATE  `participant` SET  `ParticipantSystemID` =  '6' WHERE  `participant`.`ParticipantID` =  'app03';
ALTER TABLE participant DROP PRIMARY KEY;
ALTER TABLE  `participant` ADD PRIMARY KEY (  `ParticipantSystemID` ) ;
ALTER TABLE  `participant` CHANGE  `ParticipantSystemID`  `ParticipantSystemID` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE  `participant` ADD UNIQUE (`ParticipantID`);


-- By Amit on Sep 30 2013

RENAME TABLE  `vl_assay` TO  `r_vl_assay` ;



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
	ParticipantID = PartId and
	DTSSampleID = SampID;

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


-- By Amit on Oct 01 2013

CREATE TABLE IF NOT EXISTS `scheme_list` (
  `scheme_id` varchar(10) NOT NULL,
  `scheme_name` varchar(255) NOT NULL,
  `shipment_table` varchar(45) DEFAULT NULL,
  `response_table` varchar(45) DEFAULT NULL,
  `reference_result_table` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`scheme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `enrollments` (
  `scheme_id` varchar(255) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `enrolled_on` date NOT NULL,
  `enrollment_ended_on` date NOT NULL,
  `status` varchar(255) NOT NULL,
  PRIMARY KEY (`scheme_id`,`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- By Amit on Oct 02 2013

ALTER TABLE `shipment_dts` CHANGE `DTSShipmentID` `dts_shipment_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ParticipantID` `participant_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ShipmentDate` `shipment_date` DATE NULL DEFAULT NULL, CHANGE `EvaluationStatus` `evaluation_status` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'Shipment Status					\\nUse this to flag - 					\\nABCDEFG					\\nA = 9 Not shipped 1 shipped					\\nB = 1 Sample Received 9 Not recieved					\\nC = 1 = Responded 9 = Not responded					\\nD = 1= Timeely response 2= Late					\\nE = 1 - via Web user 2 - via web Provider 3 - Scanning 					\\nF = 9 Not eligille for evaluation 1 eligible for evaluation					\\nG = 1 = Evaluated  9= not evaluated					\\n', CHANGE `ShipmentScore` `shipment_score` INT(11) NULL DEFAULT NULL, CHANGE `LastDateResponse` `lastdate_response` DATE NULL DEFAULT NULL, CHANGE `ShipmentTestDate` `shipment_test_date` DATE NULL DEFAULT NULL, CHANGE `ShipmentReceiptDate` `shipment_receipt_date` DATE NULL DEFAULT NULL, CHANGE `ShipmentTestReportDate` `shipment_test_report_date` DATETIME NULL DEFAULT NULL, CHANGE `ParticipantSupervisor` `participant_supervisor` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `supervisorApproval` `supervisor_approval` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ReviewDate` `review_date` DATE NULL DEFAULT NULL, CHANGE `SampleRehydrationDate` `sample_rehydration_date` DATE NULL DEFAULT NULL, CHANGE `NumberOfSample` `number_of_sample` INT(11) NULL DEFAULT NULL, CHANGE `UserComment` `user_comment` VARCHAR(90) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Create_on_admin` `created_on_admin` DATETIME NULL DEFAULT NULL, CHANGE `Update_on_admin` `updated_on_admin` DATETIME NULL DEFAULT NULL, CHANGE `Update_by_admin` `updated_by_admin` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Update_on_user` `updated_on_user` DATETIME NULL DEFAULT NULL, CHANGE `Updated_by_user` `updated_by_user` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `created_by_admin` `created_by_admin` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `response_result_dts` CHANGE `ShipmentID` `shipment_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ParticipantID` `participant_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `DTSSampleID` `dts_sample_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `TestKitName1` `TestKitName1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LotNo1` `LotNo1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ExpDate1` `ExpDate1` DATE NULL DEFAULT NULL, CHANGE `TestResult1` `TestResult1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `TestKitName2` `TestKitName2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LotNo2` `LotNo2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ExpDate2` `ExpDate2` DATE NULL DEFAULT NULL, CHANGE `TestResult2` `TestResult2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `TestKitName3` `TestKitName3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LotNo3` `LotNo3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ExpDate3` `ExpDate3` DATE NULL DEFAULT NULL, CHANGE `TestResult3` `TestResult3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ReportedResult` `ReportedResult` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `CalculatedScore` `CalculatedScore` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Created_by` `Created_by` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Created_on` `Created_on` DATETIME NULL DEFAULT NULL, CHANGE `Updated_by` `Updated_by` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Updated_on` `Updated_on` DATETIME NULL DEFAULT NULL;

RENAME TABLE  `eid_detection_assay` TO  `r_eid_detection_assay` ;
RENAME TABLE  `eid_extraction_assay` TO  `r_eid_extraction_assay` ;

-- By Amit on Oct 04

-- Loads of changes done .. cannot put them here :)


-- By Amit on Oct 07 2013

ALTER TABLE  `admin` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'inactive';

-- By Amit on Oct 08 2013

CREATE TABLE IF NOT EXISTS `distributions` (
  `distribution_id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_code` varchar(255) NOT NULL,
  `distribution_date` date NOT NULL,
  `status` varchar(255) NOT NULL,
  PRIMARY KEY (`distribution_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

ALTER TABLE  `shipment_dts` ADD  `distribution_id` INT NOT NULL AFTER  `shipment_date`;
ALTER TABLE  `shipment_eid` ADD  `distribution_id` INT NOT NULL AFTER  `shipment_date`;
ALTER TABLE  `shipment_vl` ADD  `distribution_id` INT NOT NULL AFTER  `shipment_date`;


-- By Amit on Oct 09 2013

CREATE TABLE IF NOT EXISTS `shipment` (
  `shipment_id` varchar(255) NOT NULL,
  `shipment_code` varchar(255) NOT NULL,
  `participant_id` varchar(255) NOT NULL,
  `shipment_date` date DEFAULT NULL,
  `distribution_id` int(11) NOT NULL,
  `evaluation_status` varchar(10) DEFAULT NULL COMMENT 'Shipment Status					\\nUse this to flag - 					\\nABCDEFG					\\nA = 9 Not shipped 1 shipped					\\nB = 1 Sample Received 9 Not recieved					\\nC = 1 = Responded 9 = Not responded					\\nD = 1= Timeely response 2= Late					\\nE = 1 - via Web user 2 - via web Provider 3 - Scanning 					\\nF = 9 Not eligille for evaluation 1 eligible for evaluation					\\nG = 1 = Evaluated  9= not evaluated					\\n',
  `shipment_score` int(11) DEFAULT NULL,
  `lastdate_response` date DEFAULT NULL,
  `shipment_test_date` date DEFAULT NULL,
  `shipment_receipt_date` date DEFAULT NULL,
  `shipment_test_report_date` datetime DEFAULT NULL,
  `participant_supervisor` varchar(255) DEFAULT NULL,
  `supervisor_approval` varchar(255) DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `sample_rehydration_date` date DEFAULT NULL,
  `number_of_samples` int(11) DEFAULT NULL,
  `user_comment` varchar(1000) DEFAULT NULL,
  `created_on_admin` datetime DEFAULT NULL,
  `updated_on_admin` datetime DEFAULT NULL,
  `updated_by_admin` varchar(255) DEFAULT NULL,
  `updated_on_user` datetime DEFAULT NULL,
  `updated_by_user` varchar(255) DEFAULT NULL,
  `created_by_admin` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`shipment_id`,`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- by Amit on Oct 10 2013

ALTER TABLE  `shipment_dts` CHANGE  `number_of_sample`  `number_of_samples` INT( 11 ) NULL DEFAULT NULL;
ALTER TABLE  `shipment_eid` ADD  `shipment_code` VARCHAR( 255 ) NOT NULL AFTER  `eid_shipment_id`;
ALTER TABLE  `shipment_dts` ADD  `shipment_code` VARCHAR( 255 ) NOT NULL AFTER  `dts_shipment_id`;
ALTER TABLE  `shipment_vl` ADD  `shipment_code` VARCHAR( 255 ) NOT NULL AFTER  `vl_shipment_id`;
ALTER TABLE  `r_possibleresult` CHANGE  `ID`  `ID` INT( 11 ) NOT NULL AUTO_INCREMENT;
INSERT INTO `r_possibleresult` (`ID`, `SchemeCode`, `SchemeSubgroup`, `Response`) VALUES ('', 'EID', 'EID_FINAL', 'Positive (HIV Detected)'), ('', 'EID', 'EID_FINAL', 'Negative (HIV Not Detected)');
INSERT INTO `r_possibleresult` (`ID`, `SchemeCode`, `SchemeSubgroup`, `Response`) VALUES (NULL, 'EID', 'EID_FINAL', 'Equivocal');


-- by Amit on Oct 15 2013

ALTER TABLE  `shipment_eid` CHANGE  `eid_shipment_id`  `eid_shipment_id` INT NOT NULL;
ALTER TABLE  `shipment_vl` CHANGE  `vl_shipment_id`  `vl_shipment_id` INT NOT NULL;
ALTER TABLE  `shipment_dts` CHANGE  `dts_shipment_id`  `dts_shipment_id` INT NOT NULL;


-- by Amit on Oct 22 2013

ALTER TABLE  `participant` ADD  `email` VARCHAR( 255 ) NOT NULL AFTER  `phone`;
ALTER TABLE  `reference_result_vl` CHANGE  `vl_sample_label`  `sample_label` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;




-- by Amit on Oct 28 2013
DELIMITER $$
DROP PROCEDURE IF EXISTS `SHIPMENT_OVERVIEW` $$
CREATE PROCEDURE `SHIPMENT_OVERVIEW`(IN uId varchar(255) )
BEGIN

Select year(shipment_date) as SHIP_YEAR,   
'DTS' AS SCHEME,
count(substr(b.evaluation_status,1,1)) as TOTALSHIPMEN,  
count(
	CASE  substr(b.evaluation_status,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
  
count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment  as a , shipment_participant_map as b, participant_manager_map pmm
where (year(shipment_date)  + 5 > year(CURDATE())) AND scheme_type='dts'
and a.shipment_id = b.shipment_id
and a.status != 'pending'
and pmm.participant_id = b.participant_id
and pmm.dm_id = uId
group by SHIP_YEAR 

union


Select year(shipment_date) as SHIP_YEAR,   
'DBS' AS SCHEME,
count(substr(b.evaluation_status,1,1)) as TOTALSHIPMEN,  
count(
	CASE  substr(b.evaluation_status,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
  
count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment  as a , shipment_participant_map as b, participant_manager_map pmm
where (year(shipment_date)  + 5 > year(CURDATE())) AND scheme_type='dbs'
and a.shipment_id = b.shipment_id
and a.status != 'pending'
and pmm.participant_id = b.participant_id
and pmm.dm_id = uId
group by SHIP_YEAR 

union

Select year(shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
count(substr(b.evaluation_status,1,1)) as TOTLASHIPPED,  
count(
	CASE  substr(b.evaluation_status,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment    as a  , shipment_participant_map as b, participant_manager_map pmm
where (year(shipment_date)  + 5 > year(CURDATE())) AND scheme_type='vl'
and a.shipment_id = b.shipment_id
and a.status != 'pending'
and pmm.participant_id = b.participant_id
and pmm.dm_id = uId
group by SHIP_YEAR 

union

Select year(shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
count(substr(b.evaluation_status,1,1)) as TOTLASHIPPED,  
count(
	CASE  substr(b.evaluation_status,3,1)
	WHEN 1 THEN   'T'
	END 
	) as 'ONTIME' ,
count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 1 THEN   'R'
	END 
	) as 'RESPOND' ,

count(
	CASE  substr(b.evaluation_status,2,1)
	WHEN 9 THEN   'N'
	END
	)  as 'NORESPONSE' 
from shipment as a , shipment_participant_map as b, participant_manager_map pmm
where (year(shipment_date)  + 5 > year(CURDATE())) AND scheme_type='eid'
and a.shipment_id = b.shipment_id
and a.status != 'pending'
and pmm.participant_id = b.participant_id
and pmm.dm_id = uId
group by SHIP_YEAR;

END $$


DELIMITER $$
DROP PROCEDURE IF EXISTS `SHIPMENT_CURRENT` $$

CREATE PROCEDURE `SHIPMENT_CURRENT`(IN uId varchar(255) )
BEGIN
Select year(a.shipment_date) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
spm.participant_id,
a.shipment_date,
a.shipment_code,
DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
a.shipment_id as SHIPID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE'  ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response >= CURDATE()
and a.scheme_type = 'dts'
and a.status != 'pending'
and pmm.dm_id = uId

union

Select year(a.shipment_date) as SHIP_YEAR,   
'DBS' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
spm.participant_id,
a.shipment_date,
a.shipment_code,
DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
a.shipment_id as SHIPID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE'  ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response >= CURDATE()
and a.scheme_type = 'dbs'
and a.status != 'pending'
and pmm.dm_id = uId

union

Select year(a.shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
spm.participant_id,
a.shipment_date,
a.shipment_code,
DATE_FORMAT(spm.shipment_test_report_date, '%Y-%m-%d') as RESPONSEDATE,
a.lastdate_response, 
a.shipment_id as SHIPID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response >= CURDATE()
and a.scheme_type = 'vl'
and a.status != 'pending'
and pmm.dm_id = uId
union

Select year(a.shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
spm.participant_id,
a.shipment_date,
a.shipment_code,
DATE_FORMAT(spm.shipment_test_report_date, '%Y-%m-%d') as RESPONSEDATE,
a.lastdate_response, 
a.shipment_id as SHIPID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response >= CURDATE() 
and a.scheme_type = 'eid'
and a.status != 'pending'
and pmm.dm_id = uId

order by SHIP_YEAR, participant_id ;

END $$


DELIMITER ;;

DROP PROCEDURE IF EXISTS `SHIPMENT_DEFAULTED` ;;
CREATE PROCEDURE `SHIPMENT_DEFAULTED`(IN uId varchar(255) )
BEGIN
Select year(a.shipment_date) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code,
spm.shipment_test_report_date as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response < CURDATE() and  substr(spm.evaluation_status,3,1) <> '1'
and a.scheme_type = 'dts'
and a.status != 'pending'
and pmm.dm_id = uId
union
Select year(a.shipment_date) as SHIP_YEAR,   
'DBS' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code,
spm.shipment_test_report_date as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response < CURDATE() and  substr(spm.evaluation_status,3,1) <> '1'
and a.scheme_type = 'dbs'
and a.status != 'pending'
and pmm.dm_id = uId
union
Select year(a.shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code,
spm.shipment_test_report_date as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response < CURDATE() and  substr(spm.evaluation_status,3,1) <> '1'
and a.scheme_type = 'vl'
and a.status != 'pending'
and pmm.dm_id = uId
union

Select year(a.shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code,
spm.shipment_test_report_date as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'ACTION' ,


	CASE  substr(spm.evaluation_status,3,1)
	WHEN '1' THEN   'On Time'
	WHEN '2' THEN   'Late'
	WHEN '0' THEN   'No Response'
	END
	as 'STATUS' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response < CURDATE() and  substr(spm.evaluation_status,3,1) <> '1'
and a.scheme_type = 'eid'
and a.status != 'pending'
and pmm.dm_id = uId
order by SHIP_YEAR, PARTICIPANT_ID ;

END ;;

DELIMITER $$

DROP PROCEDURE IF EXISTS `SHIPMENT_ALL` $$
CREATE PROCEDURE `SHIPMENT_ALL`( 
   IN paramFrom INT,
   IN paramTo INT,
   IN uId varchar(255)
   )
BEGIN
    DECLARE valFrom INT;
	DECLARE valTo   INT;

    SET valFrom = paramFrom;
    SET valTo = paramTo;

Select year(a.shipment_date) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date, 
a.shipment_code, 
-- spm.shipment_test_report_date as RESPONSEDATE,
DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 
 a.shipment_id as SHIPID,

	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE())
and scheme_type = 'dts'
and a.status != 'pending'
and pmm.dm_id = uId
union

Select year(a.shipment_date) as SHIP_YEAR,   
'DBS' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code, 
-- spm.shipment_test_report_date as RESPONSEDATE,
DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 
 a.shipment_id as SHIPID,

	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE())
and scheme_type = 'dbs'
and a.status != 'pending'
and pmm.dm_id = uId
union
Select year(a.shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code, 
-- spm.shipment_test_report_date as RESPONSEDATE,
DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 
 a.shipment_id as SHIPID,
	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE())
and scheme_type = 'vl'
and a.status != 'pending'
and pmm.dm_id = uId
union

Select year(a.shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
b.first_name as FNAME,b.last_name as LNAME,
a.shipment_date,
a.shipment_code, 
-- spm.shipment_test_report_date as RESPONSEDATE,
DATE_FORMAT(spm.shipment_test_report_date,'%Y-%m-%d')  as RESPONSEDATE,
a.lastdate_response, 
spm.participant_id as PARTICIPANT_ID,
spm.evaluation_status as EVALUATIONSTATUS, 
 a.shipment_id as SHIPID,
	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(spm.evaluation_status,2,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORT' 
from shipment  as a , shipment_participant_map spm, participant as b, participant_manager_map pmm
where spm.participant_id = b.participant_id
and a.shipment_id = spm.shipment_id
and year(a.shipment_date)  + 5 > year(CURDATE())
and scheme_type = 'eid'
and a.status = 'shipped'
and pmm.dm_id = uId
order by SHIP_YEAR, participant_id ;
-- LIMIT valFrom, valTo;
END $$

Delimiter ;


 -- by Amit on Nov 4 2013
 
 ALTER TABLE  `response_result_eid` CHANGE  `eid_sample_id`  `sample_id` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;
 ALTER TABLE  `response_result_vl` CHANGE  `vl_sample_id`  `sample_id` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;
 ALTER TABLE  `response_result_dts` CHANGE  `dts_sample_id`  `sample_id` INT( 11 ) NOT NULL;
 
 
 -- by Amit on Nov 7 2013
 
 ALTER TABLE  `participant` ADD  `lab_name` VARCHAR( 255 ) NULL AFTER  `data_manager`;
 ALTER TABLE `participant`  ADD `institute_name` VARCHAR(255) NULL AFTER `lab_name`;
 ALTER TABLE `participant`
 ADD `department_name` VARCHAR(255) NULL AFTER `institute_name`,
 ADD `address` VARCHAR(500) NULL AFTER `department_name`,
 ADD `city` VARCHAR(255) NULL AFTER `address`,
 ADD `state` VARCHAR(255) NULL AFTER `city`,
 ADD `country` VARCHAR(255) NULL AFTER `state`,
 ADD `zip` VARCHAR(255) NULL AFTER `country`,
 ADD `long` VARCHAR(255) NULL AFTER `zip`,
 ADD `lat` VARCHAR(255) NULL AFTER `long`;
 
 ALTER TABLE `response_result_dts` CHANGE `shipment_map_id` `shipment_map_id` INT(11) NOT NULL,
 CHANGE `sample_id` `sample_id` INT(11) NOT NULL,
 CHANGE `TestKitName1` `test_kit_name_1` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `LotNo1` `lot_no_1` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `ExpDate1` `exp_date_1` DATE NULL DEFAULT NULL,
 CHANGE `TestResult1` `test_result_1` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `TestKitName2` `test_kit_name_2` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `LotNo2` `lot_no_2` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `ExpDate2` `exp_date_2` DATE NULL DEFAULT NULL,
 CHANGE `TestResult2` `test_result_2` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `TestKitName3` `test_kit_name_3` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `LotNo3` `lot_no_3` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `ExpDate3` `exp_date_3` DATE NULL DEFAULT NULL,
 CHANGE `TestResult3` `test_result_3` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `ReportedResult` `reported_result` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `CalculatedScore` `calculated_score` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `Created_by` `created_by` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `Created_on` `created_on` DATETIME NULL DEFAULT NULL,
 CHANGE `Updated_by` `updated_by` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
 CHANGE `Updated_on` `updated_on` DATETIME NULL DEFAULT NULL;
 
 -- by Amit on 13 Nov 2013
 
 ALTER TABLE  `shipment` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'pending';
 ALTER TABLE  `shipment_participant_map` ADD UNIQUE (
`shipment_id` ,
`participant_id`
);

 
 -- by Amit on 19 Nov 2013
  alter table participant drop foreign key participant_ibfk_1;
  ALTER TABLE `participant` DROP `data_manager`;
  
  -- by Amit Nov 24 2013
  
CREATE TABLE IF NOT EXISTS `r_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `result_name` varchar(255) NOT NULL,
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

INSERT INTO  `r_results` (`result_id` ,`result_name`) VALUES ('1',  'Pass'), ('2',  'Fail');


-- by Amit Nov 27 2013

ALTER TABLE  `reference_result_dts` ADD  `mandatory` INT NOT NULL DEFAULT  '0',
ADD  `sample_score` INT NOT NULL DEFAULT  '1';
ALTER TABLE  `reference_result_vl` ADD  `mandatory` INT NOT NULL DEFAULT  '0',
ADD  `sample_score` INT NOT NULL DEFAULT  '1';
ALTER TABLE  `reference_result_eid` ADD  `mandatory` INT NOT NULL DEFAULT  '0',
ADD  `sample_score` INT NOT NULL DEFAULT  '1';

ALTER TABLE  `shipment_participant_map` ADD  `final_result` VARCHAR( 255 ) NOT NULL AFTER  `review_date`;
ALTER TABLE  `shipment_participant_map` ADD  `failure_reason` TEXT NOT NULL AFTER  `final_result`;


-- by Amit Dec 03 2013

CREATE TABLE IF NOT EXISTS `r_evaluation_comments` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme` varchar(255) NOT NULL,
  `comment` text NOT NULL,
  PRIMARY KEY (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE  `shipment_participant_map` ADD  `evaluation_comment` INT NOT NULL AFTER  `failure_reason` ,
ADD  `optional_eval_comment` TEXT NOT NULL AFTER  `evaluation_comment`;

ALTER TABLE  `shipment` ADD  `shipment_comment` TEXT NOT NULL AFTER  `number_of_samples`;

ALTER TABLE  `shipment` ADD  `max_score` INT NOT NULL AFTER  `number_of_samples`;



-- by Amit Dec 06 2013
ALTER TABLE  `reference_result_dts` ADD  `control` INT NOT NULL AFTER  `reference_result`;
ALTER TABLE  `reference_result_eid` ADD  `control` INT NOT NULL AFTER  `reference_result`;
ALTER TABLE  `reference_result_vl` ADD  `control` INT NOT NULL AFTER  `reference_result`;


-- by Amit Dec 09 2013

ALTER TABLE  `shipment_participant_map` CHANGE  `final_result`  `final_result` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

-- by Amit Dec 16 2013
ALTER TABLE  `shipment_participant_map` CHANGE  `final_result`  `final_result` INT NULL DEFAULT NULL



-- by Amit Dec 17 2013
CREATE TABLE IF NOT EXISTS `r_network_tiers` ( `network_id` int(11) NOT NULL AUTO_INCREMENT, `network_name` varchar(255) DEFAULT NULL, PRIMARY KEY (`network_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE  `participant` ADD  `network_tier` INT NOT NULL AFTER  `affiliation`;
ALTER TABLE  `data_manager` ADD  `institute` VARCHAR( 500 ) NULL DEFAULT NULL AFTER  `password`;

-- by Amit Dec 30 2013
ALTER TABLE  `scheme_list` ADD  `status` VARCHAR( 255 ) NULL DEFAULT NULL;

--by Ilahir JAN 22 2014

CREATE TABLE IF NOT EXISTS `reference_dbs_wb` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `wb` int(11) NOT NULL,
  `lot` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `160` int(11) DEFAULT NULL,
  `120` int(11) DEFAULT NULL,
  `66` int(11) DEFAULT NULL,
  `55` int(11) DEFAULT NULL,
  `51` int(11) DEFAULT NULL,
  `41` int(11) DEFAULT NULL,
  `31` int(11) DEFAULT NULL,
  `24` int(11) DEFAULT NULL,
  `17` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;


CREATE TABLE IF NOT EXISTS `reference_dts_eia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `eia` int(11) NOT NULL,
  `lot` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `od` varchar(255) DEFAULT NULL,
  `cutoff` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;


CREATE TABLE IF NOT EXISTS `reference_dts_wb` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `wb` int(11) NOT NULL,
  `lot` varchar(255) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `160` int(11) DEFAULT NULL,
  `120` int(11) DEFAULT NULL,
  `66` int(11) DEFAULT NULL,
  `55` int(11) DEFAULT NULL,
  `51` int(11) DEFAULT NULL,
  `41` int(11) DEFAULT NULL,
  `31` int(11) DEFAULT NULL,
  `24` int(11) DEFAULT NULL,
  `17` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;

--ilahir 8-Feb-2014

ALTER TABLE  `shipment_participant_map` ADD  `report_generated` VARCHAR( 100 ) NULL DEFAULT NULL;

--ilahir 12-Feb-2014

CREATE TABLE IF NOT EXISTS `report_config` (
  `name` varchar(255) DEFAULT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--ilahir 24-Feb-2014

INSERT INTO `report_config` (`name`, `value`) VALUES
('report-header', '<div style=""><div style="text-align: center;"><b>DEPARTMENT OF HEALTH AND HUMAN SERVICES</b></div><div style="text-align: center;">International Laboratory Branch</div><div style="text-align: center;">Division of Global HIV/AIDS, CDC-Atlanta</div></div>\r\n\r\n'),
('logo', '');

--Guna 25-Mar-2014
ALTER TABLE  `shipment_participant_map` CHANGE  `participant_supervisor`  `participant_supervisor` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

--Ilahir 27-Mar-2014
ALTER TABLE  `shipment_participant_map` ADD  `created_on_user` DATETIME NULL DEFAULT NULL AFTER  `created_by_admin`;

--Ilahir 07-Apr-2014

INSERT INTO `global_config` (`name`, `value`) VALUES ('map-center', '0,0'), ('map-zoom', '2');


ALTER TABLE  `shipment_participant_map` CHANGE  `evaluation_comment`  `evaluation_comment` INT( 11 ) NULL DEFAULT 0;

--Guna 28-may-2014
CREATE TABLE IF NOT EXISTS `countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `iso_name` varchar(255) COLLATE utf8_bin NOT NULL,
  `iso2` varchar(2) COLLATE utf8_bin NOT NULL,
  `iso3` varchar(3) COLLATE utf8_bin NOT NULL,
  `numeric_code` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin AUTO_INCREMENT=256 ;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `iso_name`, `iso2`, `iso3`, `numeric_code`) VALUES
(1, 'Afghanistan', 'AF', 'AFG', 4),
(2, 'Aland Islands', 'AX', 'ALA', 248),
(3, 'Albania', 'AL', 'ALB', 8),
(4, 'Algeria', 'DZ', 'DZA', 12),
(5, 'American Samoa', 'AS', 'ASM', 16),
(6, 'Andorra', 'AD', 'AND', 20),
(7, 'Angola', 'AO', 'AGO', 24),
(8, 'Anguilla', 'AI', 'AIA', 660),
(9, 'Antarctica', 'AQ', 'ATA', 10),
(10, 'Antigua and Barbuda', 'AG', 'ATG', 28),
(11, 'Argentina', 'AR', 'ARG', 32),
(12, 'Armenia', 'AM', 'ARM', 51),
(13, 'Aruba', 'AW', 'ABW', 533),
(14, 'Australia', 'AU', 'AUS', 36),
(15, 'Austria', 'AT', 'AUT', 40),
(16, 'Azerbaijan', 'AZ', 'AZE', 31),
(17, 'Bahamas', 'BS', 'BHS', 44),
(18, 'Bahrain', 'BH', 'BHR', 48),
(19, 'Bangladesh', 'BD', 'BGD', 50),
(20, 'Barbados', 'BB', 'BRB', 52),
(21, 'Belarus', 'BY', 'BLR', 112),
(22, 'Belgium', 'BE', 'BEL', 56),
(23, 'Belize', 'BZ', 'BLZ', 84),
(24, 'Benin', 'BJ', 'BEN', 204),
(25, 'Bermuda', 'BM', 'BMU', 60),
(26, 'Bhutan', 'BT', 'BTN', 64),
(27, 'Bolivia, Plurinational State of', 'BO', 'BOL', 68),
(28, 'Bonaire, Sint Eustatius and Saba', 'BQ', 'BES', 535),
(29, 'Bosnia and Herzegovina', 'BA', 'BIH', 70),
(30, 'Botswana', 'BW', 'BWA', 72),
(31, 'Bouvet Island', 'BV', 'BVT', 74),
(32, 'Brazil', 'BR', 'BRA', 76),
(33, 'British Indian Ocean Territory', 'IO', 'IOT', 86),
(34, 'Brunei Darussalam', 'BN', 'BRN', 96),
(35, 'Bulgaria', 'BG', 'BGR', 100),
(36, 'Burkina Faso', 'BF', 'BFA', 854),
(37, 'Burundi', 'BI', 'BDI', 108),
(38, 'Cambodia', 'KH', 'KHM', 116),
(39, 'Cameroon', 'CM', 'CMR', 120),
(40, 'Canada', 'CA', 'CAN', 124),
(41, 'Cape Verde', 'CV', 'CPV', 132),
(42, 'Cayman Islands', 'KY', 'CYM', 136),
(43, 'Central African Republic', 'CF', 'CAF', 140),
(44, 'Chad', 'TD', 'TCD', 148),
(45, 'Chile', 'CL', 'CHL', 152),
(46, 'China', 'CN', 'CHN', 156),
(47, 'Christmas Island', 'CX', 'CXR', 162),
(48, 'Cocos (Keeling) Islands', 'CC', 'CCK', 166),
(49, 'Colombia', 'CO', 'COL', 170),
(50, 'Comoros', 'KM', 'COM', 174),
(51, 'Congo', 'CG', 'COG', 178),
(52, 'Congo, the Democratic Republic of the', 'CD', 'COD', 180),
(53, 'Cook Islands', 'CK', 'COK', 184),
(54, 'Costa Rica', 'CR', 'CRI', 188),
(55, 'Cote d''Ivoire', 'CI', 'CIV', 384),
(56, 'Croatia', 'HR', 'HRV', 191),
(57, 'Cuba', 'CU', 'CUB', 192),
(58, 'Cura', 'CW', 'CUW', 531),
(59, 'Cyprus', 'CY', 'CYP', 196),
(60, 'Czech Republic', 'CZ', 'CZE', 203),
(61, 'Denmark', 'DK', 'DNK', 208),
(62, 'Djibouti', 'DJ', 'DJI', 262),
(63, 'Dominica', 'DM', 'DMA', 212),
(64, 'Dominican Republic', 'DO', 'DOM', 214),
(65, 'Ecuador', 'EC', 'ECU', 218),
(66, 'Egypt', 'EG', 'EGY', 818),
(67, 'El Salvador', 'SV', 'SLV', 222),
(68, 'Equatorial Guinea', 'GQ', 'GNQ', 226),
(69, 'Eritrea', 'ER', 'ERI', 232),
(70, 'Estonia', 'EE', 'EST', 233),
(71, 'Ethiopia', 'ET', 'ETH', 231),
(72, 'Falkland Islands (Malvinas)', 'FK', 'FLK', 238),
(73, 'Faroe Islands', 'FO', 'FRO', 234),
(74, 'Fiji', 'FJ', 'FJI', 242),
(75, 'Finland', 'FI', 'FIN', 246),
(76, 'France', 'FR', 'FRA', 250),
(77, 'French Guiana', 'GF', 'GUF', 254),
(78, 'French Polynesia', 'PF', 'PYF', 258),
(79, 'French Southern Territories', 'TF', 'ATF', 260),
(80, 'Gabon', 'GA', 'GAB', 266),
(81, 'Gambia', 'GM', 'GMB', 270),
(82, 'Georgia', 'GE', 'GEO', 268),
(83, 'Germany', 'DE', 'DEU', 276),
(84, 'Ghana', 'GH', 'GHA', 288),
(85, 'Gibraltar', 'GI', 'GIB', 292),
(86, 'Greece', 'GR', 'GRC', 300),
(87, 'Greenland', 'GL', 'GRL', 304),
(88, 'Grenada', 'GD', 'GRD', 308),
(89, 'Guadeloupe', 'GP', 'GLP', 312),
(90, 'Guam', 'GU', 'GUM', 316),
(91, 'Guatemala', 'GT', 'GTM', 320),
(92, 'Guernsey', 'GG', 'GGY', 831),
(93, 'Guinea', 'GN', 'GIN', 324),
(94, 'Guinea-Bissau', 'GW', 'GNB', 624),
(95, 'Guyana', 'GY', 'GUY', 328),
(96, 'Haiti', 'HT', 'HTI', 332),
(97, 'Heard Island and McDonald Islands', 'HM', 'HMD', 334),
(98, 'Holy See (Vatican City State)', 'VA', 'VAT', 336),
(99, 'Honduras', 'HN', 'HND', 340),
(100, 'Hong Kong', 'HK', 'HKG', 344),
(101, 'Hungary', 'HU', 'HUN', 348),
(102, 'Iceland', 'IS', 'ISL', 352),
(103, 'India', 'IN', 'IND', 356),
(104, 'Indonesia', 'ID', 'IDN', 360),
(105, 'Iran, Islamic Republic of', 'IR', 'IRN', 364),
(106, 'Iraq', 'IQ', 'IRQ', 368),
(107, 'Ireland', 'IE', 'IRL', 372),
(108, 'Isle of Man', 'IM', 'IMN', 833),
(109, 'Israel', 'IL', 'ISR', 376),
(110, 'Italy', 'IT', 'ITA', 380),
(111, 'Jamaica', 'JM', 'JAM', 388),
(112, 'Japan', 'JP', 'JPN', 392),
(113, 'Jersey', 'JE', 'JEY', 832),
(114, 'Jordan', 'JO', 'JOR', 400),
(115, 'Kazakhstan', 'KZ', 'KAZ', 398),
(116, 'Kenya', 'KE', 'KEN', 404),
(117, 'Kiribati', 'KI', 'KIR', 296),
(118, 'Korea, Democratic People''s Republic of', 'KP', 'PRK', 408),
(119, 'Korea, Republic of', 'KR', 'KOR', 410),
(120, 'Kuwait', 'KW', 'KWT', 414),
(121, 'Kyrgyzstan', 'KG', 'KGZ', 417),
(122, 'Lao People''s Democratic Republic', 'LA', 'LAO', 418),
(123, 'Latvia', 'LV', 'LVA', 428),
(124, 'Lebanon', 'LB', 'LBN', 422),
(125, 'Lesotho', 'LS', 'LSO', 426),
(126, 'Liberia', 'LR', 'LBR', 430),
(127, 'Libya', 'LY', 'LBY', 434),
(128, 'Liechtenstein', 'LI', 'LIE', 438),
(129, 'Lithuania', 'LT', 'LTU', 440),
(130, 'Luxembourg', 'LU', 'LUX', 442),
(131, 'Macao', 'MO', 'MAC', 446),
(132, 'Macedonia, the former Yugoslav Republic of', 'MK', 'MKD', 807),
(133, 'Madagascar', 'MG', 'MDG', 450),
(134, 'Malawi', 'MW', 'MWI', 454),
(135, 'Malaysia', 'MY', 'MYS', 458),
(136, 'Maldives', 'MV', 'MDV', 462),
(137, 'Mali', 'ML', 'MLI', 466),
(138, 'Malta', 'MT', 'MLT', 470),
(139, 'Marshall Islands', 'MH', 'MHL', 584),
(140, 'Martinique', 'MQ', 'MTQ', 474),
(141, 'Mauritania', 'MR', 'MRT', 478),
(142, 'Mauritius', 'MU', 'MUS', 480),
(143, 'Mayotte', 'YT', 'MYT', 175),
(144, 'Mexico', 'MX', 'MEX', 484),
(145, 'Micronesia, Federated States of', 'FM', 'FSM', 583),
(146, 'Moldova, Republic of', 'MD', 'MDA', 498),
(147, 'Monaco', 'MC', 'MCO', 492),
(148, 'Mongolia', 'MN', 'MNG', 496),
(149, 'Montenegro', 'ME', 'MNE', 499),
(150, 'Montserrat', 'MS', 'MSR', 500),
(151, 'Morocco', 'MA', 'MAR', 504),
(152, 'Mozambique', 'MZ', 'MOZ', 508),
(153, 'Myanmar', 'MM', 'MMR', 104),
(154, 'Namibia', 'NA', 'NAM', 516),
(155, 'Nauru', 'NR', 'NRU', 520),
(156, 'Nepal', 'NP', 'NPL', 524),
(157, 'Netherlands', 'NL', 'NLD', 528),
(158, 'New Caledonia', 'NC', 'NCL', 540),
(159, 'New Zealand', 'NZ', 'NZL', 554),
(160, 'Nicaragua', 'NI', 'NIC', 558),
(161, 'Niger', 'NE', 'NER', 562),
(162, 'Nigeria', 'NG', 'NGA', 566),
(163, 'Niue', 'NU', 'NIU', 570),
(164, 'Norfolk Island', 'NF', 'NFK', 574),
(165, 'Northern Mariana Islands', 'MP', 'MNP', 580),
(166, 'Norway', 'no', 'NOR', 578),
(167, 'Oman', 'OM', 'OMN', 512),
(168, 'Pakistan', 'PK', 'PAK', 586),
(169, 'Palau', 'PW', 'PLW', 585),
(170, 'Palestine, State of', 'PS', 'PSE', 275),
(171, 'Panama', 'PA', 'PAN', 591),
(172, 'Papua New Guinea', 'PG', 'PNG', 598),
(173, 'Paraguay', 'PY', 'PRY', 600),
(174, 'Peru', 'PE', 'PER', 604),
(175, 'Philippines', 'PH', 'PHL', 608),
(176, 'Pitcairn', 'PN', 'PCN', 612),
(177, 'Poland', 'PL', 'POL', 616),
(178, 'Portugal', 'PT', 'PRT', 620),
(179, 'Puerto Rico', 'PR', 'PRI', 630),
(180, 'Qatar', 'QA', 'QAT', 634),
(181, 'Reunion', 'RE', 'REU', 638),
(182, 'Romania', 'RO', 'ROU', 642),
(183, 'Russian Federation', 'RU', 'RUS', 643),
(184, 'Rwanda', 'RW', 'RWA', 646),
(185, 'Saint Barthelemy', 'BL', 'BLM', 652),
(186, 'Saint Helena, Ascension and Tristan da Cunha', 'SH', 'SHN', 654),
(187, 'Saint Kitts and Nevis', 'KN', 'KNA', 659),
(188, 'Saint Lucia', 'LC', 'LCA', 662),
(189, 'Saint Martin (French part)', 'MF', 'MAF', 663),
(190, 'Saint Pierre and Miquelon', 'PM', 'SPM', 666),
(191, 'Saint Vincent and the Grenadines', 'VC', 'VCT', 670),
(192, 'Samoa', 'WS', 'WSM', 882),
(193, 'San Marino', 'SM', 'SMR', 674),
(194, 'Sao Tome and Principe', 'ST', 'STP', 678),
(195, 'Saudi Arabia', 'SA', 'SAU', 682),
(196, 'Senegal', 'SN', 'SEN', 686),
(197, 'Serbia', 'RS', 'SRB', 688),
(198, 'Seychelles', 'SC', 'SYC', 690),
(199, 'Sierra Leone', 'SL', 'SLE', 694),
(200, 'Singapore', 'SG', 'SGP', 702),
(201, 'Sint Maarten (Dutch part)', 'SX', 'SXM', 534),
(202, 'Slovakia', 'SK', 'SVK', 703),
(203, 'Slovenia', 'SI', 'SVN', 705),
(204, 'Solomon Islands', 'SB', 'SLB', 90),
(205, 'Somalia', 'SO', 'SOM', 706),
(206, 'South Africa', 'ZA', 'ZAF', 710),
(207, 'South Georgia and the South Sandwich Islands', 'GS', 'SGS', 239),
(208, 'South Sudan', 'SS', 'SSD', 728),
(209, 'Spain', 'ES', 'ESP', 724),
(210, 'Sri Lanka', 'LK', 'LKA', 144),
(211, 'Sudan', 'SD', 'SDN', 729),
(212, 'Suriname', 'SR', 'SUR', 740),
(213, 'Svalbard and Jan Mayen', 'SJ', 'SJM', 744),
(214, 'Swaziland', 'SZ', 'SWZ', 748),
(215, 'Sweden', 'SE', 'SWE', 752),
(216, 'Switzerland', 'CH', 'CHE', 756),
(217, 'Syrian Arab Republic', 'SY', 'SYR', 760),
(218, 'Taiwan, Province of China', 'TW', 'TWN', 158),
(219, 'Tajikistan', 'TJ', 'TJK', 762),
(220, 'Tanzania, United Republic of', 'TZ', 'TZA', 834),
(221, 'Thailand', 'TH', 'THA', 764),
(222, 'Timor-Leste', 'TL', 'TLS', 626),
(223, 'Togo', 'TG', 'TGO', 768),
(224, 'Tokelau', 'TK', 'TKL', 772),
(225, 'Tonga', 'TO', 'TON', 776),
(226, 'Trinidad and Tobago', 'TT', 'TTO', 780),
(227, 'Tunisia', 'TN', 'TUN', 788),
(228, 'Turkey', 'TR', 'TUR', 792),
(229, 'Turkmenistan', 'TM', 'TKM', 795),
(230, 'Turks and Caicos Islands', 'TC', 'TCA', 796),
(231, 'Tuvalu', 'TV', 'TUV', 798),
(232, 'Uganda', 'UG', 'UGA', 800),
(233, 'Ukraine', 'UA', 'UKR', 804),
(234, 'United Arab Emirates', 'AE', 'ARE', 784),
(235, 'United Kingdom', 'GB', 'GBR', 826),
(236, 'United States', 'US', 'USA', 840),
(237, 'United States Minor Outlying Islands', 'UM', 'UMI', 581),
(238, 'Uruguay', 'UY', 'URY', 858),
(239, 'Uzbekistan', 'UZ', 'UZB', 860),
(240, 'Vanuatu', 'VU', 'VUT', 548),
(241, 'Venezuela, Bolivarian Republic of', 'VE', 'VEN', 862),
(242, 'Viet Nam', 'VN', 'VNM', 704),
(243, 'Virgin Islands, British', 'VG', 'VGB', 92),
(244, 'Virgin Islands, U.S.', 'VI', 'VIR', 850),
(245, 'Wallis and Futuna', 'WF', 'WLF', 876),
(246, 'Western Sahara', 'EH', 'ESH', 732),
(247, 'Yemen', 'YE', 'YEM', 887),
(248, 'Zambia', 'ZM', 'ZMB', 894),
(249, 'Zimbabwe', 'ZW', 'ZWE', 716);

ALTER TABLE  `participant` CHANGE  `country`  `country` INT( 11 ) NOT NULL;
UPDATE  `participant` SET  `country` =  '2' ;
ALTER TABLE  `participant` ADD  `funding_source` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `shipping_address` ,
ADD  `testing_volume` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `funding_source` ,
ADD  `region` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `testing_volume`;

--Guna 11-june-2014
ALTER TABLE  `data_manager` ADD  `created_on` DATETIME NULL DEFAULT NULL AFTER  `status` ,
ADD  `created_by` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `created_on` ,
ADD  `updated_on` DATETIME NULL DEFAULT NULL AFTER  `created_by` ,
ADD  `updated_by` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `updated_on`;

ALTER TABLE  `system_admin` ADD  `created_on` DATETIME NULL DEFAULT NULL AFTER  `status` ,
ADD  `created_by` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `created_on` ,
ADD  `updated_on` DATETIME NULL DEFAULT NULL AFTER  `created_by` ,
ADD  `updated_by` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `updated_on`;

ALTER TABLE  `distributions` ADD  `created_on` DATETIME NULL DEFAULT NULL AFTER  `status` ,
ADD  `created_by` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `created_on` ,
ADD  `updated_on` DATETIME NULL DEFAULT NULL AFTER  `created_by` ,
ADD  `updated_by` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `updated_on`;



--- Amit 15-Jul-2014

ALTER TABLE  `shipment_participant_map` ADD  `is_followup` VARCHAR( 255 ) NULL DEFAULT  'no' AFTER  `optional_eval_comment`;

---Guna 22-july-2014
ALTER TABLE  `participant` ADD  `enrolled_programs` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `testing_volume` ,
ADD  `site_type` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `enrolled_programs`;

CREATE TABLE IF NOT EXISTS `r_site_type` (
  `r_stid` int(11) NOT NULL AUTO_INCREMENT,
  `site_type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`r_stid`)
) ;



INSERT INTO `r_site_type` (`site_type`) VALUES
('VCT'),
('Mobile VCT'),
('TB Center'),
('Antenatal Clinic (PMTCT)'),
('Outpatient Clinic'),
('Hospital'),
('Laboratory'),
('District'),
('Province'),
('Region'),
('Department'),
('Other');

CREATE TABLE IF NOT EXISTS `r_enrolled_programs` (
  `r_epid` int(11) NOT NULL AUTO_INCREMENT,
  `enrolled_programs` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`r_epid`)
) ;


INSERT INTO `r_enrolled_programs` (`enrolled_programs`) VALUES
('PEPFAR RTQI Program'),
('PEPFAR');


-- Amit Jul 25 2014

ALTER TABLE `shipment_participant_map` ADD `is_excluded` VARCHAR(255) NOT NULL DEFAULT 'no' AFTER `is_followup`;

--Guna Agu 23 2014
ALTER TABLE  `participant` ADD  `individual` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `unique_identifier`;
--Guna oct 2 2014
CREATE TABLE IF NOT EXISTS `reference_dts_rapid_hiv` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shipment_id` varchar(255) NOT NULL,
  `sample_id` varchar(255) NOT NULL,
  `testkit` varchar(255) NOT NULL,
  `lot_no` varchar(255) NOT NULL,
  `expiry_date` date NOT NULL,
  `result` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
);

--Guna Oct 24 2014
UPDATE  `global_config` SET  `name` =  'admin_email' WHERE  `global_config`.`name` =  'admin-email';
INSERT INTO `global_config` (`name`, `value`) VALUES ('response_after_evaluate', 'yes');


--Amit Nov 06 2014
ALTER TABLE  `shipment_participant_map` ADD  `documentation_score` DECIMAL( 5, 2 ) NULL AFTER  `shipment_score` ;
ALTER TABLE  `shipment_participant_map` CHANGE  `shipment_score`  `shipment_score` DECIMAL( 5, 2 ) NULL DEFAULT NULL ;

--Guna Nov 29 2014
ALTER TABLE `r_testkitname_dts`  ADD `testkit_1` INT(11) NOT NULL DEFAULT '0' AFTER `CountryAdapted`,  ADD `testkit_2` INT(11) NOT NULL DEFAULT '0' AFTER `testkit_1`,  ADD `testkit_3` INT(11) NOT NULL DEFAULT '0' AFTER `testkit_2`;

-- Amit Nov 30 2014

CREATE TABLE IF NOT EXISTS `r_dts_corrective_actions` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `corrective_action` text NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`action_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=16 ;

--
-- Dumping data for table `r_dts_corrective_actions`
--

INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES
(1, 'Please submit response before last date', 'Late response, response not evaluated.\r\nYour response reported after last date and hence you are result is evaluated. Reference result for PT panel is provided for your reference. '),
(2, 'Please see the link on the PT website for the country approved algorithm. ', 'For SAMPLE-X Country Approved Algorithm not followed'),
(3, 'Please follow correct procedure for sample testing. Please contact your superior if you need more information on correct procedure for HIV Rapid testing.', 'Sample X - Sample Result does not match with reference result'),
(4, 'You are required to test all samples in PT panel', 'Mandatory Sample S01 was not reported - Result not evaluated'),
(5, 'Expired test kit should not be used for testing. If test kits are not available, please contact your superior.', 'Testkit XYZ expired M days before the test date 10-Nov-2014'),
(6, 'Tester should verify testkit expiry date and required to report with PT result.', 'Testkit XYZ reported without expiry date - Result not evaluated.'),
(7, 'Test kit name needs to be reported', 'No Test Kit name Reported - Result not evaluated'),
(8, 'Country algorithm not followed. Please see the link on the PT website for country algorithm.', 'Testkit XYZ repeated for all 3 test kits'),
(9, 'Country algorithm not followed. Please see the link on the PT website for country algorithm.', 'Test kit XYZ repeated for Testkit 1 and Testkit 2\r\n'),
(10, 'Country algorithm not followed. Please see the link on the PT website for country algorithm.', 'Lot No. X was not reported'),
(11, 'Please make sure your PT result is approved by your supervisor.', 'Supervisor approval absent'),
(12, 'Please make sure you provide the sample rehydration date', 'Rehydration date not provided\r\n'),
(13, 'Please make sure you provide date of shipment received. ', 'Shipment received Test date not provided'),
(14, 'You should perform testing within 24 hours of rehydration.', 'Difference between testing and rehydration date is more than 24 hours'),
(15, 'Please review your corrective action for improvement.\r\n', 'Participant did not meet the score criteria (Participant Score is 80 and Required Score is 95)');

-- Amit Dec 01 2014
  
CREATE TABLE IF NOT EXISTS `dts_shipment_corrective_action_map` (
  `shipment_map_id` int(11) NOT NULL,
  `corrective_action_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Amit Dec 04 2014

ALTER TABLE  `dts_shipment_corrective_action_map` ADD UNIQUE (
`shipment_map_id` ,
`corrective_action_id`
);


-- Amit Mar 18 2015


CREATE TABLE IF NOT EXISTS `reference_result_tb` (
  `shipment_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `sample_label` varchar(255) DEFAULT NULL,
  `mtb_detected` varchar(255) DEFAULT NULL,
  `rif_resistance` varchar(255) DEFAULT NULL,
  `probe_d` varchar(255) DEFAULT NULL,
  `probe_c` varchar(255) DEFAULT NULL,
  `probe_e` varchar(255) DEFAULT NULL,
  `probe_b` varchar(255) DEFAULT NULL,
  `spc` varchar(255) DEFAULT NULL,
  `probe_a` varchar(255) DEFAULT NULL,
  `control` int(11) DEFAULT NULL,
  `mandatory` int(11) NOT NULL DEFAULT '0',
  `sample_score` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `scheme_list` (`scheme_id`, `scheme_name`, `response_table`, `reference_result_table`, `attribute_list`, `status`) VALUES ('tb', 'Tuberculosis', 'response_result_tb', 'reference_result_tb', NULL, 'active');

CREATE TABLE IF NOT EXISTS `response_result_tb` (
  `shipment_map_id` int(11) NOT NULL,
  `sample_id` varchar(45) NOT NULL,
  `date_tested` date DEFAULT NULL,
  `mtb_detected` varchar(255) DEFAULT NULL,
  `rif_resistance` varchar(255) DEFAULT NULL,
  `probe_d` varchar(255) DEFAULT NULL,
  `probe_c` varchar(255) DEFAULT NULL,
  `probe_e` varchar(255) DEFAULT NULL,
  `probe_b` varchar(255) DEFAULT NULL,
  `spc` varchar(255) DEFAULT NULL,
  `probe_a` varchar(255) DEFAULT NULL,
  `calculated_score` varchar(45) DEFAULT NULL,
  `created_by` varchar(45) DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--Guna 16-May-2015---

CREATE TABLE IF NOT EXISTS `mail_template` (
  `mail_temp_id` int(11) NOT NULL AUTO_INCREMENT,
  `mail_purpose` varchar(255) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `mail_from` varchar(255) DEFAULT NULL,
  `mail_cc` varchar(255) DEFAULT NULL,
  `mail_bcc` varchar(255) DEFAULT NULL,
  `mail_subject` varchar(255) DEFAULT NULL,
  `mail_content` text,
  `mail_footer` text,
  PRIMARY KEY (`mail_temp_id`)
);
---Guna 18-Apirl-2015----
CREATE TABLE IF NOT EXISTS `temp_mail` (
  `temp_id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text,
  `from_mail` varchar(255) DEFAULT NULL,
  `to_email` varchar(255) NOT NULL,
  `bcc` text,
  `cc` text,
  `subject` text,
  `from_full_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`temp_id`)
);

ALTER TABLE  `temp_mail` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'pending' AFTER  `from_full_name` ;
ALTER TABLE  `shipment_participant_map` ADD  `last_new_shipment_mailed_on` DATETIME NULL DEFAULT NULL AFTER  `report_generated` ,
ADD  `new_shipment_mail_count` INT( 11 ) NOT NULL DEFAULT  '0' AFTER  `last_new_shipment_mailed_on` ;

ALTER TABLE  `shipment_participant_map` ADD  `last_not_participated_mailed_on` DATETIME NULL DEFAULT NULL AFTER  `new_shipment_mail_count` ,
ADD  `last_not_participated_mail_count` INT( 11 ) NOT NULL DEFAULT  '0' AFTER  `last_not_participated_mailed_on` ;


-- Amit 18 April 2015

INSERT INTO `report_config` (`name`, `value`) VALUES ('logo-right', NULL);

-- Guna 20 April 2015

ALTER TABLE  `shipment_participant_map` CHANGE  `final_result`  `final_result` INT( 11 ) NULL DEFAULT  '0';

--Guna 21 Apirl 2015
ALTER TABLE  `shipment_participant_map` CHANGE  `shipment_test_date`  `shipment_test_date` DATE NULL DEFAULT  '0000-00-00';

--Amit 22 April 2015
INSERT INTO `r_results` (`result_id`, `result_name`) VALUES ('3', 'Excluded');
ALTER TABLE `shipment` ADD `average_score` VARCHAR(255) NULL DEFAULT '0' AFTER `max_score`;

-- Amit May 8 2015
ALTER TABLE `r_testkitname_dts` ADD `scheme_type` VARCHAR(255) NOT NULL AFTER `TestKitName_ID`;
UPDATE `r_testkitname_dts` SET `scheme_type`='dts'; # RUN THIS only the first time

-- Amit Jun 4 2015
INSERT INTO `global_config` (`name`, `value`) VALUES ('custom_field_1', '');
INSERT INTO `global_config` (`name`, `value`) VALUES ('custom_field_needed', 'no');
ALTER TABLE `shipment_participant_map` ADD `custom_field_1` TEXT NULL DEFAULT NULL AFTER `user_comment`;

-- Amit Jun 8 2015
ALTER TABLE `shipment_participant_map` ADD `custom_field_2` TEXT NULL DEFAULT NULL AFTER `custom_field_1`;
INSERT INTO `global_config` (`name`, `value`) VALUES ('custom_field_2', '');

-- Amit Jun 10 2015

CREATE TABLE IF NOT EXISTS `participant_enrolled_programs_map` (
  `participant_id` int(11) NOT NULL,
  `ep_id` int(11) NOT NULL,
  PRIMARY KEY (`participant_id`,`ep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Amit Jun 23 2015
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES ('16', 'Please specify the Panel Receipt Date .', 'Please specify the Panel Receipt Date .');

INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`) VALUES (NULL, 'dts', 'DTS_FINAL', 'NOT TESTED');

-- Amit Jul 17 2015
ALTER TABLE `shipment` ADD `response_switch` VARCHAR(255) NOT NULL DEFAULT 'off' AFTER `number_of_samples`;

CREATE TABLE IF NOT EXISTS `dts_recommended_testkits` (
  `test_no` int(11) NOT NULL,
  `testkit` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `dts_recommended_testkits`
 ADD PRIMARY KEY (`test_no`,`testkit`);
 
 
 -- Amit Jul 21 2015
 
 
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES
(1, 'Please submit response before last date', 'Late response, response not evaluated. Your response received after last date. Expected result for PT panel will be available for your reference. '),
(2, 'Review and refer to SOP for testing. Sample should be tested per National HIV Testing algorithm. ', 'For sample (1/2/3?) National HIV Testing algorithm was not followed.'),
(3, 'Review all testing procedures prior to performing client testing as reported result does not match expected result.', 'Sample (1/2/3?) reported result does not match with expected result.'),
(4, 'You are required to test all samples in PT panel', 'Sample (1/2/3) was not reported '),
(5, 'Ensure expired test kit are not be used for testing. If test kits are not available, please contact your superior.', 'Test kit XYZ expired M days before the test date DD-MON-YYY.'),
(6, 'Ensure expiry date information is submitted for all performed tests.', 'Result not evaluated ? test kit expiry date (first/second/third) is not reported with PT response.'),
(7, 'Ensure test kit name is reported for all performed tests.', 'Result not evaluated ? name of test kit not reported.'),
(8, 'Please use the approved test kits according to the SOP/National HIV Testing algorithm for confirmatory and tie-breaker.', 'Testkit XYZ repeated for all 3 test kits'),
(9, 'Please use the approved test kits according to the SOP/National HIV Testing algorithm for confirmatory and tie-breaker.', 'Test kit repeated for confirmatory or tiebreaker test (T1/T2/T3).'),
(10, 'Ensure test kit lot number is reported for all performed tests. ', 'Result not evaluated ? Test Kit lot number (first/second/third) is not reported.'),
(11, 'Ensure to provide supervisor approval along with his name.', 'Missing supervisor approval for reported result.'),
(12, 'Ensure to provide sample rehydration date', 'Re-hydration date missing in PT report form.'),
(13, 'Ensure to provide to provide panel testing date.', 'Testing date missing in PT report form.'),
(14, 'DTS Testing should be done within specified hours of rehydration as per SOP.', 'Testing is not performed within X to Y hours of rehydration.'),
(15, 'Review all testing procedures prior to performing client testing and contact your supervisor for improvement.', 'Participant did not meet the score criteria (Participant Score is 80 and Required Score is 95)'),
(16, 'Ensure to provide to provide panel receipt date. ', 'Panel receipt date missing in PT report form.'),
(17, 'Please test DTS sample as per National HIV Testing algorithm. Review and refer to SOP for testing.', 'For Test (1/2/3) testing is not performed with country approved test kit.');


-- Amit Aug 26 2015


CREATE TABLE IF NOT EXISTS `reference_vl_methods` (
  `shipment_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `assay` int(11) NOT NULL,
  `value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `reference_vl_methods`
 ADD PRIMARY KEY (`shipment_id`,`sample_id`,`assay`);
 
 
 -- Amit Sep 03 2015
 
 ALTER TABLE  `r_vl_assay` ADD  `short_name` VARCHAR( 255 ) NOT NULL AFTER  `name` ;
 INSERT INTO `r_vl_assay` (`id`, `name`, `short_name`) VALUES (NULL, 'Other', 'Other');
 
 -- Amit 13 Sep 2015
 
 ALTER TABLE `participant` ADD `contact_name` VARCHAR(255) NULL DEFAULT NULL AFTER `phone`;
 
 -- Amit 23 Sep 2015

ALTER TABLE `shipment` ADD `number_of_controls` INT NOT NULL AFTER `number_of_samples`;

-- Amit 28 Sep 2015

ALTER TABLE `reference_vl_calculation` ADD `calculated_on` DATETIME NULL DEFAULT NULL AFTER `high_limit`, ADD `manual_low_limit` DOUBLE(10,2) NOT NULL DEFAULT '0' AFTER `calculated_on`, ADD `manual_high_limit` DOUBLE(10,2) NOT NULL DEFAULT '0' AFTER `manual_low_limit`, ADD `updated_on` DATETIME NULL DEFAULT NULL AFTER `manual_high_limit`, ADD `updated_by` INT NULL DEFAULT NULL AFTER `updated_on`;

ALTER TABLE `reference_vl_calculation` ADD PRIMARY KEY( `shipment_id`, `sample_id`, `vl_assay`);
ALTER TABLE `reference_vl_calculation` ADD `use_range` VARCHAR(255) NOT NULL DEFAULT 'calculated' ;


--ilahir 07-JUN-2016

INSERT INTO `global_config` (`name`, `value`) VALUES ('qc_access', 'yes');
ALTER TABLE  `data_manager` ADD  `qc_access` VARCHAR( 100 ) NULL DEFAULT NULL AFTER  `force_password_reset` ;

ALTER TABLE  `shipment_participant_map` ADD  `qc_date` DATE NULL DEFAULT NULL ;
ALTER TABLE  `shipment_participant_map` ADD  `qc_done_by` INT NULL DEFAULT NULL ;
ALTER TABLE  `shipment_participant_map` ADD  `qc_created_on` DATETIME NULL DEFAULT NULL ;


CREATE TABLE IF NOT EXISTS `r_modes_of_receipt` (
  `mode_id` int(11) NOT NULL AUTO_INCREMENT,
  `mode_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`mode_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=5 ;

--
-- Dumping data for table `r_modes_of_receipt`
--

INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES
(1, 'Courier'),
(2, 'Email'),
(3, 'Scan'),
(4, 'SMS');


ALTER TABLE  `shipment_participant_map` ADD  `mode_id` INT NULL DEFAULT NULL ;

--Pal 24th-JUN-2016
ALTER TABLE `data_manager` ADD `enable_adding_test_response_date` VARCHAR(45) NULL DEFAULT NULL AFTER `qc_access`;

INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES (NULL, 'Online Response');

--Pal 25th-JUN-2016
ALTER TABLE `shipment_participant_map` CHANGE `qc_done_by` `qc_done_by` VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE `shipment_participant_map` ADD `qc_done` VARCHAR(45) NULL DEFAULT NULL AFTER `last_not_participated_mail_count`;

ALTER TABLE `shipment_participant_map` CHANGE `qc_done` `qc_done` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'no';

-- Re-ordered mode--
Delete from `r_modes_of_receipt`;
INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES
(1, 'Online Response'),
(2, 'Courier'),
(3, 'Email'),
(4, 'Scan'),
(5, 'SMS');

--Pal 2nd-JUL-2016
INSERT INTO `global_config` (`name`, `value`) VALUES ('text_under_logo', '');

CREATE TABLE IF NOT EXISTS `publications` (
  `publication_id` int(11) NOT NULL AUTO_INCREMENT,
  `content` text,
  `file_name` varchar(255) DEFAULT NULL,
  `added_by` int(11) NOT NULL,
  `added_on` datetime NOT NULL,
  `status` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`publication_id`)
);

CREATE TABLE IF NOT EXISTS `home_banner` (
  `banner_id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`banner_id`)
);

INSERT INTO `home_banner` (`banner_id`, `image`) VALUES
(1, '');

--Pal 4th-JUL-2016

ALTER TABLE `data_manager` ADD `enable_choosing_mode_of_receipt` VARCHAR(45) NULL DEFAULT NULL AFTER `enable_adding_test_response_date`;

CREATE TABLE IF NOT EXISTS `partners` (
  `partner_id` int(11) NOT NULL AUTO_INCREMENT,
  `partner_name` varchar(500) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `added_by` int(11) NOT NULL,
  `added_on` datetime NOT NULL,
  `status` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`partner_id`)
);

INSERT INTO `partners` (`partner_id`, `partner_name`, `link`, `added_by`, `added_on`, `status`) VALUES
(1, 'CDC-Centers for Disease Control and Prevention', '', 1, '2016-07-04 17:58:43', 'active');

-- Amit Jul 5 2016
ALTER TABLE `data_manager` ADD `last_login` DATETIME NULL DEFAULT NULL AFTER `updated_by`;

--ilahir Jul 19 2016
ALTER TABLE  `reference_vl_calculation` ADD  `manual_mean` DOUBLE( 20, 10 ) NOT NULL AFTER  `calculated_on` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_sd` DOUBLE( 20, 10 ) NOT NULL AFTER  `manual_mean` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_cv` DOUBLE( 20, 10 ) NOT NULL AFTER  `manual_sd` ;


--ilahir Jul 25 2016

ALTER TABLE  `reference_vl_calculation` ADD  `manual_q1` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `calculated_on` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_q3` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_q1` ,
ADD  `manual_iqr` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_q3` ;

ALTER TABLE  `reference_vl_calculation` ADD  `manual_quartile_low` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_iqr` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_quartile_high` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_quartile_low` ;


-- Amit Jul 29 2016
INSERT INTO `r_eid_detection_assay` (`id`, `name`) VALUES (NULL, 'Abbott RealTime HIV-1 Qualitative Assay');
INSERT INTO `r_eid_extraction_assay` (`id`, `name`) VALUES (NULL, 'Abbott RealTime HIV-1 Qualitative Assay');
INSERT INTO `r_eid_detection_assay` (`id`, `name`) VALUES (NULL, 'Other');
INSERT INTO `r_eid_extraction_assay` (`id`, `name`) VALUES (NULL, 'Other');
INSERT INTO `r_results` (`result_id`, `result_name`) VALUES ('4', 'Not Evaluated');

--Ilahir Aug 25 2016
ALTER TABLE  `data_manager` ADD  `view_only_access` VARCHAR( 45 ) NULL DEFAULT NULL AFTER  `enable_choosing_mode_of_receipt` ;

--Pal 12th-Sep-2016
ALTER TABLE `publications` ADD `sort_order` INT(11) NULL DEFAULT NULL AFTER `file_name`;

ALTER TABLE `partners` ADD `sort_order` INT(11) NULL DEFAULT NULL AFTER `link`;

--Pal 15th-Sep-2016
ALTER TABLE `r_eid_detection_assay` ADD `status` VARCHAR(45) NOT NULL DEFAULT 'active' AFTER `name`;

ALTER TABLE `r_eid_extraction_assay` ADD `status` VARCHAR(45) NOT NULL DEFAULT 'active' AFTER `name`;

--Pal 28th-OCT-2016
ALTER TABLE `shipment_participant_map` CHANGE `participant_supervisor` `participant_supervisor` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

--Pal 21st-DEC-2016
ALTER TABLE `response_result_vl` ADD `is_tnd` VARCHAR(45) NULL DEFAULT NULL AFTER `calculated_score`;

ALTER TABLE `shipment_participant_map` ADD `is_pt_test_not_performed` VARCHAR(45) NULL DEFAULT NULL AFTER `shipment_test_date`, ADD `vl_not_tested_reason`INT(11) NULL DEFAULT NULL AFTER `is_pt_test_not_performed`, ADD `pt_test_not_performed_comments` TEXT NULL DEFAULT NULL AFTER `vl_not_tested_reason`;

CREATE TABLE `response_vl_not_tested_reason` (
  `vl_not_tested_reason_id` int(11) NOT NULL,
  `vl_not_tested_reason` varchar(500) DEFAULT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active'
);

INSERT INTO `response_vl_not_tested_reason` (`vl_not_tested_reason_id`, `vl_not_tested_reason`, `status`) VALUES
(1, 'invalid sample', 'active'),
(2, 'VL machine not working', 'active');

ALTER TABLE `response_vl_not_tested_reason`
  ADD PRIMARY KEY (`vl_not_tested_reason_id`);
  
ALTER TABLE `response_vl_not_tested_reason`
  MODIFY `vl_not_tested_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
  
--Pal 24th-DEC-2016
ALTER TABLE `shipment_participant_map` ADD `pt_support_comments` TEXT NULL DEFAULT NULL AFTER `pt_test_not_performed_comments`;