<?php

// Mail settings used to live in two places: the queue (send-emails.php) read the SMTP
// login from global_config.mail while direct sends read application.ini, so a password
// changed in one place broke half the mail. application.ini is now the only source.
// This moves what global_config.mail holds into application.ini, then empties that row
// so database dumps no longer carry the SMTP password.
//
// - Sender fields (from name/email, cc, bcc) were edited on the Global Config page, so
//   the database value wins over application.ini defaults.
// - The SMTP login (host, port, encryption, auth, username, password) is taken as a set.
//   When application.ini and the database disagree, both are tried against the mail
//   server and the one it accepts is kept (application.ini first).
// - With email.devTrapDsn set (a dev box, usually holding a copy of a production
//   database) the database login is never copied; the row is still emptied.
//
// Exits non-zero, leaving the database untouched, if application.ini cannot be
// written, so the next upgrade retries. --dry-run prints the plan without writing.

ini_set('memory_limit', '-1');
require_once __DIR__ . '/../cli-bootstrap.php';

use Application_Service_Common as Common;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$transportFields = ['host', 'port', 'ssl', 'auth', 'username', 'password'];
$senderFields = ['fromName', 'fromEmail', 'cc', 'bcc'];
$iniKeys = [
    'host' => 'email.host', 'port' => 'email.config.port', 'ssl' => 'email.config.ssl',
    'auth' => 'email.config.auth', 'username' => 'email.config.username', 'password' => 'email.config.password',
    'fromName' => 'email.fromName', 'fromEmail' => 'email.fromEmail', 'cc' => 'email.cc', 'bcc' => 'email.bcc',
];

$canLogin = function (array $s): bool {
    if (trim((string) ($s['host'] ?? '')) === '') {
        return false;
    }
    try {
        $transport = Transport::fromDsn(Common::smtpDsn($s));
        if ($transport instanceof SmtpTransport) {
            $transport->start();
            $transport->stop();
        }
        return true;
    } catch (Throwable) {
        return false;
    }
};

try {
    $db = Zend_Db_Table_Abstract::getDefaultAdapter();
    $raw = $db->fetchOne("SELECT `value` FROM `global_config` WHERE `name` = 'mail'");
    $dbMail = json_decode((string) $raw, true);
    if (!is_array($dbMail) || $dbMail === []) {
        echo "global_config.mail is empty; nothing to move." . PHP_EOL;
        exit(0);
    }
    $dbMail = array_map(fn($v) => is_scalar($v) ? trim((string) $v) : '', $dbMail);

    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $devTrap = trim((string) ($conf->email->devTrapDsn ?? '')) !== '';
    $ini = Common::getMailSettings();
    // getMailSettings() mirrors the username into fromEmail; compare against what is stored.
    $ini['fromEmail'] = trim((string) ($conf->email->fromEmail ?? ''));

    $final = $ini;
    foreach ($senderFields as $field) {
        if (($dbMail[$field] ?? '') !== '') {
            $final[$field] = $dbMail[$field];
        }
    }

    $iniLogin = array_intersect_key($ini, array_flip($transportFields));
    $dbLogin = array_merge(array_fill_keys($transportFields, ''), array_intersect_key($dbMail, array_flip($transportFields)));
    $iniHasLogin = $iniLogin['host'] !== '' && $iniLogin['username'] !== '';
    $dbHasLogin = $dbLogin['host'] !== '' && $dbLogin['username'] !== '';
    if ($devTrap) {
        echo "email.devTrapDsn is set: the database SMTP login is not copied." . PHP_EOL;
    } elseif ($dbHasLogin && $dbLogin != $iniLogin) {
        if (!$iniHasLogin) {
            $choice = 'database';
        } elseif ($canLogin($iniLogin)) {
            $choice = 'application.ini';
        } elseif ($canLogin($dbLogin)) {
            $choice = 'database';
        } else {
            $choice = 'application.ini';
            echo "WARNING: the mail server accepted neither SMTP login; keeping application.ini's. Fix it on Global Config > Email Settings." . PHP_EOL;
        }
        echo "SMTP login differs between application.ini and the database; keeping the {$choice} one." . PHP_EOL;
        if ($choice === 'database') {
            $final = array_merge($final, $dbLogin);
        }
    }

    $values = [];
    foreach ($iniKeys as $field => $key) {
        $value = (string) ($final[$field] ?? '');
        if (strpbrk($value, "\"\\\r\n") !== false || strpos($value, '${') !== false) {
            fwrite(STDERR, "The {$field} value contains characters application.ini cannot hold; set it on Global Config > Email Settings." . PHP_EOL);
            $value = (string) ($ini[$field] ?? '');
        }
        if ($value !== (string) ($ini[$field] ?? '')) {
            $values[$key] = $value;
        }
    }

    foreach ($values as $key => $value) {
        echo "  {$key} <- " . ($key === 'email.config.password' ? '(password)' : $value) . PHP_EOL;
    }
    if ($dryRun) {
        echo "Dry run: application.ini and global_config.mail left unchanged." . PHP_EOL;
        exit(0);
    }

    if ($values !== [] && !Common::writeProductionIniValues($values)) {
        fwrite(STDERR, 'Could not write ' . APPLICATION_PATH . '/configs/application.ini; make it writable by www-data and re-run. global_config.mail was left in place.' . PHP_EOL);
        exit(1);
    }

    // Re-read the file before dropping the database copy.
    $check = Common::getMailSettings();
    foreach ($values as $key => $value) {
        $field = array_search($key, $iniKeys, true);
        if ($field !== 'fromEmail' && ($check[$field] ?? null) !== $value) {
            fwrite(STDERR, "application.ini did not keep {$key}; global_config.mail was left in place." . PHP_EOL);
            exit(1);
        }
    }

    $db->update('global_config', ['value' => null], ["`name` = 'mail'"]);
    echo 'Moved ' . count($values) . ' mail setting(s) to application.ini and emptied global_config.mail.' . PHP_EOL;
} catch (Throwable $e) {
    Pt_Commons_LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'move-mail-settings-to-ini failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
