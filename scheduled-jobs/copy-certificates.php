<?php

require_once __DIR__ . '/../cli-bootstrap.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);




$generalModel = new Pt_Commons_General();


try {

  $db = Zend_Db::factory($conf->resources->db);
  Zend_Db_Table::setDefaultAdapter($db);

  $output = [];

  $query = $db->select()
    ->from(['p' => 'participant'], ['unique_identifier'])
    ->where("status like 'active'");
  // ->where("shipment_id IN (13,14,15,16)")
  // ->order("s.scheme_type");


  $pResult = $db->fetchCol($query);

  foreach ($pResult as $pRow) {

    $filePath = realpath(TEMP_UPLOAD_PATH) . DIRECTORY_SEPARATOR . 'certificates' . DIRECTORY_SEPARATOR;
    $files = $generalModel->recuriveSearch($filePath, "$pRow-*.pdf");
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
  error_log("ERROR : {$e->getFile()}:{$e->getLine()} : {$e->getMessage()}");
  error_log($e->getTraceAsString());
}
