<?php

/**
 * Generate a strong random value for application.ini's `security.salt`.
 *
 * Usage:
 *   php bin/generate-salt.php             # print a fresh salt to stdout
 *   php bin/generate-salt.php --write     # generate AND write into application.ini
 *   php bin/generate-salt.php --force     # with --write, replace even if a salt is already set
 *
 * The salt is a 64-character lowercase hex string (32 random bytes → 256 bits of entropy).
 * It is used as the keying material for HMAC + AES-GCM in Pt_Commons_SignedDownload and
 * anywhere else security.salt is consumed. Treat it like a password.
 */

declare(strict_types=1);

$argv = $_SERVER['argv'] ?? [];
$opts = [
    'write' => in_array('--write', $argv, true),
    'force' => in_array('--force', $argv, true),
    'help'  => in_array('--help', $argv, true) || in_array('-h', $argv, true),
];

if ($opts['help']) {
    fwrite(STDOUT, file_get_contents(__FILE__, false, null, 0, 1400) . "\n");
    exit(0);
}

$salt = bin2hex(random_bytes(32));

if (!$opts['write']) {
    fwrite(STDOUT, $salt . "\n");
    exit(0);
}

$iniPath = __DIR__ . '/../application/configs/application.ini';
if (!is_file($iniPath) || !is_writable($iniPath)) {
    fwrite(STDERR, "ERROR: cannot write to $iniPath\n");
    exit(2);
}

$content = file_get_contents($iniPath);
if ($content === false) {
    fwrite(STDERR, "ERROR: read failed for $iniPath\n");
    exit(2);
}

// Match an existing security.salt line (commented or not) and inspect its current value.
$pattern = '/^[ \t]*;?[ \t]*security\.salt[ \t]*=[ \t]*([\'"]?)([^\'"\r\n;]*)\1[ \t]*;?.*$/m';
if (preg_match($pattern, $content, $m)) {
    $existing = trim($m[2]);
    if ($existing !== '' && strlen($existing) >= 32 && !$opts['force']) {
        fwrite(STDOUT, "security.salt is already set (length=" . strlen($existing) . "). Pass --force to overwrite.\n");
        fwrite(STDOUT, "Current line: " . trim($m[0]) . "\n");
        exit(0);
    }
    $replacement = "security.salt = '" . $salt . "'";
    $content = preg_replace($pattern, $replacement, $content, 1);
    fwrite(STDOUT, "Replaced existing security.salt line.\n");
} else {
    // No line at all — append to [production] so it inherits everywhere.
    $append = "\nsecurity.salt = '" . $salt . "'\n";
    if (preg_match('/^\[production\][^\[]*/m', $content)) {
        $content = preg_replace('/(\[production\][^\[]*)/m', '$1' . $append, $content, 1);
        fwrite(STDOUT, "Inserted new security.salt under [production].\n");
    } else {
        $content .= $append;
        fwrite(STDOUT, "Appended new security.salt to end of file.\n");
    }
}

if (file_put_contents($iniPath, $content) === false) {
    fwrite(STDERR, "ERROR: write failed for $iniPath\n");
    exit(2);
}

fwrite(STDOUT, "Wrote new security.salt (64 hex chars) to $iniPath\n");
exit(0);
