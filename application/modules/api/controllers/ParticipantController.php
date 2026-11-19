<?php

class Api_ParticipantController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->layout->disableLayout();
    }

    public function getAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->getAllParams();
        $shipmentService = new Application_Service_Shipments();
        $result = $shipmentService->getIndividualReportAPI($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function getFilterAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->getAllParams();
        $participantService = new Application_Service_Participants();
        $result = $participantService->getFilterDetailsAPI($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function getProfileCheckAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->getAllParams();
        $participantService = new Application_Service_Participants();
        $result = $participantService->getProfileCheckDetailsAPI($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function updateProfileAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = json_decode(file_get_contents('php://input'));
        $participantService = new Application_Service_Participants();
        $result = $participantService->saveProfileByAPI((array)$params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function summaryAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->getAllParams();
        $shipmentService = new Application_Service_Shipments();
        $result = $shipmentService->getSummaryReportAPI($params);
        $this->getResponse()->setBody(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function downloadAction()
    {
        $this->view->result = $this->fetchReportMapForDownload();
    }

    public function downloadSummaryAction()
    {
        // The summary link carries a map_id too; the data manager must manage
        // the lab on that map row, which ties them to a lab in the shipment.
        $this->view->result = $this->fetchReportMapForDownload();
    }

    /**
     * Resolve /<download_link>/<base64 map_id> to a shipment_participant_map row,
     * but only when the link belongs to a data manager who manages that lab.
     * On any failure, writes a JSON error body, disables the view and returns null.
     */
    private function fetchReportMapForDownload()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $downloadLink = '';
        $id = '';
        $params = $this->getAllParams();
        if (empty($params)) {
            return $this->downloadFailure('Something went wrong. Please contact admin');
        }
        $defaultParams = ['module', 'controller', 'action'];
        foreach ($params as $link => $mapId) {
            if (!in_array($link, $defaultParams, true)) {
                $downloadLink = (string) $link;
                $id = is_string($mapId) ? (string) base64_decode($mapId, true) : '';
            }
        }
        if (trim($downloadLink) === '' || $id === '' || !ctype_digit($id)) {
            return $this->downloadFailure('Your link was expired. Please contact admin');
        }

        $dm = $db->fetchRow($db->select()->from('data_manager', ['dm_id'])->where('download_link = ?', $downloadLink));
        if (!$dm) {
            return $this->downloadFailure('Your link was expired. Please contact admin');
        }

        $result = $db->fetchRow($db->select()->from(['spm' => 'shipment_participant_map'], ['spm.map_id'])
            ->join(['s' => 'shipment'], 's.shipment_id=spm.shipment_id', ['s.shipment_code', 's.scheme_type'])
            ->join(['p' => 'participant'], 'p.participant_id=spm.participant_id', ['p.first_name', 'p.last_name'])
            ->join(['pmm' => 'participant_manager_map'], 'pmm.participant_id = spm.participant_id', [])
            ->where('spm.map_id = ?', $id)
            ->where('pmm.dm_id = ?', $dm['dm_id'])
            // Reports are released at finalization. Before that the PDFs on disk are drafts.
            ->where('s.status = ?', 'finalized'));
        if (!$result) {
            return $this->downloadFailure('Report not ready');
        }
        return $result;
    }

    private function downloadFailure($message)
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setBody(json_encode([
            'status'    => 'fail',
            'message'   => $message,
        ], JSON_PRETTY_PRINT));
        return null;
    }

    public function resendAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $params = $this->getAllParams();
        $explode = explode('##', base64_decode($params['id']));
        $params['registeredEmail'] = $explode[0];
        $params['oldEmail'] = $explode[1];
        $dmServices = new Application_Service_DataManagers();
        $result = $dmServices->resentDMVerifyMail($params);
        if ($result > 0) {
            $response = ['status' => 'success', 'message' => 'Please check your email for the verification link.'];
        } else {
            $response = ['status' => 'fail', 'message' => 'Something went wrong. Please try again.'];
        }
        $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
    }

    public function fileDownloadsAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $participantService = new Application_Service_Participants();
        $params = $this->getAllParams();
        $result = $participantService->getParticipantsCertificates($params);
        if ($result > 0) {
            $response = ['status' => 'success', 'message' => 'Please wait for download certificate link.', 'data' => $result];
        } else {
            $response = ['status' => 'fail', 'message' => 'Something went wrong. Please try again.'];
        }
        $this->getResponse()->setBody(json_encode($response, JSON_PRETTY_PRINT));
    }
}
