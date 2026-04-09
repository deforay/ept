<?php

declare(strict_types=1);

/**
 * Load root .env values into the current PHP process.
 * Existing environment variables win so real process-level secrets are not overwritten.
 */
function loadRootEnvFile(string $envFile): void
{
    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if ($trimmedLine === '' || str_starts_with($trimmedLine, '#')) {
            continue;
        }

        if (str_starts_with($trimmedLine, 'export ')) {
            $trimmedLine = trim(substr($trimmedLine, 7));
        }

        if (!str_contains($trimmedLine, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmedLine, 2);
        $key = trim($key);
        if ($key === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
            continue;
        }

        if (getenv($key) !== false) {
            continue;
        }

        $value = trim($value);
        if (
            strlen($value) >= 2
            && (
                ($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'")
            )
        ) {
            $value = substr($value, 1, -1);
        }

        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
