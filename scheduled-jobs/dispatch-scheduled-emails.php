<?php
// dispatch-scheduled-emails.php
//
// Queues the Email Participants emails whose scheduled time has come. Each one
// is claimed by moving it from 'scheduled' to 'dispatching', so a second run
// can never queue it again. Its recipients are then worked out and one
// temp_mail row is queued per recipient, which send-emails.php delivers as it
// does any other mail. A paused or cancelled email is never 'scheduled', so it
// is skipped. Runs every minute from ScheduledTasks.php.
//
// An email left in 'dispatching' for over 30 minutes was cut off partway, for
// example by a crash. If none of its messages made it into temp_mail it goes
// back to 'scheduled' and is retried on this run. If some did, it is marked
// 'queued' with a note rather than retried, since a retry would send those
// recipients a second copy.

require_once __DIR__ . '/../cli-bootstrap.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

try {
    $emailParticipantDb = new Application_Model_DbTable_EmailParticipants();

    $stuck = $db->fetchAll(
        "SELECT ep.id, (SELECT COUNT(*) FROM temp_mail tm WHERE tm.email_participant_id = ep.id) AS n
           FROM email_participants ep
          WHERE ep.status = 'dispatching'
            AND COALESCE(ep.updated_at, ep.date_initiated) < NOW() - INTERVAL 30 MINUTE"
    );
    foreach ($stuck as $row) {
        $n = (int) $row['n'];
        if ($n === 0) {
            $emailParticipantDb->changeStatus((int) $row['id'], ['dispatching'], 'scheduled');
        } else {
            $emailParticipantDb->changeStatus((int) $row['id'], ['dispatching'], 'queued', null, [
                'dispatched_at'  => new Zend_Db_Expr('NOW()'),
                'queued_count'   => $n,
                'failure_reason' => "Queuing stopped partway. {$n} messages were queued; the remaining recipients were not.",
            ]);
        }
        Pt_Commons_LoggerUtility::logWarning("dispatch-scheduled-emails: email {$row['id']} was stuck dispatching ({$n} messages queued)");
    }

    // scheduled_at is wall-clock time in the application timezone, which PHP runs in
    $dueIds = $emailParticipantDb->fetchDueIds(date('Y-m-d H:i:s'));
    if (empty($dueIds)) {
        return;
    }

    $participantService = new Application_Service_Participants();
    $auditDb = new Application_Model_DbTable_AuditLog();
    foreach ($dueIds as $id) {
        $claimed = $emailParticipantDb->changeStatus($id, ['scheduled'], 'dispatching', null, [
            'updated_at' => new Zend_Db_Expr('NOW()'),
        ]);
        if (!$claimed) {
            continue; // paused, cancelled or claimed by another run since the fetch
        }
        $result = $participantService->queueParticipantEmail($id);
        $email = $emailParticipantDb->fetchEmail($id);
        $auditDb->addNewAuditLog(
            "Queued scheduled email to participants — \"{$email['subject']}\" ({$result['queued']} messages)",
            'config',
            ['email' => 'system', 'role' => 'system']
        );
    }
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError('dispatch-scheduled-emails: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
