<?php

class Pt_Helper_View_GetActiveSchemes extends Zend_View_Helper_Abstract {

    public function getActiveSchemes() {
        $schemeService = new Application_Service_Schemes();
        $scheme = array();
        $schemeList = $schemeService->getAllSchemes();
        foreach($schemeList as $row){
            $scheme[$row['scheme_id']]= $row['scheme_name'];
        }
        return $scheme;
    }

}

?>
