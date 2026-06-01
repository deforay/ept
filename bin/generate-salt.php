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

$content = file_get_contents($iniPath);
if ($content === false) {
    fwrite(STDERR, "ERROR: read failed for $iniPath\n");
    exit(2);
}

$pattern = '/^[ \t]*;?[ \t]*security\.salt[ \t]*=[ \t]*([\'"]?)([^\'"\r\n;]*)\1[ \t]*;?.*$/m';
$existing = null;
if (preg_match($pattern, $content, $m)) {
    $existing = trim($m[2]);
}

// Idempotent path: a strong salt is already configured → nothing to do.
if ($existing !== null && $existing !== '' && strlen($existing) >= 32 && !$opts['force']) {
    fwrite(STDOUT, "security.salt is already set (length=" . strlen($existing) . "). No change. Pass --force to rotate.\n");
    exit(0);
}

if (!is_writable($iniPath)) {
    fwrite(STDERR, "ERROR: $iniPath is not writable\n");
    exit(2);
}

$salt = bin2hex(random_bytes(32));

if ($existing !== null) {
    $replacement = "security.salt = '" . $salt . "'";
    $content = preg_replace($pattern, $replacement, $content, 1);
    $action = $opts['force'] ? 'Rotated security.salt' : 'Filled empty/short security.salt';
} else {
    $append = "\nsecurity.salt = '" . $salt . "'\n";
    if (preg_match('/^\[production\][^\[]*/m', $content)) {
        $content = preg_replace('/(\[production\][^\[]*)/m', '$1' . $append, $content, 1);
        $action = 'Inserted new security.salt under [production]';
    } else {
        $content .= $append;
        $action = 'Appended new security.salt to end of file';
    }
}

if (file_put_contents($iniPath, $content) === false) {
    fwrite(STDERR, "ERROR: write failed for $iniPath\n");
    exit(2);
}

fwrite(STDOUT, "$action in $iniPath\n");
exit(0);
