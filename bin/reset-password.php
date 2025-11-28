#!/usr/bin/env php
<?php
// bin/reset-password.php

declare(strict_types=1);

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$cliMode = php_sapi_name() === 'cli';

// Graceful Ctrl+C handling
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . "⚠️  Password reset cancelled by user." . PHP_EOL;
        exit(130);
    });
}

try {
    require_once __DIR__ . '/../cli-bootstrap.php';
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    if (!$cliMode) {
        echo "❌ This script can only be run from the command line." . PHP_EOL;
        exit(1);
    }

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    $general = new Pt_Commons_General();
    $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    // Helper functions
    function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    function hashPassword(string $password): string
    {
        // Use PASSWORD_DEFAULT for bcrypt (or PASSWORD_ARGON2ID if available)
        return password_hash($password, PASSWORD_DEFAULT);
    }

    function generateSecurePassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }

    // Parse CLI options
    $options = getopt('e:p:g', ['email:', 'password:', 'generate', 'force-reset']);

    $emailArg = $options['email'] ?? $options['e'] ?? null;
    $passwordArg = $options['password'] ?? $options['p'] ?? null;
    $generatePassword = isset($options['generate']) || isset($options['g']);
    $forceReset = isset($options['force-reset']);

    // Display header
    $io->title('Reset User Password');

    // Step 1: Get email address
    $email = null;
    if ($emailArg !== null) {
        $email = trim((string) $emailArg);
        if (!validateEmail($email)) {
            $io->error("Invalid email format: $email");
            exit(1);
        }
        $io->text("Email: <info>$email</info>");
    } else {
        $attempts = 0;
        do {
            $attempts++;
            $userInput = $io->ask('Enter user email address');

            if ($userInput === null || $userInput === '') {
                $io->error('Email address is required.');
                if ($attempts >= 3) {
                    $io->error('Too many failed attempts. Exiting.');
                    exit(1);
                }
                continue;
            }

            $email = trim($userInput);
            if (!validateEmail($email)) {
                $io->warning('Invalid email format. Please try again.');
                continue;
            }

            break;
        } while ($attempts < 3);
    }

    // Step 2: Verify user exists
    $io->section('Verifying User');

    $user = $db->fetchRow(
        "SELECT dm_id, CONCAT(first_name, ' ', last_name) AS dm_name, primary_email, status 
         FROM data_manager 
         WHERE primary_email = ?",
        [$email]
    );

    if (!$user) {
        $io->error("No user found with email: $email");
        exit(1);
    }

    $io->text([
        "User ID: <info>{$user['dm_id']}</info>",
        "Username: <info>{$user['dm_name']}</info>",
        "Email: <info>{$user['primary_email']}</info>",
        "Status: <info>{$user['status']}</info>",
    ]);

    if (strtolower($user['status']) !== 'active') {
        $io->warning("User account status is: {$user['status']}");
        if (!$io->confirm('Continue with password reset?', false)) {
            $io->text('Operation cancelled.');
            exit(0);
        }
    }

    // Step 3: Get new password
    $io->section('Setting New Password');

    $newPassword = null;
    if ($generatePassword) {
        $newPassword = generateSecurePassword(12);
        $io->text("Generated password: <comment>$newPassword</comment>");
    } elseif ($passwordArg !== null) {
        $newPassword = trim((string) $passwordArg);
        if (strlen($newPassword) < 6) {
            $io->error('Password must be at least 6 characters long.');
            exit(1);
        }
    } else {
        $attempts = 0;
        do {
            $attempts++;

            $io->text('Password requirements:');
            $io->listing([
                'Minimum 6 characters',
                'Mix of letters and numbers recommended',
                'Special characters allowed',
            ]);

            $userInput = $io->askHidden('Enter new password (input hidden)');

            if ($userInput === null || $userInput === '') {
                $io->error('Password cannot be empty.');
                if ($attempts >= 3) {
                    $io->error('Too many failed attempts. Exiting.');
                    exit(1);
                }
                continue;
            }

            $newPassword = trim($userInput);
            if (strlen($newPassword) < 6) {
                $io->warning('Password must be at least 6 characters long. Please try again.');
                continue;
            }

            // Confirm password
            $confirmPassword = $io->askHidden('Confirm new password');
            if ($confirmPassword !== $newPassword) {
                $io->error('Passwords do not match. Please try again.');
                $newPassword = null;
                continue;
            }

            break;
        } while ($attempts < 3);

        if ($newPassword === null) {
            $io->error('Failed to set password after multiple attempts.');
            exit(1);
        }
    }

    // Step 4: Confirm action
    $io->section('Confirmation');

    $forceResetText = $forceReset ? 'YES' : 'NO';
    $io->text([
        "Email: <info>$email</info>",
        "Force password reset on next login: <info>$forceResetText</info>",
    ]);

    if (!$io->confirm('Proceed with password reset?', true)) {
        $io->text('Operation cancelled.');
        exit(0);
    }

    // Step 5: Update password
    $io->section('Updating Password');

    // $bar = Pt_Commons_MiscUtility::spinnerStart(1, 'Updating password…', '█', '░', '█', 'cyan');

    try {
        $hashedPassword = hashPassword($newPassword);

        $updateData = [
            'password' => $hashedPassword,
            'force_password_reset' => $forceReset ? 1 : 0,
        ];

        $result = $db->update('data_manager', $updateData, 'primary_email ="' .  $email . '"');

        /* Pt_Commons_MiscUtility::spinnerAdvance($bar, 1);
        Pt_Commons_MiscUtility::spinnerFinish($bar); */

        if ($result) {
            $io->success('Password updated successfully!');

            if ($generatePassword) {
                $io->warning('IMPORTANT: Save this password securely!');
                $io->text("Password: <comment>$newPassword</comment>");
            }

            if ($forceReset) {
                $io->note('User will be required to change password on next login.');
            }

            // Log the action
            /*  Pt_Commons_LoggerUtility::log("Password reset for user: $email", [
                'user_id' => $user['dm_id'],
                'email' => $email,
                'force_reset' => $forceReset,
            ]); */
        } else {
            $io->error('Failed to update password. No rows affected.');
            exit(1);
        }
    } catch (Throwable $e) {
        Pt_Commons_MiscUtility::spinnerFinish($bar);

        /* Pt_Commons_LoggerUtility::log("Password reset error: " . $e->getMessage(), [
            'email' => $email,
            'line' => $e->getLine(),
            'file' => $e->getFile(),
            'trace' => $e->getTraceAsString(),
        ]); */

        $io->error('Failed to update password. Check logs for details.');
        throw $e;
    }

    exit(0);
} catch (Throwable $e) {
    if (isset($io)) {
        $io->error("❌ Password reset failed: " . $e->getMessage());
        $io->text("<info>Please check logs for details.</info>");
    } else {
        echo "❌ Fatal error: " . $e->getMessage() . PHP_EOL;
    }

    /* Pt_Commons_LoggerUtility::log("Password reset script failure: " . $e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString(),
    ]); */

    exit(1);
}



# Interactive mode
// php bin/reset-password.php

# With email argument
// php bin/reset-password.php -e user@example.com

# Generate random password
// php bin/reset-password.php -e user@example.com --generate

# Set specific password
// php bin/reset-password.php -e user@example.com -p "NewPassword123"

# Force password reset on next login
// php bin/reset-password.php -e user@example.com --generate --force-reset