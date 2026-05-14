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

    public static function log($level, $message, array $context = []): void
    {
        $logger = self::getLogger();

        $callerInfo = self::getCallerInfo(1);

        $context['file'] ??= $callerInfo['file'] ?? '';
        $context['line'] ??= $callerInfo['line'] ?? '';
        $logger->log($level, $message, $context);
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
        self::getLogger(self::CLIENT_CHANNEL)->error($message, $context);
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
        usort($files, static fn($a, $b) => strcmp($b['date'], $a['date']));
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
