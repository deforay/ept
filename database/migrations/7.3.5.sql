-- Migration for version 7.3.5
-- Amit Dugar - Feb 2026
-- Create certificate_batches table for tracking certificate generation batches
-- Add detected_fields columns to certificate_templates table

UPDATE `system_config` SET `value` = '7.3.5' WHERE `config` = 'app_version';

-- Create certificate_batches table
CREATE TABLE IF NOT EXISTS certificate_batches (
    batch_id INT AUTO_INCREMENT PRIMARY KEY,
    batch_name VARCHAR(100) NOT NULL,
    shipment_ids TEXT NOT NULL,
    status ENUM('pending','generating','generated','approved','distributed','failed') DEFAULT 'pending',
    download_url VARCHAR(500) NULL,
    folder_path VARCHAR(500) NULL,
    excellence_count INT DEFAULT 0,
    participation_count INT DEFAULT 0,
    skipped_count INT DEFAULT 0,
    error_message TEXT NULL,
    created_by INT NOT NULL,
    created_on DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INT NULL,
    approved_on DATETIME NULL,
    distributed_on DATETIME NULL,
    INDEX idx_status (status)
);

-- Add detected_fields columns to certificate_templates table for storing PDF form field metadata
-- p_detected_fields: JSON array of field names detected in participation certificate PDF
-- e_detected_fields: JSON array of field names detected in excellence certificate PDF
ALTER TABLE `certificate_templates`
    ADD COLUMN IF NOT EXISTS `p_detected_fields` TEXT NULL COMMENT 'JSON array of detected PDF form fields for participation certificate' AFTER `participation_certificate`;
ALTER TABLE `certificate_templates`    
    ADD COLUMN IF NOT EXISTS `e_detected_fields` TEXT NULL COMMENT 'JSON array of detected PDF form fields for excellence certificate' AFTER `excellence_certificate`;


-- Insert home name in globalconfig
INSERT INTO `global_config` (`name`, `value`) VALUES ('home', '');