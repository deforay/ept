By Amit on 15 Sep 2013
ALTER TABLE  `users` DROP PRIMARY KEY;
ALTER TABLE  `users` ADD PRIMARY KEY (  `UserSystemID` );
ALTER TABLE  `users` CHANGE  `UserSystemID`  `UserSystemID` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE  `users` CHANGE  `UserID`  `UserID` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

--  By Amit on 17 Sep 2013

CREATE TABLE IF NOT EXISTS `global_config` (
  `name` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB;

INSERT INTO `global_config` (`name`, `value`) VALUES ('admin-name', 'ePT Admin'), ('admin_email', 'admin@ept.com');
ALTER TABLE  `users` ADD  `force_password_reset` INT NOT NULL DEFAULT  '0';


--  By Amit on 18 Sep 2013

--  ALTER TABLE  `schemelist` CHANGE  `schemeID`  `SchemeID` VARCHAR( 10 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
--  ALTER TABLE  `schemelist` ADD  `SchemeName` VARCHAR( 255 ) NOT NULL AFTER  `SchemeID`;
--  INSERT INTO `schemelist` (`SchemeID`, `SchemeName`, `ShipmentTable`, `ResponseTable`, `ReferanceResultTable`) VALUES ('DTS', 'Dried Tube Specimen', NULL, NULL, NULL), ('VL', 'Viral Load', NULL, NULL, NULL);

--  By Amit on 19 Sep 2013


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
--  Dumping data for table `eid_detection_assay`
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
--  Dumping data for table `eid_extraction_assay`
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
--  Dumping data for table `r_vl_assay`
--

INSERT INTO `r_vl_assay` (`id`, `name`) VALUES
(1, 'Abbott - RealTime '),
(2, 'Roche - COBAS Ampliprep/TaqMan'),
(3, 'Biocentric - Generic HIV Charge Virale'),
(4, 'Biomerieux - NucliSENS'),
(5, 'Roche - Amplicor');



--  By Amit on Sep 20 2013

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


--  By Amit on 23 Sep 2013

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
--  a.ShipmentTestReportDate as RESPONSEDATE,
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
--  a.shipment_test_report_date as RESPONSEDATE,
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
--  a.shipment_test_report_date as RESPONSEDATE,
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
--  LIMIT valFrom, valTo;
END$$


DELIMITER $$

DROP PROCEDURE IF EXISTS `SHIPMENT_OVERVIEW`$$
CREATE PROCEDURE `SHIPMENT_OVERVIEW`()
BEGIN
--  Select shipment for last five year
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


--  By Amit on Sep 25 2013

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


--  By Amit on Sep 26 2013

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


--  By Amit on Sep 30 2013

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
--  Use update
--  select * from response_result_dts;
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


--  By Amit on Oct 01 2013

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


--  By Amit on Oct 02 2013

ALTER TABLE `shipment_dts` CHANGE `DTSShipmentID` `dts_shipment_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ParticipantID` `participant_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ShipmentDate` `shipment_date` DATE NULL DEFAULT NULL, CHANGE `EvaluationStatus` `evaluation_status` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL COMMENT 'Shipment Status					\\nUse this to flag - 					\\nABCDEFG					\\nA = 9 Not shipped 1 shipped					\\nB = 1 Sample Received 9 Not recieved					\\nC = 1 = Responded 9 = Not responded					\\nD = 1= Timeely response 2= Late					\\nE = 1 - via Web user 2 - via web Provider 3 - Scanning 					\\nF = 9 Not eligille for evaluation 1 eligible for evaluation					\\nG = 1 = Evaluated  9= not evaluated					\\n', CHANGE `ShipmentScore` `shipment_score` INT(11) NULL DEFAULT NULL, CHANGE `LastDateResponse` `lastdate_response` DATE NULL DEFAULT NULL, CHANGE `ShipmentTestDate` `shipment_test_date` DATE NULL DEFAULT NULL, CHANGE `ShipmentReceiptDate` `shipment_receipt_date` DATE NULL DEFAULT NULL, CHANGE `ShipmentTestReportDate` `shipment_test_report_date` DATETIME NULL DEFAULT NULL, CHANGE `ParticipantSupervisor` `participant_supervisor` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `supervisorApproval` `supervisor_approval` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ReviewDate` `review_date` DATE NULL DEFAULT NULL, CHANGE `SampleRehydrationDate` `sample_rehydration_date` DATE NULL DEFAULT NULL, CHANGE `NumberOfSample` `number_of_sample` INT(11) NULL DEFAULT NULL, CHANGE `UserComment` `user_comment` VARCHAR(90) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Create_on_admin` `created_on_admin` DATETIME NULL DEFAULT NULL, CHANGE `Update_on_admin` `updated_on_admin` DATETIME NULL DEFAULT NULL, CHANGE `Update_by_admin` `updated_by_admin` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Update_on_user` `updated_on_user` DATETIME NULL DEFAULT NULL, CHANGE `Updated_by_user` `updated_by_user` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `created_by_admin` `created_by_admin` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `response_result_dts` CHANGE `ShipmentID` `shipment_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `ParticipantID` `participant_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `DTSSampleID` `dts_sample_id` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, CHANGE `TestKitName1` `TestKitName1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LotNo1` `LotNo1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ExpDate1` `ExpDate1` DATE NULL DEFAULT NULL, CHANGE `TestResult1` `TestResult1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `TestKitName2` `TestKitName2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LotNo2` `LotNo2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ExpDate2` `ExpDate2` DATE NULL DEFAULT NULL, CHANGE `TestResult2` `TestResult2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `TestKitName3` `TestKitName3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `LotNo3` `LotNo3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ExpDate3` `ExpDate3` DATE NULL DEFAULT NULL, CHANGE `TestResult3` `TestResult3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `ReportedResult` `ReportedResult` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `CalculatedScore` `CalculatedScore` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Created_by` `Created_by` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Created_on` `Created_on` DATETIME NULL DEFAULT NULL, CHANGE `Updated_by` `Updated_by` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `Updated_on` `Updated_on` DATETIME NULL DEFAULT NULL;

RENAME TABLE  `eid_detection_assay` TO  `r_eid_detection_assay` ;
RENAME TABLE  `eid_extraction_assay` TO  `r_eid_extraction_assay` ;

--  By Amit on Oct 04

--  Loads of changes done .. cannot put them here :)


--  By Amit on Oct 07 2013

ALTER TABLE  `admin` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'inactive';

--  By Amit on Oct 08 2013

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


--  By Amit on Oct 09 2013

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


--  by Amit on Oct 10 2013

ALTER TABLE  `shipment_dts` CHANGE  `number_of_sample`  `number_of_samples` INT( 11 ) NULL DEFAULT NULL;
ALTER TABLE  `shipment_eid` ADD  `shipment_code` VARCHAR( 255 ) NOT NULL AFTER  `eid_shipment_id`;
ALTER TABLE  `shipment_dts` ADD  `shipment_code` VARCHAR( 255 ) NOT NULL AFTER  `dts_shipment_id`;
ALTER TABLE  `shipment_vl` ADD  `shipment_code` VARCHAR( 255 ) NOT NULL AFTER  `vl_shipment_id`;
ALTER TABLE  `r_possibleresult` CHANGE  `ID`  `ID` INT( 11 ) NOT NULL AUTO_INCREMENT;
INSERT INTO `r_possibleresult` (`ID`, `SchemeCode`, `SchemeSubgroup`, `Response`) VALUES ('', 'EID', 'EID_FINAL', 'Positive (HIV Detected)'), ('', 'EID', 'EID_FINAL', 'Negative (HIV Not Detected)');
INSERT INTO `r_possibleresult` (`ID`, `SchemeCode`, `SchemeSubgroup`, `Response`) VALUES (NULL, 'EID', 'EID_FINAL', 'Equivocal');


--  by Amit on Oct 15 2013

ALTER TABLE  `shipment_eid` CHANGE  `eid_shipment_id`  `eid_shipment_id` INT NOT NULL;
ALTER TABLE  `shipment_vl` CHANGE  `vl_shipment_id`  `vl_shipment_id` INT NOT NULL;
ALTER TABLE  `shipment_dts` CHANGE  `dts_shipment_id`  `dts_shipment_id` INT NOT NULL;


--  by Amit on Oct 22 2013

ALTER TABLE  `participant` ADD  `email` VARCHAR( 255 ) NOT NULL AFTER  `phone`;
ALTER TABLE  `reference_result_vl` CHANGE  `vl_sample_label`  `sample_label` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;




--  by Amit on Oct 28 2013
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
--  spm.shipment_test_report_date as RESPONSEDATE,
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
--  spm.shipment_test_report_date as RESPONSEDATE,
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
--  spm.shipment_test_report_date as RESPONSEDATE,
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
--  spm.shipment_test_report_date as RESPONSEDATE,
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
--  LIMIT valFrom, valTo;
END $$

Delimiter ;


 --  by Amit on Nov 4 2013

 ALTER TABLE  `response_result_eid` CHANGE  `eid_sample_id`  `sample_id` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;
 ALTER TABLE  `response_result_vl` CHANGE  `vl_sample_id`  `sample_id` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;
 ALTER TABLE  `response_result_dts` CHANGE  `dts_sample_id`  `sample_id` INT( 11 ) NOT NULL;


 --  by Amit on Nov 7 2013

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

 --  by Amit on 13 Nov 2013

 ALTER TABLE  `shipment` ADD  `status` VARCHAR( 255 ) NOT NULL DEFAULT  'pending';
 ALTER TABLE  `shipment_participant_map` ADD UNIQUE (
`shipment_id` ,
`participant_id`
);


 --  by Amit on 19 Nov 2013
  alter table participant drop foreign key participant_ibfk_1;
  ALTER TABLE `participant` DROP `data_manager`;

  --  by Amit Nov 24 2013

CREATE TABLE IF NOT EXISTS `r_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `result_name` varchar(255) NOT NULL,
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

INSERT INTO  `r_results` (`result_id` ,`result_name`) VALUES ('1',  'Pass'), ('2',  'Fail');


--  by Amit Nov 27 2013

ALTER TABLE  `reference_result_dts` ADD  `mandatory` INT NOT NULL DEFAULT  '0',
ADD  `sample_score` INT NOT NULL DEFAULT  '1';
ALTER TABLE  `reference_result_vl` ADD  `mandatory` INT NOT NULL DEFAULT  '0',
ADD  `sample_score` INT NOT NULL DEFAULT  '1';
ALTER TABLE  `reference_result_eid` ADD  `mandatory` INT NOT NULL DEFAULT  '0',
ADD  `sample_score` INT NOT NULL DEFAULT  '1';

ALTER TABLE  `shipment_participant_map` ADD  `final_result` VARCHAR( 255 ) NOT NULL AFTER  `review_date`;
ALTER TABLE  `shipment_participant_map` ADD  `failure_reason` TEXT NOT NULL AFTER  `final_result`;


--  by Amit Dec 03 2013

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



--  by Amit Dec 06 2013
ALTER TABLE  `reference_result_dts` ADD  `control` INT NOT NULL AFTER  `reference_result`;
ALTER TABLE  `reference_result_eid` ADD  `control` INT NOT NULL AFTER  `reference_result`;
ALTER TABLE  `reference_result_vl` ADD  `control` INT NOT NULL AFTER  `reference_result`;


--  by Amit Dec 09 2013

ALTER TABLE  `shipment_participant_map` CHANGE  `final_result`  `final_result` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

--  by Amit Dec 16 2013
ALTER TABLE  `shipment_participant_map` CHANGE  `final_result`  `final_result` INT NULL DEFAULT NULL



--  by Amit Dec 17 2013
CREATE TABLE IF NOT EXISTS `r_network_tiers` ( `network_id` int(11) NOT NULL AUTO_INCREMENT, `network_name` varchar(255) DEFAULT NULL, PRIMARY KEY (`network_id`)) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

ALTER TABLE  `participant` ADD  `network_tier` INT NOT NULL AFTER  `affiliation`;
ALTER TABLE  `data_manager` ADD  `institute` VARCHAR( 500 ) NULL DEFAULT NULL AFTER  `password`;

--  by Amit Dec 30 2013
ALTER TABLE  `scheme_list` ADD  `status` VARCHAR( 255 ) NULL DEFAULT NULL;

-- by Ilahir JAN 22 2014

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

-- ilahir 8-Feb-2014

ALTER TABLE  `shipment_participant_map` ADD  `report_generated` VARCHAR( 100 ) NULL DEFAULT NULL;

-- ilahir 12-Feb-2014

CREATE TABLE IF NOT EXISTS `report_config` (
  `name` varchar(255) DEFAULT NULL,
  `value` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ilahir 24-Feb-2014

INSERT INTO `report_config` (`name`, `value`) VALUES
('report-header', '<div style=""><div style="text-align: center;"><b>DEPARTMENT OF HEALTH AND HUMAN SERVICES</b></div><div style="text-align: center;">International Laboratory Branch - Division of Global HIV/AIDS, CDC-Atlanta</div></div>\r\n\r\n'),
('logo', '');

-- Guna 25-Mar-2014
ALTER TABLE  `shipment_participant_map` CHANGE  `participant_supervisor`  `participant_supervisor` VARCHAR( 255 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

-- Ilahir 27-Mar-2014
ALTER TABLE  `shipment_participant_map` ADD  `created_on_user` DATETIME NULL DEFAULT NULL AFTER  `created_by_admin`;

-- Ilahir 07-Apr-2014

INSERT INTO `global_config` (`name`, `value`) VALUES ('map-center', '0,0'), ('map-zoom', '2');


ALTER TABLE  `shipment_participant_map` CHANGE  `evaluation_comment`  `evaluation_comment` INT( 11 ) NULL DEFAULT 0;

-- Guna 28-may-2014
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
--  Dumping data for table `countries`
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

-- Guna 11-june-2014
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



-- - Amit 15-Jul-2014

ALTER TABLE  `shipment_participant_map` ADD  `is_followup` VARCHAR( 255 ) NULL DEFAULT  'no' AFTER  `optional_eval_comment`;

-- -Guna 22-july-2014
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


--  Amit Jul 25 2014

ALTER TABLE `shipment_participant_map` ADD `is_excluded` VARCHAR(255) NOT NULL DEFAULT 'no' AFTER `is_followup`;

-- Guna Agu 23 2014
ALTER TABLE  `participant` ADD  `individual` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `unique_identifier`;
-- Guna oct 2 2014
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

-- Guna Oct 24 2014
UPDATE  `global_config` SET  `name` =  'admin_email' WHERE  `global_config`.`name` =  'admin-email';
INSERT INTO `global_config` (`name`, `value`) VALUES ('response_after_evaluate', 'yes');


-- Amit Nov 06 2014
ALTER TABLE  `shipment_participant_map` ADD  `documentation_score` DECIMAL( 5, 2 ) NULL AFTER  `shipment_score` ;
ALTER TABLE  `shipment_participant_map` CHANGE  `shipment_score`  `shipment_score` DECIMAL( 5, 2 ) NULL DEFAULT NULL ;

-- Guna Nov 29 2014
ALTER TABLE `r_testkitname_dts`  ADD `testkit_1` INT(11) NOT NULL DEFAULT '0' AFTER `CountryAdapted`,  ADD `testkit_2` INT(11) NOT NULL DEFAULT '0' AFTER `testkit_1`,  ADD `testkit_3` INT(11) NOT NULL DEFAULT '0' AFTER `testkit_2`;

--  Amit Nov 30 2014

CREATE TABLE IF NOT EXISTS `r_dts_corrective_actions` (
  `action_id` int(11) NOT NULL AUTO_INCREMENT,
  `corrective_action` text NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`action_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=16 ;

--
--  Dumping data for table `r_dts_corrective_actions`
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

--  Amit Dec 01 2014

CREATE TABLE IF NOT EXISTS `dts_shipment_corrective_action_map` (
  `shipment_map_id` int(11) NOT NULL,
  `corrective_action_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--  Amit Dec 04 2014

ALTER TABLE  `dts_shipment_corrective_action_map` ADD UNIQUE (
`shipment_map_id` ,
`corrective_action_id`
);


--  Amit Mar 18 2015


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

-- Guna 16-May-2015-- -

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
-- -Guna 18-Apirl-2015-- --
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


--  Amit 18 April 2015

INSERT INTO `report_config` (`name`, `value`) VALUES ('logo-right', NULL);

--  Guna 20 April 2015

ALTER TABLE  `shipment_participant_map` CHANGE  `final_result`  `final_result` INT( 11 ) NULL DEFAULT  '0';

-- Guna 21 Apirl 2015
ALTER TABLE  `shipment_participant_map` CHANGE  `shipment_test_date`  `shipment_test_date` DATE NULL DEFAULT  '0000-00-00';

-- Amit 22 April 2015
INSERT INTO `r_results` (`result_id`, `result_name`) VALUES ('3', 'Excluded');
ALTER TABLE `shipment` ADD `average_score` VARCHAR(255) NULL DEFAULT '0' AFTER `max_score`;

--  Amit May 8 2015
ALTER TABLE `r_testkitname_dts` ADD `scheme_type` VARCHAR(255) NOT NULL AFTER `TestKitName_ID`;
UPDATE `r_testkitname_dts` SET `scheme_type`='dts'; # RUN THIS only the first time

--  Amit Jun 4 2015
INSERT INTO `global_config` (`name`, `value`) VALUES ('custom_field_1', '');
INSERT INTO `global_config` (`name`, `value`) VALUES ('custom_field_needed', 'no');
ALTER TABLE `shipment_participant_map` ADD `custom_field_1` TEXT NULL DEFAULT NULL AFTER `user_comment`;

--  Amit Jun 8 2015
ALTER TABLE `shipment_participant_map` ADD `custom_field_2` TEXT NULL DEFAULT NULL AFTER `custom_field_1`;
INSERT INTO `global_config` (`name`, `value`) VALUES ('custom_field_2', '');

--  Amit Jun 10 2015

CREATE TABLE IF NOT EXISTS `participant_enrolled_programs_map` (
  `participant_id` int(11) NOT NULL,
  `ep_id` int(11) NOT NULL,
  PRIMARY KEY (`participant_id`,`ep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--  Amit Jun 23 2015
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES ('16', 'Please specify the Panel Receipt Date .', 'Please specify the Panel Receipt Date .');

INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`) VALUES (NULL, 'dts', 'DTS_FINAL', 'NOT TESTED');

--  Amit Jul 17 2015
ALTER TABLE `shipment` ADD `response_switch` VARCHAR(255) NOT NULL DEFAULT 'off' AFTER `number_of_samples`;

CREATE TABLE IF NOT EXISTS `dts_recommended_testkits` (
  `test_no` int(11) NOT NULL,
  `testkit` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `dts_recommended_testkits`
 ADD PRIMARY KEY (`test_no`,`testkit`);


 --  Amit Jul 21 2015


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


--  Amit Aug 26 2015


CREATE TABLE IF NOT EXISTS `reference_vl_methods` (
  `shipment_id` int(11) NOT NULL,
  `sample_id` int(11) NOT NULL,
  `assay` int(11) NOT NULL,
  `value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `reference_vl_methods`
 ADD PRIMARY KEY (`shipment_id`,`sample_id`,`assay`);


 --  Amit Sep 03 2015

 ALTER TABLE  `r_vl_assay` ADD  `short_name` VARCHAR( 255 ) NOT NULL AFTER  `name` ;
 INSERT INTO `r_vl_assay` (`id`, `name`, `short_name`) VALUES (NULL, 'Other', 'Other');

 --  Amit 13 Sep 2015

 ALTER TABLE `participant` ADD `contact_name` VARCHAR(255) NULL DEFAULT NULL AFTER `phone`;

 --  Amit 23 Sep 2015

ALTER TABLE `shipment` ADD `number_of_controls` INT NOT NULL AFTER `number_of_samples`;

--  Amit 28 Sep 2015

ALTER TABLE `reference_vl_calculation` ADD `calculated_on` DATETIME NULL DEFAULT NULL AFTER `high_limit`, ADD `manual_low_limit` DOUBLE(10,2) NOT NULL DEFAULT '0' AFTER `calculated_on`, ADD `manual_high_limit` DOUBLE(10,2) NOT NULL DEFAULT '0' AFTER `manual_low_limit`, ADD `updated_on` DATETIME NULL DEFAULT NULL AFTER `manual_high_limit`, ADD `updated_by` INT NULL DEFAULT NULL AFTER `updated_on`;

ALTER TABLE `reference_vl_calculation` ADD PRIMARY KEY( `shipment_id`, `sample_id`, `vl_assay`);
ALTER TABLE `reference_vl_calculation` ADD `use_range` VARCHAR(255) NOT NULL DEFAULT 'calculated' ;


-- ilahir 07-JUN-2016

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
--  Dumping data for table `r_modes_of_receipt`
--

INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES
(1, 'Courier'),
(2, 'Email'),
(3, 'Scan'),
(4, 'SMS');


ALTER TABLE  `shipment_participant_map` ADD  `mode_id` INT NULL DEFAULT NULL ;

-- Pal 24th-JUN-2016
ALTER TABLE `data_manager` ADD `enable_adding_test_response_date` VARCHAR(45) NULL DEFAULT NULL AFTER `qc_access`;

INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES (NULL, 'Online Response');

-- Pal 25th-JUN-2016
ALTER TABLE `shipment_participant_map` CHANGE `qc_done_by` `qc_done_by` VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE `shipment_participant_map` ADD `qc_done` VARCHAR(45) NULL DEFAULT NULL AFTER `last_not_participated_mail_count`;

ALTER TABLE `shipment_participant_map` CHANGE `qc_done` `qc_done` VARCHAR(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'no';

--  Re-ordered mode--
Delete from `r_modes_of_receipt`;
INSERT INTO `r_modes_of_receipt` (`mode_id`, `mode_name`) VALUES
(1, 'Online Response'),
(2, 'Courier'),
(3, 'Email'),
(4, 'Scan'),
(5, 'SMS');

-- Pal 2nd-JUL-2016
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

-- Pal 4th-JUL-2016

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

--  Amit Jul 5 2016
ALTER TABLE `data_manager` ADD `last_login` DATETIME NULL DEFAULT NULL AFTER `updated_by`;

-- ilahir Jul 19 2016
ALTER TABLE  `reference_vl_calculation` ADD  `manual_mean` DOUBLE( 20, 10 ) NOT NULL AFTER  `calculated_on` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_sd` DOUBLE( 20, 10 ) NOT NULL AFTER  `manual_mean` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_cv` DOUBLE( 20, 10 ) NOT NULL AFTER  `manual_sd` ;


-- ilahir Jul 25 2016

ALTER TABLE  `reference_vl_calculation` ADD  `manual_q1` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `calculated_on` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_q3` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_q1` ,
ADD  `manual_iqr` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_q3` ;

ALTER TABLE  `reference_vl_calculation` ADD  `manual_quartile_low` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_iqr` ;
ALTER TABLE  `reference_vl_calculation` ADD  `manual_quartile_high` DOUBLE( 20, 10 ) NULL DEFAULT NULL AFTER  `manual_quartile_low` ;


--  Amit Jul 29 2016
INSERT INTO `r_eid_detection_assay` (`id`, `name`) VALUES (NULL, 'Abbott RealTime HIV-1 Qualitative Assay');
INSERT INTO `r_eid_extraction_assay` (`id`, `name`) VALUES (NULL, 'Abbott RealTime HIV-1 Qualitative Assay');
INSERT INTO `r_eid_detection_assay` (`id`, `name`) VALUES (NULL, 'Other');
INSERT INTO `r_eid_extraction_assay` (`id`, `name`) VALUES (NULL, 'Other');
INSERT INTO `r_results` (`result_id`, `result_name`) VALUES ('4', 'Not Evaluated');

-- Ilahir Aug 25 2016
ALTER TABLE  `data_manager` ADD  `view_only_access` VARCHAR( 45 ) NULL DEFAULT NULL AFTER  `enable_choosing_mode_of_receipt` ;

-- Pal 12th-Sep-2016
ALTER TABLE `publications` ADD `sort_order` INT(11) NULL DEFAULT NULL AFTER `file_name`;

ALTER TABLE `partners` ADD `sort_order` INT(11) NULL DEFAULT NULL AFTER `link`;

-- Pal 15th-Sep-2016
ALTER TABLE `r_eid_detection_assay` ADD `status` VARCHAR(45) NOT NULL DEFAULT 'active' AFTER `name`;

ALTER TABLE `r_eid_extraction_assay` ADD `status` VARCHAR(45) NOT NULL DEFAULT 'active' AFTER `name`;

-- Pal 28th-OCT-2016
ALTER TABLE `shipment_participant_map` CHANGE `participant_supervisor` `participant_supervisor` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

-- Pal 21st-DEC-2016
ALTER TABLE `response_result_vl` ADD `is_tnd` VARCHAR(45) NULL DEFAULT NULL AFTER `calculated_score`;

ALTER TABLE `shipment_participant_map` ADD `is_pt_test_not_performed` VARCHAR(45) NULL DEFAULT NULL AFTER `shipment_test_date`, ADD `vl_not_tested_reason`INT(11) NULL DEFAULT NULL AFTER `is_pt_test_not_performed`, ADD `pt_test_not_performed_comments` TEXT NULL DEFAULT NULL AFTER `vl_not_tested_reason`;

CREATE TABLE `r_response_vl_not_tested_reason` (
  `vl_not_tested_reason_id` int(11) NOT NULL,
  `vl_not_tested_reason` varchar(500) DEFAULT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active'
);

INSERT INTO `r_response_vl_not_tested_reason` (`vl_not_tested_reason_id`, `vl_not_tested_reason`, `status`) VALUES
(1, 'No reagents for testing of PT panel', 'active'),
(2, 'No lab personal for testing of PT panel', 'active'),
(3, ' Instrument down', 'active'),
(4, 'Laboratory facility under renovation', 'active'),
(5, 'Laboratory facility no longer perform testing', 'active'),
(6, 'The results were invalid for the entire run', 'active'),
(7, 'The PT panel testing failed during sample processing', 'active'),
(8, 'The PT panel shipment was lost/damage', 'active'),
(9, 'Not received PT panel shipment due to country custom clearance issue', 'active'),
(10, 'Not received PT panel shipment due to incorrect contact info on the shipment package', 'active'),
(11, 'Other (please explain)', 'active');

ALTER TABLE `r_response_vl_not_tested_reason`
  ADD PRIMARY KEY (`vl_not_tested_reason_id`);

ALTER TABLE `r_response_vl_not_tested_reason`
  MODIFY `vl_not_tested_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--  Pal 24th-DEC-2016
ALTER TABLE `shipment_participant_map` ADD `pt_support_comments` TEXT NULL DEFAULT NULL AFTER `pt_test_not_performed_comments`;



--  Ilahir 17-JAN-2017

ALTER TABLE  `participant` ADD  `additional_email` VARCHAR( 255 ) NULL DEFAULT NULL AFTER  `email` ;


--  Ilahir 08-FEB-2017

ALTER TABLE  `participant` ADD  `force_profile_updation` INT( 1 ) NOT NULL DEFAULT  '1' AFTER  `updated_by` ;


--  Amit 28 June 2017




ALTER TABLE `r_eid_extraction_assay` ADD `sort_order` INT NULL DEFAULT '0' AFTER `name`;
ALTER TABLE `r_eid_detection_assay` ADD `sort_order` INT NULL DEFAULT '0' AFTER `name`;

INSERT INTO `r_eid_detection_assay` (`id`, `name`, `sort_order`) VALUES (NULL, 'Other', '8');
INSERT INTO `r_eid_extraction_assay` (`id`, `name`, `sort_order`) VALUES (NULL, 'Other', '8');

UPDATE `r_eid_extraction_assay` SET `sort_order` = '1' WHERE `r_eid_extraction_assay`.`id` = 1;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '2' WHERE `r_eid_extraction_assay`.`id` = 2;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '4' WHERE `r_eid_extraction_assay`.`id` = 3;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '5' WHERE `r_eid_extraction_assay`.`id` = 4;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '6' WHERE `r_eid_extraction_assay`.`id` = 5;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '7' WHERE `r_eid_extraction_assay`.`id` = 6;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '3' WHERE `r_eid_extraction_assay`.`id` = 7;
UPDATE `r_eid_extraction_assay` SET `sort_order` = '8' WHERE `r_eid_extraction_assay`.`id` = 8;

UPDATE `r_eid_detection_assay` SET `sort_order` = '1' WHERE `r_eid_detection_assay`.`id` = 1;
UPDATE `r_eid_detection_assay` SET `sort_order` = '2' WHERE `r_eid_detection_assay`.`id` = 2;
UPDATE `r_eid_detection_assay` SET `sort_order` = '4' WHERE `r_eid_detection_assay`.`id` = 3;
UPDATE `r_eid_detection_assay` SET `sort_order` = '5' WHERE `r_eid_detection_assay`.`id` = 4;
UPDATE `r_eid_detection_assay` SET `sort_order` = '6' WHERE `r_eid_detection_assay`.`id` = 5;
UPDATE `r_eid_detection_assay` SET `sort_order` = '7' WHERE `r_eid_detection_assay`.`id` = 6;
UPDATE `r_eid_detection_assay` SET `sort_order` = '3' WHERE `r_eid_detection_assay`.`id` = 7;
UPDATE `r_eid_detection_assay` SET `sort_order` = '8' WHERE `r_eid_detection_assay`.`id` = 8;

--  Pal 27 Nov 2017
CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `announcement_msg` text,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active'
);

ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`);

ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `shipment_participant_map` ADD `show_announcement` VARCHAR(45) NOT NULL DEFAULT 'yes' AFTER `mode_id`;



--  Amit 18 Sep 2018

INSERT INTO `report_config` (`name`, `value`) VALUES ('report-comment', '');


--  Amit 21 Feb 2019

ALTER TABLE `participant` CHANGE `lab_name` `lab_name` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `participant` CHANGE `first_name` `first_name` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `participant` CHANGE `last_name` `last_name` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `participant` CHANGE `institute_name` `institute_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `participant` CHANGE `lab_name` `lab_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;


ALTER TABLE `data_manager` CHANGE `institute` `institute` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;


--  Amit 1 July 2019

CREATE TABLE `evaluation_queue` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `shipment_id` int(11) NOT NULL,
 `requested_by` int(11) NOT NULL,
 `requested_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `last_updated_on` datetime DEFAULT CURRENT_TIMESTAMP,
 `status` varchar(255) NOT NULL DEFAULT 'pending',
 PRIMARY KEY (`id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8;



--  Amit Nov 15 2019

ALTER TABLE `response_result_vl` CHANGE `reported_viral_load` `reported_viral_load` DOUBLE(10,2) NULL DEFAULT NULL;


--  Version 5.0 Dec 11 2019

--  Thanaseelan 23-Dec-2019
ALTER TABLE `system_admin` ADD `privileges` VARCHAR(255) NULL DEFAULT NULL AFTER `status`;
--  Reference
UPDATE system_admin SET privileges = 'config-ept,manage-shipments,analyze-generate-reports,edit-participant-response,access-reports';

--  Sriram 11-Feb-2020
ALTER TABLE `data_manager` ADD `auth_token` VARCHAR(255) NULL DEFAULT NULL AFTER `last_login`;

-- Sriram 17Feb2020
CREATE TABLE `system_config` (
  `system_id` int(1) NOT NULL AUTO_INCREMENT,
  `display_name` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
 PRIMARY KEY (`system_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

INSERT INTO `system_config` (`system_id`, `display_name`, `name`, `value`) VALUES (NULL, 'App-Version', 'app_version', '0.0.1');
--  Thanaseelan 03 Feb, 2020
ALTER TABLE `shipment_participant_map` ADD `synced` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `show_announcement`;
ALTER TABLE `shipment_participant_map` ADD `synced_on` DATETIME NULL DEFAULT NULL AFTER `synced`;
--  Thanaseelan 25 Mar, 2020
ALTER TABLE `data_manager` ADD `download_link` VARCHAR(255) NULL DEFAULT NULL AFTER `auth_token`;
--  Thanaseelan 29 Apr, 2020
ALTER TABLE `shipment` ADD `report_in_queue` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `status`;
ALTER TABLE `evaluation_queue` ADD FOREIGN KEY (`shipment_id`) REFERENCES `shipment`(`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
--  Thanaseelan 30 Apr, 2020
INSERT INTO `report_config` (`name`, `value`) VALUES ('report-layout', 'default');
--  Thanaseelan 04 May, 2020
ALTER TABLE `evaluation_queue` ADD `report_type` VARCHAR(50) NULL DEFAULT NULL AFTER `shipment_id`;

CREATE TABLE `notify` (
 `id` int NOT NULL AUTO_INCREMENT COMMENT 'auto id',
 `title` varchar(255) DEFAULT NULL COMMENT 'notify title',
 `description` text COMMENT 'notify description',
 `link` varchar(255) DEFAULT NULL COMMENT 'link for corresponding page',
 `status` varchar(50) NOT NULL DEFAULT 'read' COMMENT 'read, readed for notify status',
 `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'current insertion date time',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
--  Thanaseelan 15 May, 2020
ALTER TABLE `data_manager` ADD `force_profile_check` VARCHAR(20) NULL DEFAULT 'no' AFTER `force_password_reset`;
--  Thanaseelan 22 May, 2020
ALTER TABLE `r_possibleresult` ADD `result_code` VARCHAR(20) NULL DEFAULT NULL AFTER `response`;
UPDATE `r_possibleresult` SET `result_code` = 'R' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_TEST' AND `r_possibleresult`.`response` = 'REACTIVE';
UPDATE `r_possibleresult` SET `result_code` = 'NR' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_TEST' AND `r_possibleresult`.`response` = 'NONREACTIVE';
UPDATE `r_possibleresult` SET `result_code` = 'I' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_TEST' AND `r_possibleresult`.`response` = 'INVALID';
UPDATE `r_possibleresult` SET `result_code` = 'P' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_FINAL' AND `r_possibleresult`.`response` = 'POSITIVE';
UPDATE `r_possibleresult` SET `result_code` = 'N' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_FINAL' AND `r_possibleresult`.`response` = 'NEGATIVE';
UPDATE `r_possibleresult` SET `result_code` = 'I' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_FINAL' AND `r_possibleresult`.`response` = 'INDETERMINATE';
UPDATE `r_possibleresult` SET `result_code` = 'D' WHERE `r_possibleresult`.`scheme_sub_group` = 'EID_FINAL' AND `r_possibleresult`.`response` = 'HIV-1 Detected';
UPDATE `r_possibleresult` SET `result_code` = 'ND' WHERE `r_possibleresult`.`scheme_sub_group` = 'EID_FINAL' AND `r_possibleresult`.`response` = 'HIV-1 Not Detected';
UPDATE `r_possibleresult` SET `result_code` = 'E' WHERE `r_possibleresult`.`scheme_sub_group` = 'EID_FINAL' AND `r_possibleresult`.`response` = 'Equivocal';
UPDATE `r_possibleresult` SET `result_code` = 'P' WHERE `r_possibleresult`.`scheme_sub_group` = 'DBS_FINAL' AND `r_possibleresult`.`response` = 'P';
UPDATE `r_possibleresult` SET `result_code` = 'N' WHERE `r_possibleresult`.`scheme_sub_group` = 'DBS_FINAL' AND `r_possibleresult`.`response` = 'N';
UPDATE `r_possibleresult` SET `result_code` = 'NT' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_FINAL' AND `r_possibleresult`.`response` = 'Not Tested';
UPDATE `r_possibleresult` SET `result_code` = 'NT' WHERE `r_possibleresult`.`scheme_sub_group` = 'DTS_FINAL' AND `r_possibleresult`.`response` = 'NOT TESTED';


-- - Amit 3 June, 2020


INSERT INTO `scheme_list` (`scheme_id`, `scheme_name`, `response_table`, `reference_result_table`, `attribute_list`, `status`)
      VALUES ('recency', 'Rapid HIV Recency Testing', 'response_result_recency', 'reference_result_recency', NULL, 'inactive');


CREATE TABLE `reference_result_recency` (
 `shipment_id` int(11) NOT NULL,
 `dts_id` int(11) DEFAULT NULL,
 `sample_id` int(11) NOT NULL,
 `sample_label` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
 `reference_result` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
 `control` int(11) DEFAULT NULL,
 `reference_control_line` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
 `reference_diagnosis_line` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
 `reference_longterm_line` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
 `mandatory` int(11) NOT NULL DEFAULT '0',
 `sample_score` int(11) NOT NULL DEFAULT '1',
 PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `response_result_recency` (
 `shipment_map_id` int(11) NOT NULL,
 `dts_id` int(11) DEFAULT NULL,
 `sample_id` varchar(45) NOT NULL,
 `reported_result` varchar(45) DEFAULT NULL,
 `control_line` varchar(255) DEFAULT NULL,
 `diagnosis_line` varchar(255) DEFAULT NULL,
 `longterm_line` varchar(255) DEFAULT NULL,
 `calculated_score` varchar(45) DEFAULT NULL,
 `created_by` varchar(45) DEFAULT NULL,
 `created_on` datetime DEFAULT NULL,
 `updated_by` varchar(255) DEFAULT NULL,
 `updated_on` datetime DEFAULT NULL,
 PRIMARY KEY (`shipment_map_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `r_recency_assay` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `name` varchar(255) CHARACTER SET latin1 NOT NULL,
 `sort_order` int(11) DEFAULT '0',
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`)
VALUES (NULL, 'recency', 'RECENCY_FINAL', 'Recent', 'R'),
(NULL, 'recency', 'RECENCY_FINAL', 'Long Term','LT'),
(NULL, 'recency', 'RECENCY_FINAL', 'Invalid', 'I'),
(NULL, 'recency', 'RECENCY_FINAL', 'Negative', 'N');
--  Thana 4 Jun, 2020
--  ALTER TABLE `reference_result_recency` CHANGE `reference_verification_line` `reference_diagnosis_line` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
--  ALTER TABLE `response_result_recency` CHANGE `verification_line` `diagnosis_line` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;

--  Thana 3 Jul, 2020
ALTER TABLE `data_manager` ADD `push_notify_token` TEXT NULL DEFAULT NULL;
CREATE TABLE `push_notification` (
 `id` int NOT NULL AUTO_INCREMENT,
 `notification_json` text,
 `data_json` text,
 `push_status` varchar(50) DEFAULT NULL,
 `created_on` datetime DEFAULT NULL,
 `token_identify_id` int DEFAULT NULL,
 `identify_type` varchar(50) DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `push_notification` CHANGE `notification_json` `notification_json` TEXT NULL DEFAULT NULL COMMENT 'create notify message (title body and icon) and convert into json and store here', CHANGE `data_json` `data_json` TEXT NULL DEFAULT NULL COMMENT 'create notify data message and convert into Json then store here', CHANGE `push_status` `push_status` VARCHAR(50) NULL DEFAULT NULL COMMENT 'refuse, pending, send, not-send', CHANGE `token_identify_id` `token_identify_id` INT NULL DEFAULT NULL COMMENT 'Set which mobile to send push notify. Here id come either shipment or DM', CHANGE `identify_type` `identify_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Type of identify id either shipment, people(DM), General and not-responded people.';
ALTER TABLE `push_notification` ADD `notification_type` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Reports, Shipment, General' AFTER `identify_type`;
ALTER TABLE `push_notification` CHANGE `token_identify_id` `token_identify_id` TEXT NULL DEFAULT NULL COMMENT 'Set which mobile to send push notify. Here id come either shipment or DM';
--  Thana 6 Jul, 2020
ALTER TABLE `data_manager` ADD `push_status` VARCHAR(50) NULL DEFAULT 'not-send' AFTER `push_notify_token`;
--  Thana 7 Jul, 2020
CREATE TABLE `push_notification_template` (
 `id` int NOT NULL AUTO_INCREMENT,
 `purpose` varchar(255) DEFAULT NULL,
 `notify_title` varchar(255) DEFAULT NULL,
 `notify_body` text,
 `data_msg` text,
 `icon` varchar(255) DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `push_notification_template` (`id`, `purpose`, `notify_title`, `notify_body`, `data_msg`, `icon`) VALUES (NULL, 'announcement', 'Announcement', 'Announcement Body', 'Announcement message', 'ic_launcher'), (NULL, 'report', 'Report', 'Report Body', 'Report Data Message', 'ic_launcher'), (NULL, 'not_participated', 'Not Participated', 'Not Participated Body', 'Not Participated Data Message', 'ic_launcher'), (NULL, 'new_shipment', 'New Shipment', 'New Shipment Body', 'New Shipment Data Message', 'ic_launcher');
--  Thana 9 Jul, 2020
CREATE TABLE `announcements_notification` (
 `id` int NOT NULL AUTO_INCREMENT,
 `subject` varchar(255) DEFAULT NULL,
 `message` text,
 `participants` text,
 `created_on` datetime DEFAULT NULL,
 `created_by` int DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
--  Thana 13 Jul, 2020
ALTER TABLE `evaluation_queue` ADD `date_finalised` DATETIME NULL DEFAULT NULL AFTER `last_updated_on`;
--  Version 6.0 14-July-2020

--  Thana 15 Jul, 2020
ALTER TABLE `data_manager` ADD `marked_push_notify` text NULL DEFAULT NULL AFTER `push_status`;
--  Thana 16 Jul, 2020
ALTER TABLE `push_notification` ADD `announcement_id` INT NULL DEFAULT NULL AFTER `notification_type`;
ALTER TABLE `push_notification` CHANGE `token_identify_id` `token_identify_id` TEXT NULL DEFAULT NULL COMMENT 'Set which mobile to send push notify. Here id come either shipment or DM';


--  Amit 29 July 2020

ALTER TABLE `shipment` ADD `shipment_attributes` JSON NULL DEFAULT NULL AFTER `average_score`;
UPDATE `shipment` SET `shipment_attributes` = '{\r\n \"sampleType\": \"dried\",\r\n \"screeningTest\": \"no\"\r\n}' WHERE `scheme_type` = 'dts' and `shipment_attributes` is null;


--  Version 6.1.0 Amit 11 Aug 2020


--  Amit 18 Aug 2020
ALTER TABLE `countries` CHANGE `iso_name` `iso_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `data_manager` CHANGE `dm_id` `dm_id` INT(11) NOT NULL AUTO_INCREMENT, CHANGE `password` `password` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `institute` `institute` VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `first_name` `first_name` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `last_name` `last_name` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `phone` `phone` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `secondary_email` `secondary_email` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `UserFld1` `UserFld1` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `UserFld2` `UserFld2` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `UserFld3` `UserFld3` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `mobile` `mobile` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `force_profile_check` `force_profile_check` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT 'no', CHANGE `qc_access` `qc_access` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `enable_adding_test_response_date` `enable_adding_test_response_date` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `enable_choosing_mode_of_receipt` `enable_choosing_mode_of_receipt` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `view_only_access` `view_only_access` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `created_on` `created_on` DATETIME NULL DEFAULT NULL, CHANGE `created_by` `created_by` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `updated_on` `updated_on` DATETIME NULL DEFAULT NULL, CHANGE `updated_by` `updated_by` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `last_login` `last_login` DATETIME NULL DEFAULT NULL, CHANGE `auth_token` `auth_token` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `download_link` `download_link` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `push_notify_token` `push_notify_token` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL, CHANGE `push_status` `push_status` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT 'not-send', CHANGE `marked_push_notify` `marked_push_notify` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;
--  Thana 16 Sep 2020
ALTER TABLE `notify` CHANGE `status` `status` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'unread' COMMENT 'read, readed for notify status';

--  Amit 09 Oct 2020
ALTER TABLE `shipment_participant_map` CHANGE `user_comment` `user_comment` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

--  Thana 27-Oct-2020
ALTER TABLE `data_manager` ADD `new_email` VARCHAR(255) NULL DEFAULT NULL AFTER `marked_push_notify`;

--  Thana 02-Nov-2020
ALTER TABLE `shipment_participant_map` ADD `mode_of_response` VARCHAR(50) NULL DEFAULT NULL COMMENT 'web,app,api' AFTER `synced_on`;
INSERT INTO `global_config` (`name`, `value`) VALUES ('disable_push_notification', 'yes');

--  Thana 24-Dec-2020
INSERT INTO `scheme_list` (`scheme_id`, `scheme_name`, `response_table`, `reference_result_table`, `attribute_list`, `status`)
      VALUES ('covid19', 'SARS-CoV-2', 'response_result_covid19', 'reference_result_covid19', NULL, 'inactive');

CREATE TABLE `r_test_type_covid19` (
 `test_type_id` varchar(50) NOT NULL,
 `scheme_type` varchar(255) NOT NULL,
 `test_type_name` varchar(100) DEFAULT NULL,
 `test_type_short_name` varchar(50) DEFAULT NULL,
 `test_type_comments` varchar(50) DEFAULT NULL,
 `updated_on` datetime DEFAULT NULL,
 `updated_by` int DEFAULT NULL,
 `installation_id` varchar(50) DEFAULT NULL,
 `test_type_manufacturer` varchar(50) DEFAULT NULL,
 `created_on` datetime DEFAULT NULL,
 `created_by` int DEFAULT NULL,
 `approval` int DEFAULT '1' COMMENT '1 = Approved , 0 not approved.',
 `test_type_approval_agency` varchar(20) DEFAULT NULL COMMENT 'USAID, FDA, LOCAL',
 `source_reference` varchar(50) DEFAULT NULL,
 `country_adapted` int DEFAULT NULL COMMENT '0= Not allowed in the country 1 = approved in country ',
 `test_type_1` int NOT NULL DEFAULT '0',
 `test_type_2` int NOT NULL DEFAULT '0',
 `test_type_3` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`test_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
--  Thana 28-Dec-2020
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`) VALUES (NULL, 'covid19', 'COVID19_FINAL', 'Postive', 'P'), (NULL, 'covid19', 'COVID19_FINAL', 'Negative', 'N'), (NULL, 'covid19', 'COVID19_FINAL', 'Interminate', 'I');

--  Thana 29-Dec-2020
UPDATE `r_possibleresult` SET `response` = 'Invalid' WHERE `r_possibleresult`.`id` = 20;

CREATE TABLE `reference_result_covid19` (
 `shipment_id` int NOT NULL,
 `sample_id` int NOT NULL,
 `sample_label` varchar(45) DEFAULT NULL,
 `reference_result` varchar(45) DEFAULT NULL,
 `control` int DEFAULT NULL,
 `mandatory` int NOT NULL DEFAULT '0',
 `sample_score` int NOT NULL DEFAULT '1',
 PRIMARY KEY (`shipment_id`,`sample_id`),
 CONSTRAINT `reference_result_covid19_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Referance Result for Covid19 Shipment';

CREATE TABLE `response_result_covid19` (
 `shipment_map_id` int NOT NULL,
 `sample_id` int NOT NULL,
 `test_type_1` varchar(45) DEFAULT NULL,
 `lot_no_1` varchar(45) DEFAULT NULL,
 `exp_date_1` date DEFAULT NULL,
 `test_result_1` varchar(45) DEFAULT NULL,
 `test_type_2` varchar(45) DEFAULT NULL,
 `lot_no_2` varchar(45) DEFAULT NULL,
 `exp_date_2` date DEFAULT NULL,
 `test_result_2` varchar(45) DEFAULT NULL,
 `test_type_3` varchar(45) DEFAULT NULL,
 `lot_no_3` varchar(45) DEFAULT NULL,
 `exp_date_3` date DEFAULT NULL,
 `test_result_3` varchar(45) DEFAULT NULL,
 `reported_result` varchar(45) DEFAULT NULL,
 `calculated_score` varchar(45) DEFAULT NULL,
 `created_by` varchar(45) DEFAULT NULL,
 `created_on` datetime DEFAULT NULL,
 `updated_by` varchar(45) DEFAULT NULL,
 `updated_on` datetime DEFAULT NULL,
 PRIMARY KEY (`shipment_map_id`,`sample_id`),
 CONSTRAINT `response_result_covid19_ibfk_1` FOREIGN KEY (`shipment_map_id`) REFERENCES `shipment_participant_map` (`map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `covid19_recommended_test_types` (
 `test_no` int NOT NULL,
 `test_type` varchar(255) NOT NULL,
 PRIMARY KEY (`test_no`,`test_type`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`) VALUES (NULL, 'covid19', 'COVID19_TEST', 'Postive', 'P'), (NULL, 'covid19', 'COVID19_TEST', 'Negative', 'N'), (NULL, 'covid19', 'COVID19_TEST', 'Invalid', 'I');

--  Thana 30-Dec-2020
CREATE TABLE `r_covid19_corrective_actions` (
 `action_id` int NOT NULL AUTO_INCREMENT,
 `corrective_action` text NOT NULL,
 `description` text NOT NULL,
 PRIMARY KEY (`action_id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=latin1;

INSERT INTO `r_covid19_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES
(1, 'Please submit response before last date', 'Late response, response not evaluated. Your response received after last date. Expected result for PT panel will be available for your reference. '),
(2, 'Review and refer to SOP for testing. Sample should be tested per National Covid-19 Testing lab.', 'For sample (1/2/3?) National Covid-19 Testing lab was not followed.'),
(3, 'Review all testing procedures prior to performing client testing as reported result does not match expected result.', 'Sample (1/2/3?) reported result does not match with expected result.'),
(4, 'You are required to test all samples in PT panel', 'Sample (1/2/3) was not reported '),
(5, 'Ensure expired test type are not be used for testing. If test types are not available, please contact your superior.', 'Test platform XYZ expired M days before the test date DD-MON-YYY.'),
(6, 'Ensure expiry date information is submitted for all performed tests.', 'Result not evaluated Ð test type expiry date (first/second/third) is not reported with PT response.'),
(7, 'Ensure test type name is reported for all performed tests.', 'Result not evaluated Ð name of test type not reported.'),
(8, 'Please use the approved test types according to the SOP/National Covid-19 Testing lab for confirmatory and tie-breaker.', 'Testtype XYZ repeated for all 3 test types'),
(9, 'Please use the approved test types according to the SOP/National Covid-19 Testing lab for confirmatory and tie-breaker.', 'Test platform repeated for confirmatory or tiebreaker test (T1/T2/T3).'),
(10, 'Ensure test type lot number is reported for all performed tests. ', 'Result not evaluated Ð Test Platform lot number (first/second/third) is not reported.'),
(11, 'Ensure to provide supervisor approval along with his name.', 'Missing supervisor approval for reported result.'),
(12, 'Ensure to provide sample rehydration date', 'Re-hydration date missing in PT report form.'),
(13, 'Ensure to provide to provide panel testing date.', 'Testing date missing in PT report form.'),
(14, 'SARS CoV-2 Testing should be done within specified hours of rehydration as per SOP.', 'Testing is not performed within X to Y hours of rehydration.'),
(15, 'Review all testing procedures prior to performing client testing and contact your supervisor for improvement.', 'Participant did not meet the score criteria (Participant Score is 80 and Required Score is 95)'),
(16, 'Ensure to provide to provide panel receipt date. ', 'Panel receipt date missing in PT report form.'),
(17, 'Please test Covid19 sample as per National Covid-19 Testing lab. Review and refer to SOP for testing.', 'For Test (1/2/3) testing is not performed with country approved test type.');

--  Thana 06-Jan-2021
CREATE TABLE `reference_covid19_test_type` (
 `id` int NOT NULL AUTO_INCREMENT,
 `shipment_id` varchar(255) NOT NULL,
 `sample_id` varchar(255) NOT NULL,
 `test_type` varchar(255) NOT NULL,
 `lot_no` varchar(255) NOT NULL,
 `expiry_date` date NOT NULL,
 `result` varchar(255) NOT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--  Thana 11-Jan-2021
CREATE TABLE `response_covid19_not_tested_reason` (
 `covid19_not_tested_reason_id` int NOT NULL AUTO_INCREMENT,
 `covid19_not_tested_reason` varchar(500) DEFAULT NULL,
 `status` varchar(45) NOT NULL DEFAULT 'active',
 PRIMARY KEY (`covid19_not_tested_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

INSERT INTO `response_covid19_not_tested_reason` (`covid19_not_tested_reason_id`, `covid19_not_tested_reason`, `status`) VALUES
(1, 'Issue with Sample', 'active'),
(2, 'Machine not working', 'active'),
(3, 'Other', 'active');

ALTER TABLE `shipment_participant_map` ADD `number_of_tests` INT(11) NULL DEFAULT NULL AFTER `shipment_test_date`;

--  Amit 1 Jan 2021

ALTER TABLE `shipment_participant_map` CHANGE `shipment_test_date` `shipment_test_date` DATE NULL DEFAULT NULL;

--  Thana 22-Jan-2021
ALTER TABLE `data_manager` ADD `api_token_generated_datetime` DATETIME NULL DEFAULT NULL AFTER `auth_token`;


--  Amit 01-Feb-2021
ALTER TABLE `reference_vl_calculation` ADD `standard_uncertainty` DOUBLE(20,10) NULL DEFAULT NULL AFTER `sd`;
ALTER TABLE `reference_vl_calculation` ADD `is_uncertainty_acceptable` VARCHAR(255) NULL DEFAULT NULL AFTER `standard_uncertainty`;

ALTER TABLE `reference_vl_calculation` ADD `median` DOUBLE(20,10) NULL DEFAULT NULL AFTER `mean`;

--  Amit 05-Feb-2021

ALTER TABLE `response_result_vl` ADD `z_score` DOUBLE(20,10) NULL DEFAULT NULL AFTER `reported_viral_load`;
ALTER TABLE `reference_vl_calculation` ADD `no_of_responses` INT NULL DEFAULT NULL AFTER `vl_assay`;

--  Amit 09-Feb-2021

ALTER TABLE `reference_vl_calculation` ADD `manual_standard_uncertainty` DOUBLE(20,10) NULL DEFAULT NULL AFTER `manual_sd`;
ALTER TABLE `reference_vl_calculation` ADD `manual_is_uncertainty_acceptable` VARCHAR(255) NULL DEFAULT NULL AFTER `manual_standard_uncertainty`;
ALTER TABLE `reference_vl_calculation` ADD `manual_median` DOUBLE(20,10) NULL DEFAULT NULL AFTER `manual_mean`;

--  Thana 11-Feb-2021
ALTER TABLE `response_result_vl` ADD `vl_assay` VARCHAR(255) NULL DEFAULT NULL AFTER `calculated_score`;

--  Thana 12-Feb-2021
ALTER TABLE `shipment` ADD `pt_co_ordinator_name` TEXT NULL DEFAULT NULL AFTER `shipment_comment`;

--  Thana 04-Mar-2021
CREATE TABLE `r_covid19_gene_types` (
 `gene_id` int NOT NULL AUTO_INCREMENT,
 `gene_name` varchar(255) DEFAULT NULL,
 `gene_status` varchar(55) DEFAULT NULL,
 `created_by` int DEFAULT NULL,
 `created_on` datetime DEFAULT NULL,
 PRIMARY KEY (`gene_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `r_covid19_gene_types` ADD `scheme_type` varchar(255) NULL DEFAULT NULL AFTER `gene_name`;

CREATE TABLE `covid19_identified_genes` (
 `map_id` int NOT NULL,
 `shipment_id` int NOT NULL,
 `sample_id` int NOT NULL,
 `gene_id` int DEFAULT NULL,
 `ct_value` varchar(255) DEFAULT NULL,
 `remarks` text,
 KEY `map_id` (`map_id`),
 KEY `shipment_id` (`shipment_id`),
 CONSTRAINT `covid19_identified_genes_ibfk_1` FOREIGN KEY (`map_id`) REFERENCES `shipment_participant_map` (`map_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT `covid19_identified_genes_ibfk_2` FOREIGN KEY (`shipment_id`) REFERENCES `shipment` (`shipment_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--  Thana 08-Mar-2021
UPDATE `global_config` SET `name` = 'pt_program_name' WHERE `global_config`.`name` = 'text_under_logo';
UPDATE `global_config` SET `value` = 'EQA Proficiency Testing' WHERE `global_config`.`name` = 'pt_program_name';
INSERT INTO `global_config` (`name`, `value`) VALUES ('pt_program_short_name', 'EQA PT');
--  Thana 09-Mar-2021
ALTER TABLE `shipment_participant_map` ADD `specimen_volume` VARCHAR(255) NULL DEFAULT NULL AFTER `number_of_tests`;
--  Thana 16-Mar-2021
INSERT INTO `global_config` (`name`, `value`) VALUES ('training_instance', 'no'), ('training_instance_text', '');
--  Thana 17-Mar-2021
ALTER TABLE `covid19_identified_genes` ADD FOREIGN KEY (`gene_id`) REFERENCES `r_covid19_gene_types`(`gene_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
ALTER TABLE `covid19_identified_genes` ADD `gene_map_id` INT(11) NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`gene_map_id`);


--  Amit 19-April-2021

UPDATE `scheme_list` SET `scheme_name` = 'Rapid Test for Recent Infection (RTRI)' WHERE `scheme_list`.`scheme_id` = 'recency';


--  Amit 14-May-2021

ALTER TABLE `r_vl_assay` ADD `status` VARCHAR(256) NULL DEFAULT 'active' AFTER `short_name`;
ALTER TABLE `r_eid_detection_assay` ADD `status` VARCHAR(256) NULL DEFAULT 'active' AFTER `sort_order`;
ALTER TABLE `r_eid_extraction_assay` ADD `status` VARCHAR(256) NULL DEFAULT 'active' AFTER `sort_order`;

--  Thana 18-Jun-2021
ALTER TABLE `reference_dts_wb` ADD `result` VARCHAR(256) NULL DEFAULT NULL AFTER `17`;
ALTER TABLE `reference_dts_eia` ADD `result` VARCHAR(556) NULL DEFAULT NULL AFTER `cutoff`;

CREATE TABLE `reference_dts_geenius` (
 `id` int NOT NULL AUTO_INCREMENT,
 `shipment_id` int DEFAULT NULL,
 `sample_id` varchar(256) DEFAULT NULL,
 `lot_no` varchar(256) DEFAULT NULL,
 `expiry_date` date DEFAULT NULL,
 `result` varchar(256) DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--  Thana 22-Jun-2021
CREATE TABLE `reference_recency_assay` (
 `id` int NOT NULL AUTO_INCREMENT,
 `shipment_id` int DEFAULT NULL,
 `sample_id` varchar(256) DEFAULT NULL,
 `assay` varchar(256) DEFAULT NULL,
 `lot_no` varchar(256) DEFAULT NULL,
 `expiry_date` date DEFAULT NULL,
 `result` varchar(256) DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--  Amit 07 July 2021
ALTER TABLE `shipment_participant_map` ADD `user_client_info` JSON NULL DEFAULT NULL AFTER `mode_of_response`;
--  Thana 12-July-2021
ALTER TABLE `shipment_participant_map` ADD `manual_override` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `mode_id`;

--  Amit 20-July-2021
UPDATE `shipment_participant_map` set shipment_test_date = NULL WHERE is_pt_test_not_performed = 'yes';


DROP TABLE IF EXISTS `system_config`;
CREATE TABLE `system_config` (
 `config` varchar(256) NOT NULL,
 `value` mediumtext,
 `display_name` mediumtext,
 PRIMARY KEY (`config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `system_config` (`config`, `value`, `display_name`) VALUES
('app_version', '7.0.0', 'App Version');
--  Version 7.0.0 Amit 20-July-2020

--  Thana 23-July-2021
INSERT INTO `global_config` (`name`, `value`) VALUES ('theme_color', 'blue');

--  Amit 28-July-2021
UPDATE `system_config` SET `value` = '7.1.0' WHERE `system_config`.`config` = 'app_version';

--  Amit 04-Aug-2021
UPDATE `system_config` SET `value` = '7.2.0' WHERE `system_config`.`config` = 'app_version';

--  Thana 10-Aug-2021
ALTER TABLE `shipment` ADD `corrective_action_file` VARCHAR(256) NULL DEFAULT NULL AFTER `report_in_queue`;

--  Thana 07-Sep-2021
ALTER TABLE `response_result_dts` ADD `repeat_test_result_1` VARCHAR(256) NULL DEFAULT NULL AFTER `test_result_1`;
ALTER TABLE `response_result_dts` ADD `repeat_test_result_2` VARCHAR(256) NULL DEFAULT NULL AFTER `test_result_2`;
ALTER TABLE `response_result_dts` ADD `repeat_test_result_3` VARCHAR(256) NULL DEFAULT NULL AFTER `test_result_3`;


--  Amit 09-Sep-2021
RENAME TABLE `response_vl_not_tested_reason` TO `r_response_vl_not_tested_reason`;



--  Thana 09-Sep-2021
CREATE TABLE `enrollment_lists_names` (
 `eln_id` int NOT NULL AUTO_INCREMENT,
 `eln_unique_id` varchar(256) NOT NULL,
 `eln_name` varchar(256) NOT NULL,
 `participant_id` int NOT NULL,
 PRIMARY KEY (`eln_id`),
 KEY `participant_id` (`participant_id`),
 CONSTRAINT `enrollment_lists_names_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participant` (`participant_id`) ON DELETE RESTRICT ON UPDATE RESTRICT

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `enrollment_lists_names` ADD `added_by` INT(11) NULL DEFAULT NULL AFTER `participant_id`, ADD `added_on` DATETIME NULL DEFAULT NULL AFTER `added_by`, ADD `updated_by` INT(11) NULL DEFAULT NULL AFTER `added_on`, ADD `updated_on` DATETIME NULL DEFAULT NULL AFTER `updated_by`;

--  Amit 14-Sep-2021
ALTER TABLE `r_possibleresult` ADD UNIQUE( `scheme_sub_group`, `result_code`);

--  Thana 15-Sep-2021
ALTER TABLE `participant` ADD `lab_director_name` VARCHAR(256) NULL DEFAULT NULL AFTER `department_name`, ADD `lab_director_email` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_director_name`;
ALTER TABLE `participant` ADD `contact_person_name` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_director_email`, ADD `contact_person_email` VARCHAR(256) NULL DEFAULT NULL AFTER `contact_person_name`, ADD `contact_person_telephone` VARCHAR(256) NULL DEFAULT NULL AFTER `contact_person_email`;
ALTER TABLE `shipment_participant_map` ADD `lab_director_name` VARCHAR(256) NULL DEFAULT NULL AFTER `participant_id`, ADD `lab_director_email` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_director_name`, ADD `contact_person_name` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_director_email`, ADD `contact_person_email` VARCHAR(256) NULL DEFAULT NULL AFTER `contact_person_name`, ADD `contact_person_telephone` VARCHAR(256) NULL DEFAULT NULL AFTER `contact_person_email`;


--  Amit 16 Sep 2021
DELETE FROM `r_dts_corrective_actions` WHERE `r_dts_corrective_actions`.`action_id` = 18;
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES (18, 'Please ensure condition of PT Samples is reported', 'Please ensure condition of PT Samples is reported');
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES (19, 'Please ensure Refridgerator availability is reported', 'Please ensure Refridgerator availability is reported');
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES (20, 'Please ensure Room Temperature is reported', 'Please ensure Room Temperature is reported');
INSERT INTO `r_dts_corrective_actions` (`action_id`, `corrective_action`, `description`) VALUES (21, 'Please ensure Stop Watch availability is reported', 'Please ensure Stop Watch availability is reported');

--  Thana 20 Sep 2021
INSERT INTO `report_config` (`name`, `value`) VALUES ('institute-address-postition', 'header');

--  Amit 21 Sep 2021
ALTER TABLE `r_response_vl_not_tested_reason` ADD `collect_panel_receipt_date` VARCHAR(256) NULL DEFAULT 'yes' AFTER `vl_not_tested_reason`;
UPDATE `r_response_vl_not_tested_reason` SET `collect_panel_receipt_date` = 'no' WHERE `r_response_vl_not_tested_reason`.`vl_not_tested_reason_id` = 8; UPDATE `r_response_vl_not_tested_reason` SET `collect_panel_receipt_date` = 'no' WHERE `r_response_vl_not_tested_reason`.`vl_not_tested_reason_id` = 9; UPDATE `r_response_vl_not_tested_reason` SET `collect_panel_receipt_date` = 'no' WHERE `r_response_vl_not_tested_reason`.`vl_not_tested_reason_id` = 10; UPDATE `r_response_vl_not_tested_reason` SET `collect_panel_receipt_date` = 'no' WHERE `r_response_vl_not_tested_reason`.`vl_not_tested_reason_id` = 11;

--  Amit 23-Sep-2021
UPDATE `system_config` SET `value` = '7.3.0' WHERE `system_config`.`config` = 'app_version';

--  Thana 24-Sep-2021
ALTER TABLE `response_result_covid19` ADD `name_of_pcr_reagent_1` VARCHAR(256) NULL DEFAULT NULL AFTER `test_type_1`, ADD `pcr_reagent_lot_no_1` VARCHAR(256) NULL DEFAULT NULL AFTER `name_of_pcr_reagent_1`, ADD `pcr_reagent_exp_date_1` DATE NULL DEFAULT NULL AFTER `pcr_reagent_lot_no_1`;
ALTER TABLE `response_result_covid19` ADD `name_of_pcr_reagent_2` VARCHAR(256) NULL DEFAULT NULL AFTER `test_type_2`, ADD `pcr_reagent_lot_no_2` VARCHAR(256) NULL DEFAULT NULL AFTER `name_of_pcr_reagent_2`, ADD `pcr_reagent_exp_date_2` DATE NULL DEFAULT NULL AFTER `pcr_reagent_lot_no_2`;
ALTER TABLE `response_result_covid19` ADD `name_of_pcr_reagent_3` VARCHAR(256) NULL DEFAULT NULL AFTER `test_type_3`, ADD `pcr_reagent_lot_no_3` VARCHAR(256) NULL DEFAULT NULL AFTER `name_of_pcr_reagent_3`, ADD `pcr_reagent_exp_date_3` DATE NULL DEFAULT NULL AFTER `pcr_reagent_lot_no_3`;



--  Amit 29 Sep 2021
  CREATE TABLE `participants_not_uploaded` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `s_no` text,
  `participant_id` text,
  `individual` text,
  `participant_lab_name` text,
  `participant_last_name` text,
  `institute_name` text,
  `department` text,
  `address` text,
  `district` text,
  `province` text,
  `country` text,
  `zip` text,
  `longitude` text,
  `latitude` text,
  `mobile_number` text,
  `participant_email` text,
  `participant_password` text,
  `additional_email` text,
  `filename` text,
  `error` text,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--  Thana 01-Oct-2021
ALTER TABLE `response_result_dts` ADD `repeat_test_kit_name_1` VARCHAR(256) NULL DEFAULT NULL AFTER `test_kit_name_1`;
ALTER TABLE `response_result_dts` ADD `repeat_test_kit_name_2` VARCHAR(256) NULL DEFAULT NULL AFTER `test_kit_name_2`;
ALTER TABLE `response_result_dts` ADD `repeat_test_kit_name_3` VARCHAR(256) NULL DEFAULT NULL AFTER `test_kit_name_3`;
ALTER TABLE `response_result_dts` ADD `repeat_lot_no_1` VARCHAR(256) NULL DEFAULT NULL AFTER `lot_no_1`;
ALTER TABLE `response_result_dts` ADD `repeat_lot_no_2` VARCHAR(256) NULL DEFAULT NULL AFTER `lot_no_2`;
ALTER TABLE `response_result_dts` ADD `repeat_lot_no_3` VARCHAR(256) NULL DEFAULT NULL AFTER `lot_no_3`;
ALTER TABLE `response_result_dts` ADD `repeat_exp_date_1` date NULL DEFAULT NULL AFTER `exp_date_1`;
ALTER TABLE `response_result_dts` ADD `repeat_exp_date_2` date NULL DEFAULT NULL AFTER `exp_date_2`;
ALTER TABLE `response_result_dts` ADD `repeat_exp_date_3` date NULL DEFAULT NULL AFTER `exp_date_3`;

--  Thana 12-Oct-2021
CREATE TABLE `r_response_not_tested_reasons` (
 `ntr_id` int NOT NULL AUTO_INCREMENT,
 `ntr_reason` varchar(256) DEFAULT NULL,
 `ntr_test_type` varchar(256) DEFAULT NULL COMMENT 'vl, eid, dts, covid19, recency, dbs',
 `ntr_status` varchar(256) DEFAULT NULL,
 PRIMARY KEY (`ntr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


--  Thana 13-Oct-2021
ALTER TABLE `shipment_participant_map` ADD `received_pt_panel` VARCHAR(256) NULL DEFAULT NULL AFTER `vl_not_tested_reason`;

--  Thana 27-Oct-2021
ALTER TABLE `r_response_not_tested_reasons` ADD `collect_panel_receipt_date` VARCHAR(256) NOT NULL DEFAULT 'no' AFTER `ntr_test_type`;
ALTER TABLE `r_response_not_tested_reasons` CHANGE `ntr_test_type` `ntr_test_type` JSON NULL DEFAULT NULL;
DELETE FROM `r_response_not_tested_reasons`;
INSERT INTO `r_response_not_tested_reasons` (`ntr_id`, `ntr_reason`, `ntr_test_type`, `ntr_status`) VALUES
(1, 'No reagents for testing of PT panel', '["vl","eid","dts","covid19","recency"]', 'active'),
(2, 'No lab personal for testing of PT panel', '["vl","eid","dts","covid19","recency"]', 'active'),
(3, ' Instrument down', '["vl","eid","dts","covid19","recency"]', 'active'),
(4, 'Laboratory facility under renovation', '["vl","eid","dts","covid19","recency"]', 'active'),
(5, 'Laboratory facility no longer perform testing', '["vl","eid","dts","covid19","recency"]', 'active'),
(6, 'The results were invalid for the entire run', '["vl","eid","dts","covid19","recency"]', 'active'),
(7, 'The PT panel testing failed during sample processing', '["vl","eid","dts","covid19","recency"]', 'active'),
(8, 'The PT panel shipment was lost/damage', '["vl","eid","dts","covid19","recency"]', 'active'),
(9, 'Not received PT panel shipment due to country custom clearance issue', '["vl","eid","dts","covid19","recency"]', 'active'),
(10, 'Not received PT panel shipment due to incorrect contact info on the shipment package', '["vl","eid","dts","covid19","recency"]', 'active'),
(11, 'Issue with Sample' ,'["vl","eid","dts","covid19","recency"]', 'active'),
(12, 'Machine not working' ,'["vl","eid","dts","covid19","recency"]', 'active');

--  Amit 03 Nov 2021

UPDATE `shipment_participant_map` SET attributes = NULL where attributes like '';
ALTER TABLE `shipment_participant_map` CHANGE `attributes` `attributes` JSON NULL DEFAULT NULL;


--  Amit 14-Dec-2021
UPDATE `system_config` SET `value` = '7.2.1' WHERE `system_config`.`config` = 'app_version';

--  Thana 20-Jan-2022
CREATE TABLE `audit_log` (
 `audit_log_id` int NOT NULL AUTO_INCREMENT,
 `statement` text COLLATE utf8mb4_general_ci,
 `created_by` VARCHAR(256) NULL DEFAULT NULL,
 `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `type` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 PRIMARY KEY (`audit_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--  Thana 16-Feb-2022
CREATE TABLE `certificate_templates` (
 `ct_id` int NOT NULL AUTO_INCREMENT,
 `scheme_type` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `participation_certificate` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `excellence_certificate` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `created_by` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `updated_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`ct_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--  Thana 22-Feb-2022
CREATE TABLE `scheduled_jobs` (
 `job_id` int NOT NULL AUTO_INCREMENT,
 `job` text COLLATE utf8mb4_general_ci,
 `requested_on` datetime DEFAULT NULL,
 `requested_by` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `completed_on` datetime DEFAULT NULL,
 `status` varchar(256) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
 PRIMARY KEY (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Amit 02-Mar-2022
ALTER TABLE `r_test_type_covid19` CHANGE `test_type_id` `test_type_id` INT NOT NULL AUTO_INCREMENT;

-- Thana 08-Apr-2022
ALTER TABLE `participant` ADD `district` VARCHAR(256) NULL DEFAULT NULL AFTER `state`;

-- Thana 19-Apr-2022
ALTER TABLE `participant` ADD `anc` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `site_type`;
ALTER TABLE `response_result_dts` ADD `is_this_retest` VARCHAR(50) NULL DEFAULT NULL AFTER `reported_result`;
ALTER TABLE `response_result_dts` ADD `syphilis_result` VARCHAR(256) NULL DEFAULT NULL AFTER `test_result_1`;
ALTER TABLE `response_result_dts` ADD `syphilis_final` VARCHAR(256) NULL DEFAULT NULL AFTER `reported_result`;

-- Thana 01-Jun-2022
ALTER TABLE `reference_result_dts` ADD `syphilis_reference_result` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `reference_result`;
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`) VALUES (24, 'dts', 'DTS_FINAL', 'INVALID', 'INV');
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`) VALUES (25, 'dts', 'DTS_SYP_TEST', 'REACTIVE', 'R'), (26, 'dts', 'DTS_SYP_TEST', 'NONREACTIVE', 'NR'), (27, 'dts', 'DTS_SYP_TEST', 'INVALID', 'INV');
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`) VALUES (28, 'dts', 'DTS_SYP_FINAL', 'POSITIVE', 'P'), (29, 'dts', 'DTS_SYP_FINAL', 'NEGATIVE', 'N'), (30, 'dts', 'DTS_SYP_FINAL', 'INDETERMINATE', 'IND');

-- Amit 24-Jun-2022
ALTER TABLE `dts_recommended_testkits` ADD `dts_test_mode` VARCHAR(256) NULL DEFAULT 'dts' AFTER `testkit`;
ALTER TABLE `dts_recommended_testkits` DROP PRIMARY KEY, ADD PRIMARY KEY(
     `test_no`,
     `testkit`,
     `dts_test_mode`);

-- Thana 28-Jun-2022
ALTER TABLE `data_manager` ADD `language` VARCHAR(256) NULL DEFAULT 'en_US' AFTER `new_email`;

-- Thana 04-Jul-2022
INSERT INTO `global_config` (`name`, `value`) VALUES ('home_left_logo', NULL), ('home_right_logo', NULL);

-- Amit 15-Jul-2022
ALTER TABLE `shipment_participant_map` ADD `is_response_late` VARCHAR(256) NULL DEFAULT NULL AFTER `shipment_test_report_date`;

-- Thana 26-Jul-2022
ALTER TABLE `response_result_dts` ADD `dts_rtri_control_line` VARCHAR(256) NULL DEFAULT NULL AFTER `is_this_retest`, ADD `dts_rtri_diagnosis_line` VARCHAR(256) NULL DEFAULT NULL AFTER `dts_rtri_control_line`, ADD `dts_rtri_longterm_line` VARCHAR(256) NULL DEFAULT NULL AFTER `dts_rtri_diagnosis_line`, ADD `dts_rtri_reported_result` VARCHAR(256) NULL DEFAULT NULL AFTER `dts_rtri_longterm_line`;

-- Thana 02-Aug-2022
ALTER TABLE `response_result_dts` ADD `dts_rtri_is_editable` VARCHAR(256) NULL DEFAULT 'no' AFTER `calculated_score`;

-- Amit 04-Aug-2022
ALTER TABLE `reference_result_dts` ADD `dts_rtri_reference_result` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `syphilis_reference_result`;

-- Amit 13-Aug-2022
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`) VALUES (31, 'dts', 'DTS_FINAL', 'Not Reported', 'NOTREPORTED');


--  Amit 24-Aug-2021
UPDATE `system_config` SET `value` = '7.2.2' WHERE `system_config`.`config` = 'app_version';

--  Thana 16-Dec-2022
ALTER TABLE `shipment_participant_map` ADD `response_status` VARCHAR(256) NULL DEFAULT NULL AFTER `user_client_info`;

--  Thana 06-Jan-2022
ALTER TABLE `response_result_tb` ADD `test_date` DATE NULL DEFAULT NULL AFTER `probe_a`, ADD `tester_name` VARCHAR(256) NULL DEFAULT NULL AFTER `test_date`, ADD `error_code` VARCHAR(256) NULL DEFAULT NULL AFTER `tester_name`;

--  Thana 17-Jan-2022
CREATE TABLE `r_tb_assay` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(255) NOT NULL,
  `assay_type` varchar(255) NOT NULL DEFAULT 'specific',
  `drug_resistance_test` varchar(255) NOT NULL DEFAULT 'yes',
  `status` varchar(256) DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

INSERT INTO `r_tb_assay` (`id`, `name`, `short_name`, `assay_type`, `drug_resistance_test`, `status`) VALUES (NULL, 'Xpert MTB RIF', 'xpert-mtb-rif', 'specific', 'yes', 'active'), (NULL, 'Xpert MTB RIF Ultra', 'xpert-mtb-rif-ultra', 'specific', 'yes', 'active'), (NULL, 'Molbio Truenat TB', 'molbio-truenat-tb', 'specific', 'yes', 'active'), (NULL, 'Molbio Truenat Plus', 'molbio-truenat-plus', 'specific', 'yes', 'active'), (NULL, 'Ref-Molbio TB-RIF Dx', 'ref-molbio-tb-rif-dx', 'specific', 'yes', 'active'), (NULL, 'Other Assay', 'other', 'generic', 'yes', 'active');

-- Thana 27-Jan-2023
ALTER TABLE `system_admin` ADD `scheme` TEXT NULL DEFAULT NULL AFTER `force_password_reset`;

-- Thana 20-Feb-2023
UPDATE `shipment_participant_map` set response_status = 'noresponse' WHERE response_status is null;
UPDATE `shipment_participant_map` set response_status = 'responded' where shipment_test_report_date is not null and DATE(shipment_test_report_date) > 1970-01-01;

-- Thana 01-Mar-2023
ALTER TABLE `reference_result_tb` ADD `assay_name` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;

-- Amit 02-Mar-2023
ALTER TABLE `reference_result_tb` ADD PRIMARY KEY(`shipment_id`, `sample_id`, `assay_name`);
ALTER TABLE `response_result_tb` DROP `date_tested`;
ALTER TABLE `response_result_tb` ADD `assay_id` INT NOT NULL AFTER `sample_id`;
ALTER TABLE `response_result_tb` ADD PRIMARY KEY(`shipment_map_id`, `sample_id`, `assay_id`);

-- Thana 03-Mar-2023
ALTER TABLE `response_result_tb` ADD `response_attributes` JSON NULL DEFAULT NULL AFTER `sample_id`;
ALTER TABLE `reference_result_tb` ADD `request_attributes` JSON NULL DEFAULT NULL AFTER `sample_id`;

-- Thana 06-Mar-2023
ALTER TABLE `response_result_tb` ADD `is1081_is6110` VARCHAR(256) NULL DEFAULT NULL AFTER `test_date`, ADD `rpo_b1` VARCHAR(256) NULL DEFAULT NULL AFTER `is1081_is6110`, ADD `rpo_b2` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b1`, ADD `rpo_b3` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b2`, ADD `rpo_b4` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b3`;

-- Thana 07-Mar-2023
ALTER TABLE `reference_result_tb` ADD `is1081_is6110` VARCHAR(256) NULL DEFAULT NULL AFTER `probe_a`, ADD `rpo_b1` VARCHAR(256) NULL DEFAULT NULL AFTER `is1081_is6110`, ADD `rpo_b2` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b1`, ADD `rpo_b3` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b2`, ADD `rpo_b4` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b3`;

-- Thana 09-Mar-2023
CREATE TABLE `reference_result_generic_test` (
  `shipment_id` int NOT NULL,
  `sample_id` int NOT NULL,
  `sample_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reference_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `control` int DEFAULT NULL,
  `mandatory` int NOT NULL DEFAULT '0',
  `sample_score` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`shipment_id`,`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `response_result_generic_test` (
  `shipment_map_id` int NOT NULL,
  `sample_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `repeat_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reported_result` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `additional_detail` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `comments` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `calculated_score` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_on` datetime DEFAULT NULL,
  `updated_by` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`shipment_map_id`, `sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `scheme_list` CHANGE `scheme_id` `scheme_id` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `shipment` CHANGE `scheme_type` `scheme_type` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
INSERT INTO `scheme_list` (`scheme_id`, `scheme_name`, `response_table`, `reference_result_table`, `attribute_list`, `status`) VALUES ('generic-test', 'Generic Test', 'response_result_generic_test', 'reference_result_generic_test', NULL, 'active');

-- Thana 16-Mar-2023
ALTER TABLE `reference_result_tb` DROP PRIMARY KEY;
ALTER TABLE `reference_result_tb` DROP `assay_name`;
ALTER TABLE `reference_result_tb` ADD PRIMARY KEY(`shipment_id`, `sample_id`);

-- Thana 20-Mar-2023
ALTER TABLE `reference_result_tb` ADD `tb_isolate` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_label`;

-- Thana 22-Mar-2023
ALTER TABLE `response_result_tb` ADD `gene_xpert_module_no` VARCHAR(256) NULL DEFAULT NULL AFTER `rpo_b4`;

-- Thana 29-Mar-2023
ALTER TABLE `shipment` ADD `issuing_authority` VARCHAR(256) NULL DEFAULT NULL AFTER `shipment_comment`;

-- Thana 10-Apr-2023
ALTER TABLE `reference_result_tb` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_id`;
ALTER TABLE `reference_result_covid19` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `reference_result_dbs` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `reference_result_dts` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `reference_result_eid` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `reference_result_generic_test` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `reference_result_recency` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `reference_result_vl` ADD `sample_preparation_date` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_label`;
ALTER TABLE `data_manager` ADD `ptcc` VARCHAR(256) NOT NULL DEFAULT 'no' AFTER `institute`;
CREATE TABLE `ptcc_countries_map` (
  `ptcc_id` int NOT NULL,
  `country_id` int NOT NULL,
  `mapped_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `ptcc_id` (`ptcc_id`),
  CONSTRAINT `ptcc_countries_map_ibfk_1` FOREIGN KEY (`ptcc_id`) REFERENCES `data_manager` (`dm_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Thana 11-Apr-2023
ALTER TABLE `data_manager` ADD `country_id` INT NULL DEFAULT NULL AFTER `secondary_email`;


-- Amit 13-Apr-2023

ALTER TABLE `partners` CHANGE `link` `link` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `partners` ADD `logo_image` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

INSERT INTO `partners` (`partner_id`, `partner_name`, `link`, `logo_image`, `sort_order`, `added_by`, `added_on`, `status`) VALUES
(1, 'PEPFAR', 'https://www.state.gov/pepfar/', 'pepfar.jpg', 1, 0, '2023-04-13 15:35:04', 'active');

-- Thana 02-May-2023
ALTER TABLE `contact_us` ADD `participant_id` VARCHAR(256) NULL DEFAULT NULL AFTER `additional_info`, ADD `subject` VARCHAR(256) NULL DEFAULT NULL AFTER `participant_id`, ADD `country` VARCHAR(256) NULL DEFAULT NULL AFTER `subject`, ADD `message` TEXT NULL DEFAULT NULL AFTER `country`;

-- Thana 03-May-2023
ALTER TABLE `r_response_not_tested_reasons` ADD `reason_code` VARCHAR(50) NULL DEFAULT NULL AFTER `collect_panel_receipt_date`;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'A' WHERE `r_response_not_tested_reasons`.`ntr_id` = 1;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'B' WHERE `r_response_not_tested_reasons`.`ntr_id` = 2;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'C' WHERE `r_response_not_tested_reasons`.`ntr_id` = 3;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'D' WHERE `r_response_not_tested_reasons`.`ntr_id` = 4;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'E' WHERE `r_response_not_tested_reasons`.`ntr_id` = 5;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'F' WHERE `r_response_not_tested_reasons`.`ntr_id` = 6;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'G' WHERE `r_response_not_tested_reasons`.`ntr_id` = 7;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'H' WHERE `r_response_not_tested_reasons`.`ntr_id` = 8;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'I' WHERE `r_response_not_tested_reasons`.`ntr_id` = 9;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'J' WHERE `r_response_not_tested_reasons`.`ntr_id` = 10;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'K' WHERE `r_response_not_tested_reasons`.`ntr_id` = 11;
UPDATE `r_response_not_tested_reasons` SET `reason_code` = 'L' WHERE `r_response_not_tested_reasons`.`ntr_id` = 12;

-- Thana 21-Jun-2023
ALTER TABLE `shipment_participant_map` CHANGE `response_status` `response_status` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'noresponse';

-- Thana 28-Jun-2023
CREATE TABLE `home_sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `link` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `text` text COLLATE utf8mb4_general_ci,
  `icon` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `display_order` int DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
ALTER TABLE `home_sections` ADD `modified_by` VARCHAR(256) NULL DEFAULT NULL AFTER `status`, ADD `modified_date_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `modified_by`;

-- Amit 04-Jul-2023
DROP TABLE `publications`;
ALTER TABLE shipment DROP FOREIGN KEY shipment_ibfk_1;

-- Thana 31-Jul-2023
INSERT INTO `r_possibleresult` (`id`, `scheme_id`, `scheme_sub_group`, `response`, `result_code`)
VALUES
(NULL, 'tb', 'TB_MOLECULAR_FINAL', 'DETECTED', 'detected'),
(NULL, 'tb', 'TB_MOLECULAR_FINAL', 'NOT DETECTED', 'not-detected'),
(NULL, 'tb', 'TB_MOLECULAR_FINAL', 'ERROR', 'error'),
(NULL, 'tb', 'TB_MOLECULAR_FINAL', 'INVALID', 'invalid'),
(NULL, 'tb', 'TB_MICROSCOPY_FINAL', 'NEGATIVE', 'negative'),
(NULL, 'tb', 'TB_MICROSCOPY_FINAL', 'SCANTY', 'scanty'),
(NULL, 'tb', 'TB_MICROSCOPY_FINAL', '1+', '1+'),
(NULL, 'tb', 'TB_MICROSCOPY_FINAL', '2+', '2+'),
(NULL, 'tb', 'TB_MICROSCOPY_FINAL', '3+', '3+');
