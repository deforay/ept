-- Migration for version 7.7.7
-- Amit Dugar - Nov 2026
UPDATE `system_config` SET `value` = '7.7.7' WHERE `config` = 'app_version';

-- Store 100 as the maximum score of rounds already evaluated.
--
-- The EID, VL, TB, Recency, COVID-19 and custom-test evaluators store each lab's
-- score as a percentage, but saved the raw sum of sample scores (for example 5 or
-- 9) as the shipment's maximum. Summary reports compare each lab's score with that
-- maximum to count full scores, so they reported that no lab scored the maximum.
-- The evaluators now store 100. This corrects rounds evaluated before the change,
-- so their summaries come out right when regenerated, without re-evaluation.
--
-- DTS is left out: its maximum comes from the algorithm and is on the same scale
-- as its scores.
--
-- Safe to re-run: rows already at 100 do not change.

UPDATE `shipment` AS `s`
LEFT JOIN `scheme_list` AS `sl` ON `sl`.`scheme_id` = `s`.`scheme_type`
SET `s`.`max_score` = 100
WHERE `s`.`max_score` IS NOT NULL
    AND `s`.`max_score` <> 100
    AND (`s`.`scheme_type` IN ('eid', 'vl', 'tb', 'recency', 'covid19')
        OR `sl`.`is_user_configured` = 'yes');
