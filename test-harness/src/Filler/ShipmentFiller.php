<?php

declare(strict_types=1);

namespace EptTestHarness\Filler;

use EptTestHarness\Db;

/**
 * Attaches to an EXISTING shipment (one an admin already created) and does the
 * remaining busywork: enroll participants if none, fill in participant responses
 * against the shipment's own reference results, and leave it ready to evaluate.
 *
 * Raw SQL only; no app classes. The caller (bin/fill-shipment) runs the real
 * evaluator + report generator as subprocesses afterward.
 *
 * Supported families:
 *   - Custom (user-configured) QUALITATIVE schemes  → response_result_generic_test
 *   - DTS with dtsSchemeType = 'updated-3-tests'     → response_result_dts (serial 3-test)
 *
 * Only participants that don't already have responses are filled, so real/admin
 * responses are never overwritten.
 */
final class ShipmentFiller
{
    private const PREFIX = 'ATEST-';

    // r_possibleresult ids for DTS test/final codes.
    private const DTS_R = 1;   // REACTIVE
    private const DTS_NR = 2;  // NONREACTIVE
    private const DTS_P = 4;   // Positive (final)
    private const DTS_N = 5;   // Negative (final)
    private const DTS_IND = 6; // Indeterminate (final)

    public function __construct(private Db $db) {}

    /** Resolve a shipment by numeric id or shipment_code; returns its meta or null. */
    public function resolveShipment(string $idOrCode): ?array
    {
        $where = ctype_digit($idOrCode) ? 's.shipment_id = ?' : 's.shipment_code = ?';
        $param = ctype_digit($idOrCode) ? (int) $idOrCode : $idOrCode;
        return $this->db->one(
            "SELECT s.shipment_id, s.shipment_code, s.scheme_type, s.shipment_attributes,
                    s.response_deadline, sl.scheme_name, sl.is_user_configured,
                    JSON_UNQUOTE(JSON_EXTRACT(sl.user_test_config, '$.testType')) AS test_type
               FROM shipment s JOIN scheme_list sl ON sl.scheme_id = s.scheme_type
              WHERE $where",
            [$param]
        );
    }

    /**
     * Classify the shipment into a fill strategy.
     * @return array{family:string, reason?:string}  family ∈ {generic, dts, unsupported}
     */
    public function classify(array $shipment): array
    {
        if (($shipment['is_user_configured'] ?? '') === 'yes') {
            if (strtolower((string) $shipment['test_type']) !== 'qualitative') {
                return ['family' => 'unsupported', 'reason' => "custom scheme is '{$shipment['test_type']}' — only qualitative custom tests are supported"];
            }
            return ['family' => 'generic'];
        }
        if ($shipment['scheme_type'] === 'dts') {
            $attrs = json_decode((string) ($shipment['shipment_attributes'] ?? ''), true) ?: [];
            $type = $attrs['dtsSchemeType'] ?? '';
            if ($type !== 'updated-3-tests') {
                return ['family' => 'unsupported', 'reason' => "DTS dtsSchemeType is '" . ($type ?: '(none)') . "' — only 'updated-3-tests' is supported by the filler"];
            }
            return ['family' => 'dts'];
        }
        return ['family' => 'unsupported', 'reason' => "scheme_type '{$shipment['scheme_type']}' is not a custom or DTS scheme"];
    }

    /**
     * Enroll participants if the shipment has none, then fill responses for every
     * enrolled participant that doesn't already have one.
     *
     * @param array{enrollCount:int, passPct:float} $opts
     * @return array{family:string, already_enrolled:int, newly_enrolled:int, filled:int, pass:int, fail:int, skipped:int, late:bool}
     */
    public function fill(array $shipment, string $family, array $opts): array
    {
        $shipmentId = (int) $shipment['shipment_id'];
        $passPct = $opts['passPct'];

        // 1. Enrollment
        $existing = $this->db->col("SELECT participant_id FROM shipment_participant_map WHERE shipment_id = ?", [$shipmentId]);
        $alreadyEnrolled = count($existing);
        $newlyEnrolled = 0;
        if ($alreadyEnrolled === 0) {
            $pids = $this->allocateParticipants($opts['enrollCount']);
            foreach ($pids as $pid) {
                $this->enrollParticipant($shipmentId, (int) $pid);
            }
            $newlyEnrolled = count($pids);
        }

        // 2. Reference panel
        $samples = $family === 'dts'
            ? $this->db->all("SELECT sample_id, reference_result, control FROM reference_result_dts WHERE shipment_id = ? ORDER BY sample_id", [$shipmentId])
            : $this->db->all("SELECT sample_id, reference_result, control FROM reference_result_generic_test WHERE shipment_id = ? ORDER BY sample_id", [$shipmentId]);
        $samples = array_values(array_filter($samples, static fn ($s) => (int) $s['control'] === 0));
        if (empty($samples)) {
            throw new \RuntimeException("Shipment $shipmentId has no (non-control) reference results — set up the sample panel first.");
        }

        $finalCodesForScheme = $family === 'generic' ? $this->genericFinalCodes((string) $shipment['scheme_type']) : [];

        // 3. Maps that still need a response (skip those already answered)
        $maps = $this->db->all(
            "SELECT map_id FROM shipment_participant_map WHERE shipment_id = ? ORDER BY map_id",
            [$shipmentId]
        );
        $respTable = $family === 'dts' ? 'response_result_dts' : 'response_result_generic_test';

        $deadline = (string) ($shipment['response_deadline'] ?? '');
        $late = $deadline !== '' && strtotime($deadline) !== false && strtotime($deadline) < time();

        $filled = $pass = $fail = $skipped = 0;
        $idx = 0;
        foreach ($maps as $m) {
            $mapId = (int) $m['map_id'];
            $hasResp = (int) $this->db->scalar("SELECT COUNT(*) FROM $respTable WHERE shipment_map_id = ?", [$mapId]);
            if ($hasResp > 0) {
                $skipped++;
                continue;
            }
            // Deterministic-ish pass/fail spread by index so most pass, some fail.
            $willPass = (($idx * 1000) % 1000) / 1000.0 >= (1 - $passPct);
            // Simpler explicit spread: fail roughly every 1/(1-passPct)th lab.
            $failEvery = max(2, (int) round(1 / max(0.01, 1 - $passPct)));
            $willPass = (($idx + 1) % $failEvery) !== 0;
            $idx++;

            $this->stampResponseMeta($mapId, $family, $shipment);

            if ($family === 'dts') {
                $this->fillDtsResponses($mapId, $samples, $willPass);
            } else {
                $this->fillGenericResponses($mapId, $samples, $finalCodesForScheme, $willPass);
            }
            $filled++;
            $willPass ? $pass++ : $fail++;
        }

        return [
            'family'          => $family,
            'already_enrolled' => $alreadyEnrolled,
            'newly_enrolled'  => $newlyEnrolled,
            'filled'          => $filled,
            'pass'            => $pass,
            'fail'            => $fail,
            'skipped'         => $skipped,
            'late'            => $late,
        ];
    }

    // ---------------------------------------------------------------- generic

    /** FINAL result codes for a custom scheme, in order. */
    private function genericFinalCodes(string $schemeId): array
    {
        $codes = $this->db->col(
            "SELECT result_code FROM r_possibleresult
              WHERE scheme_id = ? AND UPPER(TRIM(scheme_sub_group)) = 'FINAL' ORDER BY sort_order",
            [$schemeId]
        );
        if (count($codes) < 2) {
            // fall back to any result codes for the scheme
            $codes = $this->db->col("SELECT result_code FROM r_possibleresult WHERE scheme_id = ? ORDER BY sort_order", [$schemeId]);
        }
        return array_values(array_unique($codes));
    }

    private function fillGenericResponses(int $mapId, array $samples, array $finalCodes, bool $willPass): void
    {
        $flipSample = $willPass ? -1 : (int) $samples[array_rand($samples)]['sample_id'];
        foreach ($samples as $s) {
            $ref = (string) $s['reference_result'];
            $reported = $ref;
            if ((int) $s['sample_id'] === $flipSample) {
                $reported = $this->differentCode($ref, $finalCodes);
            }
            $this->db->insert('response_result_generic_test', [
                'shipment_map_id' => $mapId,
                'sample_id'       => $s['sample_id'],
                'result_1'        => $reported,
                'reported_result' => $reported,
                'created_by'      => 'test-harness',
                'created_on'      => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function differentCode(string $ref, array $codes): string
    {
        foreach ($codes as $c) {
            if ($c !== $ref) {
                return $c;
            }
        }
        return $ref; // only one code available — can't differ
    }

    // -------------------------------------------------------------------- dts

    private function fillDtsResponses(int $mapId, array $samples, bool $willPass): void
    {
        $kits = $this->dtsKits();
        $exp = date('Y-m-d', strtotime('+1 year'));
        $flipSample = $willPass ? -1 : (int) $samples[array_rand($samples)]['sample_id'];

        foreach ($samples as $s) {
            $refId = (int) $s['reference_result'];   // 4=P,5=N,6=IND
            $reportedId = ($s['sample_id'] == $flipSample) ? $this->flipDts($refId) : $refId;

            // Test results consistent with the TRUE reference (a reporting error on a
            // flipped sample still fails on reference!=reported).
            [$t1, $t2, $t3, $rep1] = $this->dtsTestPattern($refId);

            $row = [
                'shipment_map_id' => $mapId,
                'sample_id'       => $s['sample_id'],
                'test_kit_name_1' => $kits[1], 'lot_no_1' => 'AUTOTEST-LOT1', 'exp_date_1' => $exp, 'test_result_1' => $t1,
                'test_kit_name_2' => $kits[2], 'lot_no_2' => 'AUTOTEST-LOT2', 'exp_date_2' => $exp, 'test_result_2' => $t2,
                'test_kit_name_3' => $kits[3], 'lot_no_3' => 'AUTOTEST-LOT3', 'exp_date_3' => $exp, 'test_result_3' => $t3,
                'reported_result' => $reportedId,
                'created_by'      => 'test-harness',
                'created_on'      => date('Y-m-d H:i:s'),
            ];
            if ($rep1 !== null) {
                $row['repeat_test_result_1'] = $rep1;
            }
            $this->db->insert('response_result_dts', $row);
        }
    }

    /** @return array{0:int|null,1:int|null,2:int|null,3:int|null} [t1,t2,t3,repeat1] for the serial 3-test algorithm */
    private function dtsTestPattern(int $refId): array
    {
        return match ($refId) {
            self::DTS_P   => [self::DTS_R, self::DTS_R, self::DTS_R, null],   // R,R,R -> Positive
            self::DTS_IND => [self::DTS_R, self::DTS_NR, null, self::DTS_R],  // R,NR + repeat R -> Indeterminate
            default       => [self::DTS_NR, null, null, null],               // NR -> Negative
        };
    }

    private function flipDts(int $refId): int
    {
        return match ($refId) {
            self::DTS_P => self::DTS_N,
            self::DTS_N => self::DTS_P,
            default     => self::DTS_N,
        };
    }

    /** Recommended kit id per position (1..3). */
    private function dtsKits(): array
    {
        $rows = $this->db->all("SELECT test_no, testkit FROM dts_recommended_testkits ORDER BY test_no, testkit");
        $kits = [];
        foreach ($rows as $r) {
            $pos = (int) $r['test_no'];
            $kits[$pos] ??= (string) $r['testkit'];
        }
        foreach ([1, 2, 3] as $pos) {
            if (empty($kits[$pos])) {
                throw new \RuntimeException("No recommended DTS test kit for position $pos — can't fill DTS responses.");
            }
        }
        return $kits;
    }

    // --------------------------------------------------------------- shared

    /**
     * Set the shipment_participant_map response metadata (dates, supervisor, response
     * status, and family-specific attributes), merging into any existing attributes so
     * an admin's enrollment data (e.g. dts_test_panel_type) is preserved.
     */
    private function stampResponseMeta(int $mapId, string $family, array $shipment): void
    {
        $now = date('Y-m-d H:i:s');
        $testDate    = date('Y-m-d', strtotime('-3 days'));
        $rehydrate   = date('Y-m-d', strtotime('-4 days')); // test - 1 day (within rehydration window)
        $receiptDate = date('Y-m-d', strtotime('-5 days'));

        $existingAttrs = json_decode((string) $this->db->scalar("SELECT attributes FROM shipment_participant_map WHERE map_id = ?", [$mapId]), true) ?: [];
        if ($family === 'dts') {
            $existingAttrs['algorithm'] ??= 'dts-3-tests';          // satisfies the "algorithm reported" gate
            $existingAttrs['sample_rehydration_date'] = $rehydrate; // documentation score (dried panel)
        } else {
            $existingAttrs['analyst_name'] ??= 'AutoTest Analyst';
        }

        $this->db->exec(
            "UPDATE shipment_participant_map
                SET attributes = ?, shipment_receipt_date = ?, shipment_test_date = ?,
                    shipment_test_report_date = ?, supervisor_approval = 'yes',
                    participant_supervisor = COALESCE(NULLIF(participant_supervisor,''),'AutoTest Supervisor'),
                    is_pt_test_not_performed = 'no', response_status = 'responded',
                    updated_by_user = 'test-harness', updated_on_user = ?
              WHERE map_id = ?",
            [json_encode($existingAttrs), $receiptDate, $testDate, $now, $now, $mapId]
        );
    }

    private function enrollParticipant(int $shipmentId, int $participantId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('shipment_participant_map', [
            'shipment_id'      => $shipmentId,
            'participant_id'   => $participantId,
            'response_status'  => 'noresponse',
            'created_on_admin' => $now,
            'created_by_admin' => 'test-harness',
        ]);
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
}
