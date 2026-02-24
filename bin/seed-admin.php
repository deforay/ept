#!/usr/bin/env php
<?php

// Only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . '/../cli-bootstrap.php';

ini_set('memory_limit', '-1');
set_time_limit(0);

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);

echo "===========================================\n";
echo "  ePT Initial Admin Setup\n";
echo "===========================================\n\n";
echo "No admin accounts found. Let's create the first admin user.\n\n";

// Read input helper
function readInput(string $prompt, bool $required = true): string
{
    $tty = fopen('/dev/tty', 'r');
    if (!$tty) {
        $tty = STDIN;
    }

    do {
        echo $prompt;
        $value = trim(fgets($tty));
        if ($required && $value === '') {
            echo "This field is required. Please try again.\n";
        }
    } while ($required && $value === '');

    if ($tty !== STDIN) {
        fclose($tty);
    }

    return $value;
}

function readPassword(string $prompt): string
{
    if (stripos(PHP_OS, 'WIN') === false) {
        // Unix: disable echo via stty for hidden password input
        system('stty -echo 2>/dev/null');
        echo $prompt;
        $tty = fopen('/dev/tty', 'r');
        $password = trim(fgets($tty));
        fclose($tty);
        system('stty echo 2>/dev/null');
        echo "\n";
        return $password;
    }
    return readInput($prompt);
}

// Collect admin details
$firstName = readInput("First name: ");
$lastName = readInput("Last name: ");

// Email with validation
do {
    $email = readInput("Email (used for login): ");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format. Please try again.\n";
        $email = '';
    }
} while ($email === '');

// Password with confirmation
do {
    $password = readPassword("Password (min 6 characters): ");
    if (strlen($password) < 6) {
        echo "Password must be at least 6 characters. Please try again.\n";
        continue;
    }
    $confirmPassword = readPassword("Confirm password: ");
    if ($password !== $confirmPassword) {
        echo "Passwords do not match. Please try again.\n";
        $password = '';
    }
} while ($password === '');

// All privileges
$allPrivileges = implode(',', [
    'config-ept',
    'manage-participants',
    'manage-shipments',
    'analyze-generate-reports',
    'edit-participant-response',
    'access-reports',
    'delete-participants',
    'replace-finalized-summary-report',
]);

// Hash password (bcrypt cost 14, matching Application_Service_Common::passwordHash)
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 14]);

$now = date('Y-m-d H:i:s');

try {
    $db->insert('system_admin', [
        'first_name'           => $firstName,
        'last_name'            => $lastName,
        'primary_email'        => $email,
        'password'             => $hashedPassword,
        'status'               => 'active',
        'privileges'           => $allPrivileges,
        'force_password_reset' => 1,
        'created_on'           => $now,
    ]);

    echo "\nAdmin account created successfully!\n";
    echo "  Name:  {$firstName} {$lastName}\n";
    echo "  Email: {$email}\n";
    echo "  Note:  You will be asked to change your password on first login.\n\n";
} catch (Exception $e) {
    echo "\nFailed to create admin account: " . $e->getMessage() . "\n";
    exit(1);
}
