#!/usr/bin/env php
<?php

// bin/process-bounces.php
//
// Reads new messages from the configured IMAP "bounce inbox" (see
// email.bounce.* in application.ini), identifies delivery-failure reports
// (DSNs / MAILER-DAEMON / common bounce subjects), and stamps the failing
// recipient address with email_status='hard_bounce' so future bulk sends
// skip it. Soft (4.x.x) bounces are logged but ignored.
//
// Read-only by default: doesn't mark Seen, doesn't move, doesn't delete.
// UID-based idempotency via system_config.bounce_last_uid means re-runs
// only touch new messages. Set email.bounce.markSeen=yes or
// email.bounce.moveTo=Folder/Path in application.ini if you want cleanup.

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

$cliMode = php_sapi_name() === 'cli';

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . 'Cancelled by user.' . PHP_EOL;
        exit(130);
    });
}

try {
    require_once __DIR__ . '/../cli-bootstrap.php';
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    if (!$cliMode) {
        echo 'This script can only be run from the command line.' . PHP_EOL;
        exit(1);
    }

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    $opts = getopt('', ['max::', 'dry-run', 'quiet', 'reset-state']);
    $maxToProcess = max(1, (int) ($opts['max'] ?? 200));
    $dryRun       = array_key_exists('dry-run', $opts);
    $quiet        = array_key_exists('quiet', $opts);
    $resetState   = array_key_exists('reset-state', $opts);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $bounceConf = isset($conf->email->bounce) ? $conf->email->bounce : null;
    $host = trim((string) ($bounceConf->host ?? ''));
    if ($host === '') {
        // Cron-friendly no-op: feature disabled.
        if (!$quiet) {
            $io->writeln('email.bounce.host is empty — bounce processor disabled.');
        }
        exit(0);
    }

    $port     = (int) ($bounceConf->port ?? 993);
    $user     = (string) ($bounceConf->username ?? '');
    $pass     = (string) ($bounceConf->password ?? '');
    $ssl      = strtolower(trim((string) ($bounceConf->ssl ?? 'ssl')));
    $folder   = (string) ($bounceConf->folder ?? 'INBOX');
    $markSeen = strtolower(trim((string) ($bounceConf->markSeen ?? 'no'))) === 'yes';
    $moveTo   = trim((string) ($bounceConf->moveTo ?? ''));

    if (!$quiet) {
        $io->title('Bounce processor');
        $io->writeln(sprintf(
            ' host=%s:%d  folder=%s  ssl=%s  markSeen=%s  moveTo=%s  max=%d  dry-run=%s',
            $host,
            $port,
            $folder,
            $ssl ?: '(none)',
            $markSeen ? 'yes' : 'no',
            $moveTo !== '' ? $moveTo : '(none)',
            $maxToProcess,
            $dryRun ? 'yes' : 'no'
        ));
    }

    $storageParams = [
        'host'     => $host,
        'port'     => $port,
        'user'     => $user,
        'password' => $pass,
        'folder'   => $folder,
    ];
    if ($ssl === 'ssl' || $ssl === 'tls') {
        $storageParams['ssl'] = strtoupper($ssl);
    }
    $storage = new Zend_Mail_Storage_Imap($storageParams);

    // ---------- State (UID high-water mark) ----------
    if ($resetState) {
        $db->update('system_config', ['value' => '0'], ['config = ?' => 'bounce_last_uid']);
        if (!$quiet) {
            $io->writeln('  state reset: bounce_last_uid -> 0');
        }
    }
    $lastUid = (int) $db->fetchOne(
        'SELECT value FROM system_config WHERE config = ?',
        ['bounce_last_uid']
    );

    // ---------- Find new UIDs ----------
    $msgnoByUid = $storage->getUniqueId(); // [msgno => uid]
    $highestUid = $msgnoByUid ? max($msgnoByUid) : 0;

    // If our stored high-water exceeds anything in the mailbox, the mailbox
    // was probably rebuilt (UIDVALIDITY change) or purged — resync from 0.
    if ($lastUid > $highestUid) {
        if (!$quiet) {
            $io->note(sprintf('Stored UID %d exceeds mailbox max %d — resetting.', $lastUid, $highestUid));
        }
        $lastUid = 0;
    }

    // Build [uid => msgno] sorted ascending by UID, only new ones.
    $newMessages = [];
    foreach ($msgnoByUid as $msgno => $uid) {
        if ($uid > $lastUid) {
            $newMessages[$uid] = $msgno;
        }
    }
    ksort($newMessages);
    $newMessages = array_slice($newMessages, 0, $maxToProcess, true);

    if (!$newMessages) {
        if (!$quiet) {
            $io->writeln('  no new messages.');
        }
        exit(0);
    }

    if (!$quiet) {
        $io->writeln(sprintf('  %d new message(s) to scan (up to UID %d).', count($newMessages), max(array_keys($newMessages))));
    }

    $stats = ['scanned' => 0, 'bounces' => 0, 'hard' => 0, 'soft' => 0, 'stamped_rows' => 0, 'unmatched_addrs' => 0];
    $highestProcessedUid = $lastUid;

    foreach ($newMessages as $uid => $msgno) {
        $stats['scanned']++;
        try {
            $message = $storage->getMessage($msgno);
        } catch (Throwable $e) {
            if (!$quiet) {
                $io->writeln("  uid=$uid: getMessage failed: " . $e->getMessage());
            }
            $highestProcessedUid = max($highestProcessedUid, $uid);
            continue;
        }

        if (!looksLikeBounce($message)) {
            $highestProcessedUid = max($highestProcessedUid, $uid);
            continue;
        }

        $stats['bounces']++;
        $recipients = extractBouncedRecipients($message);
        if (!$recipients && !$quiet) {
            $io->writeln("  uid=$uid: detected bounce but could not extract a recipient — skipped.");
        }

        foreach ($recipients as $r) {
            $isHard = isset($r['status'][0]) && $r['status'][0] === '5';
            if (!$isHard) {
                $stats['soft']++;
                continue;
            }
            $stats['hard']++;

            if ($dryRun) {
                if (!$quiet) {
                    $io->writeln(sprintf('  uid=%d HARD %s — %s', $uid, $r['addr'], $r['reason']));
                }
                continue;
            }

            $stamped = stampHardBounce($db, $r['addr'], $r['reason']);
            $stats['stamped_rows'] += $stamped;
            if ($stamped === 0) {
                $stats['unmatched_addrs']++;
                if (!$quiet) {
                    $io->writeln("  uid=$uid: " . $r['addr'] . ' — no matching participant/DM row');
                }
            } elseif (!$quiet) {
                $io->writeln(sprintf('  uid=%d HARD %s (rows=%d) — %s', $uid, $r['addr'], $stamped, $r['reason']));
            }
        }

        if (!$dryRun) {
            if ($markSeen) {
                try {
                    $storage->setFlags($msgno, [Zend_Mail_Storage::FLAG_SEEN]);
                } catch (Throwable $e) {
                    if (!$quiet) {
                        $io->writeln("  uid=$uid: setFlags failed: " . $e->getMessage());
                    }
                }
            }
            if ($moveTo !== '') {
                try {
                    $storage->moveMessage($msgno, $moveTo);
                } catch (Throwable $e) {
                    if (!$quiet) {
                        $io->writeln("  uid=$uid: moveMessage failed: " . $e->getMessage());
                    }
                }
            }
        }

        $highestProcessedUid = max($highestProcessedUid, $uid);
    }

    if (!$dryRun && $highestProcessedUid > $lastUid) {
        $db->update('system_config', ['value' => (string) $highestProcessedUid], ['config = ?' => 'bounce_last_uid']);
    }

    if (!$quiet) {
        $io->section('Summary');
        $io->writeln(sprintf(
            '  scanned=%d  bounces=%d  hard=%d  soft=%d  stamped_rows=%d  unmatched_addrs=%d',
            $stats['scanned'],
            $stats['bounces'],
            $stats['hard'],
            $stats['soft'],
            $stats['stamped_rows'],
            $stats['unmatched_addrs']
        ));
        $io->writeln('  new bounce_last_uid: ' . ($dryRun ? '(dry-run, not written)' : $highestProcessedUid));
    }

    exit(0);
} catch (Throwable $e) {
    if (class_exists('Pt_Commons_LoggerUtility')) {
        Pt_Commons_LoggerUtility::logError($e->getMessage(), [
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    fwrite(STDERR, 'process-bounces failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Decide whether a message is a delivery-failure report by inspecting headers.
 * Order: RFC 3464 multipart/report (canonical), then sender heuristics, then
 * subject heuristics for legacy non-compliant MTAs.
 */
function looksLikeBounce(Zend_Mail_Message $msg): bool
{
    try {
        $ct = strtolower((string) $msg->getHeader('content-type', 'string'));
        if (strpos($ct, 'multipart/report') !== false && strpos($ct, 'delivery-status') !== false) {
            return true;
        }
    } catch (Throwable $e) {
        // header missing — fall through
    }

    try {
        $from = strtolower((string) $msg->getHeader('from', 'string'));
        if (strpos($from, 'mailer-daemon@') !== false || strpos($from, 'postmaster@') !== false) {
            return true;
        }
    } catch (Throwable $e) {
        // fall through
    }

    try {
        $subject = strtolower((string) $msg->getHeader('subject', 'string'));
        $patterns = [
            'mail delivery failed',
            'mail delivery failure',
            'undelivered mail',
            'undeliverable',
            'delivery status notification',
            'returned mail',
            'failure notice',
            'delivery failure',
            'mail delivery subsystem',
        ];
        foreach ($patterns as $p) {
            if (strpos($subject, $p) !== false) {
                return true;
            }
        }
    } catch (Throwable $e) {
        // no subject
    }

    return false;
}

/**
 * Extract bounced recipients from a DSN-shaped message. Returns a list of
 * ['addr' => string, 'status' => '5.x.x'|'4.x.x'|'', 'reason' => string].
 * Empty array if nothing parseable.
 */
function extractBouncedRecipients(Zend_Mail_Message $msg): array
{
    $found = [];

    // RFC 3464: look for a message/delivery-status part.
    $dsnText = findDeliveryStatusPart($msg);
    if ($dsnText !== null) {
        // Split per-recipient on blank lines; first chunk is per-message fields.
        $chunks = preg_split('/(?:\r?\n){2,}/', trim($dsnText));
        foreach ($chunks as $chunk) {
            $fields = parseDsnFields($chunk);
            if (!isset($fields['final-recipient'])) {
                continue;
            }
            $addr = cleanRecipient($fields['final-recipient']);
            if ($addr === '') {
                continue;
            }
            $found[] = [
                'addr'   => $addr,
                'status' => trim($fields['status'] ?? ''),
                'reason' => substr(trim($fields['diagnostic-code'] ?? ($fields['action'] ?? '')), 0, 500),
            ];
        }
        if ($found) {
            return $found;
        }
    }

    // Fallback: scrape the plain body for a recipient and a 5xx code.
    $body = collectTextBody($msg);
    if ($body !== '') {
        if (preg_match_all('/<([^<>@\s]+@[^<>@\s]+)>/', $body, $m)) {
            $addrs = array_unique($m[1]);
            $hard = preg_match('/\b5\d\d\b/', $body);
            $reason = '';
            if (preg_match('/.*\b5\d\d\b.*/i', $body, $rm)) {
                $reason = substr(trim($rm[0]), 0, 500);
            }
            foreach ($addrs as $a) {
                $found[] = [
                    'addr'   => strtolower($a),
                    'status' => $hard ? '5.0.0' : '',
                    'reason' => $reason,
                ];
            }
        }
    }

    return $found;
}

/**
 * Walk the MIME tree and return the body text of the first
 * Content-Type: message/delivery-status part. Returns null if absent.
 */
function findDeliveryStatusPart(Zend_Mail_Part $part): ?string
{
    if (!$part->isMultipart()) {
        try {
            $ct = strtolower((string) $part->getHeader('content-type', 'string'));
            if (strpos($ct, 'message/delivery-status') !== false) {
                return (string) $part->getContent();
            }
        } catch (Throwable $e) {
            // ignore
        }
        return null;
    }

    $count = $part->countParts();
    for ($i = 1; $i <= $count; $i++) {
        try {
            $sub = $part->getPart($i);
        } catch (Throwable $e) {
            continue;
        }
        $r = findDeliveryStatusPart($sub);
        if ($r !== null) {
            return $r;
        }
    }
    return null;
}

/**
 * Concatenate any text/plain parts of a message — used for the heuristic
 * fallback when no RFC-3464 delivery-status part is present.
 */
function collectTextBody(Zend_Mail_Part $part): string
{
    if (!$part->isMultipart()) {
        try {
            $ct = strtolower((string) $part->getHeader('content-type', 'string'));
            if (strpos($ct, 'text/') === 0 || $ct === '') {
                return (string) $part->getContent();
            }
        } catch (Throwable $e) {
            try {
                return (string) $part->getContent();
            } catch (Throwable $e2) {
                return '';
            }
        }
        return '';
    }
    $buf = '';
    $count = $part->countParts();
    for ($i = 1; $i <= $count; $i++) {
        try {
            $sub = $part->getPart($i);
        } catch (Throwable $e) {
            continue;
        }
        $buf .= collectTextBody($sub) . "\n";
    }
    return $buf;
}

/**
 * Parse RFC 822-ish "Field: value" lines into a lowercase-keyed map.
 * Folded continuation lines (leading whitespace) get joined onto the prior key.
 */
function parseDsnFields(string $text): array
{
    $fields = [];
    $currentKey = null;
    foreach (preg_split('/\r?\n/', $text) as $line) {
        if ($line === '') {
            continue;
        }
        if ($currentKey !== null && ($line[0] === ' ' || $line[0] === "\t")) {
            $fields[$currentKey] .= ' ' . trim($line);
            continue;
        }
        $pos = strpos($line, ':');
        if ($pos === false) {
            continue;
        }
        $key = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));
        $fields[$key] = $value;
        $currentKey = $key;
    }
    return $fields;
}

/**
 * Final-Recipient header value can be "rfc822; user@host" or just "user@host"
 * or sometimes quoted. Return a lowercase plain address or empty string.
 */
function cleanRecipient(string $raw): string
{
    $raw = trim($raw);
    if (strpos($raw, ';') !== false) {
        [$type, $addr] = explode(';', $raw, 2);
        $raw = trim($addr);
    }
    $raw = trim($raw, " \t\"'<>");
    if (filter_var($raw, FILTER_VALIDATE_EMAIL) === false) {
        return '';
    }
    return strtolower($raw);
}

/**
 * Apply hard_bounce stamp to every participant.email / participant.additional_email /
 * data_manager.primary_email / data_manager.secondary_email row whose value
 * equals the given address (case-insensitive). Returns number of rows touched.
 */
function stampHardBounce(Zend_Db_Adapter_Abstract $db, string $addr, string $reason): int
{
    $reason = substr($reason, 0, 500);
    $now = new Zend_Db_Expr('NOW()');
    $touched = 0;

    $touched += $db->update(
        'participant',
        ['email_status' => 'hard_bounce', 'email_status_checked_at' => $now, 'last_bounce_at' => $now, 'last_bounce_reason' => $reason],
        ['LOWER(email) = ?' => $addr]
    );
    $touched += $db->update(
        'participant',
        ['additional_email_status' => 'hard_bounce', 'email_status_checked_at' => $now, 'last_bounce_at' => $now, 'last_bounce_reason' => $reason],
        ['LOWER(additional_email) = ?' => $addr]
    );
    $touched += $db->update(
        'data_manager',
        ['primary_email_status' => 'hard_bounce', 'email_status_checked_at' => $now, 'last_bounce_at' => $now, 'last_bounce_reason' => $reason],
        ['LOWER(primary_email) = ?' => $addr]
    );
    $touched += $db->update(
        'data_manager',
        ['secondary_email_status' => 'hard_bounce', 'email_status_checked_at' => $now, 'last_bounce_at' => $now, 'last_bounce_reason' => $reason],
        ['LOWER(secondary_email) = ?' => $addr]
    );

    return $touched;
}
