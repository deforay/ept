<?php

/**
 * Idempotent setup for application.ini's `security.salt`.
 *
 * Usage:
 *   php bin/generate-salt.php             # idempotent: no-op if a strong salt is already set,
 *                                         #             otherwise generate one and write it.
 *   php bin/generate-salt.php --print     # just print a fresh salt to stdout (never touch the file)
 *   php bin/generate-salt.php --force     # overwrite even an existing strong salt
 *
 * The salt is a 64-character lowercase hex string (32 random bytes → 256 bits of entropy).
 * It is the keying material for HMAC + AES-GCM in Pt_Commons_SignedDownload and anywhere
 * else security.salt is consumed. Treat it like a password.
 *
 * Why idempotent by default: rotating the salt invalidates every outstanding signed download
 * URL. Running this on an already-configured install should be a safe no-op so it can be
 * wired into install / upgrade scripts without risk.
 */

declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
$opts = [
    'print' => in_array('--print', $argv, true),
    'force' => in_array('--force', $argv, true),
    'help'  => in_array('--help', $argv, true) || in_array('-h', $argv, true),
];

if ($opts['help']) {
    // Strip leading <?php and the docblock comment markers for a readable --help.
    $head = file_get_contents(__FILE__, false, null, 0, 1600);
    fwrite(STDOUT, $head . "\n");
    exit(0);
}

if ($opts['print']) {
    fwrite(STDOUT, bin2hex(random_bytes(32)) . "\n");
    exit(0);
}

$iniPath = __DIR__ . '/../application/configs/application.ini';
if (!is_file($iniPath)) {
    fwrite(STDERR, "ERROR: application.ini not found at $iniPath\n");
    exit(2);
}

// Detect an existing strong salt by PARSING the file with the same engine the
// app uses -- not a hand-rolled regex. Zend_Config_Ini understands section
// inheritance, `key[]` array syntax and APPLICATION_PATH constants, so it can't
// be fooled the way string matching can. Loaded standalone via the Composer
// autoloader (psr-0 maps Zend_ -> the zf1-future library).
$existing = null;
$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
    try {
        $cfg = new Zend_Config_Ini($iniPath, 'production');
        $existing = isset($cfg->security->salt) ? trim((string) $cfg->security->salt) : null;
    } catch (Throwable $e) {
        // Unparseable (e.g. a previously corrupted file). Leave $existing null so
        // a salt gets (re)written below; the targeted writer can't damage the file.
        $existing = null;
    }
}

// Idempotent path: a strong salt is already configured → nothing to do.
if ($existing !== null && $existing !== '' && strlen($existing) >= 32 && !$opts['force']) {
    fwrite(STDOUT, 'security.salt is already set (length=' . strlen($existing) . "). No change. Pass --force to rotate.\n");
    exit(0);
}

if (!is_writable($iniPath)) {
    fwrite(STDERR, "ERROR: $iniPath is not writable\n");
    exit(2);
}

$content = file_get_contents($iniPath);
if ($content === false) {
    fwrite(STDERR, "ERROR: read failed for $iniPath\n");
    exit(2);
}

$salt = bin2hex(random_bytes(32));
$saltLine = "security.salt = '" . $salt . "'";

// Writing is a TARGETED text edit so comments and APPLICATION_PATH constants
// survive untouched. preg_replace_callback (not preg_replace) so a '$' or '\'
// can never be interpreted in the replacement.
//
// Crucially we anchor an insert on the [production] *header line*, never the
// section body: ZF array keys like `resources.view[] =` contain '[', and the
// old "insert before the next [" logic split that line in two and corrupted the
// file (orphaned `[] =` -> "syntax error, unexpected '='").
$saltLinePattern = '/^[ \t]*;?[ \t]*security\.salt[ \t]*=.*$/m';
$headerPattern   = '/^\[production\][^\r\n]*\R/m';

if (preg_match($saltLinePattern, $content)) {
    // Replace an existing (possibly empty/short/commented) salt line in place.
    $content = preg_replace_callback($saltLinePattern, static fn () => $saltLine, $content, 1);
    $action  = $opts['force'] ? 'Rotated security.salt' : 'Filled empty/short security.salt';
} elseif (preg_match($headerPattern, $content)) {
    $content = preg_replace_callback(
        $headerPattern,
        static fn (array $m) => $m[0] . $saltLine . "\n",
        $content,
        1
    );
    $action = 'Inserted new security.salt under [production]';
} else {
    $content = rtrim($content, "\r\n") . "\n" . $saltLine . "\n";
    $action  = 'Appended new security.salt to end of file';
}

if (file_put_contents($iniPath, $content) === false) {
    fwrite(STDERR, "ERROR: write failed for $iniPath\n");
    exit(2);
}

fwrite(STDOUT, "$action in $iniPath\n");
exit(0);
