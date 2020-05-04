<?php

include_once 'CronInit.php';

$conf = new Zend_Config_Ini(APPLICATION_PATH . '/configs/application.ini', APPLICATION_ENV);





if ( ! function_exists('glob_recursive'))
{
    // Does not support flag GLOB_BRACE        
   function glob_recursive($pattern, $flags = 0)
   {
     $files = glob($pattern, $flags);
     foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir)
     {
       $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
     }
     return $files;
   }
}


try {
    
    $db = Zend_Db::factory($conf->resources->db);
    Zend_Db_Table::setDefaultAdapter($db);
    
    $output = array();
    
    $query = $db->select()->from(array('p' => 'participant'),array('unique_identifier'));
							// ->where("shipment_id IN (13,14,15,16)")
							// ->order("s.scheme_type");
    
    
	$pResult = $db->fetchCol($query);
	
	foreach($pResult as $pRow){
		$filePath = UPLOAD_PATH.DIRECTORY_SEPARATOR.'certificate'.DIRECTORY_SEPARATOR.'*'.$pRow.'*'.'.pdf';
		$files = glob_recursive($filePath);
		if(!empty($files)){
			

			$participantFolder = DOWNLOADS_FOLDER.DIRECTORY_SEPARATOR.$pRow;

			if(!is_dir($participantFolder)){
				mkdir($participantFolder);
			}
			
			foreach($files as $f){
				$fileName = basename($f);
				copy($f,$participantFolder.DIRECTORY_SEPARATOR.$fileName);
			}

			

		}
		
	}

   
    
    
} catch (Exception $e) {
    error_log($e->getMessage());
    error_log($e->getTraceAsString());
    error_log('whoops! Something went wrong in scheduled-jobs/GenerateCertificate.php');
}