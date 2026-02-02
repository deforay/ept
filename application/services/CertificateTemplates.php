<?php

class Application_Service_CertificateTemplates
{

    /**
     * Required field names that must be present in PDF templates
     * At least one of these must be present for participant name
     */
    private const REQUIRED_PARTICIPANT_FIELDS = [
        'participant_name',
        'participantname',
        'labname',
        'participant'
    ];

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

    /**
     * Find the pdftk binary on the system
     * @return string|null Path to pdftk binary or null if not found
     */
    private function findPdftk(): ?string
    {
        foreach (['/usr/bin/pdftk', '/usr/bin/pdftk-java', '/usr/local/bin/pdftk'] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        $which = trim(shell_exec('command -v pdftk 2>/dev/null') ?? '');
        return $which !== '' ? $which : null;
    }

    /**
     * Validate a PDF template file for certificate generation
     *
     * Uses pdftk to extract form field names and checks for required fields
     * for participant name (at least one of: participant_name, labname,
     * participantname, participant)
     *
     * @param string $filePath Path to the PDF file to validate
     * @return array ['valid' => bool, 'fields' => array, 'error' => string]
     */
    public function validatePdfTemplate(string $filePath): array
    {
        $result = [
            'valid' => false,
            'fields' => [],
            'error' => ''
        ];

        // Check if file exists
        if (!file_exists($filePath)) {
            $result['error'] = 'File not found: ' . $filePath;
            return $result;
        }

        // Check file is readable
        if (!is_readable($filePath)) {
            $result['error'] = 'File is not readable: ' . $filePath;
            return $result;
        }

        // Check file extension
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $result['error'] = 'File must be a PDF document. Received: ' . $extension;
            return $result;
        }

        // Find pdftk binary
        $pdftk = $this->findPdftk();
        if (!$pdftk) {
            $result['error'] = 'pdftk is not installed or not found on this system. Please install pdftk to validate PDF templates.';
            return $result;
        }

        // Execute pdftk to dump form field data
        // Note: Using escapeshellcmd and escapeshellarg to prevent command injection
        // $filePath is validated to exist and be readable before reaching this point
        $cmd = escapeshellcmd($pdftk) . ' ' . escapeshellarg($filePath) . ' dump_data_fields 2>&1';
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $result['error'] = 'Failed to read PDF form fields. The file may be corrupted or password-protected.';
            return $result;
        }

        // Parse the output to extract field names
        $outputText = implode("\n", $output);
        $fields = [];

        // pdftk output format:
        // ---
        // FieldType: Text
        // FieldName: participant_name
        // FieldFlags: 0
        // ---
        preg_match_all('/FieldName:\s*(.+)/i', $outputText, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $fieldName) {
                $fields[] = trim($fieldName);
            }
        }

        $result['fields'] = $fields;

        // Check if at least one required participant name field is present
        $hasRequiredField = false;
        $foundParticipantField = null;
        foreach (self::REQUIRED_PARTICIPANT_FIELDS as $requiredField) {
            foreach ($fields as $field) {
                if (strtolower($field) === strtolower($requiredField)) {
                    $hasRequiredField = true;
                    $foundParticipantField = $field;
                    break 2;
                }
            }
        }

        if (!$hasRequiredField) {
            if (empty($fields)) {
                $result['error'] = 'No form fields detected in the PDF. Please ensure the PDF has AcroForm fields. Required: one of ' . implode(', ', self::REQUIRED_PARTICIPANT_FIELDS);
            } else {
                $result['error'] = 'Missing required participant name field. Found fields: ' . implode(', ', $fields) . '. Required: one of ' . implode(', ', self::REQUIRED_PARTICIPANT_FIELDS);
            }
            return $result;
        }

        $result['valid'] = true;
        return $result;
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
            'fields' => []
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
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            $result['message'] = $errorMessages[$file['error']] ?? 'Unknown upload error';
            return $result;
        }

        // Validate the uploaded PDF
        $validation = $this->validatePdfTemplate($file['tmp_name']);
        $result['fields'] = $validation['fields'];

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
