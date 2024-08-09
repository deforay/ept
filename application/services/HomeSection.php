<?php

class Application_Service_HomeSection
{

    public function saveHomeSection($params)
    {
        $homeSectionDb = new Application_Model_DbTable_HomeSection();
        return $homeSectionDb->saveHomeSectionDetails($params);
    }
    
    public function getAllHomeSectionInGrid($params)
    {
        $homeSectionDb = new Application_Model_DbTable_HomeSection();
        return $homeSectionDb->getAllHomeSectionDetails($params);
    }

    public function getHomeSectionById($id){
        $homeSectionDb = new Application_Model_DbTable_HomeSection();
        return $homeSectionDb->fetchHomeSectionById($id);
    }
    
    public function getAllHomeSection(){
        $homeSectionDb = new Application_Model_DbTable_HomeSection();
        return $homeSectionDb->fetchAllHomeSection();
    }
    
    public function getActiveHtmlHomePage($title = null){
        $customPageDb = new Application_Model_DbTable_CustomPageContent();
        return $customPageDb->fetchActiveHtmlHomePage($title);
    }
    public function getAllHtmlHomePage(){
        $customPageDb = new Application_Model_DbTable_CustomPageContent();
        return $customPageDb->fetchAllHtmlHomePage();
    }
    public function saveHomePageHtmlContent($params){
        $customPageDb = new Application_Model_DbTable_CustomPageContent();
        return $customPageDb->saveHomePageContent($params);
    }
}
