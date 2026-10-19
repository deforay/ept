<?php

class Application_Service_CertificateTemplates
{
    /**
     * The form fields ePT fills on a certificate template. The key is the
     * field name to use in a new template. The aliases are older names that
     * existing templates use and that still work. docs/help/admin/en_US/
     * certificate-templates.md describes each field for admins.
     */
    public const FIELDS = [
        'participant_name' => ['participantname', 'labname', 'participant'],
        'lab_id' => [],
        'city' => [],
        'country' => [],
        'scheme_name' => [],
        'assay' => ['assayname'],
        'shipment_year' => ['shipmentyear'],
        'issue_date' => [],
        'signatory_name' => [],
        'signatory_title' => [],
    ];

    /** A template must have one of these, or the certificate has no name. */
    public const NAME_FIELD = 'participant_name';

    public function getAllCertificateTemplates()
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        return $certificateDb->fetchAllCertificateTemplates();
    }

    public function saveCertificateTemplate($params)
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        return $certificateDb->saveCertificateTemplateDetails($params);
    }

    /** Path to the pdftk binary, or null when it is not installed. */
    public static function findPdftk(): ?string
    {
        foreach (['/usr/bin/pdftk', '/usr/bin/pdftk-java', '/usr/local/bin/pdftk'] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        $which = trim(shell_exec('command -v pdftk 2>/dev/null') ?? '');
        return $which !== '' ? $which : null;
    }

    /** The field name and every alias of it, in lower case. */
    private static function namesFor(string $field): array
    {
        return array_merge([$field], self::FIELDS[$field] ?? []);
    }

    /**
     * Copy each value to the field's aliases, so a template that uses an
     * older name (labname, shipmentyear) gets the same value.
     */
    public static function expandFields(array $values): array
    {
        $expanded = [];
        foreach ($values as $field => $value) {
            foreach (self::namesFor($field) as $name) {
                $expanded[$name] = $value;
            }
        }
        return $expanded;
    }

    /**
     * Fill a template's form fields and flatten the result into a plain PDF.
     * Fields missing from $fields stay as the template has them.
     * Returns null on success, else the error.
     */
    public static function fillPdf(string $templateFile, array $fields, string $outputPdf): ?string
    {
        if (!is_file($templateFile)) {
            return "Template missing: $templateFile";
        }
        $pdftk = self::findPdftk();
        if (!$pdftk) {
            return 'pdftk is not installed on this server.';
        }

        // pdftk matches field names exactly. Use the template's own spelling,
        // so a field named Participant_Name still gets participant_name.
        $names = self::templateFieldNames($pdftk, $templateFile);

        // FDF strings in UTF-16BE with a byte order mark keep non-Latin
        // names (Burmese, Thai, accented French) intact.
        $fdf = "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n";
        foreach ($fields as $key => $value) {
            $key = $names[strtolower((string) $key)] ?? $key;
            $v = str_replace(["\r\n", "\r", "\n"], "\r", (string) ($value ?? ''));
            $k16 = "\xFE\xFF" . mb_convert_encoding((string) $key, 'UTF-16BE', 'UTF-8');
            $v16 = "\xFE\xFF" . mb_convert_encoding($v, 'UTF-16BE', 'UTF-8');
            $k16 = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $k16);
            $v16 = str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $v16);
            $fdf .= "<< /T ({$k16}) /V ({$v16}) >>\n";
        }
        $fdf .= "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
        $fdfFile = tempnam(sys_get_temp_dir(), 'fdf');
        file_put_contents($fdfFile, $fdf);

        @mkdir(dirname($outputPdf), 0777, true);
        $cmd = escapeshellcmd($pdftk) . ' ' . escapeshellarg($templateFile) . ' fill_form ' .
            escapeshellarg($fdfFile) . ' output ' . escapeshellarg($outputPdf) . ' flatten';
        $out = [];
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);
        unlink($fdfFile);

        if ($code !== 0 || !is_file($outputPdf) || filesize($outputPdf) === 0) {
            return "pdftk could not fill the template (code $code): " . implode("\n", $out);
        }
        return null;
    }

    /**
     * The template's form field names, keyed by lower case. Cached per file
     * for the run, because a batch fills the same template many times.
     */
    private static function templateFieldNames(string $pdftk, string $templateFile): array
    {
        static $cache = [];
        $key = $templateFile . '|' . @filemtime($templateFile);
        if (!isset($cache[$key])) {
            $output = [];
            exec(escapeshellcmd($pdftk) . ' ' . escapeshellarg($templateFile) . ' dump_data_fields_utf8 2>/dev/null', $output);
            $cache[$key] = [];
            foreach ($output as $line) {
                if (preg_match('/^FieldName:\s*(.+)$/', $line, $m)) {
                    $cache[$key][strtolower(trim($m[1]))] = trim($m[1]);
                }
            }
        }
        return $cache[$key];
    }

    /**
     * Check that a PDF works as a certificate template before it is saved.
     *
     * A template is rejected when ePT cannot read its form fields, when it
     * has no participant name field, or when a test fill fails. Fields that
     * ePT does not fill are allowed, but listed in 'unknown', because a
     * misspelled name prints nothing.
     *
     * @return array ['valid' => bool, 'fields' => array, 'unknown' => array, 'error' => string]
     */
    public function validatePdfTemplate(string $filePath): array
    {
        $result = [
            'valid' => false,
            'fields' => [],
            'unknown' => [],
            'error' => '',
        ];

        if (!is_file($filePath) || !is_readable($filePath)) {
            $result['error'] = 'The uploaded file could not be read.';
            return $result;
        }
        if (file_get_contents($filePath, false, null, 0, 5) !== '%PDF-') {
            $result['error'] = 'The file is not a PDF. Export the certificate design as PDF and upload that file.';
            return $result;
        }

        $pdftk = self::findPdftk();
        if (!$pdftk) {
            $result['error'] = 'pdftk is not installed on this server, so templates cannot be checked or filled. Ask your system administrator to install pdftk.';
            return $result;
        }

        $output = [];
        $returnCode = 0;
        exec(escapeshellcmd($pdftk) . ' ' . escapeshellarg($filePath) . ' dump_data_fields_utf8 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            $result['error'] = 'ePT could not read the form fields. The PDF may be damaged or password-protected.';
            return $result;
        }

        // pdftk prints one block per field, separated by "---" lines:
        // FieldType: Text / FieldName: participant_name / ...
        $fields = [];
        $types = [];
        foreach (preg_split('/^---$/m', implode("\n", $output)) as $block) {
            if (preg_match('/^FieldName:\s*(.+)$/m', $block, $name)) {
                $fieldName = trim($name[1]);
                $fields[] = $fieldName;
                $types[strtolower($fieldName)] = preg_match('/^FieldType:\s*(.+)$/m', $block, $type) ? trim($type[1]) : '';
            }
        }
        $fields = array_values(array_unique($fields));
        $result['fields'] = $fields;

        if (empty($fields)) {
            $result['error'] = 'The PDF has no form fields. Add a text field named participant_name where the participant name goes, then export again with "Create PDF form" ticked.';
            return $result;
        }

        $known = [];
        foreach (array_keys(self::FIELDS) as $field) {
            foreach (self::namesFor($field) as $alias) {
                $known[$alias] = $field;
            }
        }
        $nameField = null;
        foreach ($fields as $field) {
            $canonical = $known[strtolower($field)] ?? null;
            if ($canonical === null) {
                $result['unknown'][] = $field;
            } elseif ($canonical === self::NAME_FIELD && $nameField === null) {
                $nameField = $field;
            }
        }

        if ($nameField === null) {
            $result['error'] = 'The PDF has no participant name field. Name the field participant_name. Fields found: ' . implode(', ', $fields) . '.';
            return $result;
        }
        if (($types[strtolower($nameField)] ?? '') !== 'Text') {
            $result['error'] = "The field $nameField is not a text field. Delete it and add a text box with the same name.";
            return $result;
        }

        // A test fill catches templates that list fields but cannot be
        // filled, such as XFA forms.
        $testFile = tempnam(sys_get_temp_dir(), 'cert') . '.pdf';
        $fillError = self::fillPdf($filePath, self::expandFields(self::placeholderFields()), $testFile);
        @unlink($testFile);
        if ($fillError !== null) {
            Pt_Commons_LoggerUtility::logError('Certificate template test fill failed: ' . $fillError);
            $result['error'] = 'ePT could not fill this PDF. Make it again with LibreOffice and tick "Create PDF form" when exporting.';
            return $result;
        }

        $result['valid'] = true;
        return $result;
    }

    /** Values that show where each field prints, for a test fill. */
    private static function placeholderFields(): array
    {
        $values = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $values[$field] = ucfirst(str_replace('_', ' ', $field));
        }
        return $values;
    }

    /**
     * The values that do not depend on the participant: the issue date and
     * the signatory from Configure > Global Settings.
     */
    public static function batchFields(): array
    {
        return [
            'issue_date' => Pt_Commons_DateUtility::humanReadableDateFormat(date('Y-m-d')),
            'signatory_name' => (string) Application_Service_Common::getConfig('certificate_signatory_name'),
            'signatory_title' => (string) Application_Service_Common::getConfig('certificate_signatory_title'),
        ];
    }

    /**
     * Fill a template with a real participant of the scheme, so the admin
     * sees how the certificate prints. Falls back to placeholder text when
     * the scheme has no evaluated response yet. Returns the PDF's path, or
     * null when the template cannot be filled.
     */
    public function previewTemplate(string $schemeType, string $type): ?string
    {
        $template = $this->getTemplateFilePath($schemeType, $type);
        if ($template === null) {
            return null;
        }

        $values = self::placeholderFields();
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $row = $db->fetchRow(
            $db->select()
                ->from(['spm' => 'shipment_participant_map'], ['spm.attributes'])
                ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', ['s.shipment_date'])
                ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', ['sl.scheme_name'])
                ->join(['p' => 'participant'], 'p.participant_id = spm.participant_id', [
                    'p.unique_identifier',
                    'p.city',
                    'participantName' => new Zend_Db_Expr(Application_Model_DbTable_Participants::participantNameExpr('p')),
                ])
                ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['country_name' => 'c.iso_name'])
                ->where('s.scheme_type = ?', $schemeType)
                ->where(Application_Service_Reports::ANNUAL_RESULT_FILTER)
                ->order('s.shipment_date DESC')
                ->limit(1)
        );
        if ($row) {
            $vlModel = new Application_Model_Vl();
            $schemeService = new Application_Service_Schemes();
            $values = array_merge($values, [
                'participant_name' => $row['participantName'],
                'lab_id' => $row['unique_identifier'],
                'city' => $row['city'],
                'country' => $row['country_name'] ?? '',
                'scheme_name' => $row['scheme_name'],
                'assay' => Application_Service_Reports::annualReportAssayName(
                    $schemeType,
                    Pt_Commons_JsonUtility::safeDecode($row['attributes']),
                    $vlModel->getVlAssay(),
                    $schemeService->getEidExtractionAssay()
                ),
                'shipment_year' => date('Y', strtotime($row['shipment_date'])),
            ]);
        }
        foreach (self::batchFields() as $field => $value) {
            if ($value !== '') {
                $values[$field] = $value;
            }
        }

        $output = tempnam(sys_get_temp_dir(), 'preview') . '.pdf';
        $error = self::fillPdf($template, self::expandFields($values), $output);
        if ($error !== null) {
            Pt_Commons_LoggerUtility::logError('Certificate template preview failed: ' . $error);
            return null;
        }
        return $output;
    }

    /**
     * Which templates each active scheme has, keyed by scheme_id:
     * ['name' => scheme name, 'excellence' => bool, 'participation' => bool].
     */
    public function templateStatus(): array
    {
        $status = [];
        foreach ((new Application_Service_Schemes())->getAllSchemes() as $scheme) {
            $status[$scheme['scheme_id']] = [
                'name' => $scheme['scheme_name'],
                'excellence' => $this->getTemplateFilePath($scheme['scheme_id'], 'excellence') !== null,
                'participation' => $this->getTemplateFilePath($scheme['scheme_id'], 'participation') !== null,
            ];
        }
        return $status;
    }

    /**
     * Get the template file path for a given scheme and type
     * Uses standardized naming: {schemeType}-e.pdf or {schemeType}-p.pdf
     *
     * @param string $schemeType Scheme type (e.g., 'vl', 'eid', 'dts')
     * @param string $type Certificate type ('participation' or 'excellence')
     * @return string|null Full path to template file or null if not found
     */
    public function getTemplateFilePath(string $schemeType, string $type): ?string
    {
        // Standardized naming: {schemeType}-p.pdf for participation, {schemeType}-e.pdf for excellence
        $suffix = ($type === 'excellence') ? '-e.pdf' : '-p.pdf';
        $filename = $schemeType . $suffix;
        $fullPath = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates' . DIRECTORY_SEPARATOR . $filename;

        if (!file_exists($fullPath)) {
            return null;
        }

        return $fullPath;
    }

    /**
     * Remove a template file and update the database
     *
     * @param string $scheme Scheme ID
     * @param string $type Certificate type ('participation' or 'excellence')
     * @return bool True if removed successfully
     */
    public function removeTemplate(string $scheme, string $type): bool
    {
        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        return $certificateDb->removeTemplateFile($scheme, $type);
    }

    /**
     * Upload and save a new template with validation
     *
     * @param string $scheme Scheme ID
     * @param string $type Certificate type ('participation' or 'excellence')
     * @param array $file $_FILES array element for the uploaded file
     * @return array ['success' => bool, 'message' => string, 'fields' => array]
     */
    public function uploadTemplate(string $scheme, string $type, array $file): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'fields' => [],
        ];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            ];
            $result['message'] = $errorMessages[$file['error']] ?? 'Unknown upload error';
            return $result;
        }

        // Validate the uploaded PDF
        $validation = $this->validatePdfTemplate($file['tmp_name']);
        $result['fields'] = $validation['fields'];
        $result['unknown'] = $validation['unknown'];

        if (!$validation['valid']) {
            $result['message'] = $validation['error'];
            return $result;
        }

        // Save the template
        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        $saveResult = $certificateDb->saveTemplateFile($scheme, $type, $file, $validation['fields']);

        if ($saveResult['success']) {
            $result['success'] = true;
            $result['message'] = 'Template uploaded and validated successfully';
            $result['filename'] = $saveResult['filename'] ?? '';
        } else {
            $result['message'] = $saveResult['message'] ?? 'Failed to save template';
        }

        return $result;
    }
}
