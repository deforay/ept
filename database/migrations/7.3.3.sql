-- Migration for version 7.3.3

UPDATE `system_config` SET `value` = '7.3.3' WHERE `config` = 'app_version';

CREATE TABLE IF NOT EXISTS `run_once_scripts` (
  `script_name` VARCHAR(255) NOT NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`script_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
