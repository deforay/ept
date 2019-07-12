<?php

class Application_Model_DbTable_HomeBanner extends Zend_Db_Table_Abstract
{

    protected $_name = 'home_banner';
    protected $_primary = 'banner_id';
    
    
    public function updateHomeBannerDetails($params){
        $result = 0;
        if(isset($_FILES['home_banner']['name']) && trim($_FILES['home_banner']['name'])!= ''){
            //Remove exist img.
            if(isset($params['existImage']) && trim($params['existImage'])!= ''){
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-banner'. DIRECTORY_SEPARATOR . $params['existImage'])) {
                    unlink(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-banner'. DIRECTORY_SEPARATOR . $params['existImage']);
                    $this->update(array('image'=>''),"banner_id = 1");
                }
            }
            
            $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
            $extension = strtolower(pathinfo(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_FILES['home_banner']['name'], PATHINFO_EXTENSION));
            $imageName ="home_banner.".$extension;
            if (in_array($extension, $allowedExtensions)) {
                $result = 1;
                if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-banner') && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-banner')) {
                    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'home-banner');
                }
                if(move_uploaded_file($_FILES["home_banner"]["tmp_name"], UPLOAD_PATH . DIRECTORY_SEPARATOR."home-banner". DIRECTORY_SEPARATOR.$imageName)){
                    //$resizeObj = new Pt_Commons_ImageResize(UPLOAD_PATH . DIRECTORY_SEPARATOR."home-banner". DIRECTORY_SEPARATOR . $imageName);
                    //$resizeObj->resizeImage(1301, 531, 'auto');
                    //$resizeObj->saveImage(UPLOAD_PATH . DIRECTORY_SEPARATOR."home-banner". DIRECTORY_SEPARATOR . $imageName, 100);
                    $this->update(array('image'=>$imageName),"banner_id = 1");
                }
            }
        }
        
      return $result;
    }
    
    public function fetchHomeBannerDetails(){
        return $this->fetchRow($this->select()->where("banner_id = ? ",1));
    }
    
    public function fetchHomeBanner(){
        return $this->fetchRow($this->select()->where("banner_id = ? ",1));
    }
}