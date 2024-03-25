#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');
use PhpMyAdmin\SqlParser\Parser;

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

// Ensure the script only runs for VLSM APP VERSION >= 4.4.3
if (version_compare(APP_VERSION, '4.4.3', '<')) {
    exit("This script requires VERSION 4.4.3 or higher. Current version: " . APP_VERSION . "\n");
}

// Define the logs directory path
$logsDir = ROOT_PATH . "/logs";

// Initialize a flag to determine if logging is possible
$canLog = false;

// Check if the directory exists
if (!file_exists($logsDir)) {
    // Attempt to create the directory
    if (!mkdir($logsDir, 0755, true)) {
        echo "Failed to create directory: $logsDir\n";
    } else {
        echo "Directory created: $logsDir\n";
        $canLog = is_readable($logsDir) && is_writable($logsDir);
    }
} else {
    // Check if the directory is readable and writable
    $canLog = is_readable($logsDir) && is_writable($logsDir);
}



$db = Zend_Db::factory($conf->resources->db);
Zend_Db_Table::setDefaultAdapter($db);


$query = $db->select()->from("system_config", array('value'))->where('config = "app_version"');
$result = $db->fetchRow($query);
$migrationFiles = glob(DB_PATH . '/migrations/*.sql');

$currentVersion = $result['value'];
// Extract version numbers and map them to files
$versions = array_map(function ($file) {
    return basename($file, '.sql');
}, $migrationFiles);

// Sort versions
usort($versions, 'version_compare');


$options = getopt("yq");  // Parse command line options for -y and -q
$autoContinueOnError = isset($options['y']);  // Set a flag if -y option is provided

// Only output messages if -q option is not provided
$quietMode = isset($options['q']);  // Set a flag if -q option is provided

if ($quietMode) {
    error_reporting(0);  // Suppress warnings and notices
}
foreach ($versions as $version) {
    $file = DB_PATH . '/migrations/' . $version . '.sql';

    if (version_compare($version, $currentVersion, '>=')) {
        //if (!$quietMode) {
        echo "Migrating to version $version...\n";
        //}

        $sql_contents = file_get_contents($file);
        $parser = new Parser($sql_contents);

        // $db->beginTransaction();  // Start a new transaction
        $db->query("SET FOREIGN_KEY_CHECKS = 0;"); // Disable foreign key checks
        $errorOccurred = false;
        foreach ($parser->statements as $statement) {
            try {
                $query = $statement->build();
                $db->query($query);
                $errorOccurred = false;
            } catch (Exception $e) {

                $message = "Exception : " . $e->getMessage() . PHP_EOL;

                $errorOccurred = true;
                if (!$quietMode) {
                    if ($canLog) {
                        error_log('error =>' .$message);
                    }
                    echo $message;
                }
            }
            /* if ($db->getLastErrno() > 0 || $errorOccurred) {
                $dbMessage = "Error executing query: " . $db->getLastErrno() . ":" . $db->getLastError() . PHP_EOL . $db->getLastQuery() . PHP_EOL;
                if (!$quietMode) {
                    echo $dbMessage;
                    if ($canLog) {
                        error_log('error => ' .$dbMessage);
                    }
                }

                if (!$autoContinueOnError) {  // Only prompt user if -y option is not provided
                    echo "Do you want to continue? (y/n): ";
                    $handle = fopen("php://stdin", "r");
                    $response = trim(fgets($handle));
                    fclose($handle);
                    if (strtolower($response) !== 'y') {
                        $db->rollBack();  // Rollback the transaction on error
                        exit("Migration aborted by user.\n");
                    }
                }
            } */
        }
        unset($sql_contents, $parser);

        //if (!$quietMode) { // Only output messages if -q option is not provided
        echo "Migration to version $version completed." . PHP_EOL;
        //}

        //$db->where('name', 'sc_version')->update('system_config', ['value' => $version]);
        $db->fetchAll("SET FOREIGN_KEY_CHECKS = 1;"); // Re-enable foreign key checks
        $db->commit();  // Commit the transaction if no error occurred
    }

    gc_collect_cycles();
}
