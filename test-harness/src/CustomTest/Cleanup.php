<?php

declare(strict_types=1);

namespace EptTestHarness\CustomTest;

use EptTestHarness\Db;

/**
 * Namespaced cascade delete for the custom-test harness.
 *
 * Targets ONLY the harness's own shipments (shipment_code 'ATEST-CT-*') and their
 * distributions ('ATEST-CTD-*'). It runs against EXISTING real schemes, so it never
 * touches scheme_list or r_possibleresult. Shared ATEST participants are left in place.
 */
final class Cleanup
{
    private const SHIP_PREFIX = 'ATEST-CT-';
    private const DIST_PREFIX = 'ATEST-CTD-';

    public function __construct(private Db $db) {}

    /** Delete one custom-test shipment and everything attached to it. */
    public function deleteShipment(int $shipmentId): array
    {
        return $this->db->tx(function () use ($shipmentId) {
            $this->deleteShipmentRows($shipmentId);
            $distributionId = $this->db->scalar("SELECT distribution_id FROM shipment WHERE shipment_id = ?", [$shipmentId]);
            $this->db->exec("DELETE FROM shipment WHERE shipment_id = ?", [$shipmentId]);
            if ($distributionId !== null) {
                $code = (string) $this->db->scalar("SELECT distribution_code FROM distributions WHERE distribution_id = ?", [$distributionId]);
                if (str_starts_with($code, self::DIST_PREFIX)) {
                    $this->db->exec("DELETE FROM distributions WHERE distribution_id = ?", [$distributionId]);
                }
            }
            return ['shipments' => 1];
        });
    }

    /** Delete every custom-test ATEST shipment + its distributions. Leaves schemes and participants. */
    public function nuke(): array
    {
        return $this->db->tx(function () {
            $shipmentIds = array_map('intval', $this->db->col(
                "SELECT shipment_id FROM shipment WHERE shipment_code LIKE ?",
                [self::SHIP_PREFIX . '%']
            ));
            foreach ($shipmentIds as $sid) {
                $this->deleteShipmentRows($sid);
            }
            $shipmentsDeleted = 0;
            if (!empty($shipmentIds)) {
                $in = implode(',', $shipmentIds);
                $shipmentsDeleted = $this->db->exec("DELETE FROM shipment WHERE shipment_id IN ($in)")->rowCount();
            }
            $distRows = $this->db->exec("DELETE FROM distributions WHERE distribution_code LIKE ?", [self::DIST_PREFIX . '%']);

            return [
                'shipments'     => $shipmentsDeleted,
                'distributions' => $distRows->rowCount(),
            ];
        });
    }

    private function deleteShipmentRows(int $shipmentId): void
    {
        $this->db->exec(
            "DELETE rrg FROM response_result_generic_test rrg
               JOIN shipment_participant_map spm ON spm.map_id = rrg.shipment_map_id
              WHERE spm.shipment_id = ?",
            [$shipmentId]
        );
        $this->db->exec("DELETE FROM shipment_participant_map      WHERE shipment_id = ?", [$shipmentId]);
        $this->db->exec("DELETE FROM reference_result_generic_test WHERE shipment_id = ?", [$shipmentId]);
        $this->db->exec("DELETE FROM queue_report_generation        WHERE shipment_id = ?", [$shipmentId]);
        $this->db->exec("DELETE FROM participant_feedback_answer    WHERE shipment_id = ?", [$shipmentId]);
    }
}
