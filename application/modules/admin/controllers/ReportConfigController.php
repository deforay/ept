<?php

class Admin_ReportConfigController extends Zend_Controller_Action
{

    public function init()
    {
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->_getAllParams();            
            $reportService = new Application_Service_Reports();
            $reportService->updateReportConfigs($params);
            $this->_redirect("/admin/report-config/");
        }else{
            $reportService = new Application_Service_Reports();
            $this->view->logo=$reportService->getReportConfigValue('logo');
            $this->view->logoRight=$reportService->getReportConfigValue('logo-right');
            $this->view->result=$reportService->getReportConfigValue('report-header');
        }
    }


}

