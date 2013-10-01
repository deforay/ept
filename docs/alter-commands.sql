-- By Amit on 15 Sep 2013
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
-- INSERT INTO `eanalyze`.`schemelist` (`SchemeID`, `SchemeName`, `ShipmentTable`, `ResponseTable`, `ReferanceResultTable`) VALUES ('DTS', 'Dried Tube Specimen', NULL, NULL, NULL), ('VL', 'Viral Load', NULL, NULL, NULL);

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

DELIMITER $$
DROP PROCEDURE `SHIPMENT_OVERVIEW`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `SHIPMENT_OVERVIEW`()
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
group by SHIP_YEAR 

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

DROP PROCEDURE IF EXISTS `SHIPMENT_CURRENT`$$
CREATE PROCEDURE `SHIPMENT_CURRENT`(IN uId varchar(45) )
BEGIN
Select year(a.ShipmentDate) as SHIP_YEAR,   
'DTS' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
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
left join participant as b on a.ParticipantID = b.ParticipantSystemID where year(a.ShipmentDate)  + 5 > year(CURDATE()) 
and a.LASTDATERESPONSE >= CURDATE() 
-- and a.ParticipantID in (Select ParticipantID from participant where UserSystemId = '1')

union

Select year(a.shipment_date) as SHIP_YEAR,   
'VL' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
a.participant_id,
a.shipment_date, 
DATE_FORMAT(a.shipment_test_report_date, '%Y-%m-%d') as RESPONSEDATE,
a.lastdate_response, 
a.vl_shipment_id as SHIPID,
a.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(a.evaluation_status,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.evaluation_status,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment_vl  as a 
left join participant as b on a.participant_id = b.ParticipantSystemID where year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response >= CURDATE() 

union

Select year(a.shipment_date) as SHIP_YEAR,   
'EID' AS SCHEME,
b.ParticipantFName as FNAME,b.ParticipantLName as LNAME,
a.participant_id,
a.shipment_date, 
DATE_FORMAT(a.shipment_test_report_date, '%Y-%m-%d') as RESPONSEDATE,
a.lastdate_response, 
a.eid_shipment_id as SHIPID,
a.evaluation_status as EVALUATIONSTATUS, 

	CASE  substr(a.evaluation_status,3,1)
	WHEN '1' THEN   'View'
	WHEN '9' THEN   'Enter Result'
	END 
	as 'RESPONSE' ,


	CASE  substr(a.evaluation_status,3,1)
	WHEN '1' THEN   'Report'
	END
	as 'REPORTSTATUS' 
from shipment_eid  as a 
left join participant as b on a.participant_id = b.ParticipantSystemID where year(a.shipment_date)  + 5 > year(CURDATE()) 
and a.lastdate_response >= CURDATE() 

-- and a.participant_id in (Select ParticipantID from participant where UserSystemId = uId)
order by SHIP_YEAR, ParticipantID ;

END$$




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

DROP PROCEDURE IF EXISTS `SHIPMENT_DEFAULTED`$$
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
END$$



CREATE TABLE IF NOT EXISTS `reference_result_vl` (
  `vl_shipment_id` varchar(255) NOT NULL,
  `vl_sample_id` int(11) NOT NULL,
  `vl_sample_label` varchar(255) DEFAULT NULL,
  `reference_viral_load` varchar(45) DEFAULT NULL,
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
ALTER TABLE  `eanalyze`.`users` DROP PRIMARY KEY , ADD PRIMARY KEY (  `UserSystemID` );
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