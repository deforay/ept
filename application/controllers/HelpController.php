<?php

/**
 * Participant in-app help.
 *
 *   GET /help              → full-page list of all topics (renders in layout.phtml)
 *   GET /help/list         → JSON catalog index { topics: [...] }
 *   GET /help/topic/:slug  → JSON single topic { slug, title, html, ... }
 *
 * Content lives at docs/help/participant/{locale}/{slug}.md and is served
 * through Pt_Commons_HelpCatalog. Locale falls back to en_US per file.
 */
class HelpController extends Zend_Controller_Action
{
    private function catalog(): Pt_Commons_HelpCatalog
    {
        return new Pt_Commons_HelpCatalog('participant');
    }

    public function indexAction()
    {
        $this->_helper->layout()->activeMenu = 'help';
        $catalog = $this->catalog();

        $topics = [];
        foreach ($catalog->all() as $meta) {
            $topic = $catalog->find($meta['slug']);
            if ($topic !== null) $topics[] = $topic;
        }
        $this->view->topics = $topics;
    }

    public function listAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode(['topics' => $this->catalog()->all()], JSON_UNESCAPED_UNICODE);
    }

    public function topicAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        $slug = (string) $this->getRequest()->getParam('slug', '');
        $topic = $this->catalog()->find($slug);
        if ($topic === null) {
            $this->getResponse()->setHttpResponseCode(404);
            echo json_encode(['error' => 'Help topic not found']);
            return;
        }
        echo json_encode($topic, JSON_UNESCAPED_UNICODE);
    }
}
