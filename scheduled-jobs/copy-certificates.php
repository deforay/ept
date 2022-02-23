<?php

include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);


function check_folder($base, $pattern, $flags)
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
    $dirFiles = check_folder($dir, $pattern, $flags);
    $files = array_merge($files, $dirFiles);
  }

  return $files;
}

function recuriveSearch($base, $pattern, $flags = 0)
{
  $glob_nocheck = $flags & GLOB_NOCHECK;
  $flags = $flags & ~GLOB_NOCHECK;

  $files = check_folder($base, $pattern, $flags);

  if ($glob_nocheck && count($files) === 0) {
    return [$pattern];
  }

  return $files;
}


try {

  $db = Zend_Db::factory($conf->resources->db);
  Zend_Db_Table::setDefaultAdapter($db);

  $output = array();

  $query = $db->select()
    ->from(array('p' => 'participant'), array('unique_identifier'))
    ->where("status like 'active'");
  // ->where("shipment_id IN (13,14,15,16)")
  // ->order("s.scheme_type");


  $pResult = $db->fetchCol($query);

  foreach ($pResult as $pRow) {

    $filePath = realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR;
    $files = recuriveSearch($filePath, "$pRow-*.pdf");
    // Zend_Debug::dump("$pRow*.pdf");
    // Zend_Debug::dump($filePath);
    // Zend_Debug::dump($files);
    // continue;
    if (!empty($files)) {

      $participantFolder = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . $pRow;

      if (!is_dir($participantFolder)) {
        mkdir($participantFolder, 0777, true);
      }

      foreach ($files as $f) {
        $fileName = basename($f);
        copy($f, $participantFolder . DIRECTORY_SEPARATOR . $fileName);
      }
    }
  }
} catch (Exception $e) {
  error_log($e->getMessage());
  error_log($e->getTraceAsString());
  error_log('whoops! Something went wrong in scheduled-jobs/GenerateCertificate.php');
}
