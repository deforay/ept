<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Writes the synthetic shipment to the DB. Raw SQL only; no app classes.
 */
final class Provisioner
{
    private const PREFIX = 'ATEST-';

    public function __construct(private Db $db) {}

    /**
     * @param array<int, array{aberration:string, tier:string}> $assignments
     * @return array{shipment_id:int, shipment_code:string, distribution_id:int, samples:array<int,array>, assignments:array<int,array{map_id:int, participant_id:int, aberration:string, tier:string}>}
     */
    public function provision(string $variantKey, array $assignments): array
    {
        $variant = Variants::get($variantKey);
        $expectations = require $variant['expectations'];
        $samples = $expectations['samples'];

        $lookups = $this->resolveLookups();
        $kits    = $this->resolveKits();

        // Flip global DTS/report settings to match the variant BEFORE creating the
        // shipment, so the stash we attach to shipment_attributes reflects the
        // pre-harness state. Done outside the transaction so the new settings are
        // visible to the evaluator subprocess even if the tx is still open.
        $settingsStash = (new AppSettings($this->db))->applyVariant($variant);

        return $this->db->tx(function () use ($variant, $variantKey, $assignments, $samples, $lookups, $kits, $settingsStash) {
            $shortId = strtoupper(base_convert((string) time(), 10, 36));

            $participantIds = $this->allocateParticipants(count($assignments));
            $distributionId = $this->createDistribution($shortId);
            [$shipmentId, $shipmentCode] = $this->createShipment($variantKey, $variant, $distributionId, $shortId, count($samples), $settingsStash);
            $this->createReferenceResults($shipmentId, $samples, $lookups);

            $mappedAssignments = [];
            $minorityKitIndex = 0;
            foreach ($assignments as $i => $a) {
                $participantId = $participantIds[$i];
                $mapId = $this->createShipmentParticipantMap($shipmentId, $participantId, $variant['algoKey'], $a['tier']);

                $spec = \EptTestHarness\Aberrations\Vietnam::generate($a['aberration'], $a['tier']);
                foreach ($samples as $sampleId => $sampleMeta) {
                    $sampleSpec = $spec[$sampleId];
                    $kit1 = $sampleSpec['kit1'] === 'minority_unique'
                        ? $this->pickMinorityKit($kits, $minorityKitIndex++)
                        : $kits['reference'][1];
                    $kit2 = $kits['reference'][2];
                    $kit3 = $kits['reference'][3];

                    $this->createResponseRow($mapId, $sampleId, $sampleSpec, $kit1, $kit2, $kit3, $lookups);
                }

                $mappedAssignments[] = [
                    'map_id'         => $mapId,
                    'participant_id' => $participantId,
                    'aberration'     => $a['aberration'],
                    'tier'           => $a['tier'],
                ];
            }

            return [
                'shipment_id'     => $shipmentId,
                'shipment_code'   => $shipmentCode,
                'distribution_id' => $distributionId,
                'samples'         => $samples,
                'assignments'     => $mappedAssignments,
                'settings_stash'  => $settingsStash,
            ];
        });
    }

    /** Resolve r_possibleresult IDs for the DTS codes the harness writes. */
    private function resolveLookups(): array
    {
        $rows = $this->db->all(
            "SELECT id, scheme_sub_group, result_code FROM r_possibleresult
             WHERE scheme_id='dts'
               AND ((scheme_sub_group='DTS_TEST'  AND result_code IN ('R','WR','NR'))
                 OR (scheme_sub_group='DTS_FINAL' AND result_code IN ('P','N','INC')))"
        );
        $out = ['test' => [], 'final' => []];
        foreach ($rows as $r) {
            $bucket = $r['scheme_sub_group'] === 'DTS_TEST' ? 'test' : 'final';
            $out[$bucket][$r['result_code']] = (int) $r['id'];
        }
        $required = [['test', 'R'], ['test', 'WR'], ['test', 'NR'], ['final', 'P'], ['final', 'N'], ['final', 'INC']];
        foreach ($required as [$b, $c]) {
            if (!isset($out[$b][$c])) {
                throw new \RuntimeException("Missing r_possibleresult row for $b:$c. Did migration 7.4.6 run? Did you flip display_context away from 'none' so the codes are usable?");
            }
        }
        return $out;
    }

    /** Pick one reference kit per test_no (1,2,3) and gather a pool of non-reference kits. */
    private function resolveKits(): array
    {
        $reference = [];
        foreach ([1, 2, 3] as $testNo) {
            $kit = $this->db->scalar(
                "SELECT testkit FROM dts_recommended_testkits WHERE test_no = ? ORDER BY testkit LIMIT 1",
                [$testNo]
            );
            if (!$kit) {
                throw new \RuntimeException("No recommended test kit found for test_no=$testNo. Seed dts_recommended_testkits first.");
            }
            $reference[$testNo] = (string) $kit;
        }

        $nonRef = $this->db->col(
            "SELECT TestKitName_ID FROM r_testkitnames
              WHERE TestKitName_ID NOT IN (SELECT DISTINCT testkit FROM dts_recommended_testkits)
              ORDER BY TestKitName_ID"
        );

        return ['reference' => $reference, 'nonRefPool' => array_values($nonRef)];
    }

    private function pickMinorityKit(array $kits, int $index): string
    {
        $pool = $kits['nonRefPool'];
        if (empty($pool)) {
            throw new \RuntimeException('No non-reference test kits available. Cannot run consensus_minority_kit aberration.');
        }
        return (string) $pool[$index % count($pool)];
    }

    private function allocateParticipants(int $needed): array
    {
        $existing = $this->db->col(
            "SELECT participant_id FROM participant
              WHERE status='active' AND unique_identifier LIKE ?
              ORDER BY participant_id LIMIT $needed",
            [self::PREFIX . 'p%']
        );
        $existing = array_map('intval', $existing);
        $shortfall = $needed - count($existing);
        if ($shortfall <= 0) {
            return array_slice($existing, 0, $needed);
        }

        // Find next free serial number across all AUTOTEST-p* (active or not) to avoid UNIQUE conflict.
        $maxSerial = (int) $this->db->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(unique_identifier, ?) AS UNSIGNED)), 0)
               FROM participant WHERE unique_identifier LIKE ?",
            [strlen(self::PREFIX . 'p') + 1, self::PREFIX . 'p%']
        );

        $now = date('Y-m-d H:i:s');
        for ($i = 1; $i <= $shortfall; $i++) {
            $serial = $maxSerial + $i;
            $uid = sprintf('%sp%03d', self::PREFIX, $serial);
            $pid = $this->db->insert('participant', [
                'unique_identifier' => $uid,
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
            $existing[] = $pid;
        }
        return $existing;
    }

    private function createDistribution(string $shortId): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db->insert('distributions', [
            'distribution_code' => self::PREFIX . 'D' . $shortId,
            'distribution_date' => date('Y-m-d'),
            'status'            => 'shipped',
            'created_on'        => $now,
            'created_by'        => 'test-harness',
        ]);
    }

    /** @return array{0:int,1:string} */
    private function createShipment(string $variantKey, array $variant, int $distributionId, string $shortId, int $sampleCount, array $settingsStash): array
    {
        $code = self::PREFIX . $shortId;
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        // response_deadline well in the future, so test reports submitted "now" are on time
        $deadline = date('Y-m-d H:i:s', strtotime('+30 days'));

        $shipmentId = $this->db->insert('shipment', [
            'shipment_code'        => $code,
            'scheme_type'          => 'dts',
            'shipment_date'        => $today,
            'response_deadline'    => $deadline,
            'distribution_id'      => $distributionId,
            'number_of_samples'    => $sampleCount,
            'number_of_controls'   => 0,
            'max_score'            => 100,
            'shipment_attributes'  => json_encode(array_merge(
                $variant['shipmentAttributes'] ?? [],
                [
                    'dtsSchemeType'       => $variant['schemeType'],
                    'atest_prev_settings' => $settingsStash,
                ]
            )),
            'status'               => 'shipped',
            'created_by_admin'     => 'test-harness',
            'created_on_admin'     => $now,
        ]);
        return [$shipmentId, $code];
    }

    private function createReferenceResults(int $shipmentId, array $samples, array $lookups): void
    {
        $perSampleScore = (int) (100 / count($samples));
        foreach ($samples as $sampleId => $meta) {
            $refCode = $meta['ref']; // 'P' or 'N'
            $refId = $lookups['final'][$refCode] ?? null;
            if ($refId === null) {
                throw new \RuntimeException("No r_possibleresult id for final code '$refCode'");
            }
            $this->db->insert('reference_result_dts', [
                'shipment_id'        => $shipmentId,
                'sample_id'          => $sampleId,
                'sample_label'       => $meta['label'],
                'reference_result'   => (string) $refId,
                'is_sample_diluted'  => $meta['diluted'] ? 'yes' : 'no',
                'control'            => 0,
                'mandatory'          => 1,
                'sample_score'       => $perSampleScore,
            ]);
        }
    }

    private function createShipmentParticipantMap(int $shipmentId, int $participantId, string $algoKey, string $tier): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->db->insert('shipment_participant_map', [
            'shipment_id'              => $shipmentId,
            'participant_id'           => $participantId,
            'attributes'               => json_encode([
                'algorithm'             => $algoKey,
                'dts_test_panel_type'   => $tier,
            ]),
            'shipment_test_report_date' => $now,
            'shipment_test_date'        => date('Y-m-d'),
            'response_status'           => 'responded',
            'is_pt_test_not_performed'  => 'no',
            'created_on_admin'          => $now,
            'created_on_user'           => $now,
            'created_by_admin'          => 'test-harness',
        ]);
    }

    private function createResponseRow(int $mapId, int $sampleId, array $spec, string $kit1, string $kit2, string $kit3, array $lookups): void
    {
        $now = date('Y-m-d H:i:s');
        $expDate = date('Y-m-d', strtotime('+6 months'));

        $row = [
            'shipment_map_id'  => $mapId,
            'sample_id'        => $sampleId,
            'test_kit_name_1'  => $kit1,
            'lot_no_1'         => 'AUTOTEST-LOT1',
            'exp_date_1'       => $expDate,
            'test_result_1'    => $this->resolveTestCode($spec['t1'], $lookups),
            'test_kit_name_2'  => $kit2,
            'lot_no_2'         => 'AUTOTEST-LOT2',
            'exp_date_2'       => $expDate,
            'test_result_2'    => $this->resolveTestCode($spec['t2'], $lookups),
            'test_kit_name_3'  => $kit3,
            'lot_no_3'         => 'AUTOTEST-LOT3',
            'exp_date_3'       => $expDate,
            'test_result_3'    => $this->resolveTestCode($spec['t3'], $lookups),
            'reported_result'  => $this->resolveFinalCode($spec['final'], $lookups),
            'lab_comment'      => $spec['comment'] ?? null,
            'created_by'       => 'test-harness',
            'created_on'       => $now,
        ];

        $this->db->insert('response_result_dts', $row);
    }

    private function resolveTestCode(string $code, array $lookups): ?string
    {
        if ($code === '-' || $code === '') {
            return null;
        }
        $id = $lookups['test'][$code] ?? null;
        if ($id === null) {
            throw new \RuntimeException("No test-result id for code '$code'");
        }
        return (string) $id;
    }

    private function resolveFinalCode(?string $code, array $lookups): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }
        $id = $lookups['final'][$code] ?? null;
        if ($id === null) {
            throw new \RuntimeException("No final-result id for code '$code'");
        }
        return (string) $id;
    }
}
