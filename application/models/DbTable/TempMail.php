<?php

class Application_Model_DbTable_TempMail extends Zend_Db_Table_Abstract
{
    protected $_name = 'temp_mail';
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
            if (trim((string) $message) === '') {
                Pt_Commons_LoggerUtility::logWarning('TempMail insert rejected: empty message body');
                return false;
            }

            // Load application configuration
            try {
                $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
            } catch (Zend_Config_Exception $e) {
                Pt_Commons_LoggerUtility::logError('Failed to load application configuration: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw new Exception('Configuration file could not be loaded');
            }
            // Load attachment size limits from configuration with fallback defaults
            // email.limits.perAttachmentMb = 15 (maximum size per individual attachment)
            // email.limits.totalAttachmentsMb = 22 (maximum cumulative size for all attachments)
            $perAttachMb = (int) ($conf->email->limits->perAttachmentMb ?? 15);
            $totalAttachMb = (int) ($conf->email->limits->totalAttachmentsMb ?? 22);
            $PER_BYTES = $perAttachMb * 1024 * 1024;
            $TOTAL_BYTES = $totalAttachMb * 1024 * 1024;

            // Parse and validate recipient email addresses
            try {
                $recips = Application_Service_Common::parseRecipients(
                    (string) $to,
                    $cc !== null ? (string) $cc : null,
                    $bcc !== null ? (string) $bcc : null
                );
            } catch (Throwable $e) {
                Pt_Commons_LoggerUtility::logError('Failed to parse recipients: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return false;
            }
            // Ensure at least one valid TO recipient exists
            if (empty($recips['to'])) {
                if (!empty($recips['invalid'])) {
                    Pt_Commons_LoggerUtility::logWarning('TempMail insert rejected: no valid TO. Invalid: ' . implode(', ', $recips['invalid']));
                } else {
                    Pt_Commons_LoggerUtility::logWarning('TempMail insert rejected: no TO recipients provided');
                }
                return false;
            }

            // Set and validate sender email address
            // Falls back to configured default email if not provided or invalid
            try {
                $fromMail = (string) ($fromMail ?: $conf->email->config->username);
                $fromMail = Application_Service_Common::validateEmail($fromMail) ?: $conf->email->config->username;
                $fromName = $fromName ?: 'ePT Support';
            } catch (Throwable $e) {
                Pt_Commons_LoggerUtility::logWarning('Failed to set FROM address: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                // Use configuration default as ultimate fallback
                $fromMail = $conf->email->config->username;
                $fromName = 'ePT Support';
            }

            // Set and validate reply-to address
            // Extracts first email from comma/semicolon-separated list
            try {
                $replyToRaw = (string) ($replyTo ?? $fromMail);
                $replyToFirst = trim(preg_split('/[;,]+/', $replyToRaw)[0] ?? '');
                $replyToValid = $replyToFirst && Application_Service_Common::validateEmail($replyToFirst)
                    ? $replyToFirst
                    : $fromMail;
            } catch (Throwable $e) {
                Pt_Commons_LoggerUtility::logWarning('Failed to set REPLY-TO address: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
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
                            Pt_Commons_LoggerUtility::logWarning('Attachment skipped (not found): ' . (string) $path);
                            continue;
                        }

                        // Get file size with error suppression
                        $size = @filesize($path);
                        if ($size === false) {
                            Pt_Commons_LoggerUtility::logWarning('Attachment skipped (size unreadable): ' . (string) $path);
                            continue;
                        }

                        // Check per-file size limit
                        if ($size > $PER_BYTES) {
                            Pt_Commons_LoggerUtility::logWarning("Attachment skipped (per-file limit {$perAttachMb}MB): " . basename($path));
                            continue;
                        }

                        // Check cumulative size limit
                        if ($total + $size > $TOTAL_BYTES) {
                            Pt_Commons_LoggerUtility::logWarning("Attachment skipped (total limit {$totalAttachMb}MB would be exceeded): " . basename($path));
                            continue;
                        }

                        // File passed all validations - add to list
                        $files[] = $path;
                        $total += $size;
                    } catch (Throwable $e) {
                        Pt_Commons_LoggerUtility::logWarning("Error processing attachment {$path}: " . $e->getMessage(), [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        continue;
                    }
                }
            }

            // Build database row with all validated email data
            $row = [
                'from_mail' => $fromMail,
                'from_full_name' => $fromName,
                'reply_to' => $replyToValid,
                'to_email' => implode(',', $recips['to']),
                'cc' => !empty($recips['cc']) ? implode(',', $recips['cc']) : '',
                'bcc' => !empty($recips['bcc']) ? implode(',', $recips['bcc']) : '',
                'subject' => (string) $subject,
                'message' => (string) $message,
                'attachment' => $files ? json_encode($files, JSON_UNESCAPED_SLASHES) : '',
                'status' => 'pending', // Queue status for background processing
            ];
            // Insert record into database
            try {
                $insertId = $this->insert($row);
                // Verify insert was successful
                if (!$insertId) {
                    Pt_Commons_LoggerUtility::logError('Database insert failed for temp mail to: ' . implode(',', $recips['to']));
                    return false;
                }

                return $insertId;
            } catch (Zend_Db_Exception $e) {
                // Handle database-specific errors
                Pt_Commons_LoggerUtility::logError('Database error inserting temp mail: ' . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return false;
            }
        } catch (Throwable $e) {
            // Catch any unexpected errors not handled above
            Pt_Commons_LoggerUtility::logError('Unexpected error in insertTempMailDetails: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function updateTempMailStatus($id, $status = 'picked-to-process')
    {
        return $this->update(
            ['status' => $status],
            $this->getAdapter()->quoteInto('temp_id = ?', (int) $id)
        );
    }

    public function deleteTempMail($id)
    {
        return $this->delete($this->getAdapter()->quoteInto('temp_id = ?', (int) $id));
    }

    /**
     * Retroactively reconstruct admin-triggered password resets for a given
     * primary email by looking up the credentials emails we queued from
     * Application_Service_DataManagers. Only catches resets where the admin
     * chose "Reset & Email"; "copy password" / "copy email content" resets
     * left no trace before audit_log logging was added.
     */
    public function getCredentialMailsForEmail($email, $limit = 5)
    {
        $email = trim((string) $email);
        if ($email === '') {
            return [];
        }
        $limit = max(1, (int) $limit);
        $select = $this->select()
            ->from($this->_name, ['queued_on', 'created_at', 'sent_at', 'cc', 'bcc', 'status'])
            ->where('to_email = ?', $email)
            ->where('subject = ?', 'Your ePT Login Credentials')
            ->order('queued_on DESC')
            ->limit($limit);
        $rows = $this->fetchAll($select)->toArray();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'when' => $r['queued_on'] ?? $r['created_at'],
                'actorName' => '',
                'actorRole' => '',
                'actorEmail' => '',
                'source' => 'temp_mail',
                'sentStatus' => $r['status'] ?? '',
            ];
        }
        return $out;
    }

    public function fetchEmailFailureInGrid($parameters)
    {

        /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
         */

        $aColumns = ['to_email', 'subject', 'message', 'status', 'failure_type', 'failure_reason', 'updated_at'];

        /* Status scope — the page is "Failed Emails", so without an explicit
         * filter only delivery failures are shown ('skipped' rows carry
         * failure types like dev_trap_block / invalid_dsn) */
        $validStatuses = ['failed', 'skipped', 'pending', 'picked-to-process', 'sent'];
        $statusFilter = trim((string) ($parameters['statusFilter'] ?? ''));
        if ($statusFilter === 'all') {
            $statusWhere = '';
        } elseif (in_array($statusFilter, $validStatuses, true)) {
            $statusWhere = $this->getAdapter()->quoteInto('status = ?', $statusFilter);
        } else {
            $statusWhere = "status IN ('failed', 'skipped')";
        }

        /* Indexed column (used for fast and accurate table cardinality) */
        $sIndexColumn = $this->_primary;

        $sLimit = '';
        if (isset($parameters['iDisplayStart']) && $parameters['iDisplayLength'] != '-1') {
            $sOffset = $parameters['iDisplayStart'];
            $sLimit = $parameters['iDisplayLength'];
        }

        $sOrder = '';
        if (isset($parameters['iSortCol_0'])) {
            $sOrder = '';
            for ($i = 0; $i < intval($parameters['iSortingCols']); $i++) {
                if ($parameters['bSortable_' . intval($parameters['iSortCol_' . $i])] == 'true') {
                    $colIdx = intval($parameters['iSortCol_' . $i]);
                    if (!isset($aColumns[$colIdx])) {
                        continue;
                    }
                    $sOrder .= $aColumns[$colIdx] . '
				 	' . Pt_Commons_General::sanitizeSortDirection($parameters['sSortDir_' . $i]) . ', ';
                }
            }

            $sOrder = substr_replace($sOrder, '', -2);
        }

        $sWhere = '';
        if (isset($parameters['sSearch']) && $parameters['sSearch'] != '') {
            $searchArray = explode(' ', $parameters['sSearch']);
            $sWhereSub = '';
            foreach ($searchArray as $search) {
                $quoted = $this->getAdapter()->quote('%' . $search . '%');
                if ($sWhereSub == '') {
                    $sWhereSub .= '(';
                } else {
                    $sWhereSub .= ' AND (';
                }
                $colSize = count($aColumns);

                for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . $quoted . ' OR ';
                    } else {
                        $sWhereSub .= $aColumns[$i] . ' LIKE ' . $quoted . ' ';
                    }
                }
                $sWhereSub .= ')';
            }
            $sWhere .= $sWhereSub;
        }

        /* Individual column filtering */
        for ($i = 0; $i < count($aColumns); $i++) {
            if (isset($parameters['bSearchable_' . $i]) && $parameters['bSearchable_' . $i] == 'true' && $parameters['sSearch_' . $i] != '') {
                $quoted = $this->getAdapter()->quote('%' . $parameters['sSearch_' . $i] . '%');
                if ($sWhere == '') {
                    $sWhere .= $aColumns[$i] . ' LIKE ' . $quoted . ' ';
                } else {
                    $sWhere .= ' AND ' . $aColumns[$i] . ' LIKE ' . $quoted . ' ';
                }
            }
        }

        $sQuery = $this->getAdapter()->select()->from(['a' => $this->_name]);

        if ($statusWhere != '') {
            $sQuery = $sQuery->where($statusWhere);
        }
        if (isset($sWhere) && $sWhere != '') {
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

        /* Total data set length (within the current status scope) */
        $sQuery = $this->getAdapter()->select()->from($this->_name, new Zend_Db_Expr("COUNT('" . $sIndexColumn . "')"));
        if ($statusWhere != '') {
            $sQuery = $sQuery->where($statusWhere);
        }
        $aResultTotal = $this->getAdapter()->fetchCol($sQuery);
        $iTotal = $aResultTotal[0];

        /*
         * Output
         */
        $output = [
            'sEcho' => intval($parameters['sEcho']),
            'iTotalRecords' => $iTotal,
            'iTotalDisplayRecords' => $iFilteredTotal,
            'aaData' => [],
        ];

        foreach ($rResult as $aRow) {
            /* Full HTML bodies wreck the grid layout — show a plain-text
             * snippet in the cell and hand the full body to a modal viewer */
            $plainMessage = trim(preg_replace('/\s+/', ' ', strip_tags((string) $aRow['message'])));
            $snippet = mb_substr($plainMessage, 0, 160);
            if (mb_strlen($plainMessage) > 160) {
                $snippet .= '…';
            }
            $messageCell = '<div class="mail-snippet">' . htmlspecialchars($snippet, ENT_QUOTES) . '</div>'
                . '<button type="button" class="btn btn-default btn-xs view-mail" data-message="'
                . htmlspecialchars((string) $aRow['message'], ENT_QUOTES) . '">View</button>';

            $row = [];
            $row[] = htmlspecialchars((string) $aRow['to_email'], ENT_QUOTES);
            $row[] = htmlspecialchars((string) $aRow['subject'], ENT_QUOTES);
            $row[] = $messageCell;
            $row[] = ucwords((string) $aRow['status']);
            $row[] = ucwords(str_replace(['-', '_'], ' ', (string) $aRow['failure_type']));
            $row[] = htmlspecialchars((string) $aRow['failure_reason'], ENT_QUOTES);
            $row[] = Pt_Commons_DateUtility::humanReadableDateFormat($aRow['updated_at'], true);

            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }
}
