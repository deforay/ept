<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Namespaced cascade delete for AUTOTEST-* rows.
 */
final class Cleanup
{
    private const PREFIX = 'ATEST-';

    public function __construct(private Db $db) {}

    /**
     * Delete one shipment and everything attached to it; restore global settings
     * from the stash the harness wrote into shipment_attributes. Leaves participants.
     *
     * @return array{restored: array} stash that was restored (empty if none)
     */
    public function deleteShipment(int $shipmentId): array
    {
        return $this->db->tx(function () use ($shipmentId) {
            $attrsJson = (string) ($this->db->scalar(
                "SELECT shipment_attributes FROM shipment WHERE shipment_id = ?",
                [$shipmentId]
            ) ?? '');
            $stash = [];
            if ($attrsJson !== '') {
                $attrs = json_decode($attrsJson, true);
                if (is_array($attrs) && !empty($attrs['atest_prev_settings'])) {
                    $stash = (array) $attrs['atest_prev_settings'];
                }
            }

            $this->db->exec(
                "DELETE rrd FROM response_result_dts rrd
                   JOIN shipment_participant_map spm ON spm.map_id = rrd.shipment_map_id
                  WHERE spm.shipment_id = ?",
                [$shipmentId]
            );
            $this->db->exec("DELETE FROM shipment_participant_map WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM reference_result_dts    WHERE shipment_id = ?", [$shipmentId]);
            // DTS reference modal rows (entered via the per-sample Reference Results modal
            // on the shipment-edit page; populated by Provisioner::createReferenceModalData).
            $this->db->exec("DELETE FROM reference_dts_eia        WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM reference_dts_wb         WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM reference_dts_rapid_hiv  WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM reference_dts_geenius    WHERE shipment_id = ?", [$shipmentId]);
            // FK-referencing tables that the app may have populated post-provision
            // (queue_report_generation populated by report generation; others usually
            // empty for ATEST shipments but cleared defensively).
            $this->db->exec("DELETE FROM queue_report_generation  WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM participant_testkit_map  WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM shipment_testkit_map    WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM participant_feedback_answer WHERE shipment_id = ?", [$shipmentId]);
            $distributionId = $this->db->scalar("SELECT distribution_id FROM shipment WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM shipment WHERE shipment_id = ?", [$shipmentId]);
            if ($distributionId !== null) {
                $code = (string) $this->db->scalar("SELECT distribution_code FROM distributions WHERE distribution_id = ?", [$distributionId]);
                if (str_starts_with($code, self::PREFIX)) {
                    $this->db->exec("DELETE FROM distributions WHERE distribution_id = ?", [$distributionId]);
                }
            }

            if (!empty($stash)) {
                (new AppSettings($this->db))->restore($stash);
            }
            return ['restored' => $stash];
        });
    }

    /**
     * Delete every ATEST-* shipment, distribution, and participant.
     *
     * Restores global settings from the OLDEST surviving ATEST shipment's stash —
     * that's the one guaranteed to hold the true pre-harness state, since every
     * later shipment just stashed whatever was already flipped by an earlier run.
     */
    public function nuke(): array
    {
        return $this->db->tx(function () {
            $shipments = $this->db->all(
                "SELECT shipment_id, shipment_attributes FROM shipment WHERE shipment_code LIKE ?
                  ORDER BY shipment_id ASC",
                [self::PREFIX . '%']
            );

            $stash = [];
            foreach ($shipments as $s) {
                $attrs = json_decode((string) ($s['shipment_attributes'] ?? ''), true);
                if (is_array($attrs) && !empty($attrs['atest_prev_settings'])) {
                    $stash = (array) $attrs['atest_prev_settings'];
                    break;
                }
            }

            $shipmentIds = array_map(static fn ($s) => (int) $s['shipment_id'], $shipments);
            foreach ($shipmentIds as $sid) {
                $this->db->exec(
                    "DELETE rrd FROM response_result_dts rrd
                       JOIN shipment_participant_map spm ON spm.map_id = rrd.shipment_map_id
                      WHERE spm.shipment_id = ?",
                    [$sid]
                );
                $this->db->exec("DELETE FROM shipment_participant_map WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_result_dts    WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_eia        WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_wb         WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_rapid_hiv  WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_geenius    WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM queue_report_generation WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM participant_testkit_map WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM shipment_testkit_map WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM participant_feedback_answer WHERE shipment_id = ?", [$sid]);
            }
            if (!empty($shipmentIds)) {
                $in = implode(',', array_map('intval', $shipmentIds));
                $this->db->exec("DELETE FROM shipment WHERE shipment_id IN ($in)");
            }

            $distRows = $this->db->exec("DELETE FROM distributions WHERE distribution_code LIKE ?", [self::PREFIX . '%']);
            $partRows = $this->db->exec("DELETE FROM participant   WHERE unique_identifier LIKE ?", [self::PREFIX . 'p%']);

            if (!empty($stash)) {
                (new AppSettings($this->db))->restore($stash);
            }

            return [
                'shipments'     => count($shipmentIds),
                'distributions' => $distRows->rowCount(),
                'participants'  => $partRows->rowCount(),
                'restored'      => $stash,
            ];
        });
    }

    /**
     * Wipe every ATEST shipment (and its responses, maps, reference rows, queued
     * reports, distributions) WITHOUT touching ATEST participants or restoring
     * global scheme_config. Use when you want to drop synthetic shipments fast
     * and immediately re-provision against the same Vietnam settings.
     */
    public function nukeShipments(): array
    {
        return $this->db->tx(function () {
            $shipmentIds = array_map('intval', $this->db->col(
                "SELECT shipment_id FROM shipment WHERE shipment_code LIKE ?",
                [self::PREFIX . '%']
            ));

            foreach ($shipmentIds as $sid) {
                $this->db->exec(
                    "DELETE rrd FROM response_result_dts rrd
                       JOIN shipment_participant_map spm ON spm.map_id = rrd.shipment_map_id
                      WHERE spm.shipment_id = ?",
                    [$sid]
                );
                $this->db->exec("DELETE FROM shipment_participant_map WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_result_dts    WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_eia        WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_wb         WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_rapid_hiv  WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM reference_dts_geenius    WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM queue_report_generation WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM participant_testkit_map WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM shipment_testkit_map WHERE shipment_id = ?", [$sid]);
                $this->db->exec("DELETE FROM participant_feedback_answer WHERE shipment_id = ?", [$sid]);
            }
            if (!empty($shipmentIds)) {
                $in = implode(',', array_map('intval', $shipmentIds));
                $this->db->exec("DELETE FROM shipment WHERE shipment_id IN ($in)");
            }
            $distRows = $this->db->exec("DELETE FROM distributions WHERE distribution_code LIKE ?", [self::PREFIX . '%']);

            return [
                'shipments'     => count($shipmentIds),
                'distributions' => $distRows->rowCount(),
            ];
        });
    }
}
