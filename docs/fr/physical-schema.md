# Référentiel des colonnes physiques

Cet instantané consigne les métadonnées du schéma d'une base de référence au 17 septembre 2026.
La version applicative enregistrée est 7.6.21. Le serveur de base indique MySQL 8.4.11.
Il contient 108 tables et 1 156 colonnes. Aucune donnée de participant, de compte ou de réponse n'est incluse.

Il s'agit d'un inventaire de colonnes, pas de DDL exécutable.
Il omet les définitions complètes des index, les noms de contraintes, les collations, les déclencheurs, les routines et les événements.
Un export du schéma seul reste nécessaire pour remettre un schéma physique complet.

Source : requêtes en lecture seule sur `information_schema.COLUMNS`, `TABLES` et `KEY_COLUMN_USAGE`.
La version provient de `system_config.app_version`.
Les significations métier figurent dans le [dictionnaire principal](data-model.md).

Les installations peuvent différer selon leur historique de migration ou leurs extensions locales.
Des numéros de version identiques ne prouvent pas l'identité des schémas.

## Notation

`PRI`, `UNI` et `MUL` reproduisent les métadonnées MySQL des clés de colonnes.
Ils ne décrivent pas les index composites complets.
`NULL` dans Valeur par défaut signifie que les métadonnées ne signalent aucune valeur par défaut non nulle.
`(chaîne vide)` désigne une chaîne vide explicitement définie comme valeur par défaut.
Une cellule vide dans Clé ou Attributs supplémentaires signifie qu'aucune valeur n'est signalée.
Les entrées de clés étrangères conservent les lignes de métadonnées, y compris les relations de colonnes répétées.
Les indicateurs SQL `YES` et `NO` restent inchangés.

## Index des tables

| Table | Colonnes |
| --- | ---: |
| [announcements](#announcements) | 5 |
| [announcements_notification](#announcements_notification) | 6 |
| [audit_log](#audit_log) | 9 |
| [certificate_batches](#certificate_batches) | 15 |
| [certificate_templates](#certificate_templates) | 8 |
| [contact_us](#contact_us) | 14 |
| [countries](#countries) | 5 |
| [covid19_identified_genes](#covid19_identified_genes) | 7 |
| [covid19_recommended_test_types](#covid19_recommended_test_types) | 2 |
| [custom_page_content](#custom_page_content) | 6 |
| [data_manager](#data_manager) | 42 |
| [distributions](#distributions) | 8 |
| [dts_recommended_testkits](#dts_recommended_testkits) | 3 |
| [dts_shipment_corrective_action_map](#dts_shipment_corrective_action_map) | 4 |
| [email_participants](#email_participants) | 7 |
| [enrollments](#enrollments) | 6 |
| [generic_recommended_test_types](#generic_recommended_test_types) | 2 |
| [global_config](#global_config) | 3 |
| [home_banner](#home_banner) | 2 |
| [home_sections](#home_sections) | 11 |
| [mail_template](#mail_template) | 9 |
| [notify](#notify) | 6 |
| [participant](#participant) | 48 |
| [participant_enrolled_programs_map](#participant_enrolled_programs_map) | 2 |
| [participant_feedback_answer](#participant_feedback_answer) | 8 |
| [participant_manager_map](#participant_manager_map) | 2 |
| [participant_manager_map090319](#participant_manager_map090319) | 2 |
| [participant_manager_map18052022](#participant_manager_map18052022) | 2 |
| [participant_messages](#participant_messages) | 8 |
| [participant_testkit_map](#participant_testkit_map) | 3 |
| [participants_not_uploaded](#participants_not_uploaded) | 23 |
| [partners](#partners) | 8 |
| [ptcc_countries_map](#ptcc_countries_map) | 5 |
| [push_notification](#push_notification) | 9 |
| [push_notification_template](#push_notification_template) | 6 |
| [queue_report_generation](#queue_report_generation) | 14 |
| [r_control](#r_control) | 4 |
| [r_covid19_corrective_actions](#r_covid19_corrective_actions) | 3 |
| [r_covid19_gene_types](#r_covid19_gene_types) | 6 |
| [r_dbs_eia](#r_dbs_eia) | 2 |
| [r_dbs_wb](#r_dbs_wb) | 2 |
| [r_dts_corrective_actions](#r_dts_corrective_actions) | 3 |
| [r_eid_detection_assay](#r_eid_detection_assay) | 4 |
| [r_eid_extraction_assay](#r_eid_extraction_assay) | 4 |
| [r_enrolled_programs](#r_enrolled_programs) | 2 |
| [r_evaluation_comments](#r_evaluation_comments) | 3 |
| [r_feedback_questions](#r_feedback_questions) | 9 |
| [r_modes_of_receipt](#r_modes_of_receipt) | 2 |
| [r_network_tiers](#r_network_tiers) | 2 |
| [r_participant_affiliates](#r_participant_affiliates) | 2 |
| [r_participant_feedback_form](#r_participant_feedback_form) | 8 |
| [r_participant_feedback_form_files_map](#r_participant_feedback_form_files_map) | 8 |
| [r_participant_feedback_form_question_map](#r_participant_feedback_form_question_map) | 7 |
| [r_possibleresult](#r_possibleresult) | 17 |
| [r_recency_assay](#r_recency_assay) | 3 |
| [r_response_not_tested_reasons](#r_response_not_tested_reasons) | 6 |
| [r_response_vl_not_tested_reason](#r_response_vl_not_tested_reason) | 3 |
| [r_results](#r_results) | 2 |
| [r_site_type](#r_site_type) | 2 |
| [r_tb_assay](#r_tb_assay) | 6 |
| [r_test_type_covid19](#r_test_type_covid19) | 18 |
| [r_testkitname_dts](#r_testkitname_dts) | 20 |
| [r_testkitnames](#r_testkitnames) | 16 |
| [r_vl_assay](#r_vl_assay) | 5 |
| [reference_covid19_test_type](#reference_covid19_test_type) | 7 |
| [reference_dbs_eia](#reference_dbs_eia) | 8 |
| [reference_dbs_wb](#reference_dbs_wb) | 15 |
| [reference_dts_eia](#reference_dts_eia) | 10 |
| [reference_dts_geenius](#reference_dts_geenius) | 7 |
| [reference_dts_rapid_hiv](#reference_dts_rapid_hiv) | 8 |
| [reference_dts_wb](#reference_dts_wb) | 17 |
| [reference_generic_test_calculations](#reference_generic_test_calculations) | 38 |
| [reference_recency_assay](#reference_recency_assay) | 7 |
| [reference_result_covid19](#reference_result_covid19) | 8 |
| [reference_result_dbs](#reference_result_dbs) | 8 |
| [reference_result_dts](#reference_result_dts) | 11 |
| [reference_result_eid](#reference_result_eid) | 10 |
| [reference_result_generic_test](#reference_result_generic_test) | 8 |
| [reference_result_recency](#reference_result_recency) | 12 |
| [reference_result_tb](#reference_result_tb) | 31 |
| [reference_result_vl](#reference_result_vl) | 8 |
| [reference_vl_calculation](#reference_vl_calculation) | 34 |
| [reference_vl_methods](#reference_vl_methods) | 4 |
| [response_covid19_not_tested_reason](#response_covid19_not_tested_reason) | 3 |
| [response_result_covid19](#response_result_covid19) | 29 |
| [response_result_dbs](#response_result_dbs) | 35 |
| [response_result_dts](#response_result_dts) | 57 |
| [response_result_eid](#response_result_eid) | 10 |
| [response_result_generic_test](#response_result_generic_test) | 16 |
| [response_result_recency](#response_result_recency) | 12 |
| [response_result_tb](#response_result_tb) | 28 |
| [response_result_vl](#response_result_vl) | 15 |
| [run_once_scripts](#run_once_scripts) | 2 |
| [scheduled_jobs](#scheduled_jobs) | 8 |
| [scheme_config](#scheme_config) | 2 |
| [scheme_list](#scheme_list) | 9 |
| [scheme_testkit_map](#scheme_testkit_map) | 6 |
| [shipment](#shipment) | 40 |
| [shipment_participant_map](#shipment_participant_map) | 63 |
| [shipment_testkit_map](#shipment_testkit_map) | 6 |
| [system_admin](#system_admin) | 16 |
| [system_config](#system_config) | 3 |
| [system_metadata](#system_metadata) | 2 |
| [tb_instruments](#tb_instruments) | 10 |
| [temp_mail](#temp_mail) | 17 |
| [track_api_requests](#track_api_requests) | 12 |
| [track_report_downloaded_history](#track_report_downloaded_history) | 5 |
| [user_login_history](#user_login_history) | 10 |

## announcements

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `announcement_id` | `int` | NO | NULL | PRI | auto_increment |
| `announcement_msg` | `mediumtext` | YES | NULL |  |  |
| `start_date` | `date` | NO | NULL |  |  |
| `end_date` | `date` | NO | NULL |  |  |
| `status` | `varchar(45)` | NO | active |  |  |

## announcements_notification

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `subject` | `varchar(255)` | YES | NULL |  |  |
| `message` | `mediumtext` | YES | NULL |  |  |
| `participants` | `mediumtext` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `created_by` | `int` | YES | NULL |  |  |

## audit_log

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `audit_log_id` | `int` | NO | NULL | PRI | auto_increment |
| `statement` | `text` | YES | NULL |  |  |
| `created_by` | `varchar(256)` | YES | NULL | MUL |  |
| `created_by_role` | `varchar(32)` | YES | NULL |  |  |
| `created_on` | `datetime` | NO | CURRENT_TIMESTAMP | MUL | DEFAULT_GENERATED |
| `type` | `varchar(256)` | YES | NULL | MUL |  |
| `ip_address` | `varchar(64)` | YES | NULL |  |  |
| `user_agent` | `varchar(512)` | YES | NULL |  |  |
| `session_hash` | `varchar(16)` | YES | NULL | MUL |  |

## certificate_batches

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `batch_id` | `int` | NO | NULL | PRI | auto_increment |
| `batch_name` | `varchar(100)` | NO | NULL |  |  |
| `shipment_ids` | `text` | NO | NULL |  |  |
| `status` | `enum('pending','generating','generated','approved','distributed','failed')` | YES | pending | MUL |  |
| `download_url` | `varchar(500)` | YES | NULL |  |  |
| `folder_path` | `varchar(500)` | YES | NULL |  |  |
| `excellence_count` | `int` | YES | 0 |  |  |
| `participation_count` | `int` | YES | 0 |  |  |
| `skipped_count` | `int` | YES | 0 |  |  |
| `error_message` | `text` | YES | NULL |  |  |
| `created_by` | `int` | NO | NULL |  |  |
| `created_on` | `datetime` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `approved_by` | `int` | YES | NULL |  |  |
| `approved_on` | `datetime` | YES | NULL |  |  |
| `distributed_on` | `datetime` | YES | NULL |  |  |

## certificate_templates

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `ct_id` | `int` | NO | NULL | PRI | auto_increment |
| `scheme_type` | `varchar(256)` | YES | NULL |  |  |
| `participation_certificate` | `varchar(256)` | YES | NULL |  |  |
| `p_detected_fields` | `text` | YES | NULL |  |  |
| `excellence_certificate` | `varchar(256)` | YES | NULL |  |  |
| `e_detected_fields` | `text` | YES | NULL |  |  |
| `created_by` | `varchar(256)` | YES | NULL |  |  |
| `updated_on` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## contact_us

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `contact_id` | `int` | NO | NULL | PRI | auto_increment |
| `first_name` | `varchar(255)` | YES | NULL |  |  |
| `last_name` | `varchar(255)` | YES | NULL |  |  |
| `email` | `varchar(255)` | YES | NULL |  |  |
| `phone` | `varchar(255)` | YES | NULL |  |  |
| `reason` | `varchar(255)` | YES | NULL |  |  |
| `lab` | `varchar(255)` | YES | NULL |  |  |
| `additional_info` | `mediumtext` | YES | NULL |  |  |
| `participant_id` | `varchar(256)` | YES | NULL |  |  |
| `subject` | `varchar(256)` | YES | NULL |  |  |
| `country` | `varchar(256)` | YES | NULL |  |  |
| `message` | `mediumtext` | YES | NULL |  |  |
| `contacted_on` | `datetime` | YES | NULL |  |  |
| `ip_address` | `varchar(255)` | YES | NULL |  |  |

## countries

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int unsigned` | NO | NULL | PRI | auto_increment |
| `iso_name` | `varchar(255)` | NO | NULL |  |  |
| `iso2` | `varchar(2)` | NO | NULL |  |  |
| `iso3` | `varchar(3)` | NO | NULL |  |  |
| `numeric_code` | `smallint` | NO | NULL |  |  |

## covid19_identified_genes

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `gene_map_id` | `int` | NO | NULL | PRI | auto_increment |
| `map_id` | `int` | NO | NULL | MUL |  |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `sample_id` | `int` | NO | NULL |  |  |
| `gene_id` | `int` | YES | NULL | MUL |  |
| `ct_value` | `varchar(255)` | YES | NULL |  |  |
| `remarks` | `mediumtext` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `map_id` | `shipment_participant_map` | `map_id` |
| `shipment_id` | `shipment` | `shipment_id` |
| `gene_id` | `r_covid19_gene_types` | `gene_id` |

## covid19_recommended_test_types

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `test_no` | `int` | NO | NULL | PRI |  |
| `test_type` | `varchar(255)` | NO | NULL | PRI |  |

## custom_page_content

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `title` | `varchar(256)` | NO | NULL |  |  |
| `content` | `text` | YES | NULL |  |  |
| `modified_by` | `varchar(256)` | YES | NULL |  |  |
| `status` | `varchar(50)` | YES | NULL |  |  |
| `modified_date_time` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## data_manager

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `dm_id` | `int` | NO | NULL | PRI | auto_increment |
| `participant_ulid` | `mediumtext` | YES | NULL |  |  |
| `primary_email` | `varchar(255)` | NO | NULL | UNI |  |
| `primary_email_status` | `enum('unknown','valid','invalid_syntax','invalid_domain','hard_bounce')` | NO | unknown |  |  |
| `password` | `text` | YES | NULL |  |  |
| `password_reset_token` | `char(64)` | YES | NULL | UNI |  |
| `password_reset_expires_at` | `datetime` | YES | NULL |  |  |
| `institute` | `varchar(500)` | YES | NULL |  |  |
| `data_manager_type` | `varchar(50)` | NO | manager |  |  |
| `ptcc` | `enum('yes','no')` | NO | no |  |  |
| `first_name` | `varchar(255)` | YES | NULL |  |  |
| `last_name` | `varchar(255)` | YES | NULL |  |  |
| `phone` | `varchar(255)` | YES | NULL |  |  |
| `secondary_email` | `varchar(255)` | YES | NULL |  |  |
| `secondary_email_status` | `enum('unknown','valid','invalid_syntax','invalid_domain','hard_bounce')` | NO | unknown |  |  |
| `email_status_checked_at` | `datetime` | YES | NULL | MUL |  |
| `last_bounce_at` | `datetime` | YES | NULL |  |  |
| `last_bounce_reason` | `varchar(500)` | YES | NULL |  |  |
| `country_id` | `int` | YES | NULL |  |  |
| `UserFld1` | `varchar(255)` | YES | NULL |  |  |
| `UserFld2` | `varchar(255)` | YES | NULL |  |  |
| `UserFld3` | `varchar(255)` | YES | NULL |  |  |
| `mobile` | `varchar(255)` | YES | NULL |  |  |
| `force_password_reset` | `int` | NO | 0 |  |  |
| `force_profile_check` | `varchar(255)` | YES | no |  |  |
| `qc_access` | `varchar(255)` | YES | NULL |  |  |
| `enable_adding_test_response_date` | `varchar(255)` | YES | NULL |  |  |
| `enable_choosing_mode_of_receipt` | `varchar(255)` | YES | NULL |  |  |
| `view_only_access` | `varchar(255)` | YES | NULL |  |  |
| `status` | `varchar(255)` | NO | inactive |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `created_by` | `varchar(255)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(255)` | YES | NULL |  |  |
| `last_login` | `datetime` | YES | NULL |  |  |
| `login_ban` | `varchar(50)` | NO | no |  |  |
| `auth_token` | `varchar(255)` | YES | NULL |  |  |
| `api_token_generated_datetime` | `datetime` | YES | NULL |  |  |
| `download_link` | `varchar(255)` | YES | NULL |  |  |
| `new_email` | `varchar(255)` | YES | NULL |  |  |
| `last_date_for_email_reset` | `date` | YES | NULL |  |  |
| `language` | `varchar(256)` | YES | en_US |  |  |

## distributions

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `distribution_id` | `int` | NO | NULL | PRI | auto_increment |
| `distribution_code` | `varchar(255)` | NO | NULL |  |  |
| `distribution_date` | `date` | NO | NULL |  |  |
| `status` | `varchar(255)` | NO | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `created_by` | `varchar(255)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(255)` | YES | NULL |  |  |

## dts_recommended_testkits

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `test_no` | `int` | NO | NULL | PRI |  |
| `testkit` | `varchar(255)` | NO | NULL | PRI |  |
| `dts_test_mode` | `varchar(256)` | NO | dts | PRI |  |

## dts_shipment_corrective_action_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL |  |  |
| `corrective_action_id` | `int` | NO | NULL |  |  |
| `action_taken` | `text` | YES | NULL |  |  |
| `action_date` | `date` | YES | NULL |  |  |

## email_participants

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `subject` | `varchar(256)` | NO | NULL |  |  |
| `content` | `text` | YES | NULL |  |  |
| `receivers` | `text` | YES | NULL |  |  |
| `shipment_code` | `varchar(256)` | YES | NULL |  |  |
| `date_initiated` | `datetime` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `initiated_by` | `varchar(256)` | YES | NULL |  |  |

## enrollments

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `enrollment_id` | `varchar(64)` | NO | NULL |  |  |
| `list_name` | `varchar(128)` | NO | default | PRI |  |
| `scheme_id` | `varchar(32)` | NO | NULL |  |  |
| `participant_id` | `int` | NO | NULL | PRI |  |
| `enrolled_on` | `datetime` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `status` | `varchar(255)` | NO | NULL |  |  |

## generic_recommended_test_types

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `scheme_id` | `varchar(256)` | NO | NULL |  |  |
| `testkit` | `varchar(256)` | NO | NULL |  |  |

## global_config

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `name` | `varchar(255)` | NO | NULL | PRI |  |
| `value` | `longtext` | YES | NULL |  |  |
| `context` | `varchar(50)` | NO | global |  |  |

## home_banner

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `banner_id` | `int` | NO | NULL | PRI | auto_increment |
| `image` | `varchar(255)` | YES | NULL |  |  |

## home_sections

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `section` | `varchar(256)` | YES | NULL |  |  |
| `link` | `varchar(256)` | YES | NULL |  |  |
| `type` | `varchar(255)` | YES | NULL |  |  |
| `section_file` | `varchar(255)` | YES | NULL |  |  |
| `text` | `text` | YES | NULL |  |  |
| `icon` | `varchar(256)` | YES | NULL |  |  |
| `display_order` | `int` | YES | NULL |  |  |
| `status` | `varchar(50)` | YES | NULL |  |  |
| `modified_by` | `varchar(256)` | YES | NULL |  |  |
| `modified_date_time` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## mail_template

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `mail_temp_id` | `int` | NO | NULL | PRI | auto_increment |
| `mail_purpose` | `varchar(255)` | NO | NULL |  |  |
| `from_name` | `varchar(255)` | YES | NULL |  |  |
| `mail_from` | `varchar(255)` | YES | NULL |  |  |
| `mail_cc` | `varchar(255)` | YES | NULL |  |  |
| `mail_bcc` | `varchar(255)` | YES | NULL |  |  |
| `mail_subject` | `varchar(255)` | YES | NULL |  |  |
| `mail_content` | `mediumtext` | YES | NULL |  |  |
| `mail_footer` | `mediumtext` | YES | NULL |  |  |

## notify

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `title` | `varchar(255)` | YES | NULL |  |  |
| `description` | `mediumtext` | YES | NULL |  |  |
| `link` | `varchar(255)` | YES | NULL |  |  |
| `status` | `varchar(50)` | NO | unread |  |  |
| `created_on` | `timestamp` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## participant

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `participant_id` | `int` | NO | NULL | PRI | auto_increment |
| `ulid` | `mediumtext` | YES | NULL |  |  |
| `unique_identifier` | `varchar(255)` | NO | NULL | UNI |  |
| `individual` | `varchar(255)` | YES | NULL |  |  |
| `lab_name` | `varchar(255)` | YES | NULL |  |  |
| `institute_name` | `varchar(255)` | YES | NULL |  |  |
| `department_name` | `varchar(255)` | YES | NULL |  |  |
| `lab_director_name` | `varchar(256)` | YES | NULL |  |  |
| `lab_director_email` | `varchar(256)` | YES | NULL |  |  |
| `contact_person_name` | `varchar(256)` | YES | NULL |  |  |
| `contact_person_email` | `varchar(256)` | YES | NULL |  |  |
| `contact_person_telephone` | `varchar(256)` | YES | NULL |  |  |
| `address` | `varchar(500)` | YES | NULL |  |  |
| `city` | `varchar(255)` | YES | NULL |  |  |
| `state` | `varchar(255)` | YES | NULL |  |  |
| `district` | `varchar(256)` | YES | NULL |  |  |
| `country` | `int` | NO | NULL |  |  |
| `zip` | `varchar(255)` | YES | NULL |  |  |
| `lat` | `varchar(255)` | YES | NULL |  |  |
| `long` | `varchar(255)` | YES | NULL |  |  |
| `shipping_address` | `varchar(1000)` | YES | NULL |  |  |
| `funding_source` | `varchar(255)` | YES | NULL |  |  |
| `testing_volume` | `varchar(255)` | YES | NULL |  |  |
| `enrolled_programs` | `varchar(255)` | YES | NULL |  |  |
| `site_type` | `varchar(255)` | YES | NULL |  |  |
| `anc` | `varchar(50)` | NO | no |  |  |
| `pepfar_id` | `varchar(256)` | YES | NULL |  |  |
| `region` | `varchar(255)` | YES | NULL |  |  |
| `first_name` | `varchar(500)` | YES | NULL |  |  |
| `last_name` | `varchar(500)` | YES | NULL |  |  |
| `mobile` | `varchar(45)` | YES | NULL |  |  |
| `phone` | `varchar(45)` | YES | NULL |  |  |
| `email` | `varchar(255)` | YES | NULL |  |  |
| `email_status` | `enum('unknown','valid','invalid_syntax','invalid_domain','hard_bounce')` | NO | unknown |  |  |
| `additional_email` | `varchar(255)` | YES | NULL |  |  |
| `additional_email_status` | `enum('unknown','valid','invalid_syntax','invalid_domain','hard_bounce')` | NO | unknown |  |  |
| `email_status_checked_at` | `datetime` | YES | NULL | MUL |  |
| `last_bounce_at` | `datetime` | YES | NULL |  |  |
| `last_bounce_reason` | `varchar(500)` | YES | NULL |  |  |
| `affiliation` | `varchar(45)` | YES | NULL |  |  |
| `network_tier` | `int` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `force_profile_updation` | `int` | NO | 0 |  |  |
| `status` | `varchar(255)` | NO | inactive |  |  |
| `contact_name` | `varchar(45)` | YES | NULL |  |  |

## participant_enrolled_programs_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `participant_id` | `int` | NO | NULL | PRI |  |
| `ep_id` | `int` | NO | NULL | PRI |  |

## participant_feedback_answer

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `answer_id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `participant_id` | `int` | YES | NULL | MUL |  |
| `question_id` | `int` | NO | NULL | MUL |  |
| `map_id` | `int` | NO | NULL | MUL |  |
| `answer` | `text` | YES | NULL |  |  |
| `updated_datetime` | `datetime` | YES | NULL |  |  |
| `modified_by` | `int` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `map_id` | `shipment_participant_map` | `map_id` |
| `shipment_id` | `shipment` | `shipment_id` |
| `question_id` | `r_feedback_questions` | `question_id` |
| `participant_id` | `participant` | `participant_id` |
| `participant_id` | `participant` | `participant_id` |
| `participant_id` | `participant` | `participant_id` |

## participant_manager_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `participant_id` | `int` | NO | NULL | PRI |  |
| `dm_id` | `int` | NO | NULL | PRI |  |

## participant_manager_map090319

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `participant_id` | `int` | NO | NULL | PRI |  |
| `dm_id` | `int` | NO | NULL | PRI |  |

## participant_manager_map18052022

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `participant_id` | `int` | NO | NULL | PRI |  |
| `dm_id` | `int` | NO | NULL | PRI |  |

## participant_messages

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `participant_id` | `varchar(255)` | NO | NULL |  |  |
| `subject` | `varchar(255)` | YES | NULL |  |  |
| `attached_file` | `varchar(255)` | YES | NULL |  |  |
| `message` | `text` | NO | NULL |  |  |
| `status` | `enum('pending','sent','failed')` | YES | pending |  |  |
| `created_at` | `datetime` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `sent_at` | `datetime` | YES | NULL |  |  |

## participant_testkit_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `participant_id` | `int` | NO | NULL | MUL |  |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `testkit_id` | `varchar(256)` | NO | NULL | MUL |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `participant_id` | `participant` | `participant_id` |
| `testkit_id` | `r_testkitnames` | `TestKitName_ID` |
| `shipment_id` | `shipment` | `shipment_id` |

## participants_not_uploaded

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `s_no` | `mediumtext` | YES | NULL |  |  |
| `participant_id` | `mediumtext` | YES | NULL |  |  |
| `individual` | `mediumtext` | YES | NULL |  |  |
| `participant_lab_name` | `mediumtext` | YES | NULL |  |  |
| `participant_last_name` | `mediumtext` | YES | NULL |  |  |
| `institute_name` | `mediumtext` | YES | NULL |  |  |
| `department` | `mediumtext` | YES | NULL |  |  |
| `address` | `mediumtext` | YES | NULL |  |  |
| `district` | `mediumtext` | YES | NULL |  |  |
| `province` | `mediumtext` | YES | NULL |  |  |
| `country` | `mediumtext` | YES | NULL |  |  |
| `zip` | `mediumtext` | YES | NULL |  |  |
| `longitude` | `mediumtext` | YES | NULL |  |  |
| `latitude` | `mediumtext` | YES | NULL |  |  |
| `mobile_number` | `mediumtext` | YES | NULL |  |  |
| `participant_email` | `mediumtext` | YES | NULL |  |  |
| `participant_password` | `mediumtext` | YES | NULL |  |  |
| `additional_email` | `mediumtext` | YES | NULL |  |  |
| `filename` | `mediumtext` | YES | NULL |  |  |
| `import_run_id` | `varchar(48)` | YES | NULL |  |  |
| `error` | `mediumtext` | YES | NULL |  |  |
| `updated_datetime` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## partners

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `partner_id` | `int` | NO | NULL | PRI | auto_increment |
| `partner_name` | `varchar(500)` | YES | NULL |  |  |
| `link` | `text` | YES | NULL |  |  |
| `sort_order` | `int` | YES | NULL |  |  |
| `added_by` | `int` | NO | NULL |  |  |
| `added_on` | `datetime` | NO | NULL |  |  |
| `status` | `varchar(45)` | YES | NULL |  |  |
| `logo_image` | `text` | YES | NULL |  |  |

## ptcc_countries_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `ptcc_id` | `int` | NO | NULL | MUL |  |
| `country_id` | `int` | NO | NULL |  |  |
| `state` | `varchar(256)` | YES | NULL |  |  |
| `district` | `varchar(256)` | YES | NULL |  |  |
| `mapped_on` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `ptcc_id` | `data_manager` | `dm_id` |

## push_notification

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `notification_json` | `mediumtext` | YES | NULL |  |  |
| `data_json` | `mediumtext` | YES | NULL |  |  |
| `push_status` | `varchar(50)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `token_identify_id` | `mediumtext` | YES | NULL |  |  |
| `identify_type` | `varchar(50)` | YES | NULL |  |  |
| `notification_type` | `varchar(50)` | YES | NULL |  |  |
| `announcement_id` | `int` | YES | NULL |  |  |

## push_notification_template

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `purpose` | `varchar(255)` | YES | NULL |  |  |
| `notify_title` | `varchar(255)` | YES | NULL |  |  |
| `notify_body` | `mediumtext` | YES | NULL |  |  |
| `data_msg` | `mediumtext` | YES | NULL |  |  |
| `icon` | `varchar(255)` | YES | NULL |  |  |

## queue_report_generation

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `report_type` | `varchar(50)` | YES | NULL |  |  |
| `requested_by` | `int` | NO | NULL |  |  |
| `requested_on` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `last_updated_on` | `datetime` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `date_finalised` | `datetime` | YES | NULL |  |  |
| `status` | `varchar(255)` | NO | pending |  |  |
| `previous_status` | `varchar(256)` | YES | NULL |  |  |
| `error_message` | `text` | YES | NULL |  |  |
| `processing_started_at` | `datetime` | YES | NULL |  |  |
| `last_heartbeat` | `datetime` | YES | NULL |  |  |
| `completed_at` | `datetime` | YES | NULL |  |  |
| `initated_by` | `int` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `shipment_id` | `shipment` | `shipment_id` |

## r_control

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `control_id` | `int` | NO | NULL | PRI | auto_increment |
| `control_name` | `varchar(255)` | YES | NULL |  |  |
| `for_scheme` | `varchar(255)` | YES | NULL |  |  |
| `is_active` | `varchar(45)` | YES | NULL |  |  |

## r_covid19_corrective_actions

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `action_id` | `int` | NO | NULL | PRI | auto_increment |
| `corrective_action` | `mediumtext` | NO | NULL |  |  |
| `description` | `mediumtext` | NO | NULL |  |  |

## r_covid19_gene_types

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `gene_id` | `int` | NO | NULL | PRI | auto_increment |
| `gene_name` | `varchar(255)` | YES | NULL |  |  |
| `scheme_type` | `varchar(255)` | YES | NULL |  |  |
| `gene_status` | `varchar(55)` | YES | NULL |  |  |
| `created_by` | `int` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |

## r_dbs_eia

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `eia_id` | `int` | NO | NULL | PRI | auto_increment |
| `eia_name` | `varchar(500)` | NO | NULL |  |  |

## r_dbs_wb

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `wb_id` | `int` | NO | NULL | PRI | auto_increment |
| `wb_name` | `varchar(500)` | NO | NULL |  |  |

## r_dts_corrective_actions

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `action_id` | `int` | NO | NULL | PRI | auto_increment |
| `corrective_action` | `mediumtext` | NO | NULL |  |  |
| `description` | `mediumtext` | NO | NULL |  |  |

## r_eid_detection_assay

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `name` | `varchar(255)` | NO | NULL |  |  |
| `sort_order` | `int` | YES | 0 |  |  |
| `status` | `varchar(45)` | NO | active |  |  |

## r_eid_extraction_assay

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `name` | `varchar(255)` | NO | NULL |  |  |
| `sort_order` | `int` | YES | 0 |  |  |
| `status` | `varchar(45)` | NO | active |  |  |

## r_enrolled_programs

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `r_epid` | `int` | NO | NULL | PRI | auto_increment |
| `enrolled_programs` | `varchar(255)` | YES | NULL |  |  |

## r_evaluation_comments

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `comment_id` | `int` | NO | NULL | PRI | auto_increment |
| `scheme` | `varchar(255)` | NO | NULL |  |  |
| `comment` | `mediumtext` | NO | NULL |  |  |

## r_feedback_questions

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `question_id` | `int` | NO | NULL | PRI | auto_increment |
| `question_text` | `text` | YES | NULL |  |  |
| `question_code` | `varchar(256)` | YES | NULL |  |  |
| `question_type` | `enum('text','datetime','dropdown','numeric')` | YES | NULL |  |  |
| `question_show_to` | `varchar(256)` | YES | NULL |  |  |
| `question_status` | `varchar(50)` | YES | NULL |  |  |
| `response_attributes` | `json` | YES | NULL |  |  |
| `updated_datetime` | `datetime` | YES | NULL |  |  |
| `modified_by` | `varchar(256)` | YES | NULL |  |  |

## r_modes_of_receipt

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `mode_id` | `int` | NO | NULL | PRI | auto_increment |
| `mode_name` | `varchar(255)` | YES | NULL |  |  |

## r_network_tiers

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `network_id` | `int` | NO | NULL | PRI | auto_increment |
| `network_name` | `varchar(255)` | YES | NULL |  |  |

## r_participant_affiliates

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `aff_id` | `int` | NO | NULL | PRI | auto_increment |
| `affiliate` | `varchar(255)` | NO | NULL |  |  |

## r_participant_feedback_form

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `rpff_id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `scheme_type` | `varchar(50)` | NO | NULL | MUL |  |
| `form_content` | `text` | YES | NULL |  |  |
| `form_show_to` | `varchar(50)` | YES | NULL |  |  |
| `question_id` | `int` | NO | NULL | MUL |  |
| `is_response_mandatory` | `varchar(50)` | YES | NULL |  |  |
| `sort_order` | `int` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `shipment_id` | `shipment` | `shipment_id` |
| `question_id` | `r_feedback_questions` | `question_id` |

## r_participant_feedback_form_files_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `rpf_id` | `int` | NO | NULL | PRI | auto_increment |
| `rpff_id` | `int` | YES | NULL |  |  |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `scheme_type` | `varchar(50)` | YES | NULL |  |  |
| `feedback_file` | `varchar(50)` | YES | NULL |  |  |
| `file_name` | `varchar(50)` | YES | NULL |  |  |
| `files_show_to` | `varchar(255)` | YES | NULL |  |  |
| `sort_order` | `int` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `shipment_id` | `shipment` | `shipment_id` |

## r_participant_feedback_form_question_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `fqm_id` | `int` | NO | NULL | PRI | auto_increment |
| `rpff_id` | `int` | NO | NULL | MUL |  |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `scheme_type` | `varchar(50)` | YES | NULL | MUL |  |
| `question_id` | `int` | NO | NULL | MUL |  |
| `is_response_mandatory` | `varchar(50)` | YES | NULL |  |  |
| `sort_order` | `int` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `shipment_id` | `shipment` | `shipment_id` |
| `question_id` | `r_feedback_questions` | `question_id` |
| `rpff_id` | `r_participant_feedback_form` | `rpff_id` |

## r_possibleresult

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `scheme_id` | `varchar(45)` | NO | NULL | MUL |  |
| `scheme_sub_group` | `varchar(45)` | YES | NULL |  |  |
| `sub_scheme` | `varchar(256)` | YES | NULL |  |  |
| `result_type` | `varchar(256)` | YES | NULL |  |  |
| `response` | `varchar(45)` | YES | NULL |  |  |
| `result_code` | `varchar(20)` | YES | NULL |  |  |
| `display_context` | `enum('participant','admin','all','none')` | NO | all |  |  |
| `high_range` | `varchar(50)` | YES | NULL |  |  |
| `threshold_range` | `varchar(50)` | YES | NULL |  |  |
| `low_range` | `varchar(50)` | YES | NULL |  |  |
| `sd_scaling_factor` | `varchar(256)` | YES | NULL |  |  |
| `uncertainy_scaling_factor` | `varchar(256)` | YES | NULL |  |  |
| `uncertainy_threshold` | `varchar(256)` | YES | NULL |  |  |
| `minimum_number_of_responses` | `int` | YES | NULL |  |  |
| `sort_order` | `int` | YES | NULL |  |  |
| `status` | `enum('active','inactive')` | NO | active |  |  |

## r_recency_assay

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `name` | `varchar(255)` | NO | NULL |  |  |
| `sort_order` | `int` | YES | 0 |  |  |

## r_response_not_tested_reasons

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `ntr_id` | `int` | NO | NULL | PRI | auto_increment |
| `ntr_reason` | `varchar(256)` | YES | NULL |  |  |
| `ntr_test_type` | `json` | YES | NULL |  |  |
| `collect_panel_receipt_date` | `varchar(256)` | NO | no |  |  |
| `reason_code` | `varchar(50)` | YES | NULL |  |  |
| `ntr_status` | `varchar(256)` | YES | NULL |  |  |

## r_response_vl_not_tested_reason

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `vl_not_tested_reason_id` | `int` | NO | NULL | PRI | auto_increment |
| `vl_not_tested_reason` | `varchar(500)` | YES | NULL |  |  |
| `status` | `varchar(45)` | NO | active |  |  |

## r_results

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `result_id` | `int` | NO | NULL | PRI | auto_increment |
| `result_name` | `varchar(255)` | NO | NULL |  |  |

## r_site_type

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `r_stid` | `int` | NO | NULL | PRI | auto_increment |
| `site_type` | `varchar(255)` | YES | NULL |  |  |

## r_tb_assay

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `name` | `varchar(255)` | NO | NULL |  |  |
| `short_name` | `varchar(255)` | NO | NULL |  |  |
| `assay_type` | `varchar(255)` | NO | specific |  |  |
| `drug_resistance_test` | `varchar(255)` | NO | yes |  |  |
| `status` | `varchar(256)` | YES | active |  |  |

## r_test_type_covid19

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `test_type_id` | `int` | NO | NULL | PRI | auto_increment |
| `scheme_type` | `varchar(255)` | NO | NULL |  |  |
| `test_type_name` | `varchar(100)` | YES | NULL |  |  |
| `test_type_short_name` | `varchar(50)` | YES | NULL |  |  |
| `test_type_comments` | `varchar(50)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `int` | YES | NULL |  |  |
| `installation_id` | `varchar(50)` | YES | NULL |  |  |
| `test_type_manufacturer` | `varchar(50)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `created_by` | `int` | YES | NULL |  |  |
| `approval` | `int` | YES | 1 |  |  |
| `test_type_approval_agency` | `varchar(20)` | YES | NULL |  |  |
| `source_reference` | `varchar(50)` | YES | NULL |  |  |
| `country_adapted` | `int` | YES | NULL |  |  |
| `test_type_1` | `int` | NO | 0 |  |  |
| `test_type_2` | `int` | NO | 0 |  |  |
| `test_type_3` | `int` | NO | 0 |  |  |

## r_testkitname_dts

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `TestKitName_ID` | `varchar(50)` | NO | NULL | PRI |  |
| `scheme_type` | `varchar(255)` | NO | NULL |  |  |
| `TestKit_Name` | `varchar(100)` | YES | NULL |  |  |
| `TestKit_Name_Short` | `varchar(50)` | YES | NULL |  |  |
| `TestKit_Comments` | `varchar(50)` | YES | NULL |  |  |
| `Updated_On` | `datetime` | YES | NULL |  |  |
| `Updated_By` | `int` | YES | NULL |  |  |
| `Installation_id` | `varchar(50)` | YES | NULL |  |  |
| `TestKit_Manufacturer` | `varchar(50)` | YES | NULL |  |  |
| `Created_On` | `datetime` | YES | NULL |  |  |
| `Created_By` | `int` | YES | NULL |  |  |
| `Approval` | `int` | YES | 1 |  |  |
| `TestKit_ApprovalAgency` | `varchar(20)` | YES | NULL |  |  |
| `source_reference` | `varchar(50)` | YES | NULL |  |  |
| `CountryAdapted` | `int` | YES | NULL |  |  |
| `attributes` | `json` | YES | NULL |  |  |
| `testkit_1` | `int` | NO | 0 |  |  |
| `testkit_2` | `int` | NO | 0 |  |  |
| `testkit_3` | `int` | NO | 0 |  |  |
| `testkit_status` | `varchar(256)` | YES | NULL |  |  |

## r_testkitnames

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `TestKitName_ID` | `varchar(50)` | NO | NULL | PRI |  |
| `TestKit_Name` | `varchar(100)` | YES | NULL |  |  |
| `TestKit_Name_Short` | `varchar(50)` | YES | NULL |  |  |
| `TestKit_Comments` | `varchar(50)` | YES | NULL |  |  |
| `Updated_On` | `datetime` | YES | NULL |  |  |
| `Updated_By` | `int` | YES | NULL |  |  |
| `Installation_id` | `varchar(50)` | YES | NULL |  |  |
| `TestKit_Manufacturer` | `varchar(50)` | YES | NULL |  |  |
| `Created_On` | `datetime` | YES | NULL |  |  |
| `Created_By` | `int` | YES | NULL |  |  |
| `Approval` | `int` | YES | 1 |  |  |
| `moh_approved` | `varchar(20)` | YES | NULL |  |  |
| `source_reference` | `varchar(50)` | YES | NULL |  |  |
| `pt_provider_validated` | `int` | YES | NULL |  |  |
| `attributes` | `json` | YES | NULL |  |  |
| `testkit_status` | `varchar(256)` | YES | NULL |  |  |

## r_vl_assay

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `name` | `varchar(255)` | NO | NULL |  |  |
| `short_name` | `varchar(255)` | NO | NULL |  |  |
| `allow_invalid` | `varchar(10)` | NO | no |  |  |
| `status` | `varchar(256)` | YES | active |  |  |

## reference_covid19_test_type

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `varchar(255)` | NO | NULL |  |  |
| `sample_id` | `varchar(255)` | NO | NULL |  |  |
| `test_type` | `varchar(255)` | NO | NULL |  |  |
| `lot_no` | `varchar(255)` | NO | NULL |  |  |
| `expiry_date` | `date` | NO | NULL |  |  |
| `result` | `varchar(255)` | NO | NULL |  |  |

## reference_dbs_eia

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL |  |  |
| `sample_id` | `int` | NO | NULL |  |  |
| `eia` | `int` | NO | NULL |  |  |
| `lot` | `varchar(255)` | YES | NULL |  |  |
| `exp_date` | `date` | YES | NULL |  |  |
| `od` | `varchar(255)` | YES | NULL |  |  |
| `cutoff` | `varchar(255)` | YES | NULL |  |  |

## reference_dbs_wb

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL |  |  |
| `sample_id` | `int` | NO | NULL |  |  |
| `wb` | `int` | NO | NULL |  |  |
| `lot` | `varchar(255)` | YES | NULL |  |  |
| `exp_date` | `date` | YES | NULL |  |  |
| `160` | `int` | YES | NULL |  |  |
| `120` | `int` | YES | NULL |  |  |
| `66` | `int` | YES | NULL |  |  |
| `55` | `int` | YES | NULL |  |  |
| `51` | `int` | YES | NULL |  |  |
| `41` | `int` | YES | NULL |  |  |
| `31` | `int` | YES | NULL |  |  |
| `24` | `int` | YES | NULL |  |  |
| `17` | `int` | YES | NULL |  |  |

## reference_dts_eia

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL |  |  |
| `sample_id` | `int` | NO | NULL |  |  |
| `eia` | `int` | NO | NULL |  |  |
| `test_date` | `date` | YES | NULL |  |  |
| `lot` | `varchar(255)` | YES | NULL |  |  |
| `exp_date` | `date` | YES | NULL |  |  |
| `od` | `varchar(255)` | YES | NULL |  |  |
| `cutoff` | `varchar(255)` | YES | NULL |  |  |
| `result` | `varchar(556)` | YES | NULL |  |  |

## reference_dts_geenius

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | YES | NULL |  |  |
| `sample_id` | `varchar(256)` | YES | NULL |  |  |
| `test_date` | `date` | YES | NULL |  |  |
| `lot_no` | `varchar(256)` | YES | NULL |  |  |
| `expiry_date` | `date` | YES | NULL |  |  |
| `result` | `varchar(256)` | YES | NULL |  |  |

## reference_dts_rapid_hiv

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `varchar(255)` | NO | NULL |  |  |
| `sample_id` | `varchar(255)` | NO | NULL |  |  |
| `testkit` | `varchar(255)` | NO | NULL |  |  |
| `test_date` | `date` | YES | NULL |  |  |
| `lot_no` | `varchar(255)` | NO | NULL |  |  |
| `expiry_date` | `date` | NO | NULL |  |  |
| `result` | `varchar(255)` | NO | NULL |  |  |

## reference_dts_wb

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL |  |  |
| `sample_id` | `int` | NO | NULL |  |  |
| `wb` | `int` | NO | NULL |  |  |
| `test_date` | `date` | YES | NULL |  |  |
| `lot` | `varchar(255)` | YES | NULL |  |  |
| `exp_date` | `date` | YES | NULL |  |  |
| `160` | `int` | YES | NULL |  |  |
| `120` | `int` | YES | NULL |  |  |
| `66` | `int` | YES | NULL |  |  |
| `55` | `int` | YES | NULL |  |  |
| `51` | `int` | YES | NULL |  |  |
| `41` | `int` | YES | NULL |  |  |
| `31` | `int` | YES | NULL |  |  |
| `24` | `int` | YES | NULL |  |  |
| `17` | `int` | YES | NULL |  |  |
| `result` | `varchar(256)` | YES | NULL |  |  |

## reference_generic_test_calculations

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `testkit_id` | `varchar(256)` | YES | NULL |  |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `no_of_responses` | `int` | YES | NULL |  |  |
| `q1` | `double(20,10)` | YES | NULL |  |  |
| `q3` | `double(20,10)` | YES | NULL |  |  |
| `iqr` | `double(20,10)` | YES | NULL |  |  |
| `quartile_low` | `double(20,10)` | YES | NULL |  |  |
| `quartile_high` | `double(20,10)` | YES | NULL |  |  |
| `mean` | `double(20,10)` | YES | NULL |  |  |
| `median` | `double(20,10)` | YES | NULL |  |  |
| `sd` | `double(20,10)` | YES | NULL |  |  |
| `standard_uncertainty` | `double(20,10)` | YES | NULL |  |  |
| `is_uncertainty_acceptable` | `varchar(255)` | YES | NULL |  |  |
| `cv` | `double(20,10)` | YES | NULL |  |  |
| `low_limit` | `double(20,10)` | YES | NULL |  |  |
| `high_limit` | `double(20,10)` | YES | NULL |  |  |
| `calculated_on` | `datetime` | YES | NULL |  |  |
| `manual_mean` | `double(20,10)` | YES | NULL |  |  |
| `manual_median` | `double(20,10)` | YES | NULL |  |  |
| `manual_sd` | `double(20,10)` | YES | NULL |  |  |
| `manual_standard_uncertainty` | `double(20,10)` | YES | NULL |  |  |
| `manual_is_uncertainty_acceptable` | `varchar(255)` | YES | NULL |  |  |
| `manual_cv` | `double(20,10)` | YES | NULL |  |  |
| `manual_q1` | `double(20,10)` | YES | NULL |  |  |
| `manual_q3` | `double(20,10)` | YES | NULL |  |  |
| `manual_iqr` | `double(20,10)` | YES | NULL |  |  |
| `manual_quartile_low` | `double(20,10)` | YES | NULL |  |  |
| `manual_quartile_high` | `double(20,10)` | YES | NULL |  |  |
| `manual_low_limit` | `double(20,10)` | YES | NULL |  |  |
| `manual_high_limit` | `double(20,10)` | YES | NULL |  |  |
| `z_score` | `double(20,10)` | NO | NULL |  |  |
| `is_result_invalid` | `varchar(256)` | NO | NULL |  |  |
| `error_code` | `varchar(100)` | NO | NULL |  |  |
| `comment` | `varchar(256)` | NO | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `int` | YES | NULL |  |  |
| `use_range` | `varchar(255)` | NO | calculated |  |  |

## reference_recency_assay

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | YES | NULL |  |  |
| `sample_id` | `varchar(256)` | YES | NULL |  |  |
| `assay` | `varchar(256)` | YES | NULL |  |  |
| `lot_no` | `varchar(256)` | YES | NULL |  |  |
| `expiry_date` | `date` | YES | NULL |  |  |
| `result` | `varchar(256)` | YES | NULL |  |  |

## reference_result_covid19

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(45)` | YES | NULL |  |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `reference_result` | `varchar(45)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `sample_score` | `decimal(10,4)` | NO | 0.0000 |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `shipment_id` | `shipment` | `shipment_id` |

## reference_result_dbs

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(45)` | YES | NULL |  |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `reference_result` | `varchar(45)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `sample_score` | `int` | NO | 1 |  |  |

## reference_result_dts

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(45)` | YES | NULL |  |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `reference_result` | `varchar(45)` | YES | NULL |  |  |
| `syphilis_reference_result` | `varchar(256)` | YES | NULL |  |  |
| `dts_rtri_reference_result` | `varchar(256)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `is_sample_diluted` | `varchar(50)` | YES | NULL |  |  |
| `sample_score` | `decimal(10,4)` | NO | 0.0000 |  |  |

## reference_result_eid

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(255)` | YES | NULL |  |  |
| `sample_preparation_date` | `varchar(256)` | YES | NULL |  |  |
| `reference_result` | `varchar(255)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `reference_hiv_ct_od` | `varchar(45)` | YES | NULL |  |  |
| `reference_ic_qs` | `varchar(45)` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `sample_score` | `decimal(10,4)` | NO | 0.0000 |  |  |

## reference_result_generic_test

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(255)` | YES | NULL |  |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `reference_result` | `varchar(256)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `sample_score` | `decimal(10,4)` | NO | 0.0000 |  |  |

## reference_result_recency

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `dts_id` | `int` | YES | NULL |  |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(255)` | YES | NULL |  |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `reference_result` | `varchar(255)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `reference_control_line` | `varchar(255)` | YES | NULL |  |  |
| `reference_diagnosis_line` | `varchar(255)` | YES | NULL |  |  |
| `reference_longterm_line` | `varchar(255)` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `sample_score` | `int` | NO | 1 |  |  |

## reference_result_tb

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `request_attributes` | `json` | YES | NULL |  |  |
| `sample_label` | `varchar(255)` | YES | NULL |  |  |
| `tb_isolate` | `varchar(255)` | YES | NULL |  |  |
| `mtb_detected` | `varchar(255)` | YES | NULL |  |  |
| `mtb_detected_ultra` | `varchar(256)` | YES | NULL |  |  |
| `rif_resistance` | `varchar(255)` | YES | NULL |  |  |
| `rif_resistance_ultra` | `varchar(256)` | YES | NULL |  |  |
| `probe_d` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_c` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_e` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_b` | `decimal(10,4)` | YES | NULL |  |  |
| `spc_xpert` | `decimal(10,4)` | YES | NULL |  |  |
| `spc_xpert_ultra` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_a` | `decimal(10,4)` | YES | NULL |  |  |
| `mtbrif_probe_a_mean_stability_ct` | `decimal(10,4)` | YES | NULL |  |  |
| `mtbultra_lowest_rpo_b_probe_mean_stability_ct` | `decimal(10,4)` | YES | NULL |  |  |
| `is1081_is6110` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b1` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b2` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b3` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b4` | `decimal(10,4)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `mtb_detection_consensus` | `varchar(3)` | YES | NULL |  |  |
| `rif_resistance_consensus` | `varchar(3)` | YES | NULL |  |  |
| `mtb_ultra_detection_consensus` | `varchar(3)` | YES | NULL |  |  |
| `rif_ultra_resistance_consensus` | `varchar(3)` | YES | NULL |  |  |
| `sample_score` | `decimal(10,4)` | NO | 0.0000 |  |  |

## reference_result_vl

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `sample_label` | `varchar(255)` | YES | NULL |  |  |
| `sample_preparation_date` | `date` | YES | NULL |  |  |
| `reference_result` | `varchar(45)` | YES | NULL |  |  |
| `control` | `int` | YES | NULL |  |  |
| `mandatory` | `int` | NO | 0 |  |  |
| `sample_score` | `decimal(10,4)` | NO | 0.0000 |  |  |

## reference_vl_calculation

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `vl_assay` | `int` | NO | NULL | PRI |  |
| `no_of_responses` | `int` | YES | NULL |  |  |
| `q1` | `double(10,2)` | NO | NULL |  |  |
| `q3` | `double(10,2)` | NO | NULL |  |  |
| `iqr` | `double(10,2)` | NO | NULL |  |  |
| `quartile_low` | `double(10,2)` | NO | NULL |  |  |
| `quartile_high` | `double(10,2)` | NO | NULL |  |  |
| `mean` | `double(10,2)` | NO | NULL |  |  |
| `median` | `double(20,10)` | YES | NULL |  |  |
| `sd` | `double(10,2)` | NO | NULL |  |  |
| `standard_uncertainty` | `double(20,10)` | YES | NULL |  |  |
| `is_uncertainty_acceptable` | `varchar(255)` | YES | NULL |  |  |
| `cv` | `double(10,2)` | NO | NULL |  |  |
| `low_limit` | `double(10,2)` | NO | NULL |  |  |
| `high_limit` | `double(10,2)` | NO | NULL |  |  |
| `calculated_on` | `datetime` | YES | NULL |  |  |
| `manual_q1` | `double(20,10)` | YES | NULL |  |  |
| `manual_q3` | `double(20,10)` | YES | NULL |  |  |
| `manual_iqr` | `double(20,10)` | YES | NULL |  |  |
| `manual_quartile_low` | `double(20,10)` | YES | NULL |  |  |
| `manual_quartile_high` | `double(20,10)` | YES | NULL |  |  |
| `manual_mean` | `double(20,10)` | NO | NULL |  |  |
| `manual_median` | `double(20,10)` | YES | NULL |  |  |
| `manual_sd` | `double(20,10)` | NO | NULL |  |  |
| `manual_standard_uncertainty` | `double(20,10)` | YES | NULL |  |  |
| `manual_is_uncertainty_acceptable` | `varchar(255)` | YES | NULL |  |  |
| `manual_cv` | `double(20,10)` | NO | NULL |  |  |
| `manual_low_limit` | `double(10,2)` | NO | 0.00 |  |  |
| `manual_high_limit` | `double(10,2)` | NO | 0.00 |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `int` | YES | NULL |  |  |
| `use_range` | `varchar(255)` | NO | calculated |  |  |

## reference_vl_methods

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `assay` | `int` | NO | NULL | PRI |  |
| `value` | `varchar(255)` | NO | NULL |  |  |

## response_covid19_not_tested_reason

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `covid19_not_tested_reason_id` | `int` | NO | NULL | PRI | auto_increment |
| `covid19_not_tested_reason` | `varchar(500)` | YES | NULL |  |  |
| `status` | `varchar(45)` | NO | active |  |  |

## response_result_covid19

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `test_type_1` | `varchar(45)` | YES | NULL |  |  |
| `name_of_pcr_reagent_1` | `varchar(256)` | YES | NULL |  |  |
| `pcr_reagent_lot_no_1` | `varchar(256)` | YES | NULL |  |  |
| `pcr_reagent_exp_date_1` | `date` | YES | NULL |  |  |
| `lot_no_1` | `varchar(45)` | YES | NULL |  |  |
| `exp_date_1` | `date` | YES | NULL |  |  |
| `test_result_1` | `varchar(45)` | YES | NULL |  |  |
| `test_type_2` | `varchar(45)` | YES | NULL |  |  |
| `name_of_pcr_reagent_2` | `varchar(256)` | YES | NULL |  |  |
| `pcr_reagent_lot_no_2` | `varchar(256)` | YES | NULL |  |  |
| `pcr_reagent_exp_date_2` | `date` | YES | NULL |  |  |
| `lot_no_2` | `varchar(45)` | YES | NULL |  |  |
| `exp_date_2` | `date` | YES | NULL |  |  |
| `test_result_2` | `varchar(45)` | YES | NULL |  |  |
| `test_type_3` | `varchar(45)` | YES | NULL |  |  |
| `name_of_pcr_reagent_3` | `varchar(256)` | YES | NULL |  |  |
| `pcr_reagent_lot_no_3` | `varchar(256)` | YES | NULL |  |  |
| `pcr_reagent_exp_date_3` | `date` | YES | NULL |  |  |
| `lot_no_3` | `varchar(45)` | YES | NULL |  |  |
| `exp_date_3` | `date` | YES | NULL |  |  |
| `test_result_3` | `varchar(45)` | YES | NULL |  |  |
| `reported_result` | `varchar(45)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `shipment_map_id` | `shipment_participant_map` | `map_id` |

## response_result_dbs

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `eia_1` | `int` | YES | NULL |  |  |
| `lot_no_1` | `varchar(45)` | YES | NULL |  |  |
| `exp_date_1` | `date` | YES | NULL |  |  |
| `od_1` | `varchar(45)` | YES | NULL |  |  |
| `cutoff_1` | `varchar(45)` | YES | NULL |  |  |
| `eia_2` | `int` | YES | NULL |  |  |
| `lot_no_2` | `varchar(45)` | YES | NULL |  |  |
| `exp_date_2` | `date` | YES | NULL |  |  |
| `od_2` | `varchar(45)` | YES | NULL |  |  |
| `cutoff_2` | `varchar(45)` | YES | NULL |  |  |
| `eia_3` | `int` | YES | NULL |  |  |
| `lot_no_3` | `varchar(45)` | YES | NULL |  |  |
| `exp_date_3` | `date` | YES | NULL |  |  |
| `od_3` | `varchar(45)` | YES | NULL |  |  |
| `cutoff_3` | `varchar(45)` | YES | NULL |  |  |
| `wb` | `int` | YES | NULL |  |  |
| `wb_lot` | `varchar(45)` | YES | NULL |  |  |
| `wb_exp_date` | `date` | YES | NULL |  |  |
| `wb_160` | `varchar(45)` | YES | NULL |  |  |
| `wb_120` | `varchar(45)` | YES | NULL |  |  |
| `wb_66` | `varchar(45)` | YES | NULL |  |  |
| `wb_55` | `varchar(45)` | YES | NULL |  |  |
| `wb_51` | `varchar(45)` | YES | NULL |  |  |
| `wb_41` | `varchar(45)` | YES | NULL |  |  |
| `wb_31` | `varchar(45)` | YES | NULL |  |  |
| `wb_24` | `varchar(45)` | YES | NULL |  |  |
| `wb_17` | `varchar(45)` | YES | NULL |  |  |
| `reported_result` | `int` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## response_result_dts

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `int` | NO | NULL | PRI |  |
| `test_kit_name_1` | `varchar(45)` | YES | NULL |  |  |
| `repeat_test_kit_name_1` | `varchar(256)` | YES | NULL |  |  |
| `lot_no_1` | `varchar(45)` | YES | NULL |  |  |
| `repeat_lot_no_1` | `varchar(256)` | YES | NULL |  |  |
| `exp_date_1` | `date` | YES | NULL |  |  |
| `repeat_exp_date_1` | `date` | YES | NULL |  |  |
| `test_result_1` | `varchar(45)` | YES | NULL |  |  |
| `syphilis_result` | `varchar(256)` | YES | NULL |  |  |
| `repeat_test_result_1` | `varchar(256)` | YES | NULL |  |  |
| `qc_done_1` | `varchar(256)` | YES | NULL |  |  |
| `repeat_qc_done_1` | `varchar(256)` | YES | NULL |  |  |
| `qc_date_1` | `date` | YES | NULL |  |  |
| `repeat_qc_date_1` | `date` | YES | NULL |  |  |
| `test_kit_name_2` | `varchar(45)` | YES | NULL |  |  |
| `repeat_test_kit_name_2` | `varchar(256)` | YES | NULL |  |  |
| `lot_no_2` | `varchar(45)` | YES | NULL |  |  |
| `repeat_lot_no_2` | `varchar(256)` | YES | NULL |  |  |
| `exp_date_2` | `date` | YES | NULL |  |  |
| `repeat_exp_date_2` | `date` | YES | NULL |  |  |
| `test_result_2` | `varchar(45)` | YES | NULL |  |  |
| `repeat_test_result_2` | `varchar(256)` | YES | NULL |  |  |
| `qc_done_2` | `varchar(256)` | YES | NULL |  |  |
| `repeat_qc_done_2` | `varchar(256)` | YES | NULL |  |  |
| `qc_date_2` | `date` | YES | NULL |  |  |
| `repeat_qc_date_2` | `date` | YES | NULL |  |  |
| `test_kit_name_3` | `varchar(45)` | YES | NULL |  |  |
| `repeat_test_kit_name_3` | `varchar(256)` | YES | NULL |  |  |
| `lot_no_3` | `varchar(45)` | YES | NULL |  |  |
| `repeat_lot_no_3` | `varchar(256)` | YES | NULL |  |  |
| `exp_date_3` | `date` | YES | NULL |  |  |
| `repeat_exp_date_3` | `date` | YES | NULL |  |  |
| `test_result_3` | `varchar(45)` | YES | NULL |  |  |
| `repeat_test_result_3` | `varchar(256)` | YES | NULL |  |  |
| `qc_done_3` | `varchar(256)` | YES | NULL |  |  |
| `repeat_qc_done_3` | `varchar(256)` | YES | NULL |  |  |
| `qc_date_3` | `date` | YES | NULL |  |  |
| `repeat_qc_date_3` | `date` | YES | NULL |  |  |
| `reported_result` | `varchar(45)` | YES | NULL |  |  |
| `lab_comment` | `varchar(50)` | YES | NULL |  |  |
| `syphilis_final` | `varchar(256)` | YES | NULL |  |  |
| `is_this_retest` | `varchar(256)` | YES | NULL |  |  |
| `dts_rtri_control_line` | `varchar(256)` | YES | NULL |  |  |
| `dts_rtri_diagnosis_line` | `varchar(256)` | YES | NULL |  |  |
| `dts_rtri_longterm_line` | `varchar(256)` | YES | NULL |  |  |
| `dts_rtri_reported_result` | `varchar(256)` | YES | NULL |  |  |
| `if_this_is_retest` | `varchar(256)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `algorithm_result` | `varchar(50)` | YES | NULL |  |  |
| `interpretation_result` | `varchar(50)` | YES | NULL |  |  |
| `dts_rtri_is_editable` | `varchar(256)` | YES | no |  |  |
| `kit_additional_info` | `json` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## response_result_eid

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `varchar(45)` | NO | NULL | PRI |  |
| `reported_result` | `varchar(45)` | YES | NULL |  |  |
| `hiv_ct_od` | `varchar(45)` | YES | NULL |  |  |
| `ic_qs` | `varchar(45)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## response_result_generic_test

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `varchar(45)` | NO | NULL | PRI |  |
| `result_1` | `varchar(256)` | YES | NULL |  |  |
| `result_2` | `varchar(256)` | YES | NULL |  |  |
| `result_3` | `varchar(256)` | YES | NULL |  |  |
| `reported_result` | `varchar(256)` | YES | NULL |  |  |
| `additional_detail` | `varchar(256)` | YES | NULL |  |  |
| `z_score` | `double(20,10)` | YES | NULL |  |  |
| `is_result_invalid` | `varchar(256)` | YES | NULL |  |  |
| `error_code` | `varchar(256)` | YES | NULL |  |  |
| `comments` | `varchar(256)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## response_result_recency

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `dts_id` | `int` | YES | NULL |  |  |
| `sample_id` | `varchar(45)` | NO | NULL | PRI |  |
| `reported_result` | `varchar(45)` | YES | NULL |  |  |
| `control_line` | `varchar(255)` | YES | NULL |  |  |
| `diagnosis_line` | `varchar(255)` | YES | NULL |  |  |
| `longterm_line` | `varchar(255)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(255)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## response_result_tb

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `varchar(45)` | NO | NULL | PRI |  |
| `response_attributes` | `json` | YES | NULL |  |  |
| `assay_id` | `int` | NO | NULL | PRI |  |
| `mtb_detected` | `varchar(255)` | YES | NULL |  |  |
| `rif_resistance` | `varchar(255)` | YES | NULL | MUL |  |
| `probe_d` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_c` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_e` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_b` | `decimal(10,4)` | YES | NULL |  |  |
| `spc_xpert` | `decimal(10,4)` | YES | NULL |  |  |
| `spc_xpert_ultra` | `decimal(10,4)` | YES | NULL |  |  |
| `probe_a` | `decimal(10,4)` | YES | NULL |  |  |
| `test_date` | `date` | YES | NULL |  |  |
| `is1081_is6110` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b1` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b2` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b3` | `decimal(10,4)` | YES | NULL |  |  |
| `rpo_b4` | `decimal(10,4)` | YES | NULL |  |  |
| `instrument_serial_no` | `varchar(256)` | YES | NULL |  |  |
| `gene_xpert_module_no` | `varchar(256)` | YES | NULL |  |  |
| `tester_name` | `varchar(256)` | YES | NULL |  |  |
| `error_code` | `varchar(256)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## response_result_vl

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_map_id` | `int` | NO | NULL | PRI |  |
| `sample_id` | `varchar(45)` | NO | NULL | PRI |  |
| `reported_viral_load` | `double(10,2)` | YES | NULL |  |  |
| `z_score` | `double(20,10)` | YES | NULL |  |  |
| `calculated_score` | `varchar(45)` | YES | NULL |  |  |
| `vl_assay` | `varchar(255)` | YES | NULL |  |  |
| `is_tnd` | `varchar(45)` | YES | NULL |  |  |
| `is_result_invalid` | `varchar(256)` | YES | NULL |  |  |
| `error_code` | `varchar(100)` | YES | NULL |  |  |
| `module_number` | `varchar(100)` | YES | NULL |  |  |
| `comment` | `varchar(256)` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

## run_once_scripts

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `script_name` | `varchar(255)` | NO | NULL | PRI |  |
| `executed_at` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## scheduled_jobs

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `job_id` | `int` | NO | NULL | PRI | auto_increment |
| `job` | `text` | YES | NULL |  |  |
| `requested_on` | `datetime` | YES | NULL |  |  |
| `started_on` | `datetime` | YES | NULL |  |  |
| `requested_by` | `varchar(256)` | YES | NULL |  |  |
| `completed_on` | `datetime` | YES | NULL |  |  |
| `status` | `varchar(256)` | NO | pending |  |  |
| `initated_by` | `int` | YES | NULL |  |  |

## scheme_config

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `scheme_config_name` | `varchar(32)` | NO | NULL | PRI |  |
| `scheme_config_value` | `json` | YES | NULL |  |  |

## scheme_list

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `scheme_id` | `varchar(255)` | NO | NULL | PRI |  |
| `scheme_name` | `varchar(255)` | NO | NULL | UNI |  |
| `response_table` | `varchar(45)` | YES | NULL |  |  |
| `reference_result_table` | `varchar(45)` | YES | NULL |  |  |
| `is_user_configured` | `varchar(50)` | NO | no |  |  |
| `test_format` | `varchar(20)` | YES | NULL |  |  |
| `user_test_config` | `json` | YES | NULL |  |  |
| `attribute_list` | `varchar(255)` | YES | NULL |  |  |
| `status` | `varchar(255)` | YES | NULL |  |  |

## scheme_testkit_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `scheme_type` | `varchar(255)` | NO | NULL | PRI |  |
| `testkit_id` | `varchar(255)` | NO | NULL | PRI |  |
| `testkit_1` | `int` | NO | 0 |  |  |
| `testkit_2` | `int` | NO | 0 |  |  |
| `testkit_3` | `int` | NO | 0 |  |  |
| `shipment_id` | `int` | YES | NULL |  |  |

## shipment

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_code` | `varchar(255)` | NO | NULL |  |  |
| `scheme_type` | `varchar(256)` | YES | NULL | MUL |  |
| `shipment_date` | `date` | YES | NULL |  |  |
| `response_deadline` | `datetime` | YES | NULL |  |  |
| `auto_close_at_deadline` | `enum('yes','no')` | NO | yes |  |  |
| `distribution_id` | `int` | NO | NULL | MUL |  |
| `number_of_samples` | `int` | YES | NULL |  |  |
| `number_of_controls` | `int` | NO | NULL |  |  |
| `response_switch` | `varchar(255)` | NO | off |  |  |
| `allow_editing_response` | `enum('yes','no')` | NO | yes |  |  |
| `max_score` | `int` | YES | NULL |  |  |
| `average_score` | `varchar(255)` | YES | 0 |  |  |
| `shipment_attributes` | `json` | YES | NULL |  |  |
| `shipment_comment` | `mediumtext` | YES | NULL |  |  |
| `issuing_authority` | `varchar(256)` | YES | NULL |  |  |
| `pt_co_ordinator_name` | `mediumtext` | YES | NULL |  |  |
| `pt_co_ordinator_email` | `varchar(256)` | YES | NULL |  |  |
| `pt_co_ordinator_phone` | `varchar(256)` | YES | NULL |  |  |
| `created_by_admin` | `varchar(255)` | YES | NULL |  |  |
| `created_on_admin` | `datetime` | YES | NULL |  |  |
| `updated_by_admin` | `varchar(255)` | YES | NULL |  |  |
| `updated_on_admin` | `datetime` | YES | NULL |  |  |
| `status` | `varchar(255)` | NO | pending |  |  |
| `evaluated_at` | `datetime` | YES | NULL |  |  |
| `reports_generated_at` | `datetime` | YES | NULL |  |  |
| `finalized_at` | `datetime` | YES | NULL |  |  |
| `results_approved_on` | `datetime` | YES | NULL |  |  |
| `cancelled_at` | `datetime` | YES | NULL |  |  |
| `cancelled_by` | `varchar(255)` | YES | NULL |  |  |
| `cancellation_reason` | `text` | YES | NULL |  |  |
| `report_in_queue` | `varchar(50)` | NO | no |  |  |
| `corrective_action_file` | `varchar(256)` | YES | NULL |  |  |
| `tb_form_generated` | `varchar(50)` | YES | no |  |  |
| `collect_feedback` | `varchar(50)` | NO | no |  |  |
| `feedback_expiry_date` | `date` | YES | NULL |  |  |
| `previous_status` | `varchar(256)` | YES | NULL |  |  |
| `processing_started_at` | `datetime` | YES | NULL |  |  |
| `last_heartbeat` | `datetime` | YES | NULL |  |  |
| `results_approved_by` | `varchar(256)` | YES | NULL |  |  |

## shipment_participant_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `map_id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | NO | NULL | MUL |  |
| `participant_id` | `int` | NO | NULL | MUL |  |
| `lab_director_name` | `varchar(256)` | YES | NULL |  |  |
| `lab_director_email` | `varchar(256)` | YES | NULL |  |  |
| `contact_person_name` | `varchar(256)` | YES | NULL |  |  |
| `contact_person_email` | `varchar(256)` | YES | NULL |  |  |
| `contact_person_telephone` | `varchar(256)` | YES | NULL |  |  |
| `attributes` | `json` | YES | NULL |  |  |
| `evaluation_status` | `varchar(10)` | YES | NULL |  |  |
| `shipment_score` | `decimal(5,2)` | YES | NULL |  |  |
| `documentation_score` | `decimal(5,2)` | YES | 0.00 |  |  |
| `shipment_test_date` | `date` | YES | NULL |  |  |
| `number_of_tests` | `int` | YES | NULL |  |  |
| `specimen_volume` | `varchar(255)` | YES | NULL |  |  |
| `is_pt_test_not_performed` | `varchar(45)` | YES | NULL |  |  |
| `pt_not_tested_reason` | `int` | YES | NULL |  |  |
| `received_pt_panel` | `varchar(256)` | YES | NULL |  |  |
| `pt_test_not_performed_comments` | `mediumtext` | YES | NULL |  |  |
| `pt_support_comments` | `mediumtext` | YES | NULL |  |  |
| `shipment_receipt_date` | `date` | YES | NULL |  |  |
| `shipment_test_report_date` | `datetime` | YES | NULL |  |  |
| `is_response_late` | `varchar(256)` | YES | NULL |  |  |
| `participant_supervisor` | `varchar(255)` | YES | NULL |  |  |
| `supervisor_approval` | `varchar(45)` | YES | NULL |  |  |
| `review_date` | `date` | YES | NULL |  |  |
| `final_result` | `int` | YES | 0 |  |  |
| `failure_reason` | `mediumtext` | YES | NULL |  |  |
| `evaluation_comment` | `int` | YES | 0 |  |  |
| `optional_eval_comment` | `mediumtext` | YES | NULL |  |  |
| `is_followup` | `varchar(255)` | YES | no |  |  |
| `is_excluded` | `enum('yes','no')` | YES | NULL |  |  |
| `user_comment` | `varchar(90)` | YES | NULL |  |  |
| `custom_field_1` | `mediumtext` | YES | NULL |  |  |
| `custom_field_2` | `mediumtext` | YES | NULL |  |  |
| `created_on_admin` | `datetime` | YES | NULL |  |  |
| `updated_on_admin` | `datetime` | YES | NULL |  |  |
| `updated_by_admin` | `varchar(45)` | YES | NULL |  |  |
| `updated_on_user` | `datetime` | YES | NULL |  |  |
| `updated_by_user` | `varchar(45)` | YES | NULL |  |  |
| `created_by_admin` | `varchar(45)` | YES | NULL |  |  |
| `created_on_user` | `datetime` | YES | NULL |  |  |
| `started_at` | `datetime` | YES | NULL |  |  |
| `late_submit_status` | `tinyint(1)` | NO | 0 |  |  |
| `report_generated` | `varchar(100)` | YES | NULL |  |  |
| `last_new_shipment_mailed_on` | `datetime` | YES | NULL |  |  |
| `new_shipment_mail_count` | `int` | YES | 0 |  |  |
| `last_not_participated_mailed_on` | `datetime` | YES | NULL |  |  |
| `last_not_participated_mail_count` | `int` | NO | 0 |  |  |
| `qc_done` | `varchar(45)` | NO | no |  |  |
| `qc_date` | `date` | YES | NULL |  |  |
| `qc_done_by` | `varchar(255)` | YES | NULL |  |  |
| `qc_created_on` | `datetime` | YES | NULL |  |  |
| `mode_id` | `int` | YES | NULL |  |  |
| `manual_override` | `varchar(50)` | NO | no |  |  |
| `show_announcement` | `varchar(45)` | NO | yes |  |  |
| `synced` | `varchar(50)` | NO | no |  |  |
| `synced_on` | `datetime` | YES | NULL |  |  |
| `mode_of_response` | `varchar(50)` | YES | NULL |  |  |
| `user_client_info` | `json` | YES | NULL |  |  |
| `response_status` | `varchar(256)` | YES | noresponse |  |  |
| `report_download_metadata` | `json` | YES | NULL |  |  |
| `individual_report_downloaded_on` | `datetime` | YES | NULL |  |  |

## shipment_testkit_map

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `shipment_id` | `int` | NO | NULL | PRI |  |
| `scheme_type` | `varchar(255)` | NO | NULL |  |  |
| `testkit_id` | `varchar(255)` | NO | NULL | PRI |  |
| `testkit_1` | `int` | NO | 0 |  |  |
| `testkit_2` | `int` | NO | 0 |  |  |
| `testkit_3` | `int` | NO | 0 |  |  |

## system_admin

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `admin_id` | `int` | NO | NULL | PRI | auto_increment |
| `first_name` | `varchar(255)` | YES | NULL |  |  |
| `last_name` | `varchar(255)` | YES | NULL |  |  |
| `primary_email` | `varchar(255)` | YES | NULL |  |  |
| `password` | `text` | YES | NULL |  |  |
| `secondary_email` | `varchar(255)` | YES | NULL |  |  |
| `phone` | `varchar(255)` | YES | NULL |  |  |
| `force_password_reset` | `int` | YES | NULL |  |  |
| `scheme` | `mediumtext` | YES | NULL |  |  |
| `language` | `varchar(256)` | YES | en_US |  |  |
| `status` | `varchar(255)` | YES | inactive |  |  |
| `privileges` | `varchar(255)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `created_by` | `varchar(255)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(255)` | YES | NULL |  |  |

## system_config

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `config` | `varchar(256)` | NO | NULL | PRI |  |
| `value` | `longtext` | YES | NULL |  |  |
| `display_name` | `longtext` | YES | NULL |  |  |

## system_metadata

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `metadata_id` | `varchar(128)` | NO | NULL | PRI |  |
| `metadata_value` | `text` | YES | NULL |  |  |

## tb_instruments

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `instrument_id` | `int` | NO | NULL | PRI | auto_increment |
| `map_id` | `int` | YES | NULL |  |  |
| `participant_id` | `int` | NO | NULL | MUL |  |
| `instrument_serial` | `varchar(45)` | YES | NULL |  |  |
| `instrument_installed_on` | `date` | YES | NULL |  |  |
| `instrument_last_calibrated_on` | `date` | YES | NULL |  |  |
| `created_by` | `varchar(45)` | YES | NULL |  |  |
| `created_on` | `datetime` | YES | NULL |  |  |
| `updated_by` | `varchar(45)` | YES | NULL |  |  |
| `updated_on` | `datetime` | YES | NULL |  |  |

| Colonne de clé étrangère | Table référencée | Colonne référencée |
| --- | --- | --- |
| `participant_id` | `participant` | `participant_id` |

## temp_mail

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `temp_id` | `int` | NO | NULL | PRI | auto_increment |
| `message` | `mediumtext` | YES | NULL |  |  |
| `from_mail` | `varchar(255)` | YES | NULL |  |  |
| `reply_to` | `varchar(128)` | YES | NULL |  |  |
| `to_email` | `varchar(255)` | NO | NULL |  |  |
| `bcc` | `mediumtext` | YES | NULL |  |  |
| `cc` | `mediumtext` | YES | NULL |  |  |
| `subject` | `mediumtext` | YES | NULL |  |  |
| `from_full_name` | `varchar(255)` | YES | NULL |  |  |
| `queued_on` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `attachment` | `varchar(255)` | YES | NULL |  |  |
| `status` | `varchar(32)` | NO | pending |  |  |
| `failure_reason` | `text` | YES | NULL |  |  |
| `failure_type` | `varchar(64)` | YES | NULL |  |  |
| `updated_at` | `timestamp` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED on update CURRENT_TIMESTAMP |
| `sent_at` | `datetime` | YES | NULL |  |  |
| `created_at` | `timestamp` | YES | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |

## track_api_requests

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `api_track_id` | `int` | NO | NULL | PRI | auto_increment |
| `transaction_id` | `varchar(256)` | YES | NULL |  |  |
| `requested_by` | `varchar(255)` | YES | NULL |  |  |
| `requested_on` | `datetime` | YES | NULL | MUL |  |
| `number_of_records` | `varchar(50)` | YES | NULL |  |  |
| `request_type` | `varchar(50)` | YES | NULL |  |  |
| `test_type` | `varchar(255)` | YES | NULL |  |  |
| `api_url` | `mediumtext` | YES | NULL |  |  |
| `api_params` | `text` | YES | NULL |  |  |
| `request_data` | `text` | YES | NULL |  |  |
| `response_data` | `text` | YES | NULL |  |  |
| `data_format` | `varchar(255)` | YES | NULL |  |  |

## track_report_downloaded_history

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `id` | `int` | NO | NULL | PRI | auto_increment |
| `shipment_id` | `int` | YES | NULL |  |  |
| `report_type` | `varchar(100)` | YES | NULL |  |  |
| `downloaded_on` | `datetime` | NO | CURRENT_TIMESTAMP |  | DEFAULT_GENERATED |
| `downloaded_by` | `varchar(256)` | NO | NULL |  |  |

## user_login_history

| Colonne | Type SQL | Nullable | Valeur par défaut | Clé | Attributs supplémentaires |
| --- | --- | --- | --- | --- | --- |
| `history_id` | `int` | NO | NULL | PRI | auto_increment |
| `login_context` | `enum('participant','admin','')` | YES | participant |  |  |
| `user_id` | `varchar(1000)` | YES | NULL |  |  |
| `login_id` | `varchar(1000)` | NO | NULL |  |  |
| `login_attempted_datetime` | `datetime` | YES | NULL |  |  |
| `login_status` | `varchar(256)` | YES | NULL | MUL |  |
| `ip_address` | `varchar(256)` | YES | NULL |  |  |
| `browser` | `varchar(1000)` | YES | NULL |  |  |
| `operating_system` | `varchar(1000)` | YES | NULL |  |  |
| `session_hash` | `varchar(16)` | YES | NULL | MUL |  |
