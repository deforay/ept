<?php

class Admin_ReportConfigController extends Zend_Controller_Action
{

    public function init()
    {
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($this->getRequest()->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        if ($this->getRequest()->isPost()) {
            $params = $this->getAllParams();
            $reportService = new Application_Service_Reports();
            $reportService->updateReportConfigs($params);
            $this->redirect("/admin/report-config/");
        } else {
            $reportService = new Application_Service_Reports();
            $this->view->logo = $reportService->getReportConfigValue('logo');
            $this->view->logoRight = $reportService->getReportConfigValue('logo-right');
            $this->view->result = $reportService->getReportConfigValue('report-header');
            $this->view->instituteAddressPosition = $reportService->getReportConfigValue('institute-address-postition');
            $this->view->reportLayouts = scandir(PARTICIPANT_REPORT_LAYOUT, true);
            $this->view->reportLayoutsResult = $reportService->getReportConfigValue('report-layout');
        }
    }

    public function showModelLayoutAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->view->filename = $this->getParam('id');
        // Zend_Debug::dump($this->view->filename);die;
    }
}
