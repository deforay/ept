<?php

/**
 * HMAC-signed download URLs with built-in expiry.
 *
 *   sign($absolutePath)          → /d/<b64>?exp=<unix>&sig=<hmac>
 *   verify($filepath, $exp, $sig) → true if signature matches and not expired
 *
 * Stateless: no DB table, no token storage. The signature binds the encoded
 * filepath to the expiry timestamp using HMAC-SHA256 keyed by `security.salt`
 * from application.ini. Tampering with either segment invalidates the link.
 *
 * Backward-compat policy: DownloadController only enforces signatures when BOTH
 * `exp` and `sig` query params are present. Existing unsigned /d/ links keep
 * working until callers are migrated; new code should always use sign().
 */
final class Pt_Commons_DownloadUrlSigner
{
    /** Default time-to-live for new signed URLs: 24 hours. */
    public const DEFAULT_TTL_SECONDS = 86400;

    /**
     * Build a signed download URL fragment for an absolute file path.
     * Returns the path portion (e.g. "/d/<b64>?exp=...&sig=...") suitable for
     * concatenation with $this->baseUrl(...) or use as an href as-is.
     */
    public static function sign(string $absolutePath, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): string
    {
        if ($ttlSeconds < 1) {
            $ttlSeconds = self::DEFAULT_TTL_SECONDS;
        }
        $b64 = base64_encode($absolutePath);
        $exp = time() + $ttlSeconds;
        $sig = self::hash($b64, (string) $exp);
        return '/d/' . $b64 . '?exp=' . $exp . '&sig=' . $sig;
    }

    /**
     * Verify that the (filepath, exp, sig) tuple is genuine and not expired.
     * $filepath is the raw base64 segment from the URL (what came out of
     * the route's :filepath param, NOT the decoded absolute path).
     */
    public static function verify(string $filepath, ?string $exp, ?string $sig): bool
    {
        if ($exp === null || $exp === '' || $sig === null || $sig === '') {
            return false;
        }
        if (!ctype_digit($exp)) {
            return false;
        }
        if ((int) $exp < time()) {
            return false;
        }
        $expected = self::hash($filepath, $exp);
        return hash_equals($expected, $sig);
    }

    /** True iff the request carries the two signature query params. */
    public static function hasSignature(?string $exp, ?string $sig): bool
    {
        return $exp !== null && $exp !== '' && $sig !== null && $sig !== '';
    }

    private static function hash(string $b64Filepath, string $exp): string
    {
        return hash_hmac('sha256', $b64Filepath . '|' . $exp, self::secret());
    }

    private static function secret(): string
    {
        // Use application.ini's `security.salt` (already required for the
        // install; long random string). Falls back to a deterministic
        // placeholder only so the call doesn't fatal on misconfigured boxes —
        // signing under that fallback is still better than no signing.
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
            // fall through to placeholder
        }
        return 'ept-download-signer-fallback';
    }
}
