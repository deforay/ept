<?php

/**
 * General functions
 *
 * @author Amit Dugar <amit@deforay.com>
 */
class Pt_Commons_General
{

    /**
     * Used to format date from dd-mmm-yyyy to yyyy-mm-dd for storing in database
     *
     */
    public static function dateFormat($date)
    {
        if (!isset($date) || $date == null || $date == "" || $date == "0000-00-00") {
            return "0000-00-00";
        } else {
            $dateArray = explode('-', $date);
            if (sizeof($dateArray) == 0) {
                return;
            }
            $newDate = $dateArray[2] . "-";

            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = 1;
            $mon += array_search(ucfirst($dateArray[1]), $monthsArray);

            if (strlen($mon) == 1) {
                $mon = "0" . $mon;
            }
            return $newDate .= $mon . "-" . $dateArray[0];
        }
    }

    // returns true if $needle is a substring of $haystack
    public static function stringContains($needle, $haystack)
    {
        return strpos($haystack, $needle) !== false;
    }

    public static function humanDateFormat($date)
    {

        if ($date == null || $date == "" || $date == "0000-00-00" || Pt_Commons_General::stringContains("0000-00-00", $date)) {
            return "";
        } else {
            $dateArray = explode('-', $date);
            $newDate = $dateArray[2] . "-";

            $monthsArray = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
            $mon = $monthsArray[$dateArray[1] - 1];

            return $newDate .= $mon . "-" . $dateArray[0];
        }
    }

    public static function file_download($file, $name, $mime_type)
    {

        if (!is_readable($file))
            die('File not found or inaccessible!');

        $size = filesize($file);
        $name = rawurldecode($name);

        @ob_end_clean(); //turn off output buffering to decrease cpu usage
        // required for IE, otherwise Content-Disposition may be ignored
        if (ini_get('zlib.output_compression')) {
            ini_set('zlib.output_compression', 'Off');
        }

        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . $name . '"');
        header("Content-Transfer-Encoding: binary");
        header('Accept-Ranges: bytes');

        /* The three lines below basically make the download non-cacheable */
        header("Cache-control: private");
        header('Pragma: private');
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        // multipart-download and download resuming support
        if (isset($_SERVER['HTTP_RANGE'])) {
            list($a, $range) = explode("=", $_SERVER['HTTP_RANGE'], 2);
            list($range) = explode(",", $range, 2);
            list($range, $range_end) = explode("-", $range);
            $range = intval($range);

            if (!$range_end) {
                $range_end = $size - 1;
            } else {
                $range_end = intval($range_end);
            }

            $new_length = $range_end - $range + 1;

            header("HTTP/1.1 206 Partial Content");
            header("Content-Length: $new_length");
            header("Content-Range: bytes $range-$range_end/$size");
        } else {
            $new_length = $size;
            header("Content-Length: " . $size);
        }

        /* output the file itself */
        $chunksize = 1 * (1024 * 1024); // 1MB, can be tweaked if needed
        $bytes_send = 0;

        if ($file = fopen($file, 'r')) {
            if (isset($_SERVER['HTTP_RANGE'])) {
                fseek($file, $range);
            }

            while (!feof($file) && (!connection_aborted()) && ($bytes_send < $new_length)) {
                $buffer = fread($file, $chunksize);
                print($buffer); //echo($buffer); // is also possible
                flush();
                $bytes_send += strlen($buffer);
            }

            fclose($file);
        } else {
            die('Error - can not open file.');
        }

        die();
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

    public function moveDirectoryContents($source, $destination, $deleteSource = false)
    {
        if (!is_dir($destination)) {
            $oldumask = umask(0);
            mkdir($destination, 01777); // so you get the sticky bit set 
            umask($oldumask);
        }
        $dir_handle = @opendir($source) or die("Unable to open");
        while ($file = readdir($dir_handle)) {
            if ($file != "." && $file != ".." && !is_dir("$source/$file"))
                rename("$source/$file", "$destination/$file");
        }

        closedir($dir_handle);

        if ($deleteSource) {
            rmdir($source);
        }
    }

    function removeDirectory($dirname)
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
        $date = new DateTime(date('Y-m-d H:i:s'), new DateTimeZone($conf->timezone));
        return $date->format('Y-m-d H:i:s');
    }

    public static function getVersion()
    {
        $conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
        return $conf->app->version;
    }

    public static function excelDateFormat($date)
    {

        if ($date == null || $date == "" || $date == "0000-00-00") {
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
}
