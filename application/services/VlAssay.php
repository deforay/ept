<?php

class Application_Service_VlAssay
{

    public function addVlAssay($params)
    {
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
        return $vlAssayDb->addVlAssayDetails($params);
    }

    public function getAllVlAssay($parameters)
    {
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
        return $vlAssayDb->fetchAllVlAssay($parameters);
    }

    public function getVlAssay($id)
    {
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
        return $vlAssayDb->fetchVlAssay($id);
    }

    public function updateVlAssay($params)
    {
        $vlAssayDb = new Application_Model_DbTable_VlAssay();
        return $vlAssayDb->updateVlAssayDetails($params);
    }

    public function addEidExtractionAssay($params)
    {
        $eidExtractionAssayDb = new Application_Model_DbTable_EidExtractionAssay();
        return $eidExtractionAssayDb->addEidExtractionAssayDetails($params);
    }

    public function addEidDetectionAssay($params)
    {
        $eidDetectionAssayDb = new Application_Model_DbTable_EidDetectionAssay();
        return $eidDetectionAssayDb->addEidDetectionAssayDetails($params);
    }

    public function getAllEidExtractionAssay($parameters)
    {
        $eidExtractionAssayDb = new Application_Model_DbTable_EidExtractionAssay();
        return $eidExtractionAssayDb->fetchAllEidExtractionAssay($parameters);
    }

    public function getAllEidDetectionAssay($parameters)
    {
        $eidDetectionAssayDb = new Application_Model_DbTable_EidDetectionAssay();
        return $eidDetectionAssayDb->fetchAllEidDetectionAssay($parameters);
    }

    public function getEidExtractionAssay($id)
    {
        $eidExtractionAssayDb = new Application_Model_DbTable_EidExtractionAssay();
        return $eidExtractionAssayDb->fetchEidExtractionAssay($id);
    }

    public function getEidDetectionAssay($id)
    {
        $eidDetectionAssayDb = new Application_Model_DbTable_EidDetectionAssay();
        return $eidDetectionAssayDb->fetchEidDetectionAssay($id);
    }

    public function changeEidExtractionNameStatus($params)
    {
        $eidExtractionAssayDb = new Application_Model_DbTable_EidExtractionAssay();
        return $eidExtractionAssayDb->updateEidExtractionNameStatus($params);
    }

    public function changeEidDetectionNameStatus($params)
    {
        $eidDetectionAssayDb = new Application_Model_DbTable_EidDetectionAssay();
        return $eidDetectionAssayDb->updateEidDetectionNameStatus($params);
    }

    public function getchAllTbAssay()
    {
        $tbAssayDb = new Application_Model_DbTable_TbAssay();
        return $tbAssayDb->fetchAllTbAssay();
    }
}
