<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function createCliStyle(): SymfonyStyle
{
    // Prefer the shared project console when the legacy utility class is available.
    // Direct bin scripts can run before Zend autoload/bootstrap has loaded that class.
    $output = class_exists('Pt_Commons_MiscUtility')
        ? Pt_Commons_MiscUtility::console()
        : new ConsoleOutput();

    return new SymfonyStyle(new ArgvInput(), $output);
}

/**
 * Run a child command and stream its output through Symfony console styling.
 * This keeps nested bin scripts readable without hiding the underlying command output.
 *
 * @param string[] $command
 */
function runConsoleCommand(SymfonyStyle $io, array $command, ?string $sectionTitle = null): void
{
    if ($sectionTitle !== null && $sectionTitle !== '') {
        $io->section($sectionTitle);
    }

    $io->text('<comment>$</comment> ' . implode(' ', array_map('escapeshellarg', $command)));

    $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
    $output = [];
    $exitCode = 0;

    exec($escapedCommand . ' 2>&1', $output, $exitCode);

    foreach ($output as $line) {
        $trimmedLine = trim((string) $line);
        if ($trimmedLine === '') {
            continue;
        }

        $io->writeln('  ' . $line);
    }

    if ($exitCode !== 0) {
        throw new RuntimeException('Command failed: ' . implode(' ', $command));
    }
}
