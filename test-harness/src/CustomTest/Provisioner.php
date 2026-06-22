<?php

declare(strict_types=1);

namespace EptTestHarness\CustomTest;

use EptTestHarness\Db;

/**
 * Provisions a qualitative custom-test shipment against an EXISTING user-configured
 * scheme. Raw SQL only; no app classes. Never creates or mutates the scheme itself —
 * it only reads the scheme's FINAL result codes and writes ATEST-* shipment rows.
 */
final class Provisioner
{
    private const PREFIX = 'ATEST-';

    public function __construct(private Db $db) {}

    /**
     * Discover qualitative user-configured schemes the harness can run against:
     * those with at least two FINAL result codes (so a "flip" to a different valid
     * code is always possible). Resolves a positive + negative code for each.
     *
     * @return array<int,array{scheme_id:string, scheme_name:string, pos:string, neg:string}>
     */
    public static function discoverSchemes(Db $db): array
    {
        $rows = $db->all(
            "SELECT scheme_id, scheme_name FROM scheme_list
              WHERE is_user_configured = 'yes' AND status = 'active'
                AND JSON_UNQUOTE(JSON_EXTRACT(user_test_config, '$.testType')) = 'qualitative'
              ORDER BY scheme_name"
        );
        $out = [];
        foreach ($rows as $r) {
            $codes = self::finalCodes($db, (string) $r['scheme_id']);
            if (count($codes) < 2) {
                continue; // can't build a correct/incorrect pair
            }
            [$pos, $neg] = self::resolvePosNeg($codes);
            $out[] = [
                'scheme_id'   => (string) $r['scheme_id'],
                'scheme_name' => (string) $r['scheme_name'],
                'pos'         => $pos,
                'neg'         => $neg,
            ];
        }
        return $out;
    }

    /** FINAL result codes for a scheme, ordered, as [code => response]. */
    private static function finalCodes(Db $db, string $schemeId): array
    {
        $rows = $db->all(
            "SELECT result_code, response FROM r_possibleresult
              WHERE scheme_id = ? AND UPPER(TRIM(scheme_sub_group)) = 'FINAL'
              ORDER BY sort_order ASC",
            [$schemeId]
        );
        $codes = [];
        foreach ($rows as $r) {
            $codes[(string) $r['result_code']] = (string) ($r['response'] ?? '');
        }
        return $codes;
    }

    /**
     * Pick a positive and a negative FINAL code from the scheme's vocabulary by
     * matching the human-readable response text; falls back to the first two codes.
     *
     * @param array<string,string> $codes code => response
     * @return array{0:string,1:string} [posCode, negCode]
     */
    private static function resolvePosNeg(array $codes): array
    {
        $pos = $neg = null;
        foreach ($codes as $code => $resp) {
            $r = strtolower($resp);
            if ($neg === null && (str_contains($r, 'negative') || str_contains($r, 'non-reactive') || str_contains($r, 'nonreactive') || str_contains($r, 'not detected'))) {
                $neg = $code;
            } elseif ($pos === null && (str_contains($r, 'positive') || str_contains($r, 'reactive') || str_contains($r, 'detected'))) {
                $pos = $code;
            }
        }
        $keys = array_keys($codes);
        $pos ??= $keys[0];
        $neg ??= ($keys[1] ?? $keys[0]);
        if ($pos === $neg) {
            $neg = $keys[1] ?? $keys[0];
        }
        return [$pos, $neg];
    }

    /** Resolve an aberration's flip declaration into a concrete list of sample ids. */
    private static function flipSet(array $aberrationMeta, int $sampleCount): array
    {
        $flip = $aberrationMeta['flip'] ?? [];
        if ($flip === 'all') {
            return range(1, $sampleCount);
        }
        return array_values(array_filter((array) $flip, static fn ($s) => $s >= 1 && $s <= $sampleCount));
    }

    /**
     * @param array{scheme_id:string,pos:string,neg:string} $scheme
     * @param array<int,array{aberration:string}> $assignments
     * @return array{shipment_id:int, shipment_code:string, distribution_id:int, samples:array, assignments:array}
     */
    public function provision(array $scheme, array $expectations, array $assignments): array
    {
        $sampleCount = (int) $expectations['sample_count'];
        $pattern = $expectations['pattern'];
        $aberrations = $expectations['aberrations'];

        // Build the reference panel: sample_id => ['ref_code' => ..., 'wrong_code' => ..., 'label' => ...]
        $samples = [];
        for ($sid = 1; $sid <= $sampleCount; $sid++) {
            $letter = $pattern[($sid - 1) % count($pattern)];
            $refCode   = $letter === 'P' ? $scheme['pos'] : $scheme['neg'];
            $wrongCode = $letter === 'P' ? $scheme['neg'] : $scheme['pos'];
            $samples[$sid] = ['ref_code' => $refCode, 'wrong_code' => $wrongCode, 'label' => "Sample $sid"];
        }

        $this->sweepOrphans();

        return $this->db->tx(function () use ($scheme, $samples, $sampleCount, $assignments, $aberrations) {
            $shortId = strtoupper(base_convert((string) time(), 10, 36));

            $participantIds = $this->allocateParticipants(count($assignments));
            $distributionId = $this->createDistribution($shortId);
            [$shipmentId, $shipmentCode] = $this->createShipment($scheme['scheme_id'], $distributionId, $shortId, $sampleCount);
            $this->createReferenceResults($shipmentId, $samples);

            $mapped = [];
            foreach ($assignments as $i => $a) {
                $meta = $aberrations[$a['aberration']] ?? [];
                $responseState = $meta['response_state'] ?? null;
                $flip = self::flipSet($meta, $sampleCount);

                $mapId = $this->createShipmentParticipantMap($shipmentId, $participantIds[$i], $responseState);

                if ($responseState === null) {
                    foreach ($samples as $sid => $s) {
                        $reported = in_array($sid, $flip, true) ? $s['wrong_code'] : $s['ref_code'];
                        $this->createResponseRow($mapId, $sid, $reported);
                    }
                }

                $mapped[] = [
                    'map_id'         => $mapId,
                    'participant_id' => $participantIds[$i],
                    'aberration'     => $a['aberration'],
                    'flip'           => $responseState === null ? $flip : [],
                    'response_state' => $responseState,
                ];
            }

            return [
                'shipment_id'     => $shipmentId,
                'shipment_code'   => $shipmentCode,
                'distribution_id' => $distributionId,
                'samples'         => $samples,
                'assignments'     => $mapped,
            ];
        });
    }

    /** Clear orphan response rows whose parent map row was already deleted (PK reuse safety). */
    private function sweepOrphans(): void
    {
        $this->db->exec(
            "DELETE rrg FROM response_result_generic_test rrg
              LEFT JOIN shipment_participant_map spm ON spm.map_id = rrg.shipment_map_id
              WHERE spm.map_id IS NULL"
        );
    }

    private function allocateParticipants(int $needed): array
    {
        $existing = array_map('intval', $this->db->col(
            "SELECT participant_id FROM participant
              WHERE status='active' AND unique_identifier LIKE ?
              ORDER BY participant_id LIMIT $needed",
            [self::PREFIX . 'p%']
        ));

        $shortfall = $needed - count($existing);
        if ($shortfall <= 0) {
            return array_slice($existing, 0, $needed);
        }

        $maxSerial = (int) $this->db->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(unique_identifier, ?) AS UNSIGNED)), 0)
               FROM participant WHERE unique_identifier LIKE ?",
            [strlen(self::PREFIX . 'p') + 1, self::PREFIX . 'p%']
        );

        $now = date('Y-m-d H:i:s');
        for ($i = 1; $i <= $shortfall; $i++) {
            $serial = $maxSerial + $i;
            $existing[] = $this->db->insert('participant', [
                'unique_identifier' => sprintf('%sp%03d', self::PREFIX, $serial),
                'first_name'        => 'AutoTest',
                'last_name'         => sprintf('Lab %03d', $serial),
                'lab_name'          => sprintf('AutoTest Lab %03d', $serial),
                'institute_name'    => 'AutoTest Institute',
                'email'             => sprintf('autotest+p%03d@example.invalid', $serial),
                'country'           => 1,
                'status'            => 'active',
                'created_on'        => $now,
                'created_by'        => 'test-harness',
            ]);
        }
        return $existing;
    }

    private function createDistribution(string $shortId): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db->insert('distributions', [
            'distribution_code' => self::PREFIX . 'CTD-' . $shortId,
            'distribution_date' => date('Y-m-d'),
            'status'            => 'shipped',
            'created_on'        => $now,
            'created_by'        => 'test-harness',
        ]);
    }

    /** @return array{0:int,1:string} */
    private function createShipment(string $schemeId, int $distributionId, string $shortId, int $sampleCount): array
    {
        $code = self::PREFIX . 'CT-' . $shortId;
        $now = date('Y-m-d H:i:s');
        $shipmentId = $this->db->insert('shipment', [
            'shipment_code'       => $code,
            'scheme_type'         => $schemeId,
            'shipment_date'       => date('Y-m-d'),
            'response_deadline'   => date('Y-m-d H:i:s', strtotime('+60 days')),
            'distribution_id'     => $distributionId,
            'number_of_samples'   => $sampleCount,
            'number_of_controls'  => 0,
            'max_score'           => 100,
            'shipment_attributes' => json_encode(['atest' => true]),
            'status'              => 'shipped',
            'created_by_admin'    => 'test-harness',
            'created_on_admin'    => $now,
        ]);
        return [$shipmentId, $code];
    }

    private function createReferenceResults(int $shipmentId, array $samples): void
    {
        $perSampleScore = round(100 / count($samples), 4);
        foreach ($samples as $sid => $s) {
            $this->db->insert('reference_result_generic_test', [
                'shipment_id'             => $shipmentId,
                'sample_id'               => $sid,
                'sample_label'            => $s['label'],
                'sample_preparation_date' => date('Y-m-d'),
                'reference_result'        => $s['ref_code'],
                'control'                 => 0,
                'mandatory'               => 1,
                'sample_score'            => $perSampleScore,
            ]);
        }
    }

    private function createShipmentParticipantMap(int $shipmentId, int $participantId, ?string $responseState): int
    {
        $now = date('Y-m-d H:i:s');
        $testDate    = date('Y-m-d', strtotime('-2 days'));
        $receiptDate = date('Y-m-d', strtotime('-3 days'));

        $row = [
            'shipment_id'            => $shipmentId,
            'participant_id'         => $participantId,
            'attributes'             => json_encode(['analyst_name' => 'AutoTest Analyst']),
            'shipment_receipt_date'  => $receiptDate,
            'supervisor_approval'    => 'yes',
            'participant_supervisor' => 'AutoTest Supervisor',
            'created_on_admin'       => $now,
            'created_on_user'        => $now,
            'created_by_admin'       => 'test-harness',
        ];
        if ($responseState === 'noresponse') {
            $row['response_status'] = 'noresponse';
        } elseif ($responseState === 'nottested') {
            $row['response_status']           = 'responded';
            $row['shipment_test_date']        = $testDate;
            $row['shipment_test_report_date'] = $now;
            $row['is_pt_test_not_performed']  = 'yes';
        } else {
            $row['response_status']           = 'responded';
            $row['shipment_test_date']        = $testDate;
            $row['shipment_test_report_date'] = $now;
            $row['is_pt_test_not_performed']  = 'no';
        }
        return $this->db->insert('shipment_participant_map', $row);
    }

    private function createResponseRow(int $mapId, int $sampleId, string $reportedCode): void
    {
        $this->db->insert('response_result_generic_test', [
            'shipment_map_id' => $mapId,
            'sample_id'       => $sampleId,
            'result_1'        => $reportedCode,
            'reported_result' => $reportedCode,
            'created_by'      => 'test-harness',
            'created_on'      => date('Y-m-d H:i:s'),
        ]);
    }
}
