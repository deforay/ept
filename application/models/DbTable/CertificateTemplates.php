<?php

class Application_Model_DbTable_CertificateTemplates extends Zend_Db_Table_Abstract
{
    protected $_name = 'certificate_templates';
    protected $_primary = 'ct_id';

    /**
     * Ensure the certificate templates directory exists
     * @return string Path to the templates directory
     */
    private function ensureTemplateDirectory(): string
    {
        $pathPrefix = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates';
        if (!file_exists(SCHEDULED_JOBS_FOLDER)) {
            mkdir(SCHEDULED_JOBS_FOLDER, 0777, true);
        }
        if (!file_exists($pathPrefix)) {
            mkdir($pathPrefix, 0777, true);
        }
        return $pathPrefix;
    }

    /**
     * Get or create a certificate template record for a scheme
     * @param string $scheme Scheme ID
     * @return int Record ID
     */
    private function getOrCreateRecord(string $scheme): int
    {
        $row = $this->fetchRow($this->select()->where('scheme_type = ?', $scheme));
        if ($row) {
            return (int) $row->ct_id;
        }

        $authNameSpace = new Zend_Session_Namespace('administrators');
        return (int) $this->insert([
            'scheme_type' => $scheme,
            'created_by' => $authNameSpace->admin_id ?? 0,
            'updated_on' => new Zend_Db_Expr('now()')
        ]);
    }

    public function saveCertificateTemplateDetails($params)
    {
        try {
            $authNameSpace = new Zend_Session_Namespace('administrators');
            if (isset($params['scheme']) && sizeof($params['scheme']) > 0) {
                foreach ($params['scheme'] as $key => $scheme) {
                    $id = base64_decode($params['ctId'][$key]);
                    if (isset($id) && $id > 0) {
                        $this->update(array(
                            "updated_on"                => new Zend_Db_Expr('now()')
                        ), array("ct_id" => $id));
                    } else {
                        $id = $this->insert(array(
                            "scheme_type"               => $scheme,
                            "created_by"                => $authNameSpace->admin_id,
                            "updated_on"                => new Zend_Db_Expr('now()')
                        ));
                    }
                    $appDirectory = realpath(APPLICATION_PATH);
                    if (!file_exists($appDirectory . '/../scheduled-jobs')) {
                        mkdir($appDirectory . '/../scheduled-jobs', 0777, true);
                    }
                    if (!file_exists($appDirectory . '/../scheduled-jobs' . DIRECTORY_SEPARATOR . 'certificate-templates')) {
                        mkdir($appDirectory . '/../scheduled-jobs' . DIRECTORY_SEPARATOR . 'certificate-templates', 0777, true);
                    }

                    if (!empty($_FILES['pCertificate']['name'][$key])) {
                        $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['pCertificate']['name'][$key]);
                        $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                        $pathPrefix = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates';
                        $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                        $fileName = $scheme . "-p." . $extension;
                        if (move_uploaded_file($_FILES["pCertificate"]["tmp_name"][$key], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                            $this->update(array("participation_certificate" => $fileName), "ct_id = " . $id);
                        }
                    }
                    if (!empty($_FILES['eCertificate']['name'][$key])) {
                        $fileNameSanitized = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['eCertificate']['name'][$key]);
                        $fileNameSanitized = str_replace(" ", "-", $fileNameSanitized);
                        $pathPrefix = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates';
                        $extension = strtolower(pathinfo($pathPrefix . DIRECTORY_SEPARATOR . $fileNameSanitized, PATHINFO_EXTENSION));
                        $fileName = $scheme . "-e." . $extension;
                        if (move_uploaded_file($_FILES["eCertificate"]["tmp_name"][$key], $pathPrefix . DIRECTORY_SEPARATOR . $fileName)) {
                            $this->update(array("excellence_certificate" => $fileName), "ct_id = " . $id);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
        }
    }

    public function fetchAllCertificateTemplates()
    {
        $certificateTemplate = [];
        $result = $this->fetchAll();
        foreach ($result as $ct) {
            $certificateTemplate[$ct['scheme_type']] = array(
                "ct_id"                     => $ct["ct_id"],
                "scheme_type"               => $ct["scheme_type"],
                "participation_certificate" => $ct["participation_certificate"],
                "excellence_certificate"    => $ct["excellence_certificate"],
                "p_detected_fields"         => $ct["p_detected_fields"] ?? null,
                "e_detected_fields"         => $ct["e_detected_fields"] ?? null
            );
        }
        return $certificateTemplate;
    }

    /**
     * Save a template file for a specific scheme and type
     *
     * @param string $scheme Scheme ID (e.g., 'vl', 'eid', 'dts')
     * @param string $type Certificate type ('participation' or 'excellence')
     * @param array $file $_FILES array element for the uploaded file
     * @param array $detectedFields Array of detected form fields from validation
     * @return array ['success' => bool, 'message' => string, 'filename' => string]
     */
    public function saveTemplateFile(string $scheme, string $type, array $file, array $detectedFields = []): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'filename' => ''
        ];

        try {
            $pathPrefix = $this->ensureTemplateDirectory();
            $id = $this->getOrCreateRecord($scheme);

            // Determine column names based on type
            $fileColumn = ($type === 'excellence') ? 'excellence_certificate' : 'participation_certificate';
            $fieldsColumn = ($type === 'excellence') ? 'e_detected_fields' : 'p_detected_fields';
            $fileSuffix = ($type === 'excellence') ? '-e' : '-p';

            // Generate filename: {scheme}-{type}.pdf (always .pdf regardless of source name)
            $fileName = $scheme . $fileSuffix . '.pdf';
            $fullPath = $pathPrefix . DIRECTORY_SEPARATOR . $fileName;

            // Remove old file if it exists and is different
            $existingRow = $this->fetchRow($this->select()->where('ct_id = ?', $id));
            if ($existingRow && !empty($existingRow->$fileColumn)) {
                $oldFile = $pathPrefix . DIRECTORY_SEPARATOR . $existingRow->$fileColumn;
                if (file_exists($oldFile) && $oldFile !== $fullPath) {
                    @unlink($oldFile);
                }
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                $result['message'] = 'Failed to save file to destination';
                return $result;
            }

            // Update database record
            $updateData = [
                $fileColumn => $fileName,
                $fieldsColumn => json_encode($detectedFields),
                'updated_on' => new Zend_Db_Expr('now()')
            ];

            $this->update($updateData, ['ct_id = ?' => $id]);

            $result['success'] = true;
            $result['filename'] = $fileName;
            $result['message'] = 'Template saved successfully';

        } catch (Exception $e) {
            error_log("ERROR saving template: {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            $result['message'] = 'Database error while saving template';
        }

        return $result;
    }

    /**
     * Remove a template file for a specific scheme and type
     * Uses standardized naming: {scheme}-e.pdf or {scheme}-p.pdf
     *
     * @param string $scheme Scheme type (e.g., 'vl', 'eid', 'dts')
     * @param string $type Certificate type ('participation' or 'excellence')
     * @return bool True if removed successfully
     */
    public function removeTemplateFile(string $scheme, string $type): bool
    {
        try {
            $fileColumn = ($type === 'excellence') ? 'excellence_certificate' : 'participation_certificate';
            $fieldsColumn = ($type === 'excellence') ? 'e_detected_fields' : 'p_detected_fields';
            $fileSuffix = ($type === 'excellence') ? '-e.pdf' : '-p.pdf';

            // Standardized filename
            $filename = $scheme . $fileSuffix;
            $fullPath = SCHEDULED_JOBS_FOLDER . DIRECTORY_SEPARATOR . 'certificate-templates' . DIRECTORY_SEPARATOR . $filename;

            // Delete the file if it exists
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }

            // Update database to clear the filename and detected fields (if record exists)
            $row = $this->fetchRow($this->select()->where('scheme_type = ?', $scheme));
            if ($row) {
                $this->update([
                    $fileColumn => null,
                    $fieldsColumn => null,
                    'updated_on' => new Zend_Db_Expr('now()')
                ], ['ct_id = ?' => $row->ct_id]);
            }

            return true;

        } catch (Exception $e) {
            error_log("ERROR removing template: {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            return false;
        }
    }
}
