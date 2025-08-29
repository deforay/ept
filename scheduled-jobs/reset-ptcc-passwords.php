<?php

// scheduled-jobs/reset-ptcc-passwords.php

ini_set('memory_limit', '-1');

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'CronInit.php');

$generalModel = new Pt_Commons_General();

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);
$customConfig = new Zend_Config_Ini(APPLICATION_PATH . '/configs/config.ini', APPLICATION_ENV);

try {
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);

    // Initialize the data manager service
    $dataManagerService = new Application_Service_DataManagers();

    // Select all PTCC data managers with country information
    $query = $db->select()
        ->from(['dm' => 'data_manager'], '*')
        ->joinLeft(['pcm' => 'ptcc_countries_map'], 'dm.dm_id = pcm.ptcc_id', ['pcm.state', 'pcm.district'])
        ->joinLeft(['c' => 'countries'], 'pcm.country_id = c.id', ['c.iso_name'])
        ->where('dm.data_manager_type = ?', 'ptcc')
        ->where('dm.status = ?', 'active');

    $ptccManagers = $db->fetchAll($query);

    echo "Found " . count($ptccManagers) . " active PTCC data managers\n";

    if (empty($ptccManagers)) {
        echo "No active PTCC data managers found. Exiting...\n";
        exit();
    }

    // Prepare CSV data
    $csvData = [];
    $csvData[] = ['Primary Email', 'New Password', 'First Name', 'Last Name', 'Country', 'State', 'District', 'Status']; // Header row

    $successCount = 0;
    $errorCount = 0;

    foreach ($ptccManagers as $manager) {
        $primaryEmail = $manager['primary_email'];
        $firstName = $manager['first_name'] ?? '';
        $lastName = $manager['last_name'] ?? '';
        $country = $manager['iso_name'] ?? '';
        $state = $manager['state'] ?? '';
        $district = $manager['district'] ?? '';
        $status = 'Failed';

        try {
            // Generate temporary password
            $tempPassword = Pt_Commons_MiscUtility::generateTempPassword($primaryEmail);

            // Reset password from admin
            $resetResult = $dataManagerService->resetPasswordFromAdmin([
                'primaryMail' => $primaryEmail,
                'password' => $tempPassword
            ], forcePasswordReset: true);

            if ($resetResult) {
                $status = 'Success';
                $successCount++;
                echo "Successfully reset password for: $primaryEmail\n";
            } else {
                $status = 'Failed - Reset returned false';
                $errorCount++;
                echo "Failed to reset password for: $primaryEmail\n";
            }

            // Add to CSV data
            $csvData[] = [$primaryEmail, $tempPassword, $firstName, $lastName, $country, $state, $district, $status];

        } catch (Exception $e) {
            $status = 'Failed - ' . $e->getMessage();
            $errorCount++;
            error_log("Error resetting password for $primaryEmail: " . $e->getMessage());
            echo "Error resetting password for $primaryEmail: " . $e->getMessage() . "\n";

            // Still add to CSV with error status
            $csvData[] = [$primaryEmail, 'N/A', $firstName, $lastName, $country, $state, $district, $status];
        }
    }

    // Create CSV file
    $csvFileName = 'ptcc-password-reset-' . date('Y-m-d_H-i-s') . '.csv';
    $csvFilePath = TEMP_UPLOAD_PATH . DIRECTORY_SEPARATOR . $csvFileName;

    // Ensure the directory exists
    if (!is_dir(TEMP_UPLOAD_PATH)) {
        mkdir(TEMP_UPLOAD_PATH, 0777, true);
    }

    $csvFile = fopen($csvFilePath, 'w');
    if ($csvFile === false) {
        throw new Exception("Could not create CSV file at: $csvFilePath");
    }

    // Write CSV data
    foreach ($csvData as $row) {
        fputcsv($csvFile, $row);
    }

    fclose($csvFile);

    // Summary
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "PASSWORD RESET SUMMARY\n";
    echo str_repeat("=", 50) . "\n";
    echo "Total active PTCC managers: " . count($ptccManagers) . "\n";
    echo "Successful resets: $successCount\n";
    echo "Failed resets: $errorCount\n";
    echo "CSV file created: $csvFilePath\n";
    echo str_repeat("=", 50) . "\n";

} catch (Exception $e) {
    error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
    error_log($e->getTraceAsString());
    echo "Script failed with error: " . $e->getMessage() . "\n";
}
