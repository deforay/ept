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

    /**
     * Get data managers mapped to a participant code
     */
    function getDataManagersByParticipantCode($db, string $participantCode): array
    {
        $query = "
            SELECT 
                dm.dm_id,
                CONCAT(dm.first_name, ' ', dm.last_name) AS dm_name,
                dm.primary_email,
                dm.status,
                p.unique_identifier,
                CONCAT(p.first_name, ' ', p.last_name) AS participant_name
            FROM participant p
            INNER JOIN participant_manager_map pmm ON p.participant_id = pmm.participant_id
            INNER JOIN data_manager dm ON pmm.dm_id = dm.dm_id
            WHERE p.unique_identifier = ?
            ORDER BY dm.first_name
        ";

        return $db->fetchAll($query, [$participantCode]);
    }

    /**
     * Get data manager by email
     */
    function getDataManagerByEmail($db, string $email): ?array
    {
        $user = $db->fetchRow(
            "SELECT dm_id, CONCAT(first_name, ' ', last_name) AS dm_name, primary_email, status 
             FROM data_manager 
             WHERE primary_email = ?",
            [$email]
        );

        return $user ?: null;
    }

    /**
     * Display data managers and let user select one
     */
    function selectDataManager(SymfonyStyle $io, array $dataManagers): ?array
    {
        if (empty($dataManagers)) {
            return null;
        }

        $io->section('Available Data Managers');

        $choices = [];
        foreach ($dataManagers as $index => $dm) {
            $number = $index + 1;
            $status = strtolower($dm['status']) === 'active' ? '✓' : '⚠';
            $choices[$number] = sprintf(
                "%s %s (%s) - %s",
                $status,
                $dm['dm_name'],
                $dm['primary_email'],
                $dm['status']
            );

            $io->text(sprintf(
                "[%d] %s <info>%s</info> <%s> - Status: <comment>%s</comment>",
                $number,
                $status,
                $dm['dm_name'],
                $dm['primary_email'],
                $dm['status']
            ));
        }

        $io->newLine();

        $selection = $io->ask(
            'Select a data manager (enter number)',
            null,
            function ($answer) use ($dataManagers) {
                if ($answer === null || $answer === '') {
                    throw new \RuntimeException('Selection is required.');
                }

                $num = (int) $answer;
                if ($num < 1 || $num > count($dataManagers)) {
                    throw new \RuntimeException('Invalid selection. Please enter a valid number.');
                }

                return $num;
            }
        );

        return $dataManagers[$selection - 1];
    }

    // Parse CLI options
    $options = getopt('e:p:g', ['email:', 'password:', 'generate', 'force-reset', 'input:']);

    $inputArg = $options['input'] ?? $options['e'] ?? $options['email'] ?? null;
    $passwordArg = $options['password'] ?? $options['p'] ?? null;
    $generatePassword = isset($options['generate']) || isset($options['g']);
    $forceReset = isset($options['force-reset']);

    // Display header
    $io->title('Reset User Password');

    // Step 1: Get input (email or participant code)
    $userInput = null;
    if ($inputArg !== null) {
        $userInput = trim((string) $inputArg);
        $io->text("Input: <info>$userInput</info>");
    } else {
        $attempts = 0;
        do {
            $attempts++;
            $input = $io->ask('Enter Data Manager email or Participant Code');

            if ($input === null || $input === '') {
                $io->error('Input is required.');
                if ($attempts >= 3) {
                    $io->error('Too many failed attempts. Exiting.');
                    exit(1);
                }
                continue;
            }

            $userInput = trim($input);
            break;
        } while ($attempts < 3);
    }

    // Step 2: Determine if input is email or participant code
    $io->section('Looking up user information');

    $selectedUser = null;
    $isEmail = validateEmail($userInput);

    if ($isEmail) {
        // OPTION 1: Direct email lookup
        $io->text('Detected as email address, searching for data manager...');
        $selectedUser = getDataManagerByEmail($db, $userInput);

        if (!$selectedUser) {
            $io->error("No data manager found with email: $userInput");
            exit(1);
        }

        $io->success("Data manager found!");
    } else {
        // OPTION 2: Participant code lookup
        $io->text('Not an email address, searching for participant code...');
        $dataManagers = getDataManagersByParticipantCode($db, $userInput);

        if (empty($dataManagers)) {
            $io->error("No data managers found for participant code: $userInput");
            $io->note('Please verify the participant code or enter a data manager email address.');
            exit(1);
        }

        // Show participant info
        $participantInfo = $dataManagers[0];
        $io->success(sprintf(
            "Found participant: %s (%s)",
            $participantInfo['participant_name'],
            $participantInfo['unique_identifier']
        ));

        $io->text(sprintf(
            "This participant is mapped to <comment>%d</comment> data manager(s):",
            count($dataManagers)
        ));
        $io->newLine();

        // Let user select a data manager
        $selectedUser = selectDataManager($io, $dataManagers);

        if (!$selectedUser) {
            $io->error('No data manager selected.');
            exit(1);
        }
    }

    // Step 3: Display selected user info
    $io->section('Selected Data Manager');

    $email = $selectedUser['primary_email'];
    $io->text([
        "User ID: <info>{$selectedUser['dm_id']}</info>",
        "Name: <info>{$selectedUser['dm_name']}</info>",
        "Email: <info>{$selectedUser['primary_email']}</info>",
        "Status: <info>{$selectedUser['status']}</info>",
    ]);

    if (strtolower($selectedUser['status']) !== 'active') {
        $io->warning("User account status is: {$selectedUser['status']}");
        if (!$io->confirm('Continue with password reset?', false)) {
            $io->text('Operation cancelled.');
            exit(0);
        }
    }

    // Step 4: Get new password
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

    // Step 5: Confirm action
    $io->section('Confirmation');

    $forceResetText = $forceReset ? 'YES' : 'NO';
    $io->text([
        "Data Manager: <info>{$selectedUser['dm_name']}</info>",
        "Email: <info>$email</info>",
        "Force password reset on next login: <info>$forceResetText</info>",
    ]);

    if (!$io->confirm('Proceed with password reset?', true)) {
        $io->text('Operation cancelled.');
        exit(0);
    }

    // Step 6: Update password
    $io->section('Updating Password');

    try {
        $hashedPassword = hashPassword($newPassword);

        $updateData = [
            'password' => $hashedPassword,
            'force_password_reset' => $forceReset ? 1 : 0,
        ];

        $result = $db->update('data_manager', $updateData, 'primary_email = "' . $email . '"');

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
            /* Pt_Commons_LoggerUtility::log("Password reset for user: $email", [
                'user_id' => $selectedUser['dm_id'],
                'email' => $email,
                'force_reset' => $forceReset,
            ]); */
        } else {
            $io->error('Failed to update password. No rows affected.');
            exit(1);
        }
    } catch (Throwable $e) {
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

/*
===========================================
USAGE EXAMPLES
===========================================

# Interactive mode (will prompt for email or participant code)
php bin/reset-password.php

# With data manager email
php bin/reset-password.php --input user@example.com

# With participant code
php bin/reset-password.php --input PART-12345

# Using old -e flag (still works for backward compatibility)
php bin/reset-password.php -e user@example.com

# Generate random password with participant code
php bin/reset-password.php --input PART-12345 --generate

# Set specific password with force reset
php bin/reset-password.php --input PART-12345 -p "NewPassword123" --force-reset

# Full example with all options
php bin/reset-password.php --input PART-12345 --generate --force-reset
*/