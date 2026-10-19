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
     * Sample values for the scheme's preview: the participant from the latest
     * evaluated shipment, and the participant with the longest name, so a
     * preview shows whether long names fit. Placeholder text stands in when
     * the scheme has no evaluated response yet.
     *
     * @return list<array<string, string>>
     */
    public function sampleValues(string $schemeType): array
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $nameExpr = Application_Model_DbTable_Participants::participantNameExpr('p');
        $base = $db->select()
            ->from(['spm' => 'shipment_participant_map'], ['spm.attributes'])
            ->join(['s' => 'shipment'], 's.shipment_id = spm.shipment_id', ['s.shipment_date'])
            ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', ['sl.scheme_name'])
            ->join(['p' => 'participant'], 'p.participant_id = spm.participant_id', [
                'p.unique_identifier',
                'p.city',
                'participantName' => new Zend_Db_Expr($nameExpr),
            ])
            ->joinLeft(['c' => 'countries'], 'c.id = p.country', ['country_name' => 'c.iso_name'])
            ->where('s.scheme_type = ?', $schemeType)
            ->where(Application_Service_Reports::ANNUAL_RESULT_FILTER)
            ->limit(1);
        $rows = array_filter([
            $db->fetchRow((clone $base)->order('s.shipment_date DESC')),
            $db->fetchRow((clone $base)->order(new Zend_Db_Expr("CHAR_LENGTH($nameExpr) DESC"))),
        ]);

        $batch = array_filter(self::batchFields(), fn ($v) => $v !== '');
        if (empty($rows)) {
            return [array_merge(self::placeholderFields(), $batch)];
        }

        $vlAssays = (new Application_Model_Vl())->getVlAssay();
        $eidAssays = (new Application_Service_Schemes())->getEidExtractionAssay();
        $sets = [];
        foreach ($rows as $row) {
            $sets[$row['unique_identifier']] = array_merge(self::placeholderFields(), [
                'participant_name' => (string) $row['participantName'],
                'lab_id' => (string) $row['unique_identifier'],
                'city' => (string) $row['city'],
                'country' => (string) ($row['country_name'] ?? ''),
                'scheme_name' => (string) $row['scheme_name'],
                'assay' => Application_Service_Reports::annualReportAssayName(
                    $schemeType,
                    Pt_Commons_JsonUtility::safeDecode($row['attributes']),
                    $vlAssays,
                    $eidAssays
                ),
                'shipment_year' => date('Y', strtotime($row['shipment_date'])),
            ], $batch);
        }
        return array_values($sets);
    }

    /**
     * Fill a template the way a generated certificate prints, with each of
     * the scheme's sample participants on its own page. $layout previews an
     * unsaved designer layout. Returns the PDF's path, or null when the
     * template cannot be filled.
     */
    public function previewTemplate(string $schemeType, string $type, ?array $layout = null): ?string
    {
        $template = $this->getTemplateFilePath($schemeType, $type);
        if ($template === null) {
            return null;
        }
        $layout ??= $this->getLayout($schemeType, $type);
        $samples = $this->sampleValues($schemeType);
        $output = tempnam(sys_get_temp_dir(), 'preview') . '.pdf';
        $error = $layout !== null
            ? self::renderDesign($template, $layout, $samples, $output)
            : self::fillPdf($template, self::expandFields($samples[0]), $output);
        if ($error !== null) {
            Pt_Commons_LoggerUtility::logError('Certificate template preview failed: ' . $error);
            return null;
        }
        return $output;
    }

    /** Text set to shrink never goes below this share of its chosen size. */
    public const MIN_SHRINK = 0.6;

    /** The fonts the designer offers: TCPDF font name => label. */
    public const FONTS = [
        'helvetica' => 'Sans',
        'times' => 'Serif',
        'dejavusans' => 'Sans, all languages',
        'dejavuserif' => 'Serif, all languages',
    ];

    /** The designer layout of a template, or null for a fillable PDF. */
    public function getLayout(string $schemeType, string $type): ?array
    {
        $key = "$schemeType|$type";
        if (!array_key_exists($key, $this->layoutCache)) {
            $this->layoutCache[$key] = (new Application_Model_DbTable_CertificateTemplates())->getLayout($schemeType, $type);
        }
        return $this->layoutCache[$key];
    }

    /** @var array<string, array|null> */
    private array $layoutCache = [];

    /**
     * Keep only well-formed boxes from a layout sent by the designer, with
     * every value inside its allowed range.
     */
    public static function cleanLayout(array $input): array
    {
        $num = fn ($v, float $min, float $max) => max($min, min($max, round((float) $v, 2)));
        $elements = [];
        foreach (array_slice((array) ($input['elements'] ?? []), 0, 60) as $el) {
            if (!is_array($el)) {
                continue;
            }
            $text = trim(mb_substr((string) ($el['text'] ?? ''), 0, 500));
            if ($text === '') {
                continue;
            }
            $elements[] = [
                'text' => $text,
                'x' => $num($el['x'] ?? 0, 0, 2000),
                'y' => $num($el['y'] ?? 0, 0, 2000),
                'w' => $num($el['w'] ?? 50, 2, 2000),
                'h' => $num($el['h'] ?? 10, 2, 2000),
                'font' => isset(self::FONTS[$el['font'] ?? '']) ? $el['font'] : 'helvetica',
                'size' => $num($el['size'] ?? 14, 4, 120),
                'bold' => !empty($el['bold']),
                'italic' => !empty($el['italic']),
                'color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($el['color'] ?? '')) ? strtolower($el['color']) : '#000000',
                'align' => in_array($el['align'] ?? '', ['L', 'C', 'R'], true) ? $el['align'] : 'C',
                'fit' => ($el['fit'] ?? '') === 'wrap' ? 'wrap' : 'shrink',
            ];
        }
        return ['version' => 1, 'elements' => $elements];
    }

    /** True when the layout prints the participant name somewhere. */
    public static function layoutHasName(array $layout): bool
    {
        foreach ((array) ($layout['elements'] ?? []) as $el) {
            if (str_contains((string) ($el['text'] ?? ''), '{' . self::NAME_FIELD . '}')) {
                return true;
            }
        }
        return false;
    }

    /** Width and height in mm of a PDF's first page, or null when unreadable. */
    public static function pageSize(string $pdfFile): ?array
    {
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pdf->setSourceFile($pdfFile);
            $size = $pdf->getTemplateSize($pdf->importPage(1));
            return ['width' => round($size['width'], 2), 'height' => round($size['height'], 2)];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * The font to print $text in. Sans and Serif only have Western European
     * characters, so text with others, such as Vietnamese or Cyrillic, uses
     * the DejaVu font of the same style instead.
     */
    private static function fontFor(string $font, string $text): string
    {
        $fallback = ['helvetica' => 'dejavusans', 'times' => 'dejavuserif'];
        if (!isset($fallback[$font])) {
            return $font;
        }
        $latin = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        return mb_convert_encoding($latin, 'UTF-8', 'Windows-1252') === $text ? $font : $fallback[$font];
    }

    /**
     * Print a designer layout onto a design, one page per set of values.
     * Text in a box shrinks until it fits the box width, or wraps and
     * shrinks to fit the box when the box is set to wrap.
     * Returns null on success, else the error.
     */
    public static function renderDesign(string $background, array $layout, array $valueSets, string $outputPdf): ?string
    {
        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->setCellPaddings(0, 0, 0, 0);
            $pdf->setSourceFile($background);
            $page = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($page);

            foreach ($valueSets as $values) {
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($page, 0, 0, $size['width'], $size['height']);
                foreach ((array) ($layout['elements'] ?? []) as $el) {
                    $text = trim(preg_replace_callback(
                        '/\{([a-z_]+)\}/',
                        fn ($m) => (string) ($values[$m[1]] ?? ''),
                        (string) $el['text']
                    ));
                    if ($text === '') {
                        continue;
                    }
                    $style = (!empty($el['bold']) ? 'B' : '') . (!empty($el['italic']) ? 'I' : '');
                    $el['font'] = self::fontFor($el['font'], $text);
                    [$r, $g, $b] = sscanf($el['color'] ?? '#000000', '#%02x%02x%02x');
                    $pdf->SetTextColor($r, $g, $b);
                    $fontSize = (float) $el['size'];
                    $pdf->SetFont($el['font'], $style, $fontSize);
                    if (($el['fit'] ?? 'shrink') === 'wrap') {
                        $pdf->MultiCell($el['w'], $el['h'], $text, 0, $el['align'], false, 1, $el['x'], $el['y'], true, 0, false, false, $el['h'], 'M', true);
                        continue;
                    }
                    // Shrink to at most 60% of the chosen size. Smaller text is
                    // hard to read, so text still too long wraps instead.
                    $minSize = max(4, $fontSize * self::MIN_SHRINK);
                    while ($fontSize > $minSize && $pdf->GetStringWidth($text, $el['font'], $style, $fontSize) > $el['w']) {
                        $fontSize -= 0.5;
                    }
                    $pdf->SetFont($el['font'], $style, $fontSize);
                    if ($pdf->GetStringWidth($text) > $el['w']) {
                        // Wrapped lines sit centred on the box, above and below it if needed.
                        $lineHeight = $fontSize / $pdf->getScaleFactor() * $pdf->getCellHeightRatio();
                        $blockHeight = $pdf->getNumLines($text, $el['w']) * $lineHeight;
                        $pdf->MultiCell($el['w'], $lineHeight, $text, 0, $el['align'], false, 1, $el['x'], $el['y'] + ($el['h'] - $blockHeight) / 2, true, 0, false, false);
                        continue;
                    }
                    $pdf->SetXY($el['x'], $el['y']);
                    $pdf->Cell($el['w'], $el['h'], $text, 0, 0, $el['align'], false, '', 0, false, 'T', 'M');
                }
            }
            @mkdir(dirname($outputPdf), 0777, true);
            $pdf->Output($outputPdf, 'F');
        } catch (Throwable $e) {
            return 'Could not print the certificate design: ' . $e->getMessage();
        }
        return is_file($outputPdf) && filesize($outputPdf) > 0 ? null : 'Could not write ' . $outputPdf;
    }

    /**
     * Print one certificate from a scheme's template: onto the design when
     * the template has a layout, else into the fillable PDF's form fields.
     * $values holds the field values by field name. Returns null on success,
     * else the error.
     */
    public function fillTemplate(string $schemeType, string $type, array $values, string $outputPdf): ?string
    {
        $template = $this->getTemplateFilePath($schemeType, $type);
        if ($template === null) {
            return "No $type template for $schemeType";
        }
        $layout = $this->getLayout($schemeType, $type);
        if ($layout !== null && !self::layoutHasName($layout)) {
            return "The $type design for $schemeType has no participant name box";
        }
        return $layout !== null
            ? self::renderDesign($template, $layout, [$values], $outputPdf)
            : self::fillPdf($template, self::expandFields($values), $outputPdf);
    }

    /**
     * 'missing' when the scheme has no template of this type, 'needs_layout'
     * when a design has no participant name box yet, otherwise 'ready'.
     */
    public function templateState(string $schemeType, string $type): string
    {
        if ($this->getTemplateFilePath($schemeType, $type) === null) {
            return 'missing';
        }
        $layout = $this->getLayout($schemeType, $type);
        return $layout === null || self::layoutHasName($layout) ? 'ready' : 'needs_layout';
    }

    /**
     * The scheme names a template file and comes from the request, so it
     * must not hold a path.
     */
    private static function isSafeScheme(string $schemeType): bool
    {
        return $schemeType !== '' && !preg_match('#[/\\\\]|\.\.#', $schemeType);
    }

    /**
     * Turn an uploaded PNG or JPG design into a one-page PDF the size of an
     * A4 page in the image's orientation. Returns the PDF's path, or null.
     */
    private static function imageToPdf(string $imageFile): ?string
    {
        $info = @getimagesize($imageFile);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }
        [$px, $py] = $info;
        $landscape = $px >= $py;
        $width = $landscape ? 297 : 210;
        $height = round($width * $py / $px, 2);
        $pdf = new TCPDF($landscape ? 'L' : 'P', 'mm', [$width, $height]);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage($landscape ? 'L' : 'P', [$width, $height]);
        $pdf->Image($imageFile, 0, 0, $width, $height, $info[2] === IMAGETYPE_PNG ? 'PNG' : 'JPG');
        $output = tempnam(sys_get_temp_dir(), 'design') . '.pdf';
        $pdf->Output($output, 'F');
        return is_file($output) ? $output : null;
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
        if (!self::isSafeScheme($schemeType)) {
            return null;
        }
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

        if (!self::isSafeScheme($scheme)) {
            $result['message'] = 'Invalid scheme';
            return $result;
        }

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

        $certificateDb = new Application_Model_DbTable_CertificateTemplates();
        $result['unknown'] = [];
        $result['needsLayout'] = false;

        // An image is a design: make it a PDF page and place the details.
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo !== false) {
            $pdf = self::imageToPdf($file['tmp_name']);
            if ($pdf === null) {
                $result['message'] = 'Upload the design as a PDF, PNG or JPG file.';
                return $result;
            }
            $file['tmp_name'] = $pdf;
            return $this->saveDesign($certificateDb, $scheme, $type, $file, $result);
        }

        if (file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            $result['message'] = 'Upload the design as a PDF, PNG or JPG file. In Microsoft Word, choose File > Save As > PDF.';
            return $result;
        }

        // A PDF form with a participant name field is filled as a form.
        $validation = $this->validatePdfTemplate($file['tmp_name']);
        if ($validation['valid']) {
            $result['fields'] = $validation['fields'];
            $result['unknown'] = $validation['unknown'];
            $saveResult = $certificateDb->saveTemplateFile($scheme, $type, $file, $validation['fields'], false);
            $result['success'] = $saveResult['success'];
            $result['message'] = $saveResult['success'] ? 'Template uploaded and validated successfully' : ($saveResult['message'] ?? 'Failed to save template');
            $result['filename'] = $saveResult['filename'] ?? '';
            return $result;
        }

        // Any other readable PDF is a design. Word and Canva often save PDFs
        // that FPDI cannot read, so rewrite those first.
        if (self::pageSize($file['tmp_name']) === null) {
            $rewritten = self::rewritePdf($file['tmp_name']);
            if ($rewritten === null) {
                $result['message'] = 'ePT could not read this PDF. Save the design as PDF again and upload the new file.';
                return $result;
            }
            $file['tmp_name'] = $rewritten;
        }
        return $this->saveDesign($certificateDb, $scheme, $type, $file, $result);
    }

    /**
     * Rewrite a PDF that FPDI cannot read, such as one with compressed
     * cross-reference tables, into a copy it can. Tries pdftk, which keeps
     * the content as it is, then Ghostscript. Returns the copy's path, or
     * null when neither tool is installed or works.
     */
    private static function rewritePdf(string $pdfFile): ?string
    {
        $output = tempnam(sys_get_temp_dir(), 'design') . '.pdf';
        $commands = [];
        if ($pdftk = self::findPdftk()) {
            $commands[] = escapeshellcmd($pdftk) . ' ' . escapeshellarg($pdfFile) . ' output ' . escapeshellarg($output);
        }
        $gs = trim(shell_exec('command -v gs 2>/dev/null') ?? '');
        if ($gs !== '') {
            $commands[] = escapeshellcmd($gs) . ' -q -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=' . escapeshellarg($output) . ' ' . escapeshellarg($pdfFile);
        }
        foreach ($commands as $cmd) {
            @unlink($output);
            exec($cmd . ' 2>&1', $out, $code);
            if ($code === 0 && is_file($output) && self::pageSize($output) !== null) {
                return $output;
            }
        }
        @unlink($output);
        return null;
    }

    /**
     * Save an uploaded design. A replaced design keeps its layout, so the
     * admin only adjusts the boxes. A new one gets an empty layout, which
     * marks it as a design that needs its details placed.
     */
    private function saveDesign(Application_Model_DbTable_CertificateTemplates $certificateDb, string $scheme, string $type, array $file, array $result): array
    {
        $saveResult = $certificateDb->saveTemplateFile($scheme, $type, $file, []);
        if (!$saveResult['success']) {
            $result['message'] = $saveResult['message'] ?? 'Failed to save template';
            return $result;
        }
        $layout = $certificateDb->getLayout($scheme, $type);
        if ($layout === null) {
            $layout = ['version' => 1, 'elements' => []];
            $certificateDb->saveLayout($scheme, $type, $layout);
        }
        $this->layoutCache = [];
        $result['success'] = true;
        $result['needsLayout'] = !self::layoutHasName($layout);
        $result['design'] = true;
        $result['message'] = 'Design uploaded.';
        $result['filename'] = $saveResult['filename'] ?? '';
        return $result;
    }
}
