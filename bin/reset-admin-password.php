#!/usr/bin/env php
<?php
// bin/reset-admin-password.php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$cliMode = php_sapi_name() === 'cli';

// Graceful Ctrl+C handling
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . "Password reset cancelled by user." . PHP_EOL;
        exit(130);
    });
}

try {
    require_once __DIR__ . '/../cli-bootstrap.php';
    ini_set('memory_limit', '-1');
    set_time_limit(0);

    if (!$cliMode) {
        echo "This script can only be run from the command line." . PHP_EOL;
        exit(1);
    }

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

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
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 14]);
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

    function getAdminByEmail($db, string $email): ?array
    {
        $user = $db->fetchRow(
            "SELECT admin_id, CONCAT(first_name, ' ', last_name) AS admin_name, primary_email, status
             FROM system_admin
             WHERE primary_email = ?",
            [$email]
        );

        return $user ?: null;
    }

    function getAllAdmins($db): array
    {
        return $db->fetchAll(
            "SELECT admin_id, CONCAT(first_name, ' ', last_name) AS admin_name, primary_email, status
             FROM system_admin
             ORDER BY first_name"
        );
    }

    function selectAdmin(SymfonyStyle $io, array $admins): ?array
    {
        if (empty($admins)) {
            return null;
        }

        $io->section('Available Admins');

        foreach ($admins as $index => $admin) {
            $number = $index + 1;
            $status = strtolower($admin['status']) === 'active' ? '✓' : '⚠';

            $io->text(sprintf(
                "[%d] %s <info>%s</info> <%s> - Status: <comment>%s</comment>",
                $number,
                $status,
                $admin['admin_name'],
                $admin['primary_email'],
                $admin['status']
            ));
        }

        $io->newLine();

        $selection = $io->ask(
            'Select an admin (enter number)',
            null,
            function ($answer) use ($admins) {
                if ($answer === null || $answer === '') {
                    throw new \RuntimeException('Selection is required.');
                }

                $num = (int) $answer;
                if ($num < 1 || $num > count($admins)) {
                    throw new \RuntimeException('Invalid selection. Please enter a valid number.');
                }

                return $num;
            }
        );

        return $admins[$selection - 1];
    }

    // Parse CLI options
    $options = getopt('e:p:g', ['email:', 'password:', 'generate', 'force-reset']);

    $emailArg = $options['email'] ?? $options['e'] ?? null;
    $passwordArg = $options['password'] ?? $options['p'] ?? null;
    $generatePassword = isset($options['generate']) || isset($options['g']);
    $forceReset = isset($options['force-reset']);

    // Display header
    $io->title('Reset Admin Password');

    // Step 1: Find the admin
    $selectedUser = null;

    if ($emailArg !== null) {
        $email = trim((string) $emailArg);
        $io->text("Looking up admin: <info>$email</info>");
        $selectedUser = getAdminByEmail($db, $email);

        if (!$selectedUser) {
            $io->error("No admin found with email: $email");
            exit(1);
        }

        $io->success("Admin found!");
    } else {
        // List all admins and let user pick
        $admins = getAllAdmins($db);

        if (empty($admins)) {
            $io->error('No admin accounts found in the system.');
            exit(1);
        }

        if (count($admins) === 1) {
            $selectedUser = $admins[0];
            $io->text(sprintf(
                "Only one admin found: <info>%s</info> (%s)",
                $selectedUser['admin_name'],
                $selectedUser['primary_email']
            ));
        } else {
            $selectedUser = selectAdmin($io, $admins);

            if (!$selectedUser) {
                $io->error('No admin selected.');
                exit(1);
            }
        }
    }

    // Step 2: Display selected admin info
    $io->section('Selected Admin');

    $email = $selectedUser['primary_email'];
    $io->text([
        "Admin ID: <info>{$selectedUser['admin_id']}</info>",
        "Name: <info>{$selectedUser['admin_name']}</info>",
        "Email: <info>{$selectedUser['primary_email']}</info>",
        "Status: <info>{$selectedUser['status']}</info>",
    ]);

    if (strtolower($selectedUser['status']) !== 'active') {
        $io->warning("Admin account status is: {$selectedUser['status']}");
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

            $passwordInput = $io->askHidden('Enter new password (input hidden)');

            if ($passwordInput === null || $passwordInput === '') {
                $io->error('Password cannot be empty.');
                if ($attempts >= 3) {
                    $io->error('Too many failed attempts. Exiting.');
                    exit(1);
                }
                continue;
            }

            $newPassword = trim($passwordInput);
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
        "Admin: <info>{$selectedUser['admin_name']}</info>",
        "Email: <info>$email</info>",
        "Force password reset on next login: <info>$forceResetText</info>",
    ]);

    if (!$io->confirm('Proceed with password reset?', true)) {
        $io->text('Operation cancelled.');
        exit(0);
    }

    // Step 5: Update password
    $io->section('Updating Password');

    try {
        $hashedPassword = hashPassword($newPassword);

        $updateData = [
            'password' => $hashedPassword,
            'force_password_reset' => $forceReset ? 1 : 0,
        ];

        $result = $db->update(
            'system_admin',
            $updateData,
            $db->quoteInto('admin_id = ?', $selectedUser['admin_id'])
        );

        if ($result) {
            $io->success('Admin password updated successfully!');

            if ($generatePassword) {
                $io->warning('IMPORTANT: Save this password securely!');
                $io->text("Password: <comment>$newPassword</comment>");
            }

            if ($forceReset) {
                $io->note('Admin will be required to change password on next login.');
            }
        } else {
            $io->error('Failed to update password. No rows affected.');
            exit(1);
        }
    } catch (Throwable $e) {
        $io->error('Failed to update password. Check logs for details.');
        throw $e;
    }

    exit(0);
} catch (Throwable $e) {
    if (isset($io)) {
        $io->error("Password reset failed: " . $e->getMessage());
        $io->text("<info>Please check logs for details.</info>");
    } else {
        echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    }

    exit(1);
}

/*
===========================================
USAGE EXAMPLES
===========================================

# Interactive mode (lists all admins to choose from)
php bin/reset-admin-password.php

# With admin email
php bin/reset-admin-password.php -e admin@example.com

# Generate random password
php bin/reset-admin-password.php -e admin@example.com --generate

# Set specific password with force reset
php bin/reset-admin-password.php -e admin@example.com -p "NewPassword123" --force-reset

# Full example with all options
php bin/reset-admin-password.php -e admin@example.com --generate --force-reset
*/
