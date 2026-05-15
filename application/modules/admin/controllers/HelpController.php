<?php

/**
 * Admin in-app help.
 *
 *   GET /admin/help              → full-page list of all topics
 *   GET /admin/help/list         → JSON catalog index { topics: [...], guides: [...] }
 *   GET /admin/help/topic/:slug  → JSON single topic { slug, title, html, ... }
 *   GET /admin/help/guide/:slug  → JSON single guide { slug, title, steps: [...] }
 *
 * Per-page help lives at docs/help/admin/{locale}/{slug}.md.
 * Workflow guides live at docs/help/admin/{locale}/guides/{slug}.md and
 * carry a per-step schema in their frontmatter (see guides/README.md).
 *
 * Content is served through Pt_Commons_HelpCatalog. Locale falls back to
 * en_US per file.
 */
class Admin_HelpController extends Zend_Controller_Action
{
    private function catalog(): Pt_Commons_HelpCatalog
    {
        return new Pt_Commons_HelpCatalog('admin');
    }

    public function indexAction()
    {
        $catalog = $this->catalog();

        $topics = [];
        foreach ($catalog->all() as $meta) {
            $topic = $catalog->find($meta['slug']);
            if ($topic !== null) {
                $topics[] = $topic;
            }
        }
        $this->view->topics = $topics;
        $this->view->guides = $catalog->guides();
    }

    public function listAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $catalog = $this->catalog();
        echo json_encode([
            'topics' => $catalog->all(),
            'guides' => $catalog->guides(),
        ], JSON_UNESCAPED_UNICODE);
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

    public function guideAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=utf-8', true);

        $slug = (string) $this->getRequest()->getParam('slug', '');
        $guide = $this->catalog()->findGuide($slug);
        if ($guide === null) {
            $this->getResponse()->setHttpResponseCode(404);
            echo json_encode(['error' => 'Help guide not found']);
            return;
        }
        echo json_encode($guide, JSON_UNESCAPED_UNICODE);
    }
}
