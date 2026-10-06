-- Migration for version 7.6.24
-- Amit Dugar - Oct 2026
UPDATE `system_config` SET `value` = '7.6.24' WHERE `config` = 'app_version';

-- The not_participated template moves to the {{field}} merge names that Email
-- Participants suggests. Both screens that send it now merge both styles, so the old
-- ##FIELD## tokens would keep working. This only brings the stored text in line with
-- what the editors show. Other templates keep ##FIELD##, since their senders only
-- replace that style. Idempotent: a replay finds no ## tokens left to replace.
UPDATE `mail_template`
   SET `mail_subject` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`mail_subject`,
           '##NAME##', '{{recipient_name}}'), '##SHIPCODE##', '{{shipment_code}}'), '##SHIPTYPE##', '{{scheme}}'),
           '##SURVEYCODE##', '{{pt_survey_code}}'), '##SURVEYDATE##', '{{pt_survey_date}}'), '##SHIPDATE##', '{{shipment_date}}'),
           '##YEAR##', '{{year}}'), '##COUNTRY##', '{{country}}'),
       `mail_content` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`mail_content`,
           '##NAME##', '{{recipient_name}}'), '##SHIPCODE##', '{{shipment_code}}'), '##SHIPTYPE##', '{{scheme}}'),
           '##SURVEYCODE##', '{{pt_survey_code}}'), '##SURVEYDATE##', '{{pt_survey_date}}'), '##SHIPDATE##', '{{shipment_date}}'),
           '##YEAR##', '{{year}}'), '##COUNTRY##', '{{country}}'),
       `mail_footer` = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`mail_footer`,
           '##NAME##', '{{recipient_name}}'), '##SHIPCODE##', '{{shipment_code}}'), '##SHIPTYPE##', '{{scheme}}'),
           '##SURVEYCODE##', '{{pt_survey_code}}'), '##SURVEYDATE##', '{{pt_survey_date}}'), '##SHIPDATE##', '{{shipment_date}}'),
           '##YEAR##', '{{year}}'), '##COUNTRY##', '{{country}}')
 WHERE `mail_purpose` = 'not_participated';

-- Optional address participants write to for help. Blank means "use admin_email",
-- which Common::getSupportEmail() falls back to. Idempotent via NOT EXISTS.
INSERT INTO `global_config` (`name`, `value`)
SELECT 'support_email', NULL FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `global_config` WHERE `name` = 'support_email');

-- New template for telling participants a response deadline moved. It is only sent from
-- Email Participants, which merges {{field}} names. Sender details are copied from the
-- not_participated template so both reach inboxes from the same address. Idempotent:
-- the NOT EXISTS guard skips the insert once the row is there, including after an admin
-- edits it.
INSERT INTO `mail_template` (`mail_purpose`, `from_name`, `mail_from`, `mail_cc`, `mail_bcc`, `mail_subject`, `mail_content`, `mail_footer`)
SELECT 'deadline_extended', t.`from_name`, t.`mail_from`, NULL, NULL,
       'Deadline extended: {{shipment_code}} results now due {{response_deadline}}',
       '<p>Dear {{recipient_name}},</p><p>The deadline to submit results for shipment {{shipment_code}} ({{scheme}}) is now <strong>{{response_deadline}}</strong>.</p><p>If you have already submitted your results, you do not need to do anything.</p><p>If you have not, log in to ePT and submit them before the new deadline. Check that each response shows as submitted, not saved as a draft.</p>',
       '<p>If you have questions or cannot log in, contact us at {{support_email}}.</p>'
  FROM (SELECT 1 AS `one`) AS `seed`
  LEFT JOIN `mail_template` t ON t.`mail_purpose` = 'not_participated'
 WHERE NOT EXISTS (SELECT 1 FROM `mail_template` WHERE `mail_purpose` = 'deadline_extended')
 LIMIT 1;

-- Templates are sent by an admin from Email Participants or the shipment screens, so the
-- stock footer "Please note : This is a system generated email." is inaccurate. Clear the
-- footer only where that sentence is all it holds (the stock text, with its <p> wrapper,
-- stays well under 160 characters). A footer an admin has added anything to is left
-- alone. Idempotent: a cleared footer no longer matches.
UPDATE `mail_template`
   SET `mail_footer` = ''
 WHERE LOWER(`mail_footer`) LIKE '%system generated email%'
   AND CHAR_LENGTH(`mail_footer`) < 160;
