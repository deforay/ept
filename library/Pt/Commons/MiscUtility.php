<?php

use Throwable;
use Normalizer;
use ZipArchive;
use InvalidArgumentException;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

final class Pt_Commons_MiscUtility
{

    public static function sanitizeFilename($filename, $regex = '/[^a-zA-Z0-9\-]/', $replace = ''): string
    {
        return preg_replace($regex, $replace, $filename);
    }

    /**
     * Recursively convert input to valid UTF-8 and remove invisible characters.
     *
     * @param array|string|null $input
     * @return array|string|null
     */
    public static function toUtf8(mixed $input): mixed
    {
        if (is_array($input)) {
            return array_map([self::class, 'toUtf8'], $input);
        }

        if (is_string($input)) {
            // Normalize encoding
            $input = trim($input);
            if (!mb_check_encoding($input, 'UTF-8')) {
                $encoding = mb_detect_encoding($input, mb_detect_order(), true) ?? 'UTF-8';
                $input = mb_convert_encoding($input, 'UTF-8', $encoding);
            }

            // Normalize Unicode (NFC form)
            if (class_exists('Normalizer')) {
                $input = Normalizer::normalize($input, Normalizer::FORM_C);
            }

            // Remove basic BOM, ZWSP, NBSP
            $input = preg_replace(
                '/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u',
                '',
                $input
            );
        }

        return $input;
    }


    public static function cleanString($string)
    {
        if ($string === null || $string === '') {
            return null;
        }

        if (class_exists('Normalizer')) {
            $string = Normalizer::normalize($string, Normalizer::FORM_C);
        }

        // Remove common invisible Unicode characters
        $string = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}\x{202F}\x{2060}\x{00AD}]/u', '', $string);

        // Remove ASCII control characters (0–31 and 127)
        $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);

        // Trim Unicode whitespace and control characters from both ends
        $string = preg_replace('/^[\p{Z}\p{C}]+|[\p{Z}\p{C}]+$/u', '', $string);

        return $string;
    }

    public static function sanitizeAndValidateEmail($email)
    {
        // Full clean: remove invisible junk + normalize
        $sanitized = strtolower(filter_var(self::cleanString($email), FILTER_SANITIZE_EMAIL));

        // Validate as email, return null if invalid
        return filter_var($sanitized, FILTER_VALIDATE_EMAIL) ?: null;
    }

    public static function slugify(string $input): string
    {
        // Replace non-alphanumeric (excluding hyphen) with hyphen
        $slug = preg_replace("/[^a-zA-Z0-9-]/", "-", trim($input));
        // Collapse multiple hyphens into one
        $slug = preg_replace("/-+/", "-", $slug);
        // Trim leading and trailing hyphens
        return trim($slug, "-");
    }


    public static function generateRandomString(int $length = 32): string
    {
        try {
            $bytes = random_bytes($length);
            $result = '';

            // Create a character set of alphanumeric characters
            $charSet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $charSetLength = strlen($charSet);

            // Convert random bytes to characters from our character set
            for ($i = 0; $i < $length; $i++) {
                $result .= $charSet[ord($bytes[$i]) % $charSetLength];
            }

            return $result;
        } catch (Throwable $e) {
            throw new Exception('Failed to generate random string: ' . $e->getMessage());
        }
    }


    public static function generateRandomNumber(int $length = 8): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }
        return $result;
    }

    public static function sanitizeInput($input)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $input));
    }

    public static function generateFakeEmailId($uniqueId, $participantName)
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        $eptDomain = !empty($conf->domain) ? rtrim($conf->domain, "/") : 'ept';
        $sanitizedUniqueId = self::sanitizeInput(self::cleanString($uniqueId));
        $sanitizedParticipantName = self::sanitizeInput(self::cleanString($participantName));
        $host = parse_url($eptDomain, PHP_URL_HOST) ?: 'ept';
        return "{$sanitizedUniqueId}@$host";
    }

    public static function randomHexColor(): string
    {
        $hexColorPart = fn() => str_pad(dechex(random_int(0, 255)), 2, '0', STR_PAD_LEFT);

        return strtoupper($hexColorPart() . $hexColorPart() . $hexColorPart());
    }

    public static function makeDirectory($path, $mode = 0755, $recursive = true): bool
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($path)) {
            return true; // Directory already exists
        }

        try {
            $filesystem->mkdir($path, $mode); // Handles recursive creation automatically
            return true;
        } catch (Throwable $exception) {
            return false; // Directory creation failed
        }
    }


    public static function removeDirectory($dirname): bool
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($dirname)) {
            return false; // Directory doesn't exist, so nothing to remove
        }

        try {
            // This handles both files and directories recursively
            $filesystem->remove($dirname);
            return true; // Removal was successful
        } catch (Throwable $exception) {
            return false; // Removal failed
        }
    }

    //dump the contents of a variable to the error log in a readable format
    public static function dumpToErrorLog($object = null, $useVarDump = true): void
    {
        ob_start();
        if ($useVarDump) {
            var_dump($object);
            $output = ob_get_clean();
            // Remove newline characters
            $output = str_replace("\n", "", $output);
        } else {
            print_r($object);
            $output = ob_get_clean();
        }

        // Additional context
        $timestamp = date('Y-m-d H:i:s');
        $output = "[{$timestamp}] " . $output;

        error_log($output);
    }

    /**
     * Checks if the array contains any null or empty string values.
     *
     * @param array $array The array to check.
     * @return bool Returns true if any value is null or an empty string, false otherwise.
     */
    public static function hasEmpty(array $array): bool
    {
        foreach ($array as $value) {
            if ($value === null || trim((string) $value) === "") {
                return true;
            }
        }
        return false;
    }

    public static function fileExists($filePath): bool
    {
        $filesystem = new Filesystem();

        // The exists() method checks if the file exists (whether it's a file or directory)
        return $filesystem->exists($filePath) && is_file($filePath);
    }
    public static function imageExists($filePath): bool
    {
        // Check if the file exists and is a file
        if (!self::fileExists($filePath)) {
            return false;
        }

        // Suppress errors from getimagesize() in case it's not a valid image
        $imageInfo = @getimagesize($filePath);

        // Check if getimagesize() was successful and if the image type is valid
        return $imageInfo !== false && isset($imageInfo[2]) && $imageInfo[2] > 0;
    }

    public static function getMimeType($file, $allowedMimeTypes)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);

        return in_array($mime, $allowedMimeTypes) ? $mime : false;
    }

    public static function generateCsv($headings, $data, $filename, $delimiter = ',', $enclosure = '"')
    {
        $handle = fopen($filename, 'w'); // Open file for writing

        // Write the UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");

        // The headings first
        if (!empty($headings)) {
            fputcsv($handle, $headings, $delimiter, $enclosure);
        }
        // Then the data
        if (!empty($data)) {
            foreach ($data as $line) {
                fputcsv($handle, $line, $delimiter, $enclosure);
            }
        }

        //Clear Memory
        unset($data);
        fclose($handle);
        return $filename;
    }

    public static function generateCsvRow($handle, $row, $delimiter = ',', $enclosure = '"')
    {
        if ($handle) {
            fputcsv($handle, $row, $delimiter, $enclosure);
        }
    }

    public static function initializeCsv($filename, $headings, $delimiter = ',', $enclosure = '"')
    {
        $handle = fopen($filename, 'w'); // Open file for writing

        // Write the headings
        if (!empty($headings)) {
            self::generateCsvRow($handle, $headings, $delimiter, $enclosure);
        }

        return $handle;
    }

    public static function finalizeCsv($handle)
    {
        fclose($handle);
    }

    public static function removeFromAssociativeArray(array $fullArray, array $unwantedKeys)
    {
        return array_diff_key($fullArray, array_flip($unwantedKeys));
    }

    // Updates entries in targetArray with values from sourceArray where keys exist in targetArray
    public static function updateFromArray(?array $targetArray, ?array $sourceArray)
    {

        if (empty($targetArray) || empty($sourceArray)) {
            return $targetArray;
        }
        return array_merge($targetArray, array_intersect_key($sourceArray, $targetArray));
    }


    public static function getMimeTypeStrings(array $extensions): array
    {
        $mimeTypesMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'html' => 'text/html',
            'xml' => 'application/xml',
            'json' => 'application/json'
        ];

        $mappedMimeTypes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower($ext);
            if (isset($mimeTypesMap[$ext])) {
                $mappedMimeTypes[$ext] = $mimeTypesMap[$ext];
            } else {
                // If it's already a MIME type, just use it
                $mappedMimeTypes[$ext] = $ext;
            }
        }
        return $mappedMimeTypes;
    }

    public static function arrayToGenerator(array $array)
    {
        foreach ($array as $item) {
            yield $item;
        }
    }

    public static function removeMatchingElements(array $array, array $removeArray): array
    {
        return array_values(array_diff($array, $removeArray));
    }

    public static function arrayEmptyStringsToNull(?array $array, bool $convertEmptyJson = false): array
    {
        if (!$array) {
            return $array;
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = empty($value) ? null : self::arrayEmptyStringsToNull($value, $convertEmptyJson);
            } elseif ($value === '' || ($convertEmptyJson && in_array($value, ['{}', '[]'], true))) {
                $value = null;
            }
        }
        return $array;
    }


    // Generate a UUIDv4 with an optional extra string
    public static function generateUUID($attachExtraString = true): string
    {
        $uuid = Uuid::v4()->toRfc4122();
        if ($attachExtraString) {
            $uuid .= '-' . self::generateRandomString(6);
        }
        return $uuid;
    }

    // Generate a UUIDv5 with a name and namespace
    public static function generateUUIDv5($name, $namespace = null): string
    {
        if ($namespace === null) {
            $namespace = Uuid::fromString(Uuid::NAMESPACE_OID);
        } elseif (is_string($namespace)) {
            $namespace = Uuid::fromString($namespace);
        }
        return Uuid::v5($namespace, $name)->toRfc4122();
    }


    // Generate a ULID
    public static function generateULID($attachExtraString = true): string
    {
        $ulid = (new Ulid())->toRfc4122();
        if ($attachExtraString) {
            $ulid .= '-' . self::generateRandomString(6);
        }
        return $ulid;
    }

    /**
     * String to a file inside a zip archive.
     *
     * @param string $stringData
     * @param string $fileName The FULL PATH of the file inside the zip archive.
     * @return bool Returns true on success, false on failure.
     */
    public static function dataToZippedFile(string $stringData, string $fileName): bool
    {
        if (empty($stringData) || empty($fileName)) {
            return false;
        }

        $zip = new ZipArchive();
        $zipPath = $fileName . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFromString(basename($fileName), $stringData);
            $result = $zip->status == ZipArchive::ER_OK;
            $zip->close();
            return $result;
        }

        return false;
    }

    /**
     * Unzips an archive and returns contents of a file inside it.
     *
     * @param string $zipFile The path to the zip file.
     * @param string $fileName The name of the JSON file inside the zip archive.
     * @return string
     */
    public static function getDataFromZippedFile(string $zipFile, string $fileName): string
    {
        if (!file_exists($zipFile)) {
            return "{}";
        }
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === true) {
            $json = $zip->getFromName($fileName);
            $zip->close();

            return $json !== false ? $json : "{}";
        } else {
            return "{}";
        }
    }

    public static function getFileExtension($filename): string
    {
        if (empty($filename)) {
            return '';
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return strtolower($extension);
    }


    public static function isFileType(string $filePath, string $expectedMimeType): bool
    {
        // Check if the file exists
        if (!file_exists($filePath)) {
            return false;
        }

        // Get the MIME type of the file
        $actualMimeType = mime_content_type($filePath);

        // Compare the actual MIME type with the expected MIME type
        return $actualMimeType === $expectedMimeType;
    }

    public static function displayProgressBar($current, $total = null, $size = 30)
    {
        static $startTime;

        // Start the timer
        if (empty($startTime)) {
            $startTime = time();
        }

        // Calculate elapsed time
        $elapsed = time() - $startTime;

        if ($total !== null) {
            // Calculate the percentage
            $progress = ($current / $total);
            $bar = floor($progress * $size);

            // Generate the progress bar string
            $progressBar = str_repeat('=', $bar) . str_repeat(' ', $size - $bar);

            // Output the progress bar
            printf("\r[%s] %d%% Complete (%d/%d) - %d sec elapsed", $progressBar, $progress * 100, $current, $total, $elapsed);
        } else {
            // Output the current progress without percentage
            printf("\rProcessed %d items - %d sec elapsed", $current, $elapsed);
        }

        // Flush output
        if ($total !== null && $current === $total) {
            echo "\n";
        }
    }

    public static function removeDuplicates($input)
    {
        // Check if the input is a string
        if (is_string($input)) {
            // Split the string into an array
            $inputArray = explode(',', $input);
        } elseif (is_array($input)) {
            // Use the input array directly
            $inputArray = $input;
        } else {
            // Invalid input type
            return $input;
        }

        // Remove duplicate values
        $uniqueArray = array_unique($inputArray);

        // Optionally, remove any empty values
        $uniqueArray = array_filter($uniqueArray);

        // Return the same type as the input
        if (is_string($input)) {
            // Convert the array back to a comma-separated string
            return implode(',', $uniqueArray);
        } else {
            // Return the unique array
            return $uniqueArray;
        }
    }

    public static function getMacAddress(): ?string
    {
        $commands = (strncasecmp(PHP_OS, 'WIN', 3) == 0)
            ? ['getmac']
            : ['ifconfig -a', 'ip addr show'];

        foreach ($commands as $command) {
            $output = [];
            @exec($command, $output);

            foreach ($output as $line) {
                if (preg_match('/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i', $line, $matches)) {
                    return $matches[0]; // Return the MAC address as soon as it's found
                }
            }
        }

        return null; // Return null if no MAC address was found
    }

    public static function getLockFile($fileName, $lockFileLocation = TEMP_UPLOAD_PATH): string
    {
        if (file_exists($fileName) || str_contains($fileName, DIRECTORY_SEPARATOR)) {
            $fullPath = realpath($fileName);
            if ($fullPath === false) {
                throw new InvalidArgumentException("Invalid file path provided.");
            }
        } else {
            $fullPath = $fileName;
        }

        $sanitizedFullPath = preg_replace('/[^A-Za-z0-9_\-]/', '-', $fullPath);
        return $lockFileLocation . '/' . strtolower($sanitizedFullPath) . '.lock';
    }

    public static function isLockFileExpired($lockFile, $maxAgeInSeconds = 3600): bool
    {
        if (!file_exists($lockFile)) {
            return false;
        }

        $fileAge = time() - filemtime($lockFile);
        return $fileAge > $maxAgeInSeconds;
    }


    public static function deleteLockFile($fileName, $lockFileLocation = TEMP_UPLOAD_PATH): bool
    {
        $lockFile = self::getLockFile($fileName, $lockFileLocation);

        if (file_exists($lockFile)) {
            return unlink($lockFile);
        }

        return false;
    }

    public static function touchLockFile($fileName, $lockFileLocation = TEMP_UPLOAD_PATH): bool
    {
        $lockFile = self::getLockFile($fileName, $lockFileLocation);
        return touch($lockFile);
    }

    /**
     * Checks if the given string is base64 encoded.
     *
     * @param string $data The string to check.
     * @return bool Returns true if $data is base64 encoded, false otherwise.
     */
    public static function isBase64(string $data): bool
    {
        // Ensure the length is a multiple of 4 by adding necessary padding
        $paddedData = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + 4 - (strlen($data) % 4), '=');

        $decodedData = base64_decode($paddedData, true);

        // Check if decoding was successful and if re-encoding matches (ignoring padding)
        return $decodedData !== false && base64_encode($decodedData) === $paddedData;
    }

    /**
     * Safely constructs a file path by combining predefined and user-supplied components.
     * Recursively creates the folder structure if it doesn't exist.
     *
     * @param string $baseDirectory The predefined base directory.
     * @param array $pathComponents An array of path components, where some may be user-supplied.
     * @return string|bool Returns the constructed, sanitized path if valid, or false if the path is invalid.
     */
    public static function buildSafePath($baseDirectory, array $pathComponents)
    {
        if (!is_dir($baseDirectory) && !self::makeDirectory($baseDirectory)) {
            return false; // Failed to create the directory
        }

        // Normalize the base directory
        $baseDirectory = realpath($baseDirectory);

        // Clean and sanitize each component of the path
        $cleanComponents = [];
        foreach ($pathComponents as $component) {
            // Remove dangerous characters from user-supplied components
            $cleanComponent = preg_replace('/[^a-zA-Z0-9-_]/', '', $component);
            $cleanComponents[] = $cleanComponent;
        }

        // Join the base directory with the cleaned components to create the full path
        $fullPath = $baseDirectory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $cleanComponents);

        // Check if the directory exists, if not, create it recursively
        if (!is_dir($fullPath) && !self::makeDirectory($fullPath)) {
            return false; // Failed to create the directory
        }

        return realpath($fullPath); // Clean and validated path
    }

    /**
     * Cleans up the input file name, removing any unsafe characters and returning the base file name with its extension.
     *
     * @param string $filePath The input file name or full path.
     * @return string The cleaned base file name with its extension.
     */
    public static function cleanFileName($filePath)
    {
        // Extract the base file name (removes the path if provided)
        $baseFileName = basename($filePath);

        // Separate the file name from its extension
        $extension = strtolower(pathinfo($baseFileName, PATHINFO_EXTENSION));
        $fileNameWithoutExtension = pathinfo($baseFileName, PATHINFO_FILENAME);

        // Clean the file name, keeping only alphanumeric characters, dashes, and underscores
        $cleanFileName = preg_replace('/[^a-zA-Z0-9-_]/', '', $fileNameWithoutExtension);

        // Reconstruct the file name with its extension
        return $cleanFileName . ($extension ? '.' . $extension : '');
    }

    public static function startDbProfiler()
    {
        Zend_Db_Table::getDefaultAdapter()->getProfiler()->setEnabled(true);
    }

    public static function stopDbProfiler()
    {
        $profiler = Zend_Db_Table::getDefaultAdapter()->getProfiler();
        foreach ($profiler->getQueryProfiles() as $query) {
            error_log(sprintf('[%.4fs] %s', $query->getElapsedSecs(), $query->getQuery()));
        }
    }
}
