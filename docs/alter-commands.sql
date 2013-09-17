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
