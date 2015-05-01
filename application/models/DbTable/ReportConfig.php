<?php

class Application_Model_DbTable_ReportConfig extends Zend_Db_Table_Abstract
{

    protected $_name = 'report_config';
    protected $_primary = 'name';
    
    public function updateReportDetails($params){
        $data = array('value'=>$params['content']);
        
        if(!file_exists($_FILES['logo_image']['tmp_name']) || !is_uploaded_file($_FILES['logo_image']['tmp_name'])){
            
        }else{
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo_image']['name'], PATHINFO_EXTENSION));
            $imageName ="logo_example.".$extension;
            
            if (in_array($extension, $allowedExtensions)) {
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo')) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo');
                }
                if(move_uploaded_file($_FILES["logo_image"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR.$imageName)){
                    $resizeObj = new Pt_Commons_ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR . $imageName);
                    $resizeObj->resizeImage(300, 300, 'auto');
                    $resizeObj->saveImage(UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR . $imageName, 100);
                }
                $this->update(array('value'=>$imageName),"name='logo'");
            }
            
        }
        
        if(!file_exists($_FILES['logo_image_right']['tmp_name']) || !is_uploaded_file($_FILES['logo_image_right']['tmp_name'])){
            
        }else{
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['logo_image_right']['name'], PATHINFO_EXTENSION));
            $imageName ="logo_right.".$extension;
            
            if (in_array($extension, $allowedExtensions)) {
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo')) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo');
                }
                if(move_uploaded_file($_FILES["logo_image_right"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR.$imageName)){
                    $resizeObj = new Pt_Commons_ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR . $imageName);
                    $resizeObj->resizeImage(300, 300, 'auto');
                    $resizeObj->saveImage(UPLOAD_PATH . DIRECTORY_SEPARATOR."logo". DIRECTORY_SEPARATOR . $imageName, 100);
                }
                $this->update(array('value'=>$imageName),"name='logo-right'");
            }
            
        }
        
        
        //$imageName ="logo_example.jpg";
        
        
                
        
        
        return $this->update($data,"name='report-header'");
    }
    
    public function getValue($name){
        $res = $this->getAdapter()->fetchCol($this->select()
                               ->from($this->_name, array('value'))
                              ->where("name='".$name."'"));
        return $res[0];
    }
}

