<?php

class Admin_LogViewerController extends Zend_Controller_Action
{
    private const MAX_TAIL_LINES = 5000;

    public function init()
    {
        /** @var Zend_Controller_Request_Http $request */
        $request = $this->getRequest();
        $adminSession = new Zend_Session_Namespace('administrators');
        $privileges = explode(',', (string) ($adminSession->privileges ?? ''));
        if (!in_array('analyze-generate-reports', $privileges, true)) {
            if ($request->isXmlHttpRequest()) {
                return null;
            }
            $this->redirect('/admin');
            return null;
        }
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('feed', 'json')->initContext();
        $this->_helper->layout()->pageName = 'configMenu';
    }

    public function indexAction()
    {
        $channel = $this->resolveChannel();
        $this->view->channel  = $channel;
        $this->view->channels = [
            Pt_Commons_LoggerUtility::APP_CHANNEL    => 'Application Errors',
            Pt_Commons_LoggerUtility::CLIENT_CHANNEL => 'Client (Browser) Errors',
        ];
        $this->view->files  = Pt_Commons_LoggerUtility::listLogFiles($channel);
        $this->view->levels = ['', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
    }

    public function feedAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $channel = $this->resolveChannel();
        $files   = Pt_Commons_LoggerUtility::listLogFiles($channel);
        $requested = (string) $this->getRequest()->getParam('file', '');
        $selected  = $this->pickFile($files, $requested);

        $payload = ['channel' => $channel, 'file' => null, 'lines' => [], 'meta' => null];

        if ($selected !== null) {
            $maxLines = (int) $this->getRequest()->getParam('limit', 500);
            $maxLines = max(50, min(self::MAX_TAIL_LINES, $maxLines));
            $needle   = trim((string) $this->getRequest()->getParam('q', ''));
            $level    = trim((string) $this->getRequest()->getParam('level', ''));
            $lines    = Pt_Commons_LoggerUtility::tailLog($selected['path'], $maxLines, $needle ?: null, $level ?: null);

            $payload['file']  = $selected['name'];
            $payload['lines'] = array_map([$this, 'parseLine'], $lines);
            $payload['meta']  = [
                'size'  => $selected['size'],
                'mtime' => date('Y-m-d H:i:s', $selected['mtime']),
                'count' => count($lines),
                'limit' => $maxLines,
            ];
        }

        $this->getResponse()
            ->setHeader('Content-Type', 'application/json')
            ->setBody(json_encode($payload));
    }

    private function resolveChannel(): string
    {
        $channel = (string) $this->getRequest()->getParam('channel', Pt_Commons_LoggerUtility::APP_CHANNEL);
        return $channel === Pt_Commons_LoggerUtility::CLIENT_CHANNEL
            ? Pt_Commons_LoggerUtility::CLIENT_CHANNEL
            : Pt_Commons_LoggerUtility::APP_CHANNEL;
    }

    private function pickFile(array $files, string $requested): ?array
    {
        if ($requested !== '') {
            foreach ($files as $f) {
                if ($f['name'] === $requested) {
                    return $f;
                }
            }
        }
        return $files[0] ?? null;
    }

    private function parseLine(string $line): array
    {
        // Monolog default LineFormatter: [2026-05-14T10:23:45.123456+00:00] channel.LEVEL: message [context] [extra]
        $out = ['raw' => $line, 'time' => '', 'channel' => '', 'level' => '', 'message' => $line, 'context' => ''];
        if (preg_match('/^\[([^\]]+)\] ([^.]+)\.([A-Z]+): (.*)$/s', $line, $m)) {
            $out['time']    = $m[1];
            $out['channel'] = $m[2];
            $out['level']   = $m[3];
            $rest           = $m[4];
            if (preg_match('/^(.*?) (\{.*\}) (\[.*\]|\{.*\}|\[\])$/s', $rest, $mm)) {
                $out['message'] = $mm[1];
                $out['context'] = $mm[2];
            } else {
                $out['message'] = $rest;
            }
        }
        return $out;
    }
}
