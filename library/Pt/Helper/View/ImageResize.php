<?php

class Pt_Helper_View_ImageResize extends Zend_View_Helper_Abstract {

    public function imageResize($imagePath, $newWidth, $newHeight, $option="auto") {
        
        $imagePath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$imagePath;
        
        
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        
        $tempImgFolder = TEMP_UPLOAD_PATH.DIRECTORY_SEPARATOR."img";
        
        if (!file_exists($tempImgFolder) && !is_dir($tempImgFolder)) {
            mkdir($tempImgFolder);
            $indexFile = $tempImgFolder.DIRECTORY_SEPARATOR."index.php";
            $f = fopen($indexFile, 'w') or die("can't open file");
            fclose($f);
        }
        
        $fileUrl = "temporary/img/";
        $imageName = sha1($imagePath.$newWidth.$newHeight.$option).".".$extension;
        
        if(file_exists($tempImgFolder. DIRECTORY_SEPARATOR . $imageName)){
            return $fileUrl.$imageName;
        }else{
            $resizeObj = new Pt_Commons_ImageResize($imagePath);
            $resizeObj->resizeImage($newWidth, $newHeight, $option);
            $resizeObj->saveImage($tempImgFolder. DIRECTORY_SEPARATOR . $imageName, 100);
            return $fileUrl.$imageName;
        }
    }

}