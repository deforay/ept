<?php

/**
 * The Participant Response Report moved to reports/participant-response.
 * This keeps old bookmarks working.
 */
class Reports_DetailedController extends Zend_Controller_Action
{
    public function indexAction()
    {
        $this->redirect('/reports/participant-response');
    }
}
