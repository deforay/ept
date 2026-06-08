<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Writes the synthetic shipment to the DB. Raw SQL only; no app classes.
 */
final class Provisioner
{
    private const PREFIX = 'ATEST-';

    // Synthetic ELISA-style kit injected by the harness so that the Peer Group
    // Statistics section in the NIHE report has numeric Mean/SD/CV to display.
    // Idempotent: created on first provision, never removed by per-shipment cleanup
    // (so subsequent provisions reuse it). --cleanup-all wipes it.
    public const ELISA_KIT_ID    = 'ATEST-ELISA-001';
    public const ELISA_KIT_NAME  = 'ATEST ELISA Anti-HIV (S/CO)';
    public const ELISA_KIT_LABEL = 'S/CO';

    // Synthetic rapid kits for positions 1 and 3 — names mirror what NIHE actually
    // uses in their templates (Bioline / Determine). Created only if no recommended
    // kit exists for the position yet, so the harness is self-sufficient on a fresh DB.
    private const RAPID_KITS = [
        1 => ['id' => 'ATEST-RAPID-001', 'name' => 'ATEST Bioline HIV 1/2 3.0',  'short' => 'ATEST-BIO'],
        3 => ['id' => 'ATEST-RAPID-003', 'name' => 'ATEST Determine HIV 1/2',    'short' => 'ATEST-DET'],
    ];

    public function __construct(private Db $db) {}

    /**
     * @param array<int, array{aberration:string, tier:string}> $assignments
     * @param int|null $sampleCount Cap samples per panel — keeps IDs 1..N from the expectations
     *                              file. null = use the full set declared. Vietnam ships 10 by default.
     * @return array{shipment_id:int, shipment_code:string, distribution_id:int, samples:array<int,array>, assignments:array<int,array{map_id:int, participant_id:int, aberration:string, tier:string}>}
     */
    public function provision(string $variantKey, array $assignments, ?int $sampleCount = null): array
    {
        $variant = Variants::get($variantKey);
        $expectations = require $variant['expectations'];
        $samples = $expectations['samples'];
        if ($sampleCount !== null && $sampleCount > 0 && $sampleCount < count($samples)) {
            $samples = array_filter($samples, static fn ($v, $sid) => $sid <= $sampleCount, ARRAY_FILTER_USE_BOTH);
        }

        $lookups = $this->resolveLookups();
        $kits    = $this->resolveKits();
        $this->sweepOrphans();

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
            $this->createReferenceModalData($shipmentId, $samples, $lookups, $kits);

            // Per-shipment shuffle so the same input mix produces a different ordering of
            // labs across runs. Deterministic from the shipment id + short id.
            $shuffleSeed = crc32($shipmentId . ':' . $shortId);
            $assignments = $this->shuffleStable($assignments, $shuffleSeed);

            // Pre-resolve "response state" markers from the variant catalogue. Entries
            // with response_state='noresponse' or 'nottested' are non-response cases —
            // we still create the participant map but skip the response_result_dts rows
            // and stamp the appropriate flags so the report's "no response" / "PT test
            // not performed" code paths get exercised on every harness run.
            $catalogue = \EptTestHarness\Aberrations\Vietnam::catalogue();
            $notTestedReasonIds = $this->db->col(
                "SELECT ntr_id FROM r_response_not_tested_reasons
                  WHERE ntr_status='active' AND ntr_id <> 9999
                    AND JSON_CONTAINS(ntr_test_type, '\"dts\"')
                  ORDER BY ntr_id"
            );
            $notTestedReasonIds = array_map('intval', $notTestedReasonIds);

            $mappedAssignments = [];
            $minorityKitIndex = 0;
            // Track the kits actually used per position so we can provision the
            // shipment-specific testkit map (the new intermediate layer) afterwards.
            $usedKits = [1 => [], 2 => [], 3 => []];
            foreach ($assignments as $i => $a) {
                $participantId = $participantIds[$i];
                $responseState = $catalogue[$a['aberration']]['response_state'] ?? null;
                $mapId = $this->createShipmentParticipantMap(
                    $shipmentId,
                    $participantId,
                    $variant['algoKey'],
                    $a['tier'],
                    $i,
                    $responseState,
                    $responseState === 'nottested' && !empty($notTestedReasonIds)
                        ? $notTestedReasonIds[$i % count($notTestedReasonIds)]
                        : null
                );

                if ($responseState !== null) {
                    // No response data to write — record the assignment and continue.
                    $mappedAssignments[] = [
                        'map_id'         => $mapId,
                        'participant_id' => $participantId,
                        'aberration'     => $a['aberration'],
                        'tier'           => $a['tier'],
                    ];
                    continue;
                }

                // Deterministic per-(shipment, participant) seed: same inputs reproduce, but
                // different shipments/labs vary the chosen valid response shape.
                $variantSeed = (int) crc32($shipmentId . ':' . $i . ':' . $participantId);
                $spec = \EptTestHarness\Aberrations\Vietnam::generate($a['aberration'], $a['tier'], $variantSeed);
                foreach ($samples as $sampleId => $sampleMeta) {
                    $sampleSpec = $spec[$sampleId];
                    $kit1 = match ($sampleSpec['kit1']) {
                        'minority_unique' => $this->pickMinorityKit($kits, $minorityKitIndex++),
                        'minority_shared' => $kits['sharedNonRef'],
                        default           => $kits['reference'][1],
                    };
                    $kit2 = $kits['reference'][2];
                    $kit3 = $kits['reference'][3];

                    if ($kit1) {
                        $usedKits[1][(string) $kit1] = true;
                    }
                    if ($kit2) {
                        $usedKits[2][(string) $kit2] = true;
                    }
                    if ($kit3) {
                        $usedKits[3][(string) $kit3] = true;
                    }

                    // For each position whose kit has additional_info='yes', synthesize a
                    // plausible numeric value seeded by (participant index, sample). Reactive
                    // samples high, non-reactive low, diluted positive mid; small jitter.
                    $kitNumeric = [
                        1 => $kits['hasNumeric'][1] ?? false,
                        2 => $kits['hasNumeric'][2] ?? false,
                        3 => $kits['hasNumeric'][3] ?? false,
                    ];
                    $additionalInfo = $this->buildAdditionalInfo($i, $sampleMeta, $sampleSpec, $kitNumeric);

                    $this->createResponseRow($mapId, $sampleId, $sampleSpec, $kit1, $kit2, $kit3, $lookups, $additionalInfo);
                }

                $mappedAssignments[] = [
                    'map_id'         => $mapId,
                    'participant_id' => $participantId,
                    'aberration'     => $a['aberration'],
                    'tier'           => $a['tier'],
                ];
            }

            $this->createShipmentTestkitMap($shipmentId, $usedKits);

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

    /**
     * Provision the per-shipment testkit->position overrides (shipment_testkit_map)
     * for the kits this shipment actually uses, mirroring the admin "Testkit Map" step:
     * global catalog (dts_recommended_testkits) -> shipment-specific layer -> used here.
     * Shipment-scoped, so Cleanup removes it with the shipment.
     *
     * @param array<int,array<string,bool>> $usedKits [position(1..3) => [kitId => true]]
     */
    private function createShipmentTestkitMap(int $shipmentId, array $usedKits): void
    {
        $flags = []; // kitId => [1 => 0/1, 2 => 0/1, 3 => 0/1]
        foreach ([1, 2, 3] as $pos) {
            foreach (array_keys($usedKits[$pos] ?? []) as $kitId) {
                $flags[(string) $kitId][$pos] = 1;
            }
        }
        foreach ($flags as $kitId => $posFlags) {
            $this->db->insert('shipment_testkit_map', [
                'shipment_id' => $shipmentId,
                'scheme_type' => 'dts',
                'testkit_id'  => $kitId,
                'testkit_1'   => $posFlags[1] ?? 0,
                'testkit_2'   => $posFlags[2] ?? 0,
                'testkit_3'   => $posFlags[3] ?? 0,
            ]);
        }
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
        $this->ensureElisaKit();
        $this->ensureRapidKits();

        $reference = [];
        foreach ([1, 2, 3] as $testNo) {
            // Prefer the ELISA-style kit (additional_info='yes') at position 2 so the
            // Peer Group Statistics section in the NIHE report has numeric Mean/SD/CV
            // to display. Other positions pick any recommended kit.
            if ($testNo === 2) {
                $kit = $this->db->scalar(
                    "SELECT t.TestKitName_ID
                       FROM dts_recommended_testkits dr
                       JOIN r_testkitnames t ON t.TestKitName_ID = dr.testkit
                      WHERE dr.test_no = 2
                        AND JSON_UNQUOTE(JSON_EXTRACT(t.attributes, '$.additional_info')) = 'yes'
                      ORDER BY (t.TestKitName_ID = ?) DESC, t.TestKitName_ID
                      LIMIT 1",
                    [self::ELISA_KIT_ID]
                );
                if (!$kit) {
                    $kit = $this->db->scalar(
                        "SELECT testkit FROM dts_recommended_testkits WHERE test_no = 2 ORDER BY testkit LIMIT 1"
                    );
                }
            } else {
                $kit = $this->db->scalar(
                    "SELECT testkit FROM dts_recommended_testkits WHERE test_no = ? ORDER BY testkit LIMIT 1",
                    [$testNo]
                );
            }
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

        $hasNumeric = [];
        foreach ($reference as $pos => $kitId) {
            $row = $this->db->one(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(attributes, '$.additional_info')) AS ai
                   FROM r_testkitnames WHERE TestKitName_ID = ?",
                [$kitId]
            );
            $hasNumeric[$pos] = (($row['ai'] ?? '') === 'yes');
        }

        // One stable non-reference kit reused across every minority_shared participant in
        // this shipment. Picking the FIRST in the pool keeps it deterministic; minority_unique
        // participants skip past it (their loop advances $minorityKitIndex).
        $sharedNonRef = $nonRef[0] ?? null;

        return [
            'reference'    => $reference,
            'nonRefPool'   => array_values($nonRef),
            'sharedNonRef' => $sharedNonRef,
            'hasNumeric'   => $hasNumeric,
        ];
    }

    /**
     * Deterministic shuffle: same $seed always yields the same permutation. Used
     * to vary which lab gets which aberration across shipments without losing
     * reproducibility for a given (shipmentId, ts) pair.
     */
    private function shuffleStable(array $items, int $seed): array
    {
        $keys = array_keys($items);
        usort($keys, static function ($a, $b) use ($seed) {
            $ha = crc32($seed . ':' . $a);
            $hb = crc32($seed . ':' . $b);
            return $ha <=> $hb;
        });
        $out = [];
        foreach ($keys as $k) {
            $out[] = $items[$k];
        }
        return $out;
    }

    /**
     * Idempotently ensure an ELISA-style kit exists (additional_info='yes') and is
     * marked recommended at test_no=2. Sentinel id ATEST-ELISA-001 so cleanup can
     * recognize it. Real kits remain untouched.
     */
    private function ensureElisaKit(): void
    {
        $exists = $this->db->scalar(
            "SELECT 1 FROM r_testkitnames WHERE TestKitName_ID = ?",
            [self::ELISA_KIT_ID]
        );
        if (!$exists) {
            $this->db->exec(
                "INSERT INTO r_testkitnames (TestKitName_ID, TestKit_Name, TestKit_Name_Short, Approval, attributes, testkit_status, Created_On)
                 VALUES (?, ?, 'ATEST-ELISA', 1, ?, 'active', NOW())",
                [
                    self::ELISA_KIT_ID,
                    self::ELISA_KIT_NAME,
                    json_encode([
                        'additional_info'           => 'yes',
                        'additional_info_label'     => self::ELISA_KIT_LABEL,
                        'additional_info_mandatory' => 'yes',
                    ]),
                ]
            );
        } else {
            // Heal any pre-existing rows whose status was left NULL — the kit-name dropdown
            // filters by testkit_status='active', so a NULL row renders as "— Select Kit —".
            $this->db->exec(
                "UPDATE r_testkitnames SET testkit_status = 'active' WHERE TestKitName_ID = ? AND (testkit_status IS NULL OR testkit_status = '')",
                [self::ELISA_KIT_ID]
            );
        }
        $inRec = $this->db->scalar(
            "SELECT 1 FROM dts_recommended_testkits WHERE testkit = ? AND test_no = 2",
            [self::ELISA_KIT_ID]
        );
        if (!$inRec) {
            $this->db->exec(
                "INSERT INTO dts_recommended_testkits (test_no, testkit, dts_test_mode) VALUES (2, ?, 'dts')",
                [self::ELISA_KIT_ID]
            );
        }
    }

    /**
     * Idempotently ensure rapid kits exist at positions 1 and 3 of dts_recommended_testkits.
     * Only acts if the position has no recommendations yet — never overrides real seeded data.
     * Mirrors ensureElisaKit() so the harness can run on a clean DB without manual seeding.
     */
    private function ensureRapidKits(): void
    {
        foreach (self::RAPID_KITS as $pos => $kit) {
            $hasAny = $this->db->scalar(
                "SELECT 1 FROM dts_recommended_testkits WHERE test_no = ? LIMIT 1",
                [$pos]
            );
            if ($hasAny) {
                continue; // real recommendation already present — leave alone
            }
            $exists = $this->db->scalar(
                "SELECT 1 FROM r_testkitnames WHERE TestKitName_ID = ?",
                [$kit['id']]
            );
            if (!$exists) {
                $this->db->exec(
                    "INSERT INTO r_testkitnames (TestKitName_ID, TestKit_Name, TestKit_Name_Short, Approval, testkit_status, Created_On)
                     VALUES (?, ?, ?, 1, 'active', NOW())",
                    [$kit['id'], $kit['name'], $kit['short']]
                );
            } else {
                $this->db->exec(
                    "UPDATE r_testkitnames SET testkit_status = 'active' WHERE TestKitName_ID = ? AND (testkit_status IS NULL OR testkit_status = '')",
                    [$kit['id']]
                );
            }
            $this->db->exec(
                "INSERT INTO dts_recommended_testkits (test_no, testkit, dts_test_mode) VALUES (?, ?, 'dts')",
                [$pos, $kit['id']]
            );
        }
    }

    /**
     * Clear orphan rows whose parent shipment_participant_map row was already deleted.
     * Prior cleanup paths (or aborted provisions) can leave response_result_dts /
     * reference_* rows pointing at map_ids that no longer exist; when auto_increment
     * eventually re-hands those IDs (e.g. after a manual TRUNCATE / counter reset),
     * fresh INSERTs collide on the composite PK (shipment_map_id, sample_id).
     * Self-healing here keeps the harness usable on any DB state.
     */
    private function sweepOrphans(): void
    {
        $this->db->exec(
            "DELETE rrd FROM response_result_dts rrd
              LEFT JOIN shipment_participant_map spm ON spm.map_id = rrd.shipment_map_id
              WHERE spm.map_id IS NULL"
        );
    }

    private function pickMinorityKit(array $kits, int $index): string
    {
        // Skip pool[0] — that's reserved for minority_shared so the two consensus
        // aberrations can coexist without polluting each other's peer group.
        $pool = array_slice($kits['nonRefPool'], 1);
        if (empty($pool)) {
            throw new \RuntimeException('Need at least 2 non-reference test kits available (one for shared consensus group, one+ for unique minorities).');
        }
        return (string) $pool[$index % count($pool)];
    }

    /**
     * Returns the list of network_id values to distribute harness participants across.
     * Prefers the NIHE function categories seeded by migration 7.5.3 (so Summary §2.1
     * "Proportion of Participants by Function" renders with multiple slices). Falls back
     * to whatever tiers exist in the install, so non-Vietnam dev DBs still get coverage.
     */
    private function networkTierIds(): array
    {
        $nihe = [
            'National hospital', 'Provincial general hospital', 'Cottage hospital',
            'Private hospital', 'Military hospital', 'Centers for Disease Control and Prevention',
            'Local Health center', 'Research Institute', 'Other',
        ];
        $rows = $this->db->all(
            "SELECT network_id FROM r_network_tiers WHERE network_name IN (" . implode(',', array_fill(0, count($nihe), '?')) . ") ORDER BY network_id",
            $nihe
        );
        if (!empty($rows)) {
            return array_map(static fn ($r) => (int) $r['network_id'], $rows);
        }
        $all = $this->db->all("SELECT network_id FROM r_network_tiers ORDER BY network_id");
        return array_map(static fn ($r) => (int) $r['network_id'], $all);
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

        // Make sure even pre-existing harness participants have a network_tier so the
        // Vietnam Summary §2.1 function pie has variety. round-robin across the tier
        // set keyed on participant_id so the assignment is stable across runs.
        $tierIds = $this->networkTierIds();
        if (!empty($tierIds) && !empty($existing)) {
            $nullParticipants = $this->db->col(
                "SELECT participant_id FROM participant WHERE participant_id IN (" . implode(',', array_fill(0, count($existing), '?')) . ") AND (network_tier IS NULL OR network_tier = 0)",
                $existing
            );
            foreach ($nullParticipants as $idx => $pidStr) {
                $pid = (int) $pidStr;
                $this->db->exec(
                    "UPDATE participant SET network_tier = ? WHERE participant_id = ?",
                    [$tierIds[$idx % count($tierIds)], $pid]
                );
            }
        }

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
            $tier = !empty($tierIds) ? $tierIds[($serial - 1) % count($tierIds)] : null;
            $pid = $this->db->insert('participant', [
                'unique_identifier' => $uid,
                'first_name'        => 'AutoTest',
                'last_name'         => sprintf('Lab %03d', $serial),
                'lab_name'          => sprintf('AutoTest Lab %03d', $serial),
                'institute_name'    => 'AutoTest Institute',
                'email'             => sprintf('autotest+p%03d@example.invalid', $serial),
                'country'           => 1,
                'network_tier'      => $tier,
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
        // Mirror what a real admin would set when creating a shipment today:
        //   - response_deadline 30-60 days out (jittered so harness shipments
        //     don't all share the same deadline if multiple are provisioned).
        //   - shipment_date = today (same as survey creation date).
        $deadlineDays = mt_rand(30, 60);
        $deadline = date('Y-m-d H:i:s', strtotime("+{$deadlineDays} days"));

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
        // sample_preparation_date — real admins prep samples up to a week before the
        // shipment goes out. Today / -1 / -3 / -7 days deterministically by sample_id.
        $prepOffsetCycle = [0, -1, -3, -7];
        foreach ($samples as $sampleId => $meta) {
            $refCode = $meta['ref']; // 'P' or 'N'
            $refId = $lookups['final'][$refCode] ?? null;
            if ($refId === null) {
                throw new \RuntimeException("No r_possibleresult id for final code '$refCode'");
            }
            $offset = $prepOffsetCycle[((int) $sampleId - 1) % count($prepOffsetCycle)];
            $prepDate = $offset === 0 ? date('Y-m-d') : date('Y-m-d', strtotime("$offset days"));
            $this->db->insert('reference_result_dts', [
                'shipment_id'             => $shipmentId,
                'sample_id'               => $sampleId,
                'sample_label'            => $meta['label'],
                'sample_preparation_date' => $prepDate,
                'reference_result'        => (string) $refId,
                'is_sample_diluted'       => $meta['diluted'] ? 'yes' : 'no',
                'control'                 => 0,
                'mandatory'               => 1,
                'sample_score'            => $perSampleScore,
            ]);
        }
    }

    /**
     * Seed per-shipment reference characterisation rows (the data normally entered
     * through the per-sample "Reference Results" modal on the admin shipment-edit
     * page). Populates reference_dts_eia + reference_dts_rapid_hiv for every sample
     * so the Vietnam Summary §3 Appendix A1 table renders with real numeric / +-
     * values instead of falling back to the simplified reference-only table.
     *
     * Values aren't medically meaningful — they're plausible synthetic numbers that
     * match the sample's expected reference result (Positive samples emit reactive
     * EIA OD values + '+' rapids; Negative samples emit non-reactive ODs + '-' rapids).
     * WB / Geenius are intentionally skipped to keep the table compact.
     */
    private function createReferenceModalData(int $shipmentId, array $samples, array $lookups, array $kits): void
    {
        // The reference_dts_eia.eia column is FK to r_dbs_eia.eia_id (integer), NOT a
        // TestKitName_ID — the admin EIA dropdown lists r_dbs_eia entries. Pick the
        // lowest-numbered entry so the harness has a real, valid reference. If the
        // r_dbs_eia table is empty, skip the EIA seed.
        $eiaRefId = $this->db->scalar("SELECT eia_id FROM r_dbs_eia ORDER BY eia_id LIMIT 1");
        if (!$eiaRefId) {
            $eiaRefId = null;
        }
        // Rapid kits at positions 1 and 3 are the reference rapids — these are real
        // TestKitName_IDs (varchar) matching reference_dts_rapid_hiv.testkit.
        $refKits = $kits['reference'] ?? [];
        $rapid1 = $refKits[1] ?? null;
        $rapid3 = $refKits[3] ?? null;

        $rTestRows = $this->db->all(
            "SELECT id, result_code FROM r_possibleresult
             WHERE scheme_id='dts' AND scheme_sub_group='DTS_TEST' AND result_code IN ('R','NR')"
        );
        $testIdByCode = [];
        foreach ($rTestRows as $r) {
            $testIdByCode[$r['result_code']] = (int) $r['id'];
        }
        if (empty($testIdByCode['R']) || empty($testIdByCode['NR'])) {
            return;
        }

        $today = date('Y-m-d');
        foreach ($samples as $sampleId => $meta) {
            $isPositive = ($meta['ref'] ?? '') === 'P';
            $isDiluted  = !empty($meta['diluted']);

            // Plausible OD / cutoff numbers — positive ~ above-cutoff, negative ~ below
            $cutoff = '0.300';
            if ($isPositive && !$isDiluted) {
                $od = number_format(mt_rand(15000, 25000) / 1000, 3);   // 15-25
            } elseif ($isPositive && $isDiluted) {
                $od = number_format(mt_rand(400, 900) / 1000, 3);        // 0.4-0.9 (weak)
            } else {
                $od = number_format(mt_rand(80, 240) / 1000, 3);         // 0.08-0.24
            }
            $eiaResultId = $isPositive ? $testIdByCode['R'] : $testIdByCode['NR'];

            if ($eiaRefId !== null) {
                $this->db->insert('reference_dts_eia', [
                    'shipment_id' => $shipmentId,
                    'sample_id'   => $sampleId,
                    'eia'         => $eiaRefId,
                    'lot'         => 'REF-EIA-LOT',
                    'test_date'   => $today,
                    'exp_date'    => date('Y-m-d', strtotime('+1 year')),
                    'od'          => $od,
                    'cutoff'      => $cutoff,
                    'result'      => (string) $eiaResultId,
                ]);
            }

            $rapidResultId = $isPositive ? $testIdByCode['R'] : $testIdByCode['NR'];
            foreach (array_filter([$rapid1, $rapid3]) as $rkit) {
                $this->db->insert('reference_dts_rapid_hiv', [
                    'shipment_id' => $shipmentId,
                    'sample_id'   => $sampleId,
                    'testkit'     => $rkit,
                    'lot_no'      => 'REF-RAPID-LOT',
                    'test_date'   => $today,
                    'expiry_date' => date('Y-m-d', strtotime('+1 year')),
                    'result'      => (string) $rapidResultId,
                ]);
            }
        }
    }

    private function createShipmentParticipantMap(int $shipmentId, int $participantId, string $algoKey, string $tier, int $mapIndex = 0, ?string $responseState = null, ?int $notTestedReasonId = null): int
    {
        $now = date('Y-m-d H:i:s');
        // Pull this shipment's own shipment_date so test-day offsets are meaningful
        // (the Summary §2.2 reporting-time bar buckets DATEDIFF(test_date, shipment_date)).
        // Spread across <7 / 7-14 / >14 buckets using a deterministic modulo on map index.
        $shipDate = $this->db->scalar("SELECT shipment_date FROM shipment WHERE shipment_id = ?", [$shipmentId]);
        $shipDate = $shipDate ?: date('Y-m-d');
        $offsetCycle = [3, 5, 6, 10, 12, 14, 18, 22];   // mostly mid-range with tails
        $offset = $offsetCycle[$mapIndex % count($offsetCycle)];
        $testDate = date('Y-m-d', strtotime("$shipDate +$offset days"));
        $receiptDate = date('Y-m-d', strtotime("$shipDate +1 day"));

        // Default = regular responded state. Two non-response paths:
        //  - noresponse: lab never submitted anything — leave shipment_test_date NULL,
        //    leave is_pt_test_not_performed NULL, response_status='noresponse'.
        //  - nottested:  lab submitted "PT test not performed" with a reason — keeps
        //    response_status='responded', flips is_pt_test_not_performed='yes', stamps
        //    pt_not_tested_reason. Lab still received the panel so receipt_date stays.
        $row = [
            'shipment_id'               => $shipmentId,
            'participant_id'            => $participantId,
            'attributes'                => json_encode([
                'algorithm'             => $algoKey,
                'dts_test_panel_type'   => $tier,
            ]),
            'shipment_receipt_date'     => $receiptDate,
            'created_on_admin'          => $now,
            'created_on_user'           => $now,
            'created_by_admin'          => 'test-harness',
        ];
        if ($responseState === 'noresponse') {
            $row['response_status']          = 'noresponse';
        } elseif ($responseState === 'nottested') {
            $row['response_status']            = 'responded';
            $row['shipment_test_date']         = $testDate;
            $row['shipment_test_report_date']  = $now;
            $row['is_pt_test_not_performed']   = 'yes';
            $row['pt_not_tested_reason']       = $notTestedReasonId;
        } else {
            $row['response_status']            = 'responded';
            $row['shipment_test_date']         = $testDate;
            $row['shipment_test_report_date']  = $now;
            $row['is_pt_test_not_performed']   = 'no';
        }
        return $this->db->insert('shipment_participant_map', $row);
    }

    private function createResponseRow(int $mapId, int $sampleId, array $spec, string $kit1, string $kit2, string $kit3, array $lookups, ?array $additionalInfo = null): void
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
            'kit_additional_info' => !empty($additionalInfo) ? json_encode($additionalInfo) : null,
            'created_by'       => 'test-harness',
            'created_on'       => $now,
        ];

        $this->db->insert('response_result_dts', $row);
    }

    /**
     * Build a kit_additional_info JSON object for one (participant, sample) — one entry
     * per test position whose kit captures a numeric value. Sample-typed bands:
     *   - Reactive (P, non-diluted)  → S/CO 80–140      (well above 1.0 cutoff)
     *   - Reactive (P, diluted)      → S/CO  3–15       (weak positive band)
     *   - Non-reactive (N)           → S/CO 0.05–0.6    (well below cutoff)
     *   - This-lab reported NR/-     → low value regardless of reference (lab's read)
     *   - This-lab reported R/WR     → above cutoff regardless of reference
     * Deterministic jitter seeded by (participantIndex, sampleId, position) so re-runs
     * with the same input produce the same numbers; different participants vary.
     */
    private function buildAdditionalInfo(int $participantIndex, array $sampleMeta, array $sampleSpec, array $kitHasNumeric): array
    {
        $out = [];
        foreach ([1, 2, 3] as $position) {
            if (empty($kitHasNumeric[$position])) {
                continue;
            }
            $code = strtoupper((string) ($sampleSpec['t' . $position] ?? '-'));
            if ($code === '-' || $code === '') {
                continue; // didn't run this test
            }

            $ref = strtoupper((string) ($sampleMeta['ref'] ?? ''));
            $diluted = (bool) ($sampleMeta['diluted'] ?? false);

            // Hash seed for deterministic per-row jitter.
            $seed = crc32($participantIndex . ':' . ($sampleMeta['label'] ?? '') . ':' . $position);
            $jitter01 = (($seed % 1000) / 1000.0); // [0, 1)

            if (in_array($code, ['R', 'WR'], true)) {
                // Reactive read by the lab
                if ($code === 'WR' || $diluted) {
                    $val = 3.0 + $jitter01 * 12.0; // 3.0 .. 15.0
                } else {
                    $val = 80.0 + $jitter01 * 60.0; // 80 .. 140
                }
            } elseif ($code === 'NR') {
                $val = 0.05 + $jitter01 * 0.55; // 0.05 .. 0.60
            } elseif ($code === 'IND') {
                $val = 0.7 + $jitter01 * 0.4;   // 0.7 .. 1.1 (around cutoff)
            } else {
                continue;
            }
            // Two decimals — matches how a lab tech would key it in.
            $out['test' . $position] = (string) round($val, 2);
        }
        return $out;
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
