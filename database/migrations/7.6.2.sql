-- Migration for version 7.6.2
-- Amit Dugar - Jun 2026
UPDATE `system_config` SET `value` = '7.6.2' WHERE `config` = 'app_version';

-- Per-shipment test-kit -> test-position map. Until now the admin "Testkit Map"
-- page mutated the GLOBAL scheme_testkit_map row per kit (PK scheme_type,testkit_id),
-- so a per-shipment mapping was impossible and any edit clobbered the global default
-- every other shipment relies on. This table holds the shipment-specific overrides;
-- scheme_testkit_map stays the catalog + final fallback (used per position when a
-- shipment has no rows for that position).
--
-- Idempotent: CREATE TABLE IF NOT EXISTS (migrate.php has a create-if-missing handler),
-- so this is safe to re-run.
CREATE TABLE IF NOT EXISTS `shipment_testkit_map` (
  `shipment_id` int NOT NULL,
  `scheme_type` varchar(255) NOT NULL,
  `testkit_id` varchar(255) NOT NULL,
  `testkit_1` int NOT NULL DEFAULT 0,
  `testkit_2` int NOT NULL DEFAULT 0,
  `testkit_3` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`shipment_id`, `testkit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
