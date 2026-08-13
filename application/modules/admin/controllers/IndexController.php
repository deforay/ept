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

        // Task 3 / Task 4: a round is "still open" for the table for as long as
        // it's not finalized yet — that covers both shipments still taking
        // responses and shipments whose deadline has passed but haven't been
        // evaluated. Only once every shipment is finalized do we drop to the
        // between-rounds card. See Application_Service_Dashboard::getOpenRoundsStatus().
        $openRounds = $dashboardService->getOpenRoundsStatus();
        $this->view->openRounds = $openRounds;
        $this->view->showRoundTable = count($openRounds) > 0;
        // Header count ("N rounds open for response") is the strict subset:
        // status='shipped' AND deadline not crossed. The table itself still
        // shows closed-but-unevaluated rows on top of that (see
        // Application_Service_Dashboard::getOpenRoundsStatus() docblock).
        $this->view->openRoundsCount = $dashboardService->countStrictlyOpenRounds($openRounds);

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