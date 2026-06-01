<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Reads and writes the few global settings that gate evaluation and report
 * generation for DTS — currently:
 *
 *   scheme_config.dts.dtsSchemeType   — algorithm dispatch + scheme behavior
 *   report_config.report-layout       — which country layout phtml is rendered
 *
 * The harness flips these to match the chosen variant before evaluation runs,
 * stashes the previous values on shipment_attributes, and restores them when
 * the test shipment is cleaned up.
 */
final class AppSettings
{
    public function __construct(private Db $db) {}

    /** Flip every setting the variant declares; return a stash of prior values. */
    public function applyVariant(array $variant): array
    {
        $stash = [];
        // Compose the scheme_config.dts overrides: always set dtsSchemeType; layer
        // any variant-declared dtsConfig overrides on top (e.g. allowedAlgorithms,
        // documentationScore for Vietnam).
        $overrides = array_merge(
            ['dtsSchemeType' => $variant['schemeType']],
            $variant['dtsConfig'] ?? []
        );
        $stash['dtsConfig'] = $this->applyDtsConfigOverrides($overrides);
        if (!empty($variant['reportLayout'])) {
            $stash['reportLayout'] = $this->setReportLayout($variant['reportLayout']);
        }
        if (!empty($variant['exposeResultCodes'])) {
            $stash['resultCodeDisplay'] = $this->exposeResultCodes($variant['exposeResultCodes']);
        }
        return $stash;
    }

    /** Restore everything in a stash. Missing keys are skipped. */
    public function restore(array $stash): void
    {
        if (!empty($stash['dtsConfig']) && is_array($stash['dtsConfig'])) {
            $this->applyDtsConfigOverrides($stash['dtsConfig']);
        } elseif (isset($stash['dtsSchemeType'])) {
            // back-compat with stashes from before dtsConfig was introduced
            $this->applyDtsConfigOverrides(['dtsSchemeType' => (string) $stash['dtsSchemeType']]);
        }
        if (isset($stash['reportLayout'])) {
            $this->setReportLayout((string) $stash['reportLayout']);
        }
        if (!empty($stash['resultCodeDisplay']) && is_array($stash['resultCodeDisplay'])) {
            $this->restoreResultCodeDisplay($stash['resultCodeDisplay']);
        }
    }

    /**
     * Write multiple scheme_config.dts keys in one shot, returning a per-key stash
     * of the prior values for restore. Keys missing from the original JSON come
     * back as '' so restore writes them back as empty (matches the form's "not set"
     * behavior).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function applyDtsConfigOverrides(array $overrides): array
    {
        $cfg = $this->getDtsConfig();
        $stash = [];
        $dirty = false;
        foreach ($overrides as $k => $v) {
            $prev = $cfg[$k] ?? '';
            $stash[$k] = $prev;
            if ((string) $prev !== (string) $v) {
                $cfg[$k] = $v;
                $dirty = true;
            }
        }
        if ($dirty) {
            $this->db->exec(
                "UPDATE scheme_config SET scheme_config_value = ? WHERE scheme_config_name = 'dts'",
                [json_encode($cfg)]
            );
        }
        return $stash;
    }

    /**
     * Set display_context='all' on each declared r_possibleresult row and return a
     * per-id stash of the prior display_context values so cleanup can revert.
     *
     * @param array<int,array{scheme:string,sub_group:string,code:string}> $codes
     * @return array<int,string> id => prior display_context
     */
    private function exposeResultCodes(array $codes): array
    {
        $stash = [];
        foreach ($codes as $c) {
            $row = $this->db->one(
                "SELECT id, display_context FROM r_possibleresult
                  WHERE scheme_id = ? AND scheme_sub_group = ? AND result_code = ?",
                [$c['scheme'], $c['sub_group'], $c['code']]
            );
            if ($row === null) {
                continue; // migration that seeds this code hasn't run; nothing to flip
            }
            $id   = (int) $row['id'];
            $prev = (string) $row['display_context'];
            $stash[$id] = $prev;
            if ($prev !== 'all') {
                $this->db->exec("UPDATE r_possibleresult SET display_context = 'all' WHERE id = ?", [$id]);
            }
        }
        return $stash;
    }

    /** @param array<int,string> $stash id => prior display_context */
    private function restoreResultCodeDisplay(array $stash): void
    {
        foreach ($stash as $id => $prev) {
            $this->db->exec("UPDATE r_possibleresult SET display_context = ? WHERE id = ?", [$prev, (int) $id]);
        }
    }

    public function getDtsConfig(): array
    {
        $json = (string) ($this->db->scalar(
            "SELECT scheme_config_value FROM scheme_config WHERE scheme_config_name = 'dts'"
        ) ?? '');
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Update scheme_config.dts.dtsSchemeType, returning whatever was there before. */
    public function setDtsSchemeType(string $type): string
    {
        $cfg = $this->getDtsConfig();
        $prev = (string) ($cfg['dtsSchemeType'] ?? '');
        if ($prev === $type) {
            return $prev;
        }
        $cfg['dtsSchemeType'] = $type;
        $this->db->exec(
            "UPDATE scheme_config SET scheme_config_value = ? WHERE scheme_config_name = 'dts'",
            [json_encode($cfg)]
        );
        return $prev;
    }

    /** Update report_config.report-layout, returning the prior value. */
    public function setReportLayout(string $layout): string
    {
        $prev = (string) ($this->db->scalar(
            "SELECT value FROM report_config WHERE name = 'report-layout'"
        ) ?? '');
        if ($prev === $layout) {
            return $prev;
        }
        $existed = $this->db->scalar("SELECT 1 FROM report_config WHERE name = 'report-layout'");
        if ($existed) {
            $this->db->exec("UPDATE report_config SET value = ? WHERE name = 'report-layout'", [$layout]);
        } else {
            $this->db->exec("INSERT INTO report_config (name, value) VALUES ('report-layout', ?)", [$layout]);
        }
        return $prev;
    }
}
