<?php

class Pt_Helper_View_DecodeString extends Zend_View_Helper_Abstract{
    
    public function decodeString($str){
        return html_entity_decode(stripslashes($str), ENT_QUOTES, 'UTF-8');
    }
    

}
?>
