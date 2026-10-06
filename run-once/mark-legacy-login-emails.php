<?php

// Older imports made up login addresses on hosts that no longer exist, such as
// 3142_pt@vlsmartconnect.com or eid1031_pt@vlsmartconnect.com. The bounce checks stamped
// them invalid_domain, so the admin lists flag them "Undeliverable" next to the real
// addresses that need fixing. They are login-only accounts, so stamp them login_only.
//
// Only invalid_domain rows change, and those are never mailed either way, so a wrong
// match changes the label and nothing else. The local part has to look made up: digits
// then an underscore (3142_pt, 5308_vl, 5560_labname), or one of the tags imports
// appended (_pt, _ept, _vl, _eid). Real addresses on dead domains, such as
// someshni.nair@... or john_doe@..., keep invalid_domain. Safe to re-run.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    $madeUpLocalPart = '^([0-9]+_[A-Za-z0-9]+|[A-Za-z0-9-]+_(e?pt|vl|eid))$';

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
                "`$statusCol` = 'invalid_domain'",
                $db->quoteInto("SUBSTRING_INDEX(TRIM(`$emailCol`), '@', 1) REGEXP ?", $madeUpLocalPart),
            ]
        );
        echo "$table.$emailCol: $rows row(s) marked login_only" . PHP_EOL;
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'mark-legacy-login-emails failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
