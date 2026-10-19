<?php

/**
 * Who gets an annual certificate, of which type, and why a participant gets
 * none. The Annual Certificates page shows this plan before generating, and
 * generate-certificates.php prints from the same plan, so the two agree.
 */
class Application_Service_AnnualCertificates
{
    /**
     * Finalized, not cancelled shipments with a shipment date in $year,
     * grouped by scheme: scheme_id => ['name' => ..., 'shipments' => [...]].
     */
    public function shipmentsForYear(int $year): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $rows = $db->fetchAll(
            $db->select()
                ->from(['s' => 'shipment'], ['s.shipment_id', 's.shipment_code', 's.scheme_type', 's.shipment_date'])
                ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', ['sl.scheme_name'])
                ->joinLeft(['spm' => 'shipment_participant_map'], 'spm.shipment_id = s.shipment_id', [
                    'enrolled' => new Zend_Db_Expr('COUNT(spm.map_id)'),
                    'counted' => new Zend_Db_Expr('SUM(' . Application_Service_Reports::ANNUAL_RESULT_FILTER . ')'),
                ])
                ->where('s.cancelled_at IS NULL')
                ->where("s.finalized_at IS NOT NULL OR s.status = 'finalized'")
                ->where('YEAR(s.shipment_date) = ?', $year)
                ->group('s.shipment_id')
                ->order(['sl.scheme_name', 's.shipment_date', 's.shipment_code'])
        );
        $schemes = [];
        foreach ($rows as $row) {
            $schemes[$row['scheme_type']]['name'] = $row['scheme_name'];
            $schemes[$row['scheme_type']]['shipments'][] = [
                'id' => (int) $row['shipment_id'],
                'code' => $row['shipment_code'],
                'date' => Pt_Commons_DateUtility::humanReadableDateFormat($row['shipment_date']),
                'enrolled' => (int) $row['enrolled'],
                'counted' => (int) $row['counted'],
            ];
        }
        return $schemes;
    }

    /** Years that have at least one finalized shipment, newest first. */
    public function years(): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return array_map('intval', $db->fetchCol(
            $db->select()
                ->from(['s' => 'shipment'], [new Zend_Db_Expr('DISTINCT YEAR(s.shipment_date)')])
                ->where('s.cancelled_at IS NULL')
                ->where("s.finalized_at IS NOT NULL OR s.status = 'finalized'")
                ->where('s.shipment_date IS NOT NULL')
                ->order(new Zend_Db_Expr('YEAR(s.shipment_date) DESC'))
        ));
    }

    /**
     * The certificates for a set of shipments.
     *
     * 'certificates' lists one entry per participant and scheme that earns
     * one: unique_identifier, participant_name, scheme_type, scheme_name,
     * certificate_type and the template field values. 'skipped' lists the
     * participants of a scheme who earn none, with the shipments they did
     * not respond to. 'schemes' maps each scheme to its shipment codes.
     */
    public function plan(array $shipmentIds): array
    {
        $shipmentIds = array_values(array_filter(array_map('intval', $shipmentIds)));
        $result = ['schemes' => [], 'certificates' => [], 'skipped' => []];
        if (empty($shipmentIds)) {
            return $result;
        }

        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        foreach ($db->fetchAll(
            $db->select()
                ->from(['s' => 'shipment'], ['s.shipment_code', 's.scheme_type'])
                ->where('s.shipment_id IN (?)', $shipmentIds)
                ->order(['s.scheme_type', 's.shipment_date'])
        ) as $row) {
            $result['schemes'][$row['scheme_type']][] = $row['shipment_code'];
        }

        // The same responses, name and country the review Excel shows.
        $rows = $db->fetchAll(
            $db->select()
                ->from(['spm' => 'shipment_participant_map'], ['spm.attributes', 'spm.shipment_test_report_date', 'spm.final_result'])
                ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', ['s.shipment_code', 's.scheme_type', 's.shipment_date'])
                ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', ['sl.scheme_name'])
                ->join(['p' => 'participant'], 'p.participant_id = spm.participant_id', [
                    'p.unique_identifier',
                    'participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
                    'p.city',
                ])
                ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['country_name' => 'c.iso_name'])
                ->where('spm.shipment_id IN (?)', $shipmentIds)
                ->where(Application_Service_Reports::ANNUAL_RESULT_FILTER)
                ->order(['p.unique_identifier ASC', 's.scheme_type ASC', 's.shipment_date ASC'])
        );

        $participants = [];
        foreach ($rows as $row) {
            $uid = (string) $row['unique_identifier'];
            $scheme = $row['scheme_type'];
            $participants[$uid]['name'] = Pt_Commons_MiscUtility::toUtf8((string) $row['participantName']);
            $participants[$uid]['city'] = (string) $row['city'];
            $participants[$uid]['country'] = (string) ($row['country_name'] ?? '');
            $participants[$uid]['schemes'][$scheme]['entries'][$row['shipment_code']] = [
                'result' => $row['final_result'],
                'shipment_test_report_date' => $row['shipment_test_report_date'],
            ];
            // Rows run oldest shipment first, so the assay and the year come
            // from the participant's latest shipment of the scheme.
            $participants[$uid]['schemes'][$scheme]['attributes'] = Pt_Commons_JsonUtility::safeDecode($row['attributes']);
            $participants[$uid]['schemes'][$scheme]['year'] = date('Y', strtotime($row['shipment_date']));
            $participants[$uid]['schemes'][$scheme]['scheme_name'] = $row['scheme_name'];
        }

        $vlAssays = (new Application_Model_Vl())->getVlAssay();
        $eidAssays = (new Application_Service_Schemes())->getEidExtractionAssay();
        $batchFields = Application_Service_CertificateTemplates::batchFields();
        foreach ($participants as $uid => $participant) {
            foreach ($participant['schemes'] as $scheme => $data) {
                $codes = $result['schemes'][$scheme] ?? [];
                $type = Application_Service_Reports::annualCertificateType($data['entries'], $codes);
                $base = [
                    'unique_identifier' => (string) $uid,
                    'participant_name' => $participant['name'],
                    'scheme_type' => $scheme,
                    'scheme_name' => $data['scheme_name'],
                ];
                if ($type === '') {
                    $missed = array_values(array_filter($codes, fn ($code) => empty($data['entries'][$code]['shipment_test_report_date'])));
                    $result['skipped'][] = $base + ['missed' => $missed];
                    continue;
                }
                $assay = Application_Service_Reports::annualReportAssayName($scheme, $data['attributes'], $vlAssays, $eidAssays);
                $result['certificates'][] = $base + [
                    'certificate_type' => $type,
                    'fields' => array_merge([
                        'participant_name' => $participant['name'],
                        'lab_id' => (string) $uid,
                        'city' => $participant['city'],
                        'country' => $participant['country'],
                        'scheme_name' => $data['scheme_name'],
                        'assay' => $assay,
                        'shipment_year' => $data['year'],
                    ], $batchFields),
                ];
            }
        }
        return $result;
    }

    /**
     * The plan's counts per scheme: excellence, participation and skipped.
     */
    public static function counts(array $plan): array
    {
        $counts = [];
        foreach (array_keys($plan['schemes']) as $scheme) {
            $counts[$scheme] = ['excellence' => 0, 'participation' => 0, 'skipped' => 0];
        }
        foreach ($plan['certificates'] as $certificate) {
            $counts[$certificate['scheme_type']][$certificate['certificate_type']]++;
        }
        foreach ($plan['skipped'] as $skipped) {
            $counts[$skipped['scheme_type']]['skipped']++;
        }
        return $counts;
    }

    /**
     * A batch name for the shipments: the year and the scheme codes, with
     * a number added when a batch already has that name.
     */
    public function suggestName(int $year, array $schemes): string
    {
        $name = preg_replace('/[^A-Za-z0-9-]+/', '-', $year . '-' . strtoupper(implode('-', $schemes)));
        $name = trim(substr((string) $name, 0, 80), '-');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $taken = $db->fetchCol($db->select()->from('certificate_batches', ['batch_name'])->where('batch_name LIKE ?', $name . '%'));
        $candidate = $name;
        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = "$name-$i";
        }
        return $candidate;
    }

    /**
     * The batch the page resumes: the newest one still on its way to the
     * participants, or one distributed in the last 30 days.
     */
    public function activeBatch(): ?array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $row = $db->fetchRow(
            $db->select()
                ->from('certificate_batches')
                ->where("status IN ('pending','generating','generated','approved','distributing') OR (status = 'distributed' AND distributed_on >= NOW() - INTERVAL 30 DAY)")
                ->order('batch_id DESC')
                ->limit(1)
        );
        return $row ?: null;
    }

    /**
     * The certificates of a generated batch, from its manifest, each with a
     * signed link to view it. Batches from before the manifest return none.
     */
    public function batchCertificates(array $batch): array
    {
        $folder = (string) ($batch['folder_path'] ?? '');
        $manifest = $folder !== '' ? $folder . DIRECTORY_SEPARATOR . 'manifest.json' : '';
        if ($manifest === '' || !is_file($manifest)) {
            return [];
        }
        $list = [];
        foreach ((array) json_decode((string) file_get_contents($manifest), true) as $entry) {
            $path = $folder . DIRECTORY_SEPARATOR . $entry['file'];
            if (!is_file($path)) {
                continue;
            }
            $list[] = [
                'unique_identifier' => (string) $entry['unique_identifier'],
                'participant_name' => (string) ($entry['participant_name'] ?? ''),
                'scheme_type' => (string) $entry['scheme_type'],
                'certificate_type' => (string) $entry['certificate_type'],
                'url' => Pt_Commons_SignedDownload::url($path, 8 * 3600),
            ];
        }
        return $list;
    }

    /** The participants a generated batch skipped, from its skipped.json. */
    public function batchSkipped(array $batch): array
    {
        $file = (string) ($batch['folder_path'] ?? '') . DIRECTORY_SEPARATOR . 'skipped.json';
        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }
}
