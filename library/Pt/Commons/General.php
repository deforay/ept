<?php

class Pt_Commons_General
{

    public static function isDateValid($date): bool
    {
        $date = trim($date);

        if (empty($date) || 'undefined' === $date || 'null' === $date) {
            $response = false;
        } else {
            try {
                $dateTime = new DateTimeImmutable($date);
                $errors = DateTimeImmutable::getLastErrors();
                if (
                    !empty($errors['warning_count'])
                    || !empty($errors['error_count'])
                ) {
                    $response = false;
                } else {
                    $response = true;
                }
            } catch (Exception $e) {
                error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
                error_log($e->getTraceAsString());
                $response = false;
            }
        }

        return $response;
    }

    public static function isoDateFormat($date, $includeTime = false)
    {
        if (false === self::isDateValid($date)) {
            return null;
        } else {
            $format = "Y-m-d";
            if ($includeTime === true) {
                $format = $format . " H:i:s";
            }
            return (new DateTimeImmutable($date))->format($format);
        }
    }

    // returns true if $needle is a substring of $haystack
    public static function stringContains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    // Returns the given date in d-M-Y format
    // (with or without time depending on the $includeTime parameter)
    public static function humanReadableDateFormat($date, $includeTime = false, $format = "d-M-Y")
    {
        $date = trim($date);
        if (false === self::isDateValid($date)) {
            return null;
        } else {

            if ($includeTime === true) {
                $format = $format . " H:i";
            }

            return (new DateTimeImmutable($date))->format($format);
        }
    }

    public function copyDirectoryContents($source, $destination, $deleteSource = false)
    {
        if (!is_dir($destination)) {
            $oldumask = umask(0);
            mkdir($destination, 01777); // so you get the sticky bit set
            umask($oldumask);
        }
        $dir_handle = @opendir($source) or die("Unable to open");
        while ($file = readdir($dir_handle)) {
            if ($file != "." && $file != ".." && !is_dir("$source/$file"))
                copy("$source/$file", "$destination/$file");
        }
        closedir($dir_handle);
    }

    public function removeDirectory($dirname)
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }

        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }

        // Loop through the folder
        $dir = dir($dirname);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            // Recurse
            $this->removeDirectory($dirname . DIRECTORY_SEPARATOR . $entry);
        }

        // Clean up
        $dir->close();
        return rmdir($dirname);
    }

    public static function getDateTime()
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        // Try to get timezone from config, then from the server, else default to UTC
        $timezone = !empty($conf->timezone) ? $conf->timezone : (date_default_timezone_get() ?: 'UTC');

        $date = new DateTime('now', new DateTimeZone($timezone));
        return $date->format('Y-m-d H:i:s');
    }


    public static function getVersion()
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        return $conf->app->version;
    }

    public static function excelDateFormat($date)
    {

        if (empty($date) || $date == "" || $date == "0000-00-00") {
            return "";
        } else {
            $dateTimeArray = explode(' ', $date);
            $time = isset($dateTimeArray[1]) ? " " . $dateTimeArray[1] : '';
            $dateArray = explode('-', $dateTimeArray[0]);
            $newDate = $dateArray[2] . "/";
            return $newDate .= $dateArray[1] . "/" . $dateArray[0] . $time;
        }
    }

    public function getMonthsInRange($startDate, $endDate, $type = "")
    {
        $months = [];
        while (strtotime($startDate) <= strtotime($endDate)) {
            //$monthYear=array('year' => date('Y', strtotime($startDate)),'month' => date('M', strtotime($startDate)),);
            $monthYear = date('M', strtotime($startDate)) . "-" . date('Y', strtotime($startDate));
            $months[$monthYear] = $monthYear;
            $startDate = date('d M Y', strtotime($startDate . '+ 1 month'));
        }
        if ($type == "dashboard") {
            $monthYear = date('M', strtotime($endDate)) . "-" . date('Y', strtotime($endDate));
            $months[$monthYear] = $monthYear;
        }
        return $months;
    }

    public function zipFolder($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
                    continue;
                }

                $file = realpath($file);

                if (is_dir($file) === true) {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                } elseif (is_file($file) === true) {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        } elseif (is_file($source) === true) {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }
    public function rmdirRecursive($dir)
    {
        foreach (scandir($dir) as $file) {
            if ('.' === $file || '..' === $file) continue;
            if (is_dir("$dir/$file")) {
                $this->rmdirRecursive("$dir/$file");
            } else {
                unlink("$dir/$file");
            }
        }
        rmdir($dir);
    }

    public function checkFolder($base, $pattern, $flags)
    {
        if (substr($base, -1) !== DIRECTORY_SEPARATOR) {
            $base .= DIRECTORY_SEPARATOR;
        }

        $files = glob($base . $pattern, $flags);
        if (!is_array($files)) {
            $files = [];
        }

        $dirs = glob($base . '*', GLOB_ONLYDIR | GLOB_NOSORT | GLOB_MARK);
        if (!is_array($dirs)) {
            return $files;
        }

        foreach ($dirs as $dir) {
            $dirFiles = $this->checkFolder($dir, $pattern, $flags);
            $files = array_merge($files, $dirFiles);
        }

        return $files;
    }


    public function recuriveSearch($base, $pattern, $flags = 0)
    {
        $glob_nocheck = $flags & GLOB_NOCHECK;
        $flags = $flags & ~GLOB_NOCHECK;

        $files = $this->checkFolder($base, $pattern, $flags);

        if ($glob_nocheck && count($files) === 0) {
            return [$pattern];
        }

        return $files;
    }


    // Generate a ULID
    public static function generateULID($attachExtraString = true): string
    {
        return Pt_Commons_MiscUtility::generateULID($attachExtraString);
    }
    // Fetch Global Config
    public static function getConfig($name)
    {
        $gc = new Application_Model_DbTable_GlobalConfig();
        return $gc->getValue($name);
    }
}
