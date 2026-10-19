<?php

class Reports_TbAllSitesResultsController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', (string) $adminSession->privileges);
        if (!in_array('access-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                // init() returning does not abort ZF1 dispatch; halt so the
                // action never runs for unauthorized XHR callers.
                $this->getResponse()->setHttpResponseCode(403)->sendResponse();
                exit;
            }
            $this->redirect('/admin');
        }
        $this->_helper->layout()->pageName = 'report';
    }

    public function indexAction()
    {
        $reportService = new Application_Service_Reports();
        $this->view->shipments = $reportService->getResponseReportShipments('tb', null, null);
    }

    public function generateAction()
    {
        $reportService = new Application_Service_Reports();
        $result = $reportService->getTbAllSitesResultsReport(['shipmentId' => (int) $this->getParam('shipmentId')]);
        $this->_helper->json(['file' => is_array($result) ? ($result['report-name'] ?? '') : '']);
    }
}
