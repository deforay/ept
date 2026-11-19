<?php

declare(strict_types=1);

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The new-password step shared by bin/reset-password.php and bin/reset-admin-password.php.
 *
 * The minimum length is the one the web forms enforce (Common::minimumPasswordLength), so a
 * password set here always passes the web check too. A generated password is at least 12
 * characters, or the minimum when that is longer. Exits the script on failure.
 *
 * @param bool $generate      in: -g was given. Out: true when the password was generated
 * @param bool $offerGenerate ask whether to generate one when neither -p nor -g was given
 * @param bool $showGenerated print the generated password right away
 */
function resolveNewPassword(SymfonyStyle $io, ?string $passwordArg, bool &$generate, bool $offerGenerate, bool $showGenerated): string
{
    $minLength = Application_Service_Common::minimumPasswordLength();
    $tooShort = "Password must be at least {$minLength} characters long.";

    if (!$generate && $passwordArg === null && $offerGenerate) {
        $generate = $io->confirm('Auto-generate a secure password?', true);
    }
    if ($generate) {
        $password = Pt_Commons_MiscUtility::generateTempPassword(null, max(12, $minLength));
        // The generator caps a password at 20 characters. Top it up for a higher minimum.
        while (strlen($password) < $minLength) {
            $password .= Pt_Commons_MiscUtility::generateTempPassword(null, 20);
        }
        if ($showGenerated) {
            $io->text("Generated password: <comment>$password</comment>");
        }
        return $password;
    }
    if ($passwordArg !== null) {
        $password = trim($passwordArg);
        if (strlen($password) < $minLength) {
            $io->error($tooShort);
            exit(1);
        }
        return $password;
    }

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $io->text('Password requirements:');
        $io->listing([
            "Minimum {$minLength} characters",
            'Mix of letters and numbers recommended',
            'Special characters allowed',
        ]);

        $password = trim((string) $io->askHidden('Enter new password (input hidden)'));
        if ($password === '') {
            $io->error('Password cannot be empty.');
            continue;
        }
        if (strlen($password) < $minLength) {
            $io->warning($tooShort . ' Please try again.');
            continue;
        }
        if ($io->askHidden('Confirm new password') !== $password) {
            $io->error('Passwords do not match. Please try again.');
            continue;
        }
        return $password;
    }

    $io->error('Failed to set password after multiple attempts.');
    exit(1);
}
