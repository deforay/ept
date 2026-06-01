<?php

declare(strict_types=1);

namespace EptTestHarness;

/**
 * Parses application/configs/application.ini as plain text. No Zend classes.
 * Extracts the resolved environment section (dev/testing/production etc).
 */
final class Config
{
    public string $env;
    public string $dbHost;
    public string $dbUser;
    public string $dbPass;
    public string $dbName;
    public string $dbCharset;
    public string $repoRoot;
    public string $phpBinary;

    public static function load(string $repoRoot): self
    {
        $env = getenv('APPLICATION_ENV');
        if ($env === false || $env === '') {
            $env = 'production';
        }
        if (!in_array($env, ['development', 'testing'], true)) {
            fwrite(STDERR, "ERROR: this harness refuses to run unless APPLICATION_ENV is 'development' or 'testing' (got '$env'). Set the env var and retry.\n");
            exit(2);
        }

        $iniPath = $repoRoot . '/application/configs/application.ini';
        if (!is_file($iniPath)) {
            fwrite(STDERR, "ERROR: cannot read $iniPath\n");
            exit(2);
        }

        $sections = self::parseIni($iniPath);
        $resolved = self::resolveSection($sections, $env);

        $required = [
            'resources.db.params.host',
            'resources.db.params.username',
            'resources.db.params.password',
            'resources.db.params.dbname',
        ];
        foreach ($required as $key) {
            if (!isset($resolved[$key])) {
                fwrite(STDERR, "ERROR: $key missing from application.ini [$env]\n");
                exit(2);
            }
        }

        $c = new self();
        $c->env       = $env;
        $c->dbHost    = (string) $resolved['resources.db.params.host'];
        $c->dbUser    = (string) $resolved['resources.db.params.username'];
        $c->dbPass    = (string) $resolved['resources.db.params.password'];
        $c->dbName    = (string) $resolved['resources.db.params.dbname'];
        $c->dbCharset = (string) ($resolved['resources.db.params.charset'] ?? 'utf8mb4');
        $c->repoRoot  = $repoRoot;
        $c->phpBinary = (string) ($resolved['php.path'] ?? 'php');
        return $c;
    }

    /** Parse the INI into a sections-keyed map, preserving "inheritance" markers ("staging : production"). */
    private static function parseIni(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $sections = [];
        $current = null;
        $parents = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || $trim[0] === ';' || $trim[0] === '#') {
                continue;
            }
            if ($trim[0] === '[' && substr($trim, -1) === ']') {
                $header = trim(substr($trim, 1, -1));
                if (str_contains($header, ':')) {
                    [$child, $parent] = array_map('trim', explode(':', $header, 2));
                    $current = $child;
                    $parents[$child] = $parent;
                } else {
                    $current = $header;
                }
                if (!isset($sections[$current])) {
                    $sections[$current] = [];
                }
                continue;
            }
            if ($current === null) {
                continue;
            }
            $eq = strpos($trim, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($trim, 0, $eq));
            $val = trim(substr($trim, $eq + 1));
            // strip inline trailing comments only for unquoted values
            if ($val !== '' && $val[0] !== '"' && $val[0] !== "'") {
                $semi = strpos($val, ';');
                if ($semi !== false) {
                    $val = trim(substr($val, 0, $semi));
                }
            }
            if (strlen($val) >= 2 && (($val[0] === '"' && $val[-1] === '"') || ($val[0] === "'" && $val[-1] === "'"))) {
                $val = substr($val, 1, -1);
            }
            $sections[$current][$key] = $val;
        }

        // resolve inheritance
        foreach ($parents as $child => $parent) {
            if (isset($sections[$parent])) {
                $sections[$child] = array_merge($sections[$parent], $sections[$child]);
            }
        }
        return $sections;
    }

    private static function resolveSection(array $sections, string $env): array
    {
        if (isset($sections[$env])) {
            return $sections[$env];
        }
        if (isset($sections['production'])) {
            return $sections['production'];
        }
        return [];
    }
}
