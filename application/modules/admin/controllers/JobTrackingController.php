<?php

class Admin_JobTrackingController extends Zend_Controller_Action
{

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', $adminSession->privileges);
        if (!in_array('analyze-generate-reports', $privileges)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            } else {
                $this->redirect('/admin');
            }
        }
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
            ->addActionContext('get-all-jobs', 'json')
            ->addActionContext('cancel-job', 'json')
            ->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        // Render the job tracking page
    }

    public function getAllJobsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        $params = $this->getAllParams();
        $evalService = new Application_Service_Evaluation();
        $jobs = $evalService->getAllActiveJobs($params);
        echo json_encode($jobs);
    }

    public function cancelJobAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        header('Content-Type: application/json');

        $jobId = $this->_getParam('jobId');
        $queueType = $this->_getParam('queueType');

        if (empty($jobId) || empty($queueType)) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            return;
        }

        $evalService = new Application_Service_Evaluation();
        $result = $evalService->cancelJob((int) $jobId, $queueType);
        echo json_encode($result);
    }
}
