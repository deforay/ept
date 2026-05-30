<?php

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

final class Pt_Commons_LoggerUtility
{
    public const APP_CHANNEL    = 'app';
    public const CLIENT_CHANNEL = 'client';

    /** @var array<string,Logger> */
    private static array $loggers = [];

    private static function getLogger(string $channel = self::APP_CHANNEL): Logger
    {
        if (!isset(self::$loggers[$channel])) {
            $basename = $channel === self::CLIENT_CHANNEL ? 'client.log' : 'logfile.log';
            $logger = new Logger($channel);

            try {
                $handler = new RotatingFileHandler(ROOT_PATH . '/logs/' . $basename, 30, Logger::DEBUG);
                $handler->setFilenameFormat('{date}-{filename}', 'Y-m-d');
                $logger->pushHandler($handler);
            } catch (Throwable $e) {
                $fallbackHandler = new StreamHandler('php://stderr', Logger::WARNING);
                $logger->pushHandler($fallbackHandler);
                $logger->warning('Log file could not be written to. Fallback to stderr: ' . $e->getMessage());
            }
            self::$loggers[$channel] = $logger;
        }
        return self::$loggers[$channel];
    }

    public static function getCallerInfo($index = 1)
    {
        $backtrace = debug_backtrace();

        $callerInfo = [
            'file' => '',
            'line' => 0,
        ];

        if (isset($backtrace[$index])) {
            $callerInfo['file'] = $backtrace[$index]['file'];
            $callerInfo['line'] = $backtrace[$index]['line'];
        }

        return $callerInfo;
    }

    public static function log($level, $message, array $context = [], string $channel = self::APP_CHANNEL): void
    {
        $logger = self::getLogger($channel);

        // Auto-capture the real call site (file/line) and — for error-level and
        // above — a stack trace, UNLESS the caller already supplied them (e.g. a
        // caught exception's own getFile()/getLine()/getTraceAsString()).
        if (!isset($context['file'], $context['line']) || !isset($context['trace'])) {
            $caller = self::resolveCaller();
            $context['file'] ??= $caller['file'];
            $context['line'] ??= $caller['line'];
            if (!isset($context['trace']) && self::isAtLeastError($level)) {
                $context['trace'] = $caller['trace'];
            }
        }

        // Opportunistically attach request/session context (session hash, client
        // IP, request line, current user). Only filled when available and never
        // overrides anything the caller passed.
        foreach (self::ambientContext() as $key => $value) {
            $context[$key] ??= $value;
        }

        $logger->log($level, $message, $context);
    }

    /**
     * Resolve the first stack frame outside this utility — i.e. the real call
     * site — and build a trace string from there upward. Used to enrich records
     * that did not pass explicit file/line/trace.
     *
     * @return array{file:string,line:int,trace:string}
     */
    private static function resolveCaller(): array
    {
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        $start = null;
        foreach ($bt as $i => $frame) {
            if (isset($frame['file']) && $frame['file'] !== __FILE__) {
                $start = $i;
                break;
            }
        }
        if ($start === null) {
            return ['file' => '', 'line' => 0, 'trace' => ''];
        }

        $lines = [];
        $depth = 0;
        for ($i = $start, $count = count($bt); $i < $count; $i++) {
            $frame = $bt[$i];
            $loc = isset($frame['file'])
                ? $frame['file'] . '(' . ($frame['line'] ?? 0) . ')'
                : '[internal function]';
            $fn = (isset($frame['class']) ? $frame['class'] . ($frame['type'] ?? '::') : '')
                . ($frame['function'] ?? '');
            $lines[] = '#' . $depth++ . ' ' . $loc . ': ' . $fn . '()';
        }

        return [
            'file'  => $bt[$start]['file'] ?? '',
            'line'  => $bt[$start]['line'] ?? 0,
            'trace' => implode("\n", $lines),
        ];
    }

    private static function isAtLeastError($level): bool
    {
        if (is_int($level)) {
            return $level >= Logger::ERROR;
        }
        return in_array(strtolower((string) $level), ['error', 'critical', 'alert', 'emergency'], true);
    }

    /**
     * Best-effort request/session context. Returns [] in CLI or when nothing is
     * available; any failure is swallowed so logging never breaks the caller.
     *
     * @return array<string,string>
     */
    private static function ambientContext(): array
    {
        $ctx = [];
        try {
            if (($ip = self::clientIp()) !== null) {
                $ctx['ip'] = $ip;
            }
            if (class_exists('Pt_Commons_General')) {
                $hash = Pt_Commons_General::sessionHash();
                if (!empty($hash)) {
                    $ctx['session'] = $hash;
                }
            }
            if (!empty($_SERVER['REQUEST_METHOD']) && !empty($_SERVER['REQUEST_URI'])) {
                $ctx['request'] = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'];
            }
            if (($user = self::currentUser()) !== null) {
                $ctx['user'] = $user;
            }
        } catch (Throwable $e) {
            // enrichment is best-effort only — ignore
        }
        return $ctx;
    }

    private static function clientIp(): ?string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $value = (string) $_SERVER[$key];
                // X-Forwarded-For can be a comma-separated list; take the first hop.
                if (strpos($value, ',') !== false) {
                    $value = trim(explode(',', $value)[0]);
                }
                return $value;
            }
        }
        return null;
    }

    /**
     * Best-effort current user across the app's session namespaces. Only reads
     * an already-active session so logging never starts or locks one.
     */
    private static function currentUser(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE || !class_exists('Zend_Session_Namespace')) {
            return null;
        }
        try {
            $admin = new Zend_Session_Namespace('administrators');
            if (!empty($admin->admin_id)) {
                return 'admin:' . $admin->admin_id;
            }
            $dm = new Zend_Session_Namespace('datamanagers');
            if (!empty($dm->dm_id)) {
                return 'dm:' . $dm->dm_id;
            }
        } catch (Throwable $e) {
            // ignore — user context is optional
        }
        return null;
    }

    public static function logError($message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function logWarning($message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function logInfo($message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function logClientError($message, array $context = []): void
    {
        self::log('error', $message, $context, self::CLIENT_CHANNEL);
    }

    /**
     * List rotating log files (newest first) for a channel.
     * @return array<int,array{path:string,name:string,date:string,size:int,mtime:int}>
     */
    public static function listLogFiles(string $channel = self::APP_CHANNEL): array
    {
        $dir = ROOT_PATH . '/logs';
        if (!is_dir($dir)) {
            return [];
        }
        $suffix = $channel === self::CLIENT_CHANNEL ? '-client.log' : '-logfile.log';
        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})' . preg_quote($suffix, '/') . '$/', $entry, $m)) {
                continue;
            }
            $path = $dir . '/' . $entry;
            $files[] = [
                'path'  => $path,
                'name'  => $entry,
                'date'  => $m[1],
                'size'  => filesize($path) ?: 0,
                'mtime' => filemtime($path) ?: 0,
            ];
        }
        usort($files, static fn ($a, $b) => strcmp($b['date'], $a['date']));
        return $files;
    }

    /**
     * Read the last $maxLines lines of a file matching optional level/needle filters.
     * Returns most-recent-first.
     * @return array<int,string>
     */
    public static function tailLog(string $path, int $maxLines = 500, ?string $needle = null, ?string $level = null): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }
        $fp = @fopen($path, 'rb');
        if (!$fp) {
            return [];
        }
        $maxLines  = max(1, min($maxLines, 20000));
        $pos       = filesize($path);
        $leftover  = '';
        $collected = [];
        $needleLc  = $needle !== null && $needle !== '' ? mb_strtolower($needle) : null;
        $levelUc   = $level !== null && $level !== '' ? strtoupper($level) : null;

        while ($pos > 0 && count($collected) < $maxLines) {
            $read = min(8192, $pos);
            $pos -= $read;
            fseek($fp, $pos);
            $data  = (string) fread($fp, $read) . $leftover;
            $lines = explode("\n", $data);
            $leftover = $pos > 0 ? array_shift($lines) : '';
            self::filterLinesReverse($lines, $collected, $maxLines, $needleLc, $levelUc);
        }
        fclose($fp);
        return $collected;
    }

    private static function filterLinesReverse(array $lines, array &$collected, int $maxLines, ?string $needleLc, ?string $levelUc): void
    {
        for ($i = count($lines) - 1; $i >= 0 && count($collected) < $maxLines; $i--) {
            $line = $lines[$i];
            if ($line === '') {
                continue;
            }
            if ($levelUc !== null && strpos($line, '.' . $levelUc . ':') === false) {
                continue;
            }
            if ($needleLc !== null && strpos(mb_strtolower($line), $needleLc) === false) {
                continue;
            }
            $collected[] = $line;
        }
    }
}
