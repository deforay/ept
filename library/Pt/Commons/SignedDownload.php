<?php

/**
 * Encrypted, time-limited download URLs.
 *
 *   url($absolutePath, $ttlSeconds = 86400) → "/dl/<encrypted-token>"
 *   decode($token)                          → ['path' => string, 'exp' => int] or null
 *
 * Replaces the legacy /d/<base64> route. Tokens are AES-256-GCM ciphertexts of
 * a small JSON envelope {path, exp}; the file path is NOT readable from the
 * URL, and an attacker can neither tamper with nor extend the link without
 * the key. Stateless — no DB table, no cleanup job. The key is derived from
 * application.ini's `security.salt` via SHA-256.
 *
 * Token format on the wire (all components url-safe-base64'd as one blob):
 *
 *   [12 bytes IV][16 bytes AES-GCM tag][N bytes ciphertext]
 *
 * Old /d/ links keep working unchanged via DownloadController; migrate callers
 * to ::url() at leisure, then delete the /d/ route once the audit log shows
 * zero unsigned hits.
 */
final class Pt_Commons_SignedDownload
{
    public const DEFAULT_TTL_SECONDS = 86400;

    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    /**
     * Build a signed, encrypted download URL fragment for an absolute path.
     * Returns "/dl/<token>" suitable for direct use as an href, or to be
     * combined with $this->baseUrl(...) in views.
     *
     * Defaults are deliberately conservative:
     *   - $ttlSeconds defaults to 24h; pass a smaller value for sensitive links.
     *   - $requireAuth defaults to true — the controller will demand a logged-in
     *     session before serving. Pass false ONLY for genuinely public links
     *     (e.g. emailed report PDFs the recipient might open without logging in).
     */
    public static function url(string $absolutePath, int $ttlSeconds = self::DEFAULT_TTL_SECONDS, bool $requireAuth = true): string
    {
        if ($ttlSeconds < 1) {
            $ttlSeconds = self::DEFAULT_TTL_SECONDS;
        }
        $payload = json_encode([
            'path' => $absolutePath,
            'exp'  => time() + $ttlSeconds,
            'auth' => $requireAuth ? 1 : 0,
        ]);
        $iv = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt($payload, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Pt_Commons_SignedDownload: openssl_encrypt failed');
        }
        return '/dl/' . self::base64UrlEncode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt and validate a token. Returns ['path' => ..., 'exp' => ...] on
     * success, or null if the token is malformed, tampered with, or expired.
     */
    public static function decode(string $token): ?array
    {
        $blob = self::base64UrlDecode($token);
        if ($blob === null || strlen($blob) < (self::IV_LEN + self::TAG_LEN + 1)) {
            return null;
        }
        $iv = substr($blob, 0, self::IV_LEN);
        $tag = substr($blob, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($blob, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            return null; // tampered or wrong key
        }
        $decoded = json_decode($plaintext, true);
        if (!is_array($decoded) || !isset($decoded['path'], $decoded['exp'])) {
            return null;
        }
        if ((int) $decoded['exp'] < time()) {
            return null; // expired
        }
        return [
            'path' => (string) $decoded['path'],
            'exp'  => (int) $decoded['exp'],
            'auth' => !empty($decoded['auth']),
        ];
    }

    /** @var string|null Cached, derived AES-256 key — populated on first call. */
    private static ?string $cachedKey = null;

    /**
     * 32-byte AES-256 key derived from application.ini's security.salt.
     *
     * Throws if the salt is missing or under-entropy. Silently falling back to
     * a hardcoded string would let every install share the same key, allowing
     * anyone with the public codebase to forge signed download URLs against
     * any other install. Fail loudly so the misconfiguration is fixed.
     */
    private static function key(): string
    {
        if (self::$cachedKey !== null) {
            return self::$cachedKey;
        }
        $salt = self::resolveSalt();
        if ($salt === null || strlen($salt) < 32) {
            throw new \RuntimeException(
                'Pt_Commons_SignedDownload requires security.salt in application.ini ' .
                'to be set to a random string of at least 32 characters.'
            );
        }
        self::$cachedKey = hash('sha256', $salt, true);
        return self::$cachedKey;
    }

    /**
     * Pull security.salt from the bootstrap's resolved config (the usual web
     * request path) or, failing that, parse application.ini directly. The
     * direct-parse fallback keeps the helper working from CLI contexts (cron
     * jobs, the test harness) where the front-controller bootstrap may not be
     * registered.
     */
    private static function resolveSalt(): ?string
    {
        try {
            $front = Zend_Controller_Front::getInstance();
            $bootstrap = $front->getParam('bootstrap');
            if ($bootstrap instanceof Zend_Application_Bootstrap_Bootstrap) {
                $options = $bootstrap->getOptions();
                if (!empty($options['security']['salt'])) {
                    return (string) $options['security']['salt'];
                }
            }
        } catch (\Throwable $e) {
            // continue to direct-parse
        }

        $iniPath = defined('APPLICATION_PATH') ? APPLICATION_PATH . '/configs/application.ini' : null;
        if ($iniPath === null || !is_file($iniPath)) {
            return null;
        }
        // parse_ini_file with INI_SCANNER_TYPED preserves quoting / dotted keys.
        $sections = @parse_ini_file($iniPath, true, INI_SCANNER_TYPED);
        if (!is_array($sections)) {
            return null;
        }
        $env = defined('APPLICATION_ENV') ? APPLICATION_ENV : 'production';
        // Search the resolved env section first, then its parent (handled by INI
        // inheritance syntax "[env : parent]"), then production as last resort.
        foreach ([$env, 'production'] as $section) {
            if (isset($sections[$section]['security.salt'])) {
                return (string) $sections[$section]['security.salt'];
            }
        }
        return null;
    }

    private static function base64UrlEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $s): ?string
    {
        $padding = strlen($s) % 4;
        if ($padding > 0) {
            $s .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($s, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
