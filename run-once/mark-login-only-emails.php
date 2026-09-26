<?php

// Stamp the login-only addresses bulk import made up before 7.6.23 as login_only, so
// shipment mails, copy-email lists and queued mail all skip them. Matches the current
// instance host and the old 'ept' fallback host (MiscUtility::isGeneratedEmail), plus the
// direct-participant-login accounts. Safe to re-run: it only touches rows not already stamped.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

use Pt_Commons_MiscUtility as MiscUtility;

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    $hosts = array_values(array_unique([MiscUtility::generatedEmailHost(), 'ept']));

    $targets = [
        ['participant', 'email', 'email_status'],
        ['participant', 'additional_email', 'additional_email_status'],
        ['data_manager', 'primary_email', 'primary_email_status'],
        ['data_manager', 'secondary_email', 'secondary_email_status'],
    ];
    foreach ($targets as [$table, $emailCol, $statusCol]) {
        $rows = $db->update(
            $table,
            [$statusCol => 'login_only'],
            [
                $db->quoteInto("LOWER(SUBSTRING_INDEX(TRIM(`$emailCol`), '@', -1)) IN (?)", $hosts),
                $db->quoteInto("`$emailCol` LIKE ?", '%@%'),
                "`$statusCol` <> 'login_only'",
            ]
        );
        echo "$table.$emailCol: $rows row(s) marked login_only" . PHP_EOL;
    }

    // Direct participant login accounts sign in with <prefix><unique id>, not an address.
    $rows = $db->update(
        'data_manager',
        ['primary_email_status' => 'login_only'],
        ["data_manager_type = 'participant'", "primary_email_status <> 'login_only'"]
    );
    echo "data_manager (direct participant login): $rows row(s) marked login_only" . PHP_EOL;
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'mark-login-only-emails failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
