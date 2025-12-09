<?php

class Application_Model_DbTable_TempMail extends Zend_Db_Table_Abstract
{
    protected $_name    = 'temp_mail';
    protected $_primary = 'temp_id';

    /**
     * Insert temporary mail details into the database for queued email processing
     * 
     * @param string $to Primary recipient email address(es)
     * @param string|null $cc Carbon copy recipient email address(es)
     * @param string|null $bcc Blind carbon copy recipient email address(es)
     * @param string $subject Email subject line
     * @param string $message Email message body (HTML or plain text)
     * @param string|null $fromMail Sender email address (defaults to config value)
     * @param string|null $fromName Sender display name (defaults to 'ePT Support')
     * @param array $attachments Array of file paths to attach to the email
     * @param string|null $replyTo Reply-to email address (defaults to fromMail)
     * @return int|false Insert ID on success, false on failure
     */
    public function insertTempMailDetails(
        $to,
        $cc,
        $bcc,
        $subject,
        $message,
        $fromMail = null,
        $fromName = null,
        $attachments = [],
        $replyTo = null
    ) {

        try {
            // Validate message content - reject empty messages
            if (trim((string)$message) === '') {
                error_log("TempMail insert rejected: empty message body");
                return false;
            }

            // Load application configuration
            try {
                $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            } catch (Zend_Config_Exception $e) {
                error_log("Failed to load application configuration: " . $e->getMessage());
                throw new Exception("Configuration file could not be loaded");
            }
            // Load attachment size limits from configuration with fallback defaults
            // email.limits.perAttachmentMb = 15 (maximum size per individual attachment)
            // email.limits.totalAttachmentsMb = 22 (maximum cumulative size for all attachments)
            $perAttachMb    = (int)($conf->email->limits->perAttachmentMb ?? 15);
            $totalAttachMb  = (int)($conf->email->limits->totalAttachmentsMb ?? 22);
            $PER_BYTES      = $perAttachMb   * 1024 * 1024;
            $TOTAL_BYTES    = $totalAttachMb * 1024 * 1024;

            // Parse and validate recipient email addresses
            try {
                $recips = Application_Service_Common::parseRecipients(
                    (string)$to,
                    $cc !== null  ? (string)$cc  : null,
                    $bcc !== null ? (string)$bcc : null
                );
            } catch (Exception $e) {
                error_log("Failed to parse recipients: " . $e->getMessage());
                return false;
            }
            // Ensure at least one valid TO recipient exists
            if (empty($recips['to'])) {
                if (!empty($recips['invalid'])) {
                    error_log("TempMail insert rejected: no valid TO. Invalid: " . implode(', ', $recips['invalid']));
                } else {
                    error_log("TempMail insert rejected: no TO recipients provided");
                }
                return false;
            }

            // Set and validate sender email address
            // Falls back to configured default email if not provided or invalid
            try {
                $fromMail = (string)($fromMail ?: $conf->email->config->username);
                $fromMail = Application_Service_Common::validateEmail($fromMail) ?: $conf->email->config->username;
                $fromName = $fromName ?: 'ePT Support';
            } catch (Exception $e) {
                error_log("Failed to set FROM address: " . $e->getMessage());
                // Use configuration default as ultimate fallback
                $fromMail = $conf->email->config->username;
                $fromName = 'ePT Support';
            }

            // Set and validate reply-to address
            // Extracts first email from comma/semicolon-separated list
            try {
                $replyToRaw   = (string)($replyTo ?? $fromMail);
                $replyToFirst = trim(preg_split('/[;,]+/', $replyToRaw)[0] ?? '');
                $replyToValid = $replyToFirst && Application_Service_Common::validateEmail($replyToFirst)
                    ? $replyToFirst
                    : $fromMail;
            } catch (Exception $e) {
                error_log("Failed to set REPLY-TO address: " . $e->getMessage());
                $replyToValid = $fromMail;
            }

            // Process attachments with size validation
            // - Skip files that don't exist or can't be read
            // - Skip files exceeding per-file size limit
            // - Stop adding files once cumulative limit is reached
            $files = [];
            $total = 0;
            if (!empty($attachments)) {
                $list = is_array($attachments) ? $attachments : [$attachments];
                foreach ($list as $path) {
                    try {
                        // Validate file path is a string and file exists
                        if (!is_string($path) || !file_exists($path)) {
                            error_log("Attachment skipped (not found): " . (string)$path);
                            continue;
                        }

                        // Get file size with error suppression
                        $size = @filesize($path);
                        if ($size === false) {
                            error_log("Attachment skipped (size unreadable): " . (string)$path);
                            continue;
                        }

                        // Check per-file size limit
                        if ($size > $PER_BYTES) {
                            error_log("Attachment skipped (per-file limit {$perAttachMb}MB): " . basename($path));
                            continue;
                        }

                        // Check cumulative size limit
                        if ($total + $size > $TOTAL_BYTES) {
                            error_log("Attachment skipped (total limit {$totalAttachMb}MB would be exceeded): " . basename($path));
                            continue;
                        }

                        // File passed all validations - add to list
                        $files[] = $path;
                        $total  += $size;
                    } catch (Exception $e) {
                        error_log("Error processing attachment {$path}: " . $e->getMessage());
                        continue;
                    }
                }
            }

            // Build database row with all validated email data
            $row = [
                'from_mail'      => $fromMail,
                'from_full_name' => $fromName,
                'reply_to'       => $replyToValid,
                'to_email'       => implode(',', $recips['to']),
                'cc'             => !empty($recips['cc'])  ? implode(',', $recips['cc'])  : '',
                'bcc'            => !empty($recips['bcc']) ? implode(',', $recips['bcc']) : '',
                'subject'        => (string)$subject,
                'message'        => (string)$message,
                'attachment'     => $files ? json_encode($files, JSON_UNESCAPED_SLASHES) : '',
                'status'         => 'pending', // Queue status for background processing
            ];
            // Insert record into database
            try {
                $insertId = $this->insert($row);
                // Verify insert was successful
                if (!$insertId) {
                    error_log("Database insert failed for temp mail to: " . implode(',', $recips['to']));
                    return false;
                }

                return $insertId;
            } catch (Zend_Db_Exception $e) {
                // Handle database-specific errors
                error_log("Database error inserting temp mail: " . $e->getMessage());
                return false;
            }
        } catch (Exception $e) {
            // Catch any unexpected errors not handled above
            error_log("Unexpected error in insertTempMailDetails: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }


    public function updateTempMailStatus($id, $status = 'picked-to-process')
    {
        return $this->update(
            ['status' => $status],
            $this->getAdapter()->quoteInto('temp_id = ?', (int)$id)
        );
    }

    public function deleteTempMail($id)
    {
        return $this->delete($this->getAdapter()->quoteInto('temp_id = ?', (int)$id));
    }

    public function fetchEmailFailureInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = array('to_email', 'subject', 'message', 'status', 'failure_type', 'failure_reason', 'updated_at');

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;



        $sLimit = "";
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }


        $sOrder = "";
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = "";
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == "true") {
                    $sOrder .= $aColumns[intval($parameters['iSortCol_' . $i])] . "
				 	" . ($parameters['sSortDir_' . $i]) . ", ";
                }
            }

            $sOrder = substr_replace($sOrder, "", -2);
        }


        $sWhere = "";
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != "") {
            $searchArray = explode(" ", $parameters['sSearch']);
            $sWhereSub = "";
            foreach ($searchArray as $search) {
                if ($sWhereSub == "") {
                    $sWhereSub .= "(";
                } else {
                    $sWhereSub .= " AND (";
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                        $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
                }
                $sWhereSub .= ")";
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == "true" && $parameters['sSearch_' . $i] != '') {
                if ($sWhere == "") {
                    $sWhere .= $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                } else {
                    $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($parameters['sSearch_' . $i]) . "%' ";
                }
            }
        }




        $sQuery = $this->getAdapter()->select()->from(array('a' => $this->_name));

        if (isset($sWhere) && $sWhere != "") {
            $sQuery = $sQuery->where($sWhere);
        }
        if (!empty($sOrder)) {
            $sQuery = $sQuery->order($sOrder);
        }

        if (isset($sLimit) && isset($sOffset)) {
            $sQuery = $sQuery->limit($sLimit, $sOffset);
        }
        $rResult = $this->getAdapter()->fetchAll($sQuery);


        /* Data set length after filtering */
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_COUNT);
        $sQuery = $sQuery->reset(Zend_Db_Select::LIMIT_OFFSET);
        $aResultFilterTotal = $this->getAdapter()->fetchAll($sQuery);
        $iFilteredTotal = count($aResultFilterTotal);

        /* Total data set length */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = array(
            "sEcho" => intval($parameters['sEcho']),
            "iTotalRecords" => $iTotal,
            "iTotalDisplayRecords" => $iFilteredTotal,
            "aaData" => array()
        );

        $general = new Pt_Commons_General();
        foreach ($rResult as $aRow) {
            $row = [];
            $row[] = $aRow['to_email'];
            $row[] = $aRow['subject'];
            $row[] = $aRow['message'];
            $row[] = ucwords($aRow['status']);
            $row[] = ucwords(str_replace("-", " ", $aRow['failure_type']));
            $row[] = $aRow['failure_reason'];
            $row[] = $general->humanReadableDateFormat($aRow['updated_at']);
            // $row[] = '<a href="/admin/sample-not-tested-reasons/edit/53s5k85_8d/' . base64_encode($aRow['ntr_id']) . '" class="btn btn-warning btn-xs" style="margin-right: 2px;"><i class="icon-pencil"></i> Edit</a>';

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
