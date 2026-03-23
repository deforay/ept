#!/usr/bin/env php
<?php
// bin/change-dm-email.php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$cliMode = php_sapi_name() === 'cli';

// Graceful Ctrl+C handling
if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () {
        echo PHP_EOL . "⚠️  Email change cancelled by user." . PHP_EOL;
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

    /**
     * Check if an email is already used by another data manager
     */
    function isEmailTakenByAnotherDm($db, string $email, int $currentDmId): bool
    {
        $row = $db->fetchRow(
            "SELECT dm_id FROM data_manager WHERE primary_email = ? AND dm_id != ?",
            [$email, $currentDmId]
        );

        return !empty($row);
    }

    // Parse CLI options
    $options = getopt('', ['input:', 'new-email:', 'reset-password']);

    $inputArg = $options['input'] ?? null;
    $newEmailArg = $options['new-email'] ?? null;
    $resetPassword = isset($options['reset-password']);

    // Display header
    $io->title('Change Data Manager Email');

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

    $currentEmail = $selectedUser['primary_email'];
    $dmId = (int) $selectedUser['dm_id'];
    $io->text([
        "User ID: <info>{$selectedUser['dm_id']}</info>",
        "Name: <info>{$selectedUser['dm_name']}</info>",
        "Email: <info>{$selectedUser['primary_email']}</info>",
        "Status: <info>{$selectedUser['status']}</info>",
    ]);

    if (strtolower($selectedUser['status']) !== 'active') {
        $io->warning("User account status is: {$selectedUser['status']}");
        if (!$io->confirm('Continue with email change?', false)) {
            $io->text('Operation cancelled.');
            exit(0);
        }
    }

    // Step 4: Get new email
    $io->section('Setting New Email');

    $newEmail = null;
    if ($newEmailArg !== null) {
        $newEmail = trim((string) $newEmailArg);
    } else {
        $attempts = 0;
        do {
            $attempts++;

            $emailInput = $io->ask('Enter new email address');

            if ($emailInput === null || $emailInput === '') {
                $io->error('Email address is required.');
                if ($attempts >= 3) {
                    $io->error('Too many failed attempts. Exiting.');
                    exit(1);
                }
                continue;
            }

            $newEmail = trim($emailInput);
            break;
        } while ($attempts < 3);
    }

    if ($newEmail === null) {
        $io->error('Failed to get new email after multiple attempts.');
        exit(1);
    }

    // Validate new email format
    if (!validateEmail($newEmail)) {
        $io->error("Invalid email format: $newEmail");
        exit(1);
    }

    // Check if same as current
    if (strtolower($newEmail) === strtolower($currentEmail)) {
        $io->error('New email is the same as the current email.');
        exit(1);
    }

    // Check if already used by another DM
    if (isEmailTakenByAnotherDm($db, $newEmail, $dmId)) {
        $io->error("Email '$newEmail' is already in use by another data manager.");
        exit(1);
    }

    // Step 5: Ask about password reset (if not already specified via flag)
    if (!$resetPassword) {
        $resetPassword = $io->confirm('Also reset password for this data manager?', false);
    }

    // Step 6: Confirm action
    $io->section('Confirmation');

    $confirmLines = [
        "Data Manager: <info>{$selectedUser['dm_name']}</info>",
        "Current Email: <info>$currentEmail</info>",
        "New Email: <info>$newEmail</info>",
        "Reset Password: <info>" . ($resetPassword ? 'YES' : 'NO') . "</info>",
    ];
    $io->text($confirmLines);

    if (!$io->confirm('Proceed with email change?', true)) {
        $io->text('Operation cancelled.');
        exit(0);
    }

    // Step 7: Update email
    $io->section('Updating Email');

    try {
        $updateData = [
            'primary_email' => $newEmail,
            'new_email' => null,
        ];

        $result = $db->update('data_manager', $updateData, $db->quoteInto('dm_id = ?', $dmId));

        if ($result) {
            $io->success('Email updated successfully!');
            $io->text([
                "Old email: <comment>$currentEmail</comment>",
                "New email: <comment>$newEmail</comment>",
            ]);
        } else {
            $io->error('Failed to update email. No rows affected.');
            exit(1);
        }
    } catch (Throwable $e) {
        $io->error('Failed to update email. Check logs for details.');
        throw $e;
    }

    // Step 8: Optionally reset password by calling reset-password.php
    if ($resetPassword) {
        $io->section('Resetting Password');
        $io->text('Invoking password reset for the new email...');
        $io->newLine();

        $resetScript = __DIR__ . '/reset-password.php';
        $escapedEmail = escapeshellarg($newEmail);
        $cmd = sprintf('php %s --input %s --generate --force-reset --no-interaction', escapeshellarg($resetScript), $escapedEmail);

        passthru($cmd, $exitCode);

        if ($exitCode !== 0) {
            $io->error('Password reset failed. Please run reset-password.php manually.');
            exit(1);
        }
    }

    exit(0);
} catch (Throwable $e) {
    if (isset($io)) {
        $io->error("❌ Email change failed: " . $e->getMessage());
        $io->text("<info>Please check logs for details.</info>");
    } else {
        echo "❌ Fatal error: " . $e->getMessage() . PHP_EOL;
    }

    exit(1);
}

/*
===========================================
USAGE EXAMPLES
===========================================

# Interactive mode (will prompt for email or participant code)
php bin/change-dm-email.php

# With data manager email
php bin/change-dm-email.php --input old@example.com --new-email new@example.com

# With participant code
php bin/change-dm-email.php --input PART-12345 --new-email new@example.com

# Change email and also reset password (generates a random password with force-reset)
php bin/change-dm-email.php --input old@example.com --new-email new@example.com --reset-password

# Mixed: participant code via flag, new email interactively
php bin/change-dm-email.php --input PART-12345
*/
