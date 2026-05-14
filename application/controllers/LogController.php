<?php

class LogController extends Zend_Controller_Action
{
    private const MAX_BODY_BYTES   = 32768;
    private const MAX_FIELD_LENGTH = 2048;
    private const MAX_STACK_LENGTH = 8000;

    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    public function clientAction()
    {
        $request  = $this->getRequest();
        $response = $this->getResponse();
        $response->setHeader('Content-Type', 'application/json');

        $payload = $this->parseRequestBody($request, $response);
        if ($payload === null) {
            return;
        }
        $message = $this->trimField($payload['message'] ?? '', self::MAX_FIELD_LENGTH);
        if ($message === '') {
            $response->setHttpResponseCode(204);
            return;
        }

        $userAgent = $request->getHeader('User-Agent') ?: '';
        $uaParts   = $this->parseUserAgent($userAgent);

        Pt_Commons_LoggerUtility::logClientError($message, [
            'kind'    => $this->trimField($payload['kind'] ?? 'error', 32),
            'source'  => $this->trimField($payload['source'] ?? '', self::MAX_FIELD_LENGTH),
            'line'    => isset($payload['line']) ? (int) $payload['line'] : null,
            'col'     => isset($payload['col']) ? (int) $payload['col'] : null,
            'stack'   => $this->trimField($payload['stack'] ?? '', self::MAX_STACK_LENGTH),
            'url'     => $this->trimField($payload['url'] ?? '', self::MAX_FIELD_LENGTH),
            'referrer' => $this->trimField($payload['referrer'] ?? '', self::MAX_FIELD_LENGTH),
            'browser' => $uaParts['browser'],
            'os'      => $uaParts['os'],
            'device'  => $uaParts['device'],
            'ua'      => $this->trimField($userAgent, self::MAX_FIELD_LENGTH),
            'lang'    => $this->trimField($payload['lang'] ?? '', 32),
            'tz'      => $this->trimField($payload['tz'] ?? '', 64),
            'viewport' => $this->trimField($payload['viewport'] ?? '', 32),
            'screen'  => $this->trimField($payload['screen'] ?? '', 32),
            'dpr'     => isset($payload['dpr']) ? (float) $payload['dpr'] : null,
            'netType' => $this->trimField($payload['netType'] ?? '', 32),
            'platform' => $this->trimField($payload['platform'] ?? '', 64),
            'memoryGB' => isset($payload['memoryGB']) ? (float) $payload['memoryGB'] : null,
            'cores'   => isset($payload['cores']) ? (int) $payload['cores'] : null,
            'ip'      => $this->clientIp($request),
            'session' => $this->sessionHash(),
            'role'    => $this->detectRole(),
        ]);

        $this->getResponse()->setHttpResponseCode(204);
    }

    private function parseRequestBody(Zend_Controller_Request_Http $request, Zend_Controller_Response_Abstract $response): ?array
    {
        if (!$request->isPost()) {
            $response->setHttpResponseCode(405);
            $response->setBody(json_encode(['status' => 'method_not_allowed']));
            return null;
        }
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            $response->setHttpResponseCode(204);
            return null;
        }
        $payload = json_decode(substr($raw, 0, self::MAX_BODY_BYTES), true);
        if (!is_array($payload)) {
            $response->setHttpResponseCode(400);
            $response->setBody(json_encode(['status' => 'bad_json']));
            return null;
        }
        return $payload;
    }

    private function trimField($value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $value = (string) $value;
        if (strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }
        return $value;
    }

    private function clientIp(Zend_Controller_Request_Http $request): string
    {
        $forwarded = $request->getHeader('X-Forwarded-For');
        if ($forwarded) {
            $first = trim(explode(',', $forwarded)[0]);
            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        $real = $request->getHeader('X-Real-IP');
        if ($real && filter_var($real, FILTER_VALIDATE_IP)) {
            return $real;
        }
        return (string) ($request->getServer('REMOTE_ADDR') ?: '');
    }

    private function sessionHash(): string
    {
        $sid = session_id();
        if (!$sid) {
            return '';
        }
        return substr(hash('sha256', $sid), 0, 16);
    }

    private function detectRole(): string
    {
        $admin = new Zend_Session_Namespace('administrators');
        if (!empty($admin->primary)) {
            return 'admin:' . $admin->primary;
        }
        $dm = new Zend_Session_Namespace('datamanagers');
        if (!empty($dm->primary)) {
            return 'datamanager:' . $dm->primary;
        }
        return 'anon';
    }

    private function parseUserAgent(string $ua): array
    {
        $out = ['browser' => '', 'os' => '', 'device' => ''];
        if ($ua === '') {
            return $out;
        }
        $out['browser'] = $this->detectBrowser($ua);
        $out['os']      = $this->detectOs($ua);
        $out['device']  = $this->detectDevice($ua);
        return $out;
    }

    private function detectBrowser(string $ua): string
    {
        $patterns = [
            'Edge'      => '/Edg\/([\d.]+)/',
            'Opera'     => '/OPR\/([\d.]+)/',
            'Samsung'   => '/SamsungBrowser\/([\d.]+)/',
            'Chrome'    => '/Chrome\/([\d.]+)/',
            'Firefox'   => '/Firefox\/([\d.]+)/',
            'Safari'    => '/Version\/([\d.]+).*Safari/',
            'IE'        => '/(?:MSIE |Trident\/.*; rv:)([\d.]+)/',
        ];
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $ua, $m)) {
                return $name . ' ' . $m[1];
            }
        }
        return 'Unknown';
    }

    private function detectOs(string $ua): string
    {
        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
            $map = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7'];
            return 'Windows ' . ($map[$m[1]] ?? $m[1]);
        }
        if (preg_match('/Android ([\d.]+)/', $ua, $m)) {
            return 'Android ' . $m[1];
        }
        if (preg_match('/iPhone OS ([\d_]+)|iPad; CPU OS ([\d_]+)/', $ua, $m)) {
            return 'iOS ' . str_replace('_', '.', $m[1] ?: $m[2]);
        }
        if (preg_match('/Mac OS X ([\d_.]+)/', $ua, $m)) {
            return 'macOS ' . str_replace('_', '.', $m[1]);
        }
        if (stripos($ua, 'Linux') !== false) {
            return 'Linux';
        }
        return 'Unknown';
    }

    private function detectDevice(string $ua): string
    {
        if (preg_match('/iPad/', $ua)) {
            return 'Tablet (iPad)';
        }
        if (preg_match('/iPhone|iPod/', $ua)) {
            return 'Mobile (iPhone)';
        }
        if (preg_match('/Android/', $ua)) {
            return stripos($ua, 'Mobile') !== false ? 'Mobile (Android)' : 'Tablet (Android)';
        }
        if (preg_match('/Mobile|Phone/i', $ua)) {
            return 'Mobile';
        }
        return 'Desktop';
    }
}
