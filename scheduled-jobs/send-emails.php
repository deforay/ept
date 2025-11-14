<?php

require_once __DIR__ . '/../cli-bootstrap.php';

/* ========= Tunables ========= */
const QUEUE_FETCH_LIMIT    = 100; // fetch up to N rows per run
const RECIPIENTS_PER_EMAIL = 100; // To+Cc+Bcc cap per message
const BATCH_SLEEP_MS       = 150; // tiny delay between batches; set 0 to disable
const LOCK_TTL_SEC         = 600; // lock auto-expires after 10 minutes
const ROW_SLEEP_MS         = 25; // tiny delay between rows; set 0 to disable
/* ============================ */

$LOCK_FILE = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ept_mail_cron.lock';
$lockFp = null;
$haveLock = false;

/**
 * Acquire a short-lived lock (flock) with TTL.
 * - If a stale lock exists (older than TTL), override it.
 * - Ensure we clean up lock on shutdown.
 */
function acquireLock(string $path, int $ttlSec): array
{
    $fp = @fopen($path, 'c+'); // create if not exists
    if (!$fp) {
        throw new RuntimeException("Cannot open lock file: $path");
    }

    clearstatcache(true, $path);
    $mtime = @filemtime($path) ?: 0;
    $age   = time() - $mtime;

    // Try non-blocking exclusive lock
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        // Another process appears to hold it. If stale, force reclaim.
        if ($age > $ttlSec) {
            // Try to steal: truncate and relock
            ftruncate($fp, 0);
            clearstatcache(true, $path);
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                fclose($fp);
                return [false, null];
            }
        } else {
            fclose($fp);
            return [false, null];
        }
    }

    // We now hold the lock. Write pid+timestamp (for observability).
    ftruncate($fp, 0);
    fwrite($fp, json_encode(['pid' => getmypid(), 'ts' => time()]));
    fflush($fp);
    // Touch to update mtime (used for staleness check)
    @touch($path);

    return [true, $fp];
}

/** Release lock safely */
function releaseLock($fp, $path)
{
    if (is_resource($fp)) {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
    // Best-effort cleanup; it's okay if it fails (next run will reuse/overwrite).
    @unlink($path);
}

/** Shutdown cleanup (fatal errors, exceptions, normal exit) */
function registerCleanup(callable $fn)
{
    register_shutdown_function($fn);
    // Catch SIGINT/SIGTERM if pcntl is available
    if (function_exists('pcntl_async_signals')) {
        pcntl_async_signals(true);
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($fn) {
                $fn();
                exit(1);
            });
            pcntl_signal(SIGTERM, function () use ($fn) {
                $fn();
                exit(1);
            });
        }
    }
}

try {
    // Acquire lock
    [$haveLock, $lockFp] = acquireLock($LOCK_FILE, LOCK_TTL_SEC);
    if (!$haveLock) {
        // Someone else is running (and not stale) — exit quietly
        return;
    }

    // Ensure lock is released on all exits
    registerCleanup(function () use ($lockFp, $LOCK_FILE) {
        releaseLock($lockFp, $LOCK_FILE);
    });

    // === App setup ===
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db   = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    $smtpTransportObj = new Zend_Mail_Transport_Smtp(
        $conf->email->host,
        array_merge($conf->email->config->toArray(), ['connection_timeout' => 30])
    );

    // === Pull up to N pending rows this minute ===
    $sQuery = $db->select()->from(['tm' => 'temp_mail'])
        ->where("tm.status = ?", 'pending')
        ->order('tm.temp_id ASC')
        ->limit(QUEUE_FETCH_LIMIT);

    $mailResult = $db->fetchAll($sQuery);

    if (empty($mailResult)) {
        return; // nothing to do
    }

    foreach ($mailResult as $result) {
        $failureReason = null;
        try {
            // Mark in-flight (idempotent-ish; helps visibility and avoids double sends across overlapping runs)
            $claimed = $db->update(
                'temp_mail',
                ['status' => 'picked-to-process', 'updated_at' => new Zend_Db_Expr('NOW()')],
                ['temp_id = ?' => $result['temp_id'], 'status = ?' => 'pending'] // atomic guard
            );
            if ($claimed !== 1) {
                continue; // someone else grabbed it
            }

            // Parse recipients
            $recips = Application_Service_Common::parseRecipients(
                trim($result['to_email'] ?? ''),
                isset($result['cc'])  ? trim($result['cc'])  : null,
                isset($result['bcc']) ? trim($result['bcc']) : null
            );

            if (!empty($recips['invalid'])) {
                error_log("Invalid emails (temp_id={$result['temp_id']}): " . implode(', ', $recips['invalid']));
            }

            if (empty($recips['to']) && empty($recips['cc']) && empty($recips['bcc'])) {
                throw new Exception("No valid recipients for temp_id={$result['temp_id']}");
            }

            // Build batches up to cap. Priority: To -> Cc -> Bcc.
            $buildBatches = static function (array $to, array $cc, array $bcc, int $cap): array {
                $batches = [];
                while (!empty($to) || !empty($cc) || !empty($bcc)) {
                    $remaining = $cap;
                    $takeTo  = array_splice($to, 0, min($remaining, count($to)));
                    $remaining -= count($takeTo);

                    $takeCc  = array_splice($cc, 0, min($remaining, count($cc)));
                    $remaining -= count($takeCc);

                    $takeBcc = array_splice($bcc, 0, min($remaining, count($bcc)));

                    if (empty($takeTo) && empty($takeCc) && empty($takeBcc)) {
                        break; // safety
                    }

                    // Ensure at least one visible To per batch (if not, promote one from Cc or Bcc)
                    if (empty($takeTo)) {
                        if (!empty($takeCc)) {
                            $takeTo[] = array_shift($takeCc);
                        } elseif (!empty($takeBcc)) {
                            $takeTo[] = array_shift($takeBcc);
                        }
                    }

                    $batches[] = ['to' => $takeTo, 'cc' => $takeCc, 'bcc' => $takeBcc];
                }
                return $batches;
            };

            // Start from parsed recipients
            $to  = $recips['to']  ?? [];
            $cc  = $recips['cc']  ?? [];
            $bcc = $recips['bcc'] ?? [];

            // Dedupe and avoid duplicate visibility across fields
            $to  = array_values(array_unique($to));
            $cc  = array_values(array_unique(array_diff($cc, $to)));
            $bcc = array_values(array_unique(array_diff($bcc, $to, $cc)));

            // Now build capped batches
            $batches = $buildBatches($to, $cc, $bcc, RECIPIENTS_PER_EMAIL);

            // Safety
            if (!$batches) {
                throw new Exception("No deliverable batches for temp_id={$result['temp_id']}");
            }

            // Parse attachments once
            $attachments = [];
            if (!empty($result['attachment'])) {
                $decoded = json_decode($result['attachment'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $attachments = $decoded;
                } else {
                    error_log("Attachment JSON invalid for temp_id={$result['temp_id']}: " . json_last_error_msg());
                }
            }

            // Common From
            $fromEmail    = $conf->email->config->username;
            $fromFullName = $conf->email->fromName ?? 'ePT System';

            // Validate reply_to (single address; take first if commas/semicolons present)
            $replyToRaw = isset($result['reply_to']) ? trim((string)$result['reply_to']) : '';
            $replyTo = null;
            if ($replyToRaw !== '') {
                $first = trim(preg_split('/[;,]+/', $replyToRaw)[0] ?? '');
                if ($first !== '' && filter_var($first, FILTER_VALIDATE_EMAIL)) {
                    $replyTo = $first;
                } else {
                    error_log("Invalid reply_to for temp_id={$result['temp_id']}: {$replyToRaw}");
                }
            }
            if ($replyTo === null) {
                $replyTo = $fromEmail;
            }

            $allOk = true;
            $batchIndex = 0;

            foreach ($batches as $batch) {
                $batchIndex++;

                $mail = new Zend_Mail('UTF-8');
                $mail->setHeaderEncoding(Zend_Mime::ENCODING_QUOTEDPRINTABLE);
                $subject = strip_tags(trim((string)$result['subject']));
                $rawHtml  = (string)$result['message'];
                $bodyHtml = function_exists('mb_strimwidth') ? mb_strimwidth($rawHtml, 0, 2_000_000, '') : substr($rawHtml, 0, 2_000_000);
                $bodyText = strip_tags($bodyHtml) ?: '[no content]';


                $mail->setSubject($subject !== '' ? $subject : '(no subject)');
                $mail->setBodyHtml($bodyHtml);
                $mail->setBodyText($bodyText);

                $mail->setFrom($fromEmail, $fromFullName);
                $mail->setReplyTo($replyTo, $fromFullName);

                // Diagnostics (headers visible in logs)
                $mail->addHeader('X-Mail-Batch', (string)$batchIndex);
                $mail->addHeader('X-Temp-Mail-ID', (string)$result['temp_id']);
                $mail->addHeader('X-Batch-To',  (string)count($batch['to']));
                $mail->addHeader('X-Batch-Cc',  (string)count($batch['cc']));
                $mail->addHeader('X-Batch-Bcc', (string)count($batch['bcc']));

                foreach ($batch['to'] as $toId) {
                    $mail->addTo($toId);
                }
                foreach ($batch['cc'] as $ccId) {
                    $mail->addCc($ccId);
                }
                foreach ($batch['bcc'] as $bccId) {
                    $mail->addBcc($bccId);
                }

                // Attachments
                if (!empty($attachments)) {
                    $maxTotal = 22 * 1024 * 1024; // ~22 MB raw (≈ 29 MB base64)
                    $total = 0;
                    foreach ($attachments as $filePath) {
                        if (!file_exists($filePath)) {
                            error_log("Attachment missing (temp_id={$result['temp_id']} batch={$batchIndex}): $filePath");
                            continue;
                        }
                        $size = filesize($filePath);
                        if ($size === false || $size > 15 * 1024 * 1024 || ($total + $size) > $maxTotal) {
                            // skip if single file >15MB or would exceed per-message budget
                            continue;
                        }
                        try {
                            $content = file_get_contents($filePath);
                            $mail->createAttachment(
                                $content,
                                Zend_Mime::TYPE_OCTETSTREAM,
                                Zend_Mime::DISPOSITION_ATTACHMENT,
                                Zend_Mime::ENCODING_BASE64,
                                basename($filePath)
                            );
                            $total += $size;
                            unset($content);
                        } catch (Exception $e) {
                            error_log("Attachment error (temp_id={$result['temp_id']} batch={$batchIndex}): " . $e->getMessage());
                        }
                    }
                }


                try {
                    $mail->send($smtpTransportObj);
                    if (BATCH_SLEEP_MS > 0) {
                        usleep(BATCH_SLEEP_MS * 1000);
                    }
                } catch (Throwable $e) {
                    $allOk = false;
                    $batchError = "Batch {$batchIndex} failed: {$e->getMessage()}";
                    $failureReason = $failureReason ?? $batchError;
                    error_log("Batch send failed (temp_id={$result['temp_id']} batch={$batchIndex}): {$e->getMessage()}");
                    error_log($e->getTraceAsString());
                    // keep trying remaining batches; mark row 'not-sent' afterwards
                }
                unset($mail);
                if (function_exists('gc_collect_cycles')) gc_collect_cycles();
            }

            // Finalize the row
            if ($allOk) {
                $db->delete('temp_mail', $db->quoteInto('temp_id = ?', $result['temp_id']));
            } else {
                Application_Service_Common::markTempMailFailed(
                    $db,
                    (int) $result['temp_id'],
                    $failureReason ?: 'One or more batches failed to send'
                );
            }

            if (ROW_SLEEP_MS > 0) {
                usleep(ROW_SLEEP_MS * 1000);
            }
        } catch (Throwable $e) {
            $failureReason = $failureReason ?? $e->getMessage();
            Application_Service_Common::markTempMailFailed(
                $db,
                (int) $result['temp_id'],
                $failureReason
            );
            error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
            error_log($e->getTraceAsString());
            continue;
        } finally {
            // Refresh lock mtime to keep it from being considered stale during long runs
            @touch($LOCK_FILE);
        }
    }
} catch (Throwable $e) {
    error_log("CRON FATAL: {$e->getMessage()}");
    error_log($e->getTraceAsString());
    // lock is released by registered shutdown handler
}
