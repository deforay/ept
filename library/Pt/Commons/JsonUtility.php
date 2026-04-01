<?php

final class Pt_Commons_JsonUtility
{
    // Validate if a string is valid JSON
    public static function isJSON($string, bool $logError = false, $checkUtf8Encoding = false): bool
    {
        if (empty($string) || !is_string($string)) {
            return false;
        }

        // Optional check for UTF-8 encoding
        if ($checkUtf8Encoding && !mb_check_encoding($string, 'UTF-8')) {
            if ($logError) {
                Pt_Commons_LoggerUtility::log('error', 'String is not valid UTF-8.');
            }
            return false;
        }

        json_decode($string);

        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        } else {
            if ($logError) {
                Pt_Commons_LoggerUtility::logError('JSON decoding error (' . json_last_error() . '): ' . json_last_error_msg());
                Pt_Commons_LoggerUtility::logError('Invalid JSON: ' . self::previewString($string));
            }
            return false;
        }
    }




    // Encode data to JSON with UTF-8 encoding
    public static function encodeUtf8Json(array|string|null $data): string|null
    {
        $result = null;

        if (is_array($data) && empty($data)) {
            $result = '[]';
        } elseif (is_null($data) || $data === '') {
            $result = '{}';
        } elseif (is_string($data) && self::isJSON($data, checkUtf8Encoding: true)) {
            $result = $data;
        } else {
            $result = self::toJSON(Pt_Commons_MiscUtility::toUtf8($data));
        }

        return $result;
    }

    // Convert data to JSON string
    public static function toJSON(mixed $data, int $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): ?string
    {
        $json = null;
        // Check if the data is already a valid JSON string
        if (is_string($data) && self::isJSON($data)) {
            $json = $data;
        } elseif (is_array($data)) {
            // Convert the data to JSON
            $json = json_encode($data, $flags);
            if ($json === false) {
                Pt_Commons_LoggerUtility::log('error', 'Data could not be encoded as JSON: ' . json_last_error_msg());
                $json = null;
            }
        }
        return $json;
    }

    // Pretty-print JSON
    public static function prettyJson(array|string $json): string
    {
        $decodedJson = is_array($json) ? $json : self::decodeJson($json);
        if ($decodedJson === null) {
            return htmlspecialchars("Error in JSON decoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        $encodedJson = json_encode($decodedJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return htmlspecialchars("Error in JSON encoding: " . json_last_error_msg(), ENT_QUOTES, 'UTF-8');
        }

        return $encodedJson;
    }

    // Merge multiple JSON strings into one
    public static function mergeJson(...$jsonStrings): ?string
    {
        $mergedArray = [];

        foreach ($jsonStrings as $json) {
            $array = self::decodeJson($json);
            if ($array === null) {
                return null;
            }
            $mergedArray = array_merge_recursive($mergedArray, $array);
        }

        return self::toJSON($mergedArray);
    }

    // Extract specific data from JSON using a path
    public static function extractJsonData($json, $path): mixed
    {
        $data = self::decodeJson($json);
        if ($data === null) {
            return null;
        }

        foreach (explode('.', $path) as $segment) {
            if (!isset($data[$segment])) {
                return null;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    // Decode JSON string to array or object
    public static function decodeJson($json, bool $returnAssociative = true): mixed
    {
        $data = json_decode($json, $returnAssociative);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Pt_Commons_LoggerUtility::log('error', 'Error decoding JSON: ' . json_last_error_msg());
            return null;
        }
        return $data;
    }

    // Minify JSON string
    public static function minifyJson($json): string
    {
        $decodedJson = self::decodeJson($json);
        if ($decodedJson === null) {
            return '';
        }

        return self::toJSON($decodedJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // Get keys from JSON object
    public static function getJsonKeys($json): array
    {
        $data = self::decodeJson($json);
        if ($data === null) {
            return [];
        }

        return array_keys($data);
    }

    // Get values from JSON object
    public static function getJsonValues($json): array
    {
        $data = self::decodeJson($json);
        if ($data === null) {
            return [];
        }

        return array_values($data);
    }

    private const MAX_LOG_PREVIEW = 2000;

    private static function previewString(string $s, int $max = self::MAX_LOG_PREVIEW): string
    {
        $len = mb_strlen($s, 'UTF-8');
        $p = mb_substr($s, 0, $max, 'UTF-8');
        $p = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $p);
        // redact common secrets
        $p = preg_replace('/("?(password|token|secret|authorization|api[_-]?key)"?\s*:\s*)"[^"]*"/i', '$1"***"', (string) $p);
        return $len > $max ? ($p . '… (len=' . $len . ')') : $p . " (len={$len})";
    }

    // Double single quotes for MySQL/MariaDB string literal safety
    private static function sqlQuote(string $s): string
    {
        return "'" . str_replace("'", "''", $s) . "'";
    }

    // Convert a value to a JSON-compatible string representation
    public static function jsonValueToString($value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $json = str_replace("'", "''", $json);
            return "'" . $json . "'";
        }
        // string
        return self::sqlQuote((string) $value);
    }

    // Convert a JSON string to a string that can be used with JSON_SET()
    public static function jsonToSetString(?string $json, string $column, $newData = []): ?string
    {
        // Normalize existing JSON data
        $jsonData = [];
        if (is_array($json)) {
            $jsonData = $json;
        } elseif (is_string($json) && trim($json) !== '') {
            if (self::isJSON($json)) {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $jsonData = $decoded;
                }
            } else {
                Pt_Commons_LoggerUtility::logWarning('Dropping invalid existing JSON while building JSON_SET', [
                    'payload_preview' => self::previewString($json, 200),
                ]);
            }
        }

        // Normalize new data
        if (is_string($newData) && trim($newData) !== '') {
            $newData = trim($newData);
            if (self::isJSON($newData)) {
                $newData = json_decode($newData, true);
            } else {
                Pt_Commons_LoggerUtility::logWarning('Wrapping invalid JSON payload into raw wrapper', [
                    'payload_preview' => self::previewString($newData, 200),
                ]);
                $newData = ['raw' => $newData];
            }
        }
        if (!is_array($newData)) {
            $newData = [];
        }

        if (!is_array($jsonData)) {
            $jsonData = [];
        }

        // Combine original data and new data
        $data = array_merge($jsonData, $newData);

        // Return null if there's nothing to set
        if ($data === []) {
            return null;
        }

        // Build the set string
        $setString = '';
        $rawDataFallback = [];

        foreach ($data as $key => $value) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($encoded === false) {
                Pt_Commons_LoggerUtility::logWarning('JSON encoding failed, saving to raw_data fallback', [
                    'key' => $key,
                    'value_type' => gettype($value),
                ]);
                $rawDataFallback[$key] = is_string($value) ? $value : serialize($value);
                continue;
            }

            if (!self::isJSON($encoded)) {
                Pt_Commons_LoggerUtility::logWarning('Invalid JSON after encoding, saving to raw_data fallback', [
                    'key' => $key,
                    'encoded_preview' => self::previewString($encoded, 200),
                ]);
                $rawDataFallback[$key] = $encoded;
                continue;
            }

            // Escape single quotes for SQL literal
            $encoded = str_replace("'", "''", $encoded);

            $setString .= ', "$.' . $key . '", CAST(\'' . $encoded . '\' AS JSON)';
        }

        // Add fallback raw data if any values failed validation
        if (!empty($rawDataFallback)) {
            $rawEncoded = json_encode($rawDataFallback, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($rawEncoded !== false) {
                $rawEncoded = str_replace("'", "''", $rawEncoded);
                $setString .= ', "$.raw_data", CAST(\'' . $rawEncoded . '\' AS JSON)';
            }
        }

        // Return null if no valid data to set
        if ($setString === '') {
            return null;
        }

        // Construct and return the JSON_SET query
        return 'JSON_SET(COALESCE(' . $column . ', \'{}\')' . $setString . ')';
    }
}
