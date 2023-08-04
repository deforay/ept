SELECT
    flattenedevaluationresults.`Country`,
    flattenedevaluationresults.`Site No.`,
    flattenedevaluationresults.`Site Name/Location`,
    flattenedevaluationresults.`PT-ID`,
    flattenedevaluationresults.`Submitted`,
    flattenedevaluationresults.`Submission Excluded`,
    flattenedevaluationresults.`Panel Received date`,
    flattenedevaluationresults.`Date PT Results Reported`,
    JSON_UNQUOTE(
        flattenedevaluationresults.attributes_json -> " $ .assay_lot_number"
    ) AS `Cartridge/Assay Lot`,
    flattenedevaluationresults.`assay_name` AS `Assay Name`,
    CASE WHEN JSON_UNQUOTE(
        flattenedevaluationresults.attributes_json -> " $ .expiry_date"
    ) = '0000-00-00' THEN NULL ELSE COALESCE(
        STR_TO_DATE(
            JSON_UNQUOTE(
                flattenedevaluationresults.attributes_json -> " $ .expiry_date"
            ),
            '%d-%b-%Y'
        ),
        STR_TO_DATE(
            JSON_UNQUOTE(
                flattenedevaluationresults.attributes_json -> " $ .expiry_date"
            ),
            '%Y-%b-%d'
        ),
        STR_TO_DATE(
            JSON_UNQUOTE(
                flattenedevaluationresults.attributes_json -> " $ .expiry_date"
            ),
            '%d-%m-%Y'
        ),
        STR_TO_DATE(
            JSON_UNQUOTE(
                flattenedevaluationresults.attributes_json -> " $ .expiry_date"
            ),
            '%Y-%m-%d'
        )
    ) END AS `Cartridge/Assay Expiration`,
    JSON_UNQUOTE(
        flattenedevaluationresults.attributes_json -> " $ .date_of_xpert_instrument_calibration"
    ) AS `Date of last instrument calibration`,
    JSON_UNQUOTE(
        flattenedevaluationresults.attributes_json -> " $ .instrument_sn"
    ) AS `Instrument Serial`,
    flattenedevaluationresults.`Ability to test panel`,
    flattenedevaluationresults.`Reason for No Submission`,
    flattenedevaluationresults.`Reason for No Submission`,
    flattenedevaluationresults.`1-Test Date`,
    flattenedevaluationresults.`1-Error Code`,
    flattenedevaluationresults.`1-MTB Result`,
    flattenedevaluationresults.`1-Rif Resistance Result`,
    flattenedevaluationresults.`1-Probe-1`,
    flattenedevaluationresults.`1-Probe-2`,
    flattenedevaluationresults.`1-Probe-3`,
    flattenedevaluationresults.`1-Probe-4`,
    flattenedevaluationresults.`1-Probe-5`,
    flattenedevaluationresults.`1-Probe-6`,
    flattenedevaluationresults.`2-Test Date`,
    flattenedevaluationresults.`2-Error Code`,
    flattenedevaluationresults.`2-MTB Result`,
    flattenedevaluationresults.`2-Rif Resistance Result`,
    flattenedevaluationresults.`2-Probe-1`,
    flattenedevaluationresults.`2-Probe-2`,
    flattenedevaluationresults.`2-Probe-3`,
    flattenedevaluationresults.`2-Probe-4`,
    flattenedevaluationresults.`2-Probe-5`,
    flattenedevaluationresults.`2-Probe-6`,
    flattenedevaluationresults.`3-Test Date`,
    flattenedevaluationresults.`3-Error Code`,
    flattenedevaluationresults.`3-MTB Result`,
    flattenedevaluationresults.`3-Rif Resistance Result`,
    flattenedevaluationresults.`3-Probe-1`,
    flattenedevaluationresults.`3-Probe-2`,
    flattenedevaluationresults.`3-Probe-3`,
    flattenedevaluationresults.`3-Probe-4`,
    flattenedevaluationresults.`3-Probe-5`,
    flattenedevaluationresults.`3-Probe-6`,
    flattenedevaluationresults.`4-Test Date`,
    flattenedevaluationresults.`4-Error Code`,
    flattenedevaluationresults.`4-MTB Result`,
    flattenedevaluationresults.`4-Rif Resistance Result`,
    flattenedevaluationresults.`4-Probe-1`,
    flattenedevaluationresults.`4-Probe-2`,
    flattenedevaluationresults.`4-Probe-3`,
    flattenedevaluationresults.`4-Probe-4`,
    flattenedevaluationresults.`4-Probe-5`,
    flattenedevaluationresults.`4-Probe-6`,
    flattenedevaluationresults.`5-Test Date`,
    flattenedevaluationresults.`5-Error Code`,
    flattenedevaluationresults.`5-MTB Result`,
    flattenedevaluationresults.`5-Rif Resistance Result`,
    flattenedevaluationresults.`5-Probe-1`,
    flattenedevaluationresults.`5-Probe-2`,
    flattenedevaluationresults.`5-Probe-3`,
    flattenedevaluationresults.`5-Probe-4`,
    flattenedevaluationresults.`5-Probe-5`,
    flattenedevaluationresults.`5-Probe-6`,
    flattenedevaluationresults.`Comments`,
    flattenedevaluationresults.`Comments for reports`,
    flattenedevaluationresults.`1-Score`,
    flattenedevaluationresults.`2-Score`,
    flattenedevaluationresults.`3-Score`,
    flattenedevaluationresults.`4-Score`,
    flattenedevaluationresults.`5-Score`,
    flattenedevaluationresults.`Final score`,
    flattenedevaluationresults.`Satisfactory/Unsatisfactory`
FROM
    (
        SELECT
            countries.iso_name AS `Country`,
            participant.participant_id AS `Site No.`,
            Concat(
                IF(lab_name IS NULL, CONCAT(COALESCE(participant.first_name, ''), ' ', COALESCE(participant.last_name, '')), lab_name),
                COALESCE(
                    CONCAT(
                        ' - ',
                        CASE WHEN participant.state = '' THEN NULL ELSE participant.state END
                    ),
                    CONCAT(
                        ' - ',
                        CASE WHEN participant.city = '' THEN NULL ELSE participant.city END
                    ),
                    ''
                )
            ) AS `Site Name/Location`,
            participant.unique_identifier AS `PT-ID`,
            CASE
            WHEN (IFNULL(shipment_participant_map.response_status, 'noresponse') like 'responded' AND IFNULL(shipment_participant_map.is_response_late, 'no') like 'yes') THEN 'Yes (Late)'
            WHEN IFNULL(shipment_participant_map.response_status, 'noresponse') like 'noresponse' THEN 'No'
            WHEN IFNULL(shipment_participant_map.response_status, 'noresponse') like 'responded' THEN 'Yes'
            END
            AS `Submitted`,
            CASE WHEN shipment_participant_map.is_excluded = 'yes' THEN 'Yes' ELSE 'No' END AS `Submission Excluded`,
            shipment_participant_map.shipment_receipt_date AS `Panel Received date`,
            CAST(
                shipment_participant_map.shipment_test_report_date AS DATE
            ) AS `Date PT Results Reported`,
            CAST(attributes AS json) AS attributes_json,
            r_tb_assay.name AS assay_name,
            CASE WHEN ifnull(
                shipment_participant_map.is_pt_test_not_performed,
                'no'
            ) = 'no' THEN 'Yes' ELSE 'No' END AS `Ability to test panel`,
            ifnull(
                shipment_participant_map.pt_test_not_performed_comments,
                r_response_not_tested_reasons.ntr_reason
            ) AS `Reason for No Submission`,
            response_result_tb_1.test_date AS `1-Test Date`,
            CASE
                WHEN response_result_tb_1.error_code = 'error' THEN 'Error'
                WHEN ifnull(response_result_tb_1.error_code, '') != '' THEN concat('Error ', response_result_tb_1.error_code)
            END AS `1-Error Code`,
            CASE WHEN response_result_tb_1.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_1.error_code, '') != '' THEN concat('Error ', response_result_tb_1.error_code) WHEN response_result_tb_1.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_1.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_1.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_1.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_1.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_1.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_1.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_1.mtb_detected, 2, 254)
            ) END AS `1-MTB Result`,
            CASE WHEN response_result_tb_1.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_1.error_code, '') != '' THEN concat('Error ', response_result_tb_1.error_code) WHEN response_result_tb_1.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_1.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_1.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_1.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_1.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_1.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_1.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_1.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_1.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_1.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_1.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_1.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_1.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_1.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_1.rif_resistance, 2, 254)
            ) END AS `1-Rif Resistance Result`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_1.probe_d WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_1.is1081_is6110 END AS `1-Probe-1`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_1.probe_c WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_1.rpo_b1 END AS `1-Probe-2`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_1.probe_e WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_1.rpo_b2 END AS `1-Probe-3`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_1.probe_b WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_1.rpo_b3 END AS `1-Probe-4`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_1.spc WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_1.rpo_b4 END AS `1-Probe-5`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_1.probe_a WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_1.gene_xpert_module_no END AS `1-Probe-6`,
            response_result_tb_2.test_date AS `2-Test Date`,
            CASE
                WHEN response_result_tb_2.error_code = 'error' THEN 'Error'
                WHEN ifnull(response_result_tb_2.error_code, '') != '' THEN concat('Error ', response_result_tb_2.error_code)
            END AS `2-Error Code`,
            CASE WHEN response_result_tb_2.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_2.error_code, '') != '' THEN concat('Error ', response_result_tb_2.error_code) WHEN response_result_tb_2.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_2.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_2.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_2.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_2.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_2.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_2.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_2.mtb_detected, 2, 254)
            ) END AS `2-MTB Result`,
            CASE WHEN response_result_tb_2.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_2.error_code, '') != '' THEN concat('Error ', response_result_tb_2.error_code) WHEN response_result_tb_2.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_2.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_2.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_2.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_2.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_2.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_2.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_2.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_2.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_2.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_2.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_2.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_2.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_2.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_2.rif_resistance, 2, 254)
            ) END AS `2-Rif Resistance Result`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_2.probe_d WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_2.is1081_is6110 END AS `2-Probe-1`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_2.probe_c WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_2.rpo_b1 END AS `2-Probe-2`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_2.probe_e WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_2.rpo_b2 END AS `2-Probe-3`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_2.probe_b WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_2.rpo_b3 END AS `2-Probe-4`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_2.spc WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_2.rpo_b4 END AS `2-Probe-5`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_2.probe_a WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_2.gene_xpert_module_no END AS `2-Probe-6`,
            response_result_tb_3.test_date AS `3-Test Date`,
            CASE
                WHEN response_result_tb_3.error_code = 'error' THEN 'Error'
                WHEN ifnull(response_result_tb_3.error_code, '') != '' THEN concat('Error ', response_result_tb_3.error_code)
            END AS `3-Error Code`,
            CASE WHEN response_result_tb_3.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_3.error_code, '') != '' THEN concat('Error ', response_result_tb_3.error_code) WHEN response_result_tb_3.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_3.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_3.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_3.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_3.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_3.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_3.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_3.mtb_detected, 2, 254)
            ) END AS `3-MTB Result`,
            CASE WHEN response_result_tb_3.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_3.error_code, '') != '' THEN concat('Error ', response_result_tb_3.error_code) WHEN response_result_tb_3.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_3.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_3.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_3.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_3.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_3.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_3.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_3.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_3.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_3.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_3.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_3.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_3.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_3.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_3.rif_resistance, 2, 254)
            ) END AS `3-Rif Resistance Result`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_3.probe_d WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_3.is1081_is6110 END AS `3-Probe-1`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_3.probe_c WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_3.rpo_b1 END AS `3-Probe-2`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_3.probe_e WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_3.rpo_b2 END AS `3-Probe-3`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_3.probe_b WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_3.rpo_b3 END AS `3-Probe-4`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_3.spc WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_3.rpo_b4 END AS `3-Probe-5`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_3.probe_a WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_3.gene_xpert_module_no END AS `3-Probe-6`,
            response_result_tb_4.test_date AS `4-Test Date`,
            CASE
                WHEN response_result_tb_4.error_code = 'error' THEN 'Error'
                WHEN ifnull(response_result_tb_4.error_code, '') != '' THEN concat('Error ', response_result_tb_4.error_code)
            END AS `4-Error Code`,
            CASE WHEN response_result_tb_4.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_4.error_code, '') != '' THEN concat('Error ', response_result_tb_4.error_code) WHEN response_result_tb_4.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_4.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_4.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_4.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_4.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_4.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_4.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_4.mtb_detected, 2, 254)
            ) END AS `4-MTB Result`,
            CASE WHEN response_result_tb_4.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_4.error_code, '') != '' THEN concat('Error ', response_result_tb_4.error_code) WHEN response_result_tb_4.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_4.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_4.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_4.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_4.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_4.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_4.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_4.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_4.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_4.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_4.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_4.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_4.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_4.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_4.rif_resistance, 2, 254)
            ) END AS `4-Rif Resistance Result`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_4.probe_d WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_4.is1081_is6110 END AS `4-Probe-1`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_4.probe_c WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_4.rpo_b1 END AS `4-Probe-2`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_4.probe_e WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_4.rpo_b2 END AS `4-Probe-3`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_4.probe_b WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_4.rpo_b3 END AS `4-Probe-4`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_4.spc WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_4.rpo_b4 END AS `4-Probe-5`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_4.probe_a WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_4.gene_xpert_module_no END AS `4-Probe-6`,
            response_result_tb_5.test_date AS `5-Test Date`,
            CASE
                WHEN response_result_tb_5.error_code = 'error' THEN 'Error'
                WHEN ifnull(response_result_tb_5.error_code, '') != '' THEN concat('Error ', response_result_tb_5.error_code)
            END AS `5-Error Code`,
            CASE WHEN response_result_tb_5.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_5.error_code, '') != '' THEN concat('Error ', response_result_tb_5.error_code) WHEN response_result_tb_5.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_5.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_5.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_5.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_5.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_5.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_5.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_5.mtb_detected, 2, 254)
            ) END AS `5-MTB Result`,
            CASE WHEN response_result_tb_5.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_5.error_code, '') != '' THEN concat('Error ', response_result_tb_5.error_code) WHEN response_result_tb_5.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_5.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_5.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_5.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_5.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_5.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_5.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_5.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_5.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_5.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_5.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_5.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_5.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_5.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_5.rif_resistance, 2, 254)
            ) END AS `5-Rif Resistance Result`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_5.probe_d WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_5.is1081_is6110 END AS `5-Probe-1`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_5.probe_c WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_5.rpo_b1 END AS `5-Probe-2`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_5.probe_e WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_5.rpo_b2 END AS `5-Probe-3`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_5.probe_b WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_5.rpo_b3 END AS `5-Probe-4`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_5.spc WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_5.rpo_b4 END AS `5-Probe-5`,
            CASE WHEN (r_tb_assay.short_name like 'xpert-mtb-rif') THEN response_result_tb_5.probe_a WHEN (r_tb_assay.short_name like 'xpert-mtb-rif-ultra') THEN response_result_tb_5.gene_xpert_module_no END AS `5-Probe-6`,
            TRIM(shipment_participant_map.user_comment) AS `Comments`,
            TRIM(
                COALESCE(
                    CASE WHEN r_evaluation_comments.`comment` = '' THEN NULL ELSE r_evaluation_comments.`comment` END,
                    shipment_participant_map.optional_eval_comment
                )
            ) AS `Comments for reports`,
            response_result_tb_1.calculated_score AS `1-Score`,
            response_result_tb_2.calculated_score AS `2-Score`,
            response_result_tb_3.calculated_score AS `3-Score`,
            response_result_tb_4.calculated_score AS `4-Score`,
            response_result_tb_5.calculated_score AS `5-Score`,
            CONCAT(TRIM(SUM(ifnull(shipment_participant_map.documentation_score, 0) + ifnull(shipment_participant_map.shipment_score, 0)))+0, '%')AS `Final score`,
            CASE WHEN r_results.result_name = 'Pass' THEN 'Satisfactory' ELSE 'Unsatisfactory' END AS `Satisfactory/Unsatisfactory`
        FROM
            shipment
            JOIN shipment_participant_map ON shipment_participant_map.shipment_id = shipment.shipment_id
            JOIN participant ON participant.participant_id = shipment_participant_map.participant_id
            JOIN countries ON countries.id = participant.country
            LEFT JOIN r_response_not_tested_reasons ON r_response_not_tested_reasons.ntr_id = shipment_participant_map.vl_not_tested_reason
            LEFT JOIN r_evaluation_comments ON r_evaluation_comments.comment_id = shipment_participant_map.evaluation_comment
            LEFT JOIN r_results ON r_results.result_id = shipment_participant_map.final_result
            LEFT JOIN r_tb_assay ON r_tb_assay.id = json_unquote(
                json_extract(
                    shipment_participant_map.attributes,
                    " $ .assay_name"
                )
            )
            LEFT JOIN response_result_tb AS response_result_tb_1 ON response_result_tb_1.shipment_map_id = shipment_participant_map.map_id
            AND response_result_tb_1.sample_id = '1'
            LEFT JOIN response_result_tb AS response_result_tb_2 ON response_result_tb_2.shipment_map_id = shipment_participant_map.map_id
            AND response_result_tb_2.sample_id = '2'
            LEFT JOIN response_result_tb AS response_result_tb_3 ON response_result_tb_3.shipment_map_id = shipment_participant_map.map_id
            AND response_result_tb_3.sample_id = '3'
            LEFT JOIN response_result_tb AS response_result_tb_4 ON response_result_tb_4.shipment_map_id = shipment_participant_map.map_id
            AND response_result_tb_4.sample_id = '4'
            LEFT JOIN response_result_tb AS response_result_tb_5 ON response_result_tb_5.shipment_map_id = shipment_participant_map.map_id
            AND response_result_tb_5.sample_id = '5'
        WHERE
            shipment.shipment_id = ?
        GROUP BY
            shipment_participant_map.map_id
    ) AS flattenedevaluationresults
ORDER BY
    flattenedevaluationresults.`PT-ID` * 1 ASC;
