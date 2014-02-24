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

INSERT INTO `global_config` (`name`, `value`) VALUES ('admin-name', 'ePT Admin'), ('admin-email', 'admin@ept.com');
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
