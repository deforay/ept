SELECT
    flattenedevaluationresults.`Country`,
    flattenedevaluationresults.`Site No.`,
    flattenedevaluationresults.`Site Name/Location`,
    flattenedevaluationresults.`PT-ID`,
    flattenedevaluationresults.`Submitted`,
    flattenedevaluationresults.`Submission Excluded`,
    flattenedevaluationresults.`Date PT Received`,
    flattenedevaluationresults.`Date PT Results Reported`,
    JSON_UNQUOTE(
        flattenedevaluationresults.attributes_json -> " $ .cartridge_lot_no"
    ) AS `Cartridge Lot Number`,
    flattenedevaluationresults.`assay_name` AS `Assay`,
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
    ) END AS `Expiry Date`,
    flattenedevaluationresults.`Participated`,
    flattenedevaluationresults.`Reason for No Submission`,
    flattenedevaluationresults.`1-Date Tested`,
    flattenedevaluationresults.`1-MTB`,
    flattenedevaluationresults.`1-Rif`,
    flattenedevaluationresults.`1-Probe D`,
    flattenedevaluationresults.`1-Probe C`,
    flattenedevaluationresults.`1-Probe E`,
    flattenedevaluationresults.`1-Probe B`,
    flattenedevaluationresults.`1-SPC`,
    flattenedevaluationresults.`1-Probe A`,
    flattenedevaluationresults.`2-Date Tested`,
    flattenedevaluationresults.`2-MTB`,
    flattenedevaluationresults.`2-Rif`,
    flattenedevaluationresults.`2-Probe D`,
    flattenedevaluationresults.`2-Probe C`,
    flattenedevaluationresults.`2-Probe E`,
    flattenedevaluationresults.`2-Probe B`,
    flattenedevaluationresults.`2-SPC`,
    flattenedevaluationresults.`2-Probe A`,
    flattenedevaluationresults.`3-Date Tested`,
    flattenedevaluationresults.`3-MTB`,
    flattenedevaluationresults.`3-Rif`,
    flattenedevaluationresults.`3-Probe D`,
    flattenedevaluationresults.`3-Probe C`,
    flattenedevaluationresults.`3-Probe E`,
    flattenedevaluationresults.`3-Probe B`,
    flattenedevaluationresults.`3-SPC`,
    flattenedevaluationresults.`3-Probe A`,
    flattenedevaluationresults.`4-Date Tested`,
    flattenedevaluationresults.`4-MTB`,
    flattenedevaluationresults.`4-Rif`,
    flattenedevaluationresults.`4-Probe D`,
    flattenedevaluationresults.`4-Probe C`,
    flattenedevaluationresults.`4-Probe E`,
    flattenedevaluationresults.`4-Probe B`,
    flattenedevaluationresults.`4-SPC`,
    flattenedevaluationresults.`4-Probe A`,
    flattenedevaluationresults.`5-Date Tested`,
    flattenedevaluationresults.`5-MTB`,
    flattenedevaluationresults.`5-Rif`,
    flattenedevaluationresults.`5-Probe D`,
    flattenedevaluationresults.`5-Probe C`,
    flattenedevaluationresults.`5-Probe E`,
    flattenedevaluationresults.`5-Probe B`,
    flattenedevaluationresults.`5-SPC`,
    flattenedevaluationresults.`5-Probe A`,
    flattenedevaluationresults.`Comments`,
    flattenedevaluationresults.`Comments for reports`,
    flattenedevaluationresults.`1-Score`,
    flattenedevaluationresults.`2-Score`,
    flattenedevaluationresults.`3-Score`,
    flattenedevaluationresults.`4-Score`,
    flattenedevaluationresults.`5-Score`,
    flattenedevaluationresults.`Fin Score`,
    flattenedevaluationresults.`Sat/Unsat`
FROM
    (
        SELECT
            countries.iso_name AS `Country`,
            participant.participant_id AS `Site No.`,
            Concat(
                participant.lab_name,
                COALESCE(
                    Concat(
                        ' - ',
                        CASE WHEN participant.state = '' THEN NULL ELSE participant.state END
                    ),
                    Concat(
                        ' - ',
                        CASE WHEN participant.city = '' THEN NULL ELSE participant.city END
                    ),
                    ''
                )
            ) AS `Site Name/Location`,
            participant.unique_identifier AS `PT-ID`,
            CASE WHEN SUBSTRING(shipment_participant_map.evaluation_status, 3, 1) = '9'
            OR SUBSTRING(shipment_participant_map.evaluation_status, 4, 1) = '0' THEN 'No' WHEN SUBSTRING(shipment_participant_map.evaluation_status, 3, 1) = '1'
            AND SUBSTRING(shipment_participant_map.evaluation_status, 4, 1) = '1' THEN 'Yes' WHEN SUBSTRING(shipment_participant_map.evaluation_status, 4, 1) = '2' THEN 'Yes (Late)' END AS `Submitted`,
            CASE WHEN shipment_participant_map.is_excluded = 'yes' THEN 'Yes' ELSE 'No' END AS `Submission Excluded`,
            shipment_participant_map.shipment_receipt_date AS `Date PT Received`,
            CAST(
                shipment_participant_map.shipment_test_report_date AS DATE
            ) AS `Date PT Results Reported`,
            CAST(attributes AS json) AS attributes_json,
            r_tb_assay.name AS assay_name,
            CASE WHEN ifnull(
                shipment_participant_map.is_pt_test_not_performed,
                'no'
            ) = 'no' THEN 'Yes' ELSE 'No' END AS `Participated`,
            ifnull(
                shipment_participant_map.pt_test_not_performed_comments,
                r_response_not_tested_reasons.ntr_reason
            ) AS `Reason for No Submission`,
            response_result_tb_1.test_date AS `1-Date Tested`,
            CASE WHEN response_result_tb_1.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_1.error_code, '') != '' THEN concat('Error ', response_result_tb_1.error_code) WHEN response_result_tb_1.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_1.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_1.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_1.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_1.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_1.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_1.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_1.mtb_detected, 2, 254)
            ) END AS `1-MTB`,
            CASE WHEN response_result_tb_1.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_1.error_code, '') != '' THEN concat('Error ', response_result_tb_1.error_code) WHEN response_result_tb_1.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_1.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_1.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_1.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_1.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_1.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_1.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_1.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_1.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_1.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_1.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_1.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_1.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_1.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_1.rif_resistance, 2, 254)
            ) END AS `1-Rif`,
            response_result_tb_1.probe_d AS `1-Probe D`,
            response_result_tb_1.probe_c AS `1-Probe C`,
            response_result_tb_1.probe_e AS `1-Probe E`,
            response_result_tb_1.probe_b AS `1-Probe B`,
            response_result_tb_1.spc AS `1-SPC`,
            response_result_tb_1.probe_a AS `1-Probe A`,
            response_result_tb_2.test_date AS `2-Date Tested`,
            CASE WHEN response_result_tb_2.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_2.error_code, '') != '' THEN concat('Error ', response_result_tb_2.error_code) WHEN response_result_tb_2.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_2.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_2.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_2.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_2.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_2.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_2.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_2.mtb_detected, 2, 254)
            ) END AS `2-MTB`,
            CASE WHEN response_result_tb_2.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_2.error_code, '') != '' THEN concat('Error ', response_result_tb_2.error_code) WHEN response_result_tb_2.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_2.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_2.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_2.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_2.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_2.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_2.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_2.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_2.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_2.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_2.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_2.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_2.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_2.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_2.rif_resistance, 2, 254)
            ) END AS `2-Rif`,
            response_result_tb_2.probe_d AS `2-Probe D`,
            response_result_tb_2.probe_c AS `2-Probe C`,
            response_result_tb_2.probe_e AS `2-Probe E`,
            response_result_tb_2.probe_b AS `2-Probe B`,
            response_result_tb_2.spc AS `2-SPC`,
            response_result_tb_2.probe_a AS `2-Probe A`,
            response_result_tb_3.test_date AS `3-Date Tested`,
            CASE WHEN response_result_tb_3.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_3.error_code, '') != '' THEN concat('Error ', response_result_tb_3.error_code) WHEN response_result_tb_3.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_3.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_3.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_3.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_3.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_3.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_3.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_3.mtb_detected, 2, 254)
            ) END AS `3-MTB`,
            CASE WHEN response_result_tb_3.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_3.error_code, '') != '' THEN concat('Error ', response_result_tb_3.error_code) WHEN response_result_tb_3.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_3.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_3.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_3.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_3.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_3.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_3.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_3.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_3.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_3.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_3.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_3.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_3.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_3.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_3.rif_resistance, 2, 254)
            ) END AS `3-Rif`,
            response_result_tb_3.probe_d AS `3-Probe D`,
            response_result_tb_3.probe_c AS `3-Probe C`,
            response_result_tb_3.probe_e AS `3-Probe E`,
            response_result_tb_3.probe_b AS `3-Probe B`,
            response_result_tb_3.spc AS `3-SPC`,
            response_result_tb_3.probe_a AS `3-Probe A`,
            response_result_tb_4.test_date AS `4-Date Tested`,
            CASE WHEN response_result_tb_4.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_4.error_code, '') != '' THEN concat('Error ', response_result_tb_4.error_code) WHEN response_result_tb_4.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_4.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_4.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_4.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_4.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_4.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_4.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_4.mtb_detected, 2, 254)
            ) END AS `4-MTB`,
            CASE WHEN response_result_tb_4.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_4.error_code, '') != '' THEN concat('Error ', response_result_tb_4.error_code) WHEN response_result_tb_4.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_4.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_4.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_4.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_4.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_4.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_4.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_4.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_4.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_4.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_4.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_4.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_4.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_4.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_4.rif_resistance, 2, 254)
            ) END AS `4-Rif`,
            response_result_tb_4.probe_d AS `4-Probe D`,
            response_result_tb_4.probe_c AS `4-Probe C`,
            response_result_tb_4.probe_e AS `4-Probe E`,
            response_result_tb_4.probe_b AS `4-Probe B`,
            response_result_tb_4.spc AS `4-SPC`,
            response_result_tb_4.probe_a AS `4-Probe A`,
            response_result_tb_5.test_date AS `5-Date Tested`,
            CASE WHEN response_result_tb_5.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_5.error_code, '') != '' THEN concat('Error ', response_result_tb_5.error_code) WHEN response_result_tb_5.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_5.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_5.mtb_detected = 'veryLow' THEN 'Very Low' WHEN response_result_tb_5.mtb_detected = 'trace' THEN 'Trace' WHEN response_result_tb_5.mtb_detected = 'na' THEN 'N/A' WHEN ifnull(response_result_tb_5.mtb_detected, '') = '' THEN NULL ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_5.mtb_detected, 1, 1)
                ),
                SUBSTRING(response_result_tb_5.mtb_detected, 2, 254)
            ) END AS `5-MTB`,
            CASE WHEN response_result_tb_5.error_code = 'error' THEN 'Error' WHEN ifnull(response_result_tb_5.error_code, '') != '' THEN concat('Error ', response_result_tb_5.error_code) WHEN response_result_tb_5.mtb_detected = 'noResult' THEN 'No Result' WHEN response_result_tb_5.mtb_detected = 'invalid' THEN 'Invalid' WHEN response_result_tb_5.mtb_detected = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_5.mtb_detected IN ('detected', 'veryLow', 'low', 'medium', 'high')
            AND ifnull(response_result_tb_5.rif_resistance, 'na') = 'na' THEN 'Not Detected' WHEN response_result_tb_5.rif_resistance = 'notDetected' THEN 'Not Detected' WHEN response_result_tb_5.rif_resistance = 'noResult' THEN 'No Result' WHEN response_result_tb_5.rif_resistance = 'veryLow' THEN 'Very Low' WHEN response_result_tb_5.rif_resistance = 'na' THEN 'N/A' WHEN response_result_tb_5.mtb_detected = 'notDetected'
            AND ifnull(response_result_tb_5.rif_resistance, '') = '' THEN 'N/A' WHEN response_result_tb_5.mtb_detected NOT IN ('noResult', 'notDetected', 'invalid')
            AND ifnull(response_result_tb_5.rif_resistance, '') = '' THEN 'N/A' ELSE concat(
                UPPER(
                    SUBSTRING(response_result_tb_5.rif_resistance, 1, 1)
                ),
                SUBSTRING(response_result_tb_5.rif_resistance, 2, 254)
            ) END AS `5-Rif`,
            response_result_tb_5.probe_d AS `5-Probe D`,
            response_result_tb_5.probe_c AS `5-Probe C`,
            response_result_tb_5.probe_e AS `5-Probe E`,
            response_result_tb_5.probe_b AS `5-Probe B`,
            response_result_tb_5.spc AS `5-SPC`,
            response_result_tb_5.probe_a AS `5-Probe A`,
            TRIM(shipment_participant_map.user_comment) AS `Comments`,
            TRIM(
                COALESCE(
                    CASE WHEN r_evaluation_comments.`comment` = '' THEN NULL ELSE r_evaluation_comments.`comment` END,
                    shipment_participant_map.optional_eval_comment
                )
            ) AS `Comments for reports`,
            CASE WHEN response_result_tb_1.calculated_score IN ('pass', 'concern', 'exempt') THEN 20 WHEN response_result_tb_1.calculated_score = 'partial' THEN 10 WHEN response_result_tb_1.calculated_score = 'noresult' THEN 5 WHEN response_result_tb_1.calculated_score IN ('fail', 'excluded') THEN 0 ELSE 0 END AS `1-Score`,
            CASE WHEN response_result_tb_2.calculated_score IN ('pass', 'concern', 'exempt') THEN 20 WHEN response_result_tb_2.calculated_score = 'partial' THEN 10 WHEN response_result_tb_2.calculated_score = 'noresult' THEN 5 WHEN response_result_tb_2.calculated_score IN ('fail', 'excluded') THEN 0 ELSE 0 END AS `2-Score`,
            CASE WHEN response_result_tb_3.calculated_score IN ('pass', 'concern', 'exempt') THEN 20 WHEN response_result_tb_3.calculated_score = 'partial' THEN 10 WHEN response_result_tb_3.calculated_score = 'noresult' THEN 5 WHEN response_result_tb_3.calculated_score IN ('fail', 'excluded') THEN 0 ELSE 0 END AS `3-Score`,
            CASE WHEN response_result_tb_4.calculated_score IN ('pass', 'concern', 'exempt') THEN 20 WHEN response_result_tb_4.calculated_score = 'partial' THEN 10 WHEN response_result_tb_4.calculated_score = 'noresult' THEN 5 WHEN response_result_tb_4.calculated_score IN ('fail', 'excluded') THEN 0 ELSE 0 END AS `4-Score`,
            CASE WHEN response_result_tb_5.calculated_score IN ('pass', 'concern', 'exempt') THEN 20 WHEN response_result_tb_5.calculated_score = 'partial' THEN 10 WHEN response_result_tb_5.calculated_score = 'noresult' THEN 5 WHEN response_result_tb_5.calculated_score IN ('fail', 'excluded') THEN 0 ELSE 0 END AS `5-Score`,
            ifnull(shipment_participant_map.documentation_score, 0) + ifnull(shipment_participant_map.shipment_score, 0) AS `Fin Score`,
            CASE WHEN r_results.result_name = 'Pass' THEN 'Satisfactory' ELSE 'Unsatisfactory' END AS `Sat/Unsat`
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