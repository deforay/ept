<?php

class Pt_Helper_View_GetCommitSha extends Zend_View_Helper_Abstract
{
    /** @var string|false|null false = resolved as unknown, null = not resolved yet */
    private static $sha = null;

    /**
     * The commit this install is running, short form (7 chars) for footer display,
     * or null if it can't be determined.
     *
     * Two sources, in order: the working tree's git metadata (dev checkouts), then
     * VERSION.txt, which setup.sh/upgrade.sh write at deploy time -- deployed
     * instances never receive .git, so the file is the only signal there.
     */
    public function getCommitSha(bool $short = true): ?string
    {
        if (self::$sha === null) {
            self::$sha = $this->resolve();
        }
        if (self::$sha === false) {
            return null;
        }
        return $short ? substr(self::$sha, 0, 7) : self::$sha;
    }

    /**
     * @return string|false
     */
    private function resolve()
    {
        $gitDir = ROOT_PATH . DIRECTORY_SEPARATOR . '.git';
        if (is_dir($gitDir)) {
            $head = @file_get_contents($gitDir . DIRECTORY_SEPARATOR . 'HEAD');
            if ($head !== false) {
                $head = trim($head);
                if (preg_match('/^[0-9a-f]{40}$/', $head)) {
                    return $head; // detached HEAD
                }
                if (strpos($head, 'ref:') === 0) {
                    $ref = trim(substr($head, 4));
                    $sha = $this->shaFromRef($gitDir, $ref);
                    if ($sha !== false) {
                        return $sha;
                    }
                }
            }
        }

        $versionFile = ROOT_PATH . DIRECTORY_SEPARATOR . 'VERSION.txt';
        if (is_readable($versionFile)) {
            $handle = @fopen($versionFile, 'r');
            if ($handle !== false) {
                $line = trim((string) fgets($handle, 64));
                fclose($handle);
                if (preg_match('/^[0-9a-f]{7,40}$/', $line)) {
                    return $line;
                }
            }
        }

        return false;
    }

    /**
     * @return string|false
     */
    private function shaFromRef(string $gitDir, string $ref)
    {
        $loose = $gitDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ref);
        if (is_readable($loose)) {
            $sha = trim((string) @file_get_contents($loose));
            if (preg_match('/^[0-9a-f]{40}$/', $sha)) {
                return $sha;
            }
        }

        // Ref was packed away by `git gc`.
        $packed = $gitDir . DIRECTORY_SEPARATOR . 'packed-refs';
        if (is_readable($packed)) {
            $handle = @fopen($packed, 'r');
            if ($handle !== false) {
                while (($line = fgets($handle)) !== false) {
                    if ($line === '' || $line[0] === '#' || $line[0] === '^') {
                        continue;
                    }
                    $parts = preg_split('/\s+/', trim($line));
                    if (count($parts) === 2 && $parts[1] === $ref && preg_match('/^[0-9a-f]{40}$/', $parts[0])) {
                        fclose($handle);
                        return $parts[0];
                    }
                }
                fclose($handle);
            }
        }

        return false;
    }
}
