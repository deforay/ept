<?php

class Admin_IndexController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->layout()->pageName = 'dashboard';
        /** @var Zend_Controller_Action_Helper_AjaxContext $ajaxContext */
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('ept-overview', 'html')
            ->initContext();
        // NOTE: 'get-scheme-participants' and 'load-charts' ajax contexts were
        // removed along with getSchemeParticipantsAction() / loadChartsAction()
        // below — both existed solely to feed the "Active Participants enrolled
        // per PT Scheme" and "List of all PT Surveys" charts, which are gone
        // per Task 1. If anything else in the app still calls those endpoints,
        // restore them before deploying this.
    }

    public function indexAction()
    {
        $clientsServices = new Application_Service_Participants();
        $dashboardService = new Application_Service_Dashboard();

        $this->view->pendingParticipants = $clientsServices->getPendingParticipants();

        // Task 2: always-visible summary strip.
        $this->view->summaryCounts = $dashboardService->getSummaryCounts();

        // Task 3 / Task 4, following the spec's decision tree: the round table
        // covers every unfinalized shipment, whether it is still taking
        // responses or has closed and is waiting to be evaluated. Only when
        // there are none at all does the dashboard fall through to the Task 4
        // between-rounds card.
        $openRounds = $dashboardService->getOpenRoundsStatus();
        $openForResponse = array_filter($openRounds, function ($round) {
            return $round['isOpen'];
        });

        $this->view->openRounds = $openRounds;
        $this->view->openRoundsCount = count($openForResponse);
        // Derived from the rows already fetched above, so it costs no query.
        $this->view->pipelineStages = $dashboardService->summarisePipeline($openRounds);
        // Engagement is about the participant base, not about any one round,
        // so it does not vary with whether a round is open. Both panels are
        // shown either way.
        $this->view->engagement = $dashboardService->getParticipantEngagement();
        $this->view->schemeEngagement = $dashboardService->getSchemeEngagement();
        // Closed but not yet finalized — the heading names these separately so
        // an idle system reads differently from one with a backlog.
        $this->view->awaitingFinalizeCount = count($openRounds) - count($openForResponse);
        $this->view->showRoundTable = count($openRounds) > 0;

        if (!$this->view->showRoundTable) {
            $this->view->betweenRounds = $dashboardService->getBetweenRoundsSummary(5);
        }

        // Nudge: shipments whose scores are out of date because responses arrived after
        // they were evaluated. Only for admins with 'config-ept' — the re-evaluate endpoint
        // enforces that privilege, so there's no point nudging someone who can't act on it.
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = $adminSession->privileges ? explode(',', $adminSession->privileges) : [];
        if (in_array('config-ept', $privileges, true)) {
            $this->view->staleShipments = (new Application_Service_Evaluation())->getShipmentsNeedingReEvaluation();
        }
    }

    public function eptOverviewAction()
    {
        $this->view->overview = Application_Service_Common::getEptOverview();
    }
}
