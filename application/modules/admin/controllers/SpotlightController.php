<?php

class Admin_SpotlightController extends Zend_Controller_Action
{
    public function init()
    {
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('search', 'json')
            ->initContext();
    }

    public function searchAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Get admin session for access control
        $adminSession = new Zend_Session_Namespace('administrators');
        $activeSchemes = $adminSession->activeSchemes ?? [];
        $privileges = explode(',', $adminSession->privileges ?? '');

        $query = $this->getRequest()->getParam('q', '');
        $results = [];

        if (strlen($query) >= 2 && !empty($activeSchemes)) {
            $shipmentDb = new Application_Model_DbTable_Shipments();
            $select = $shipmentDb->select()
                ->setIntegrityCheck(false)
                ->from(['s' => 'shipment'])
                ->join(['sl' => 'scheme_list'], 'sl.scheme_id = s.scheme_type', ['scheme_name'])
                ->joinLeft(
                    ['spm' => 'shipment_participant_map'],
                    's.shipment_id = spm.shipment_id AND spm.response_status = "responded"',
                    ['response_count' => new Zend_Db_Expr('COUNT(spm.map_id)')]
                )
                ->where('s.shipment_code LIKE ?', '%' . $query . '%')
                ->where('s.scheme_type IN (?)', $activeSchemes)
                ->group('s.shipment_id')
                ->order('s.shipment_date DESC')
                ->limit(5);

            $shipments = $shipmentDb->fetchAll($select);

            foreach ($shipments as $shipment) {
                $sid = base64_encode($shipment->shipment_id);
                $code = $shipment->shipment_code;
                $status = $shipment->status;
                $schemeName = $shipment->scheme_name;

                $actions = [];
                $isFinalized = ($status === 'finalized');

                // Edit action - requires manage-shipments privilege, not available for finalized
                if (in_array('manage-shipments', $privileges) && !$isFinalized) {
                    $actions[] = [
                        'label' => $this->view->translate->_('Edit'),
                        'url' => '/admin/shipment/edit/sid/' . $sid . '/userConfig/' . base64_encode('no'),
                        'icon' => 'icon-edit'
                    ];
                    $actions[] = [
                        'label' => $this->view->translate->_('Manage Enrollment'),
                        'url' => '/admin/shipment/manage-enroll/sid/' . $sid . '/sctype/' . base64_encode($shipment->scheme_type),
                        'icon' => 'icon-group'
                    ];
                }

                // Evaluate, Generate Reports, Finalize - requires analyze-generate-reports privilege, not available for finalized, requires at least one response
                $hasResponses = ($shipment->response_count > 0);
                if (in_array('analyze-generate-reports', $privileges) && !$isFinalized && $hasResponses) {
                    $actions[] = [
                        'label' => $this->view->translate->_('Evaluate'),
                        'url' => '/admin/evaluate/shipment/sid/' . $sid,
                        'icon' => 'icon-check-sign'
                    ];
                    $actions[] = [
                        'label' => $this->view->translate->_('Generate Reports'),
                        'url' => '/reports/distribution/shipment/sid/' . $sid,
                        'icon' => 'icon-file-text'
                    ];
                    $actions[] = [
                        'label' => $this->view->translate->_('Finalize'),
                        'url' => '/reports/distribution/finalize/sid/' . $sid,
                        'icon' => 'icon-ok-sign'
                    ];
                }

                // Download actions - requires access-reports privilege, available for evaluated/finalized (not pending/queued)
                if (in_array('access-reports', $privileges) && !in_array($status, ['pending', 'queued'])) {
                    $summaryPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . $code . '-summary.pdf';
                    $zipPath = DOWNLOADS_FOLDER . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $code . '.zip';

                    if (file_exists($summaryPath)) {
                        $actions[] = [
                            'label' => $this->view->translate->_('Download Summary'),
                            'url' => '/d/' . base64_encode($summaryPath),
                            'icon' => 'icon-download'
                        ];
                    }
                    if (file_exists($zipPath)) {
                        $actions[] = [
                            'label' => $this->view->translate->_('Download All'),
                            'url' => '/d/' . base64_encode($zipPath),
                            'icon' => 'icon-download-alt'
                        ];
                    }
                }

                // Only add shipment to results if there are any actions available
                if (!empty($actions)) {
                    $results[] = [
                        'type' => 'shipment',
                        'id' => $shipment->shipment_id,
                        'title' => $code,
                        'subtitle' => $schemeName . ' - ' . ucfirst($status),
                        'icon' => 'icon-truck',
                        'actions' => $actions
                    ];
                }
            }
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        echo json_encode(['results' => $results]);
    }
}
