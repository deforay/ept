<?php

/**
 * Holds a reviewed bulk-import file between the Review and Import steps.
 *
 *   stash($kind, $path, $params) → token (after the dry run)
 *   claim($kind, $token)         → ['path' => string, 'params' => array] or null
 *
 * The file stays on the server, so what gets imported is the file that was
 * reviewed, not a second upload. One pending review per kind per session; a
 * token is single-use, so a double-submitted confirm can't import twice.
 */
final class Pt_Commons_ImportReview
{
    private const NAMESPACE = 'bulkImportReview';
    private const TTL_SECONDS = 3600;

    public static function stash(string $kind, string $path, array $params): string
    {
        $token = bin2hex(random_bytes(16));
        $session = new Zend_Session_Namespace(self::NAMESPACE);
        $session->{$kind} = [
            'token' => $token,
            'path' => $path,
            'params' => $params,
            'created' => time(),
        ];
        return $token;
    }

    public static function claim(string $kind, string $token): ?array
    {
        $session = new Zend_Session_Namespace(self::NAMESPACE);
        $pending = $session->{$kind} ?? null;
        if (
            !is_array($pending)
            || $token === ''
            || !hash_equals((string) $pending['token'], $token)
        ) {
            return null;
        }
        unset($session->{$kind});

        if (time() - (int) $pending['created'] > self::TTL_SECONDS || !is_file($pending['path'])) {
            return null;
        }
        return ['path' => $pending['path'], 'params' => (array) $pending['params']];
    }
}
