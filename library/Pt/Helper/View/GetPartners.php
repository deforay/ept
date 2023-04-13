<?php

class Pt_Helper_View_GetPartners extends Zend_View_Helper_Abstract
{

    public function getPartners()
    {
        $partnerService = new Application_Service_Partner();
        $partners = [];
        $partnerList = $partnerService->getAllActivePartners();
        foreach ($partnerList as $row) {
            $partners[$row['partner_id']]['partner_name'] = $row['partner_name'];
            $partners[$row['partner_id']]['link'] = $row['link'];
            $partners[$row['partner_id']]['logo_image'] = $row['logo_image'];
        }
        return $partners;
    }
}
