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
                return;
            }
            $this->redirect('/admin');
            return;
        }
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('feed', 'json')
                    ->addActionContext('delete', 'json')
                    ->initContext();
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

    public function deleteAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $request  = $this->getRequest();
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json');

        if (!$request->isPost()) {
            $response->setHttpResponseCode(405);
            $response->setBody(json_encode(['ok' => false, 'error' => 'method_not_allowed']));
            return;
        }

        $channel = $this->resolveChannel();
        $files   = Pt_Commons_LoggerUtility::listLogFiles($channel);
        $mode    = (string) $request->getParam('mode', 'file');
        $result  = $mode === 'all'
            ? $this->deleteAllFiles($channel, $files)
            : $this->deleteSingleFile($channel, $files, (string) $request->getParam('file', ''));

        $response->setHttpResponseCode($result['status']);
        $response->setBody(json_encode($result['body']));
    }

    private function deleteAllFiles(string $channel, array $files): array
    {
        $deleted = 0;
        foreach ($files as $f) {
            if (@unlink($f['path'])) {
                $deleted++;
            }
        }
        Pt_Commons_LoggerUtility::logWarning('Log files cleared via admin viewer', [
            'channel' => $channel,
            'deleted' => $deleted,
            'admin'   => (new Zend_Session_Namespace('administrators'))->primary ?? null,
        ]);
        return ['status' => 200, 'body' => ['ok' => true, 'deleted' => $deleted]];
    }

    private function deleteSingleFile(string $channel, array $files, string $name): array
    {
        $target = null;
        foreach ($files as $f) {
            if ($f['name'] === $name) {
                $target = $f;
                break;
            }
        }
        if ($target === null) {
            return ['status' => 404, 'body' => ['ok' => false, 'error' => 'not_found']];
        }
        if (!@unlink($target['path'])) {
            return ['status' => 500, 'body' => ['ok' => false, 'error' => 'unlink_failed']];
        }
        Pt_Commons_LoggerUtility::logWarning('Log file deleted via admin viewer', [
            'channel' => $channel,
            'file'    => $target['name'],
            'admin'   => (new Zend_Session_Namespace('administrators'))->primary ?? null,
        ]);
        return ['status' => 200, 'body' => ['ok' => true, 'deleted' => 1]];
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
            if (preg_match('/^(.*?) (\{.*\}) (\[.*\]|\{.*\})$/s', $rest, $mm)) {
                $out['message'] = $mm[1];
                $out['context'] = $mm[2];
            } else {
                $out['message'] = $rest;
            }
        }
        return $out;
    }
}
