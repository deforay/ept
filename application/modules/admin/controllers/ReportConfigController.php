<?php

class Admin_ReportConfigController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();

        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('config-ept', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {

        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        if ($request->isPost()) {
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
            $participantLayouts = scandir(PARTICIPANT_REPORTS_LAYOUT, true);
            $summaryLayouts = scandir(SUMMARY_REPORTS_LAYOUT, true);
            $reportLayouts = array_diff(array_unique(array_merge($participantLayouts ?: [], $summaryLayouts ?: [])), ['.', '..']);
            $this->view->reportLayouts = $reportLayouts;
            $this->view->reportLayoutsResult = $reportService->getReportConfigValue('report-layout');
            $this->view->reportFormatPdf = $reportService->getReportConfigValue('report-format');
        }
    }

    public function showModelLayoutAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->view->filename = $this->getParam('id');
    }
}
