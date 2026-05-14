/* global navigator, window, document */
(function () {
    'use strict';

    var endpoint = (window.EPT_CLIENT_LOG_URL || '/log/client');
    var maxPerMinute = 10;
    var sent = [];
    var sessionKey = '__ept_clog_sid';

    function clientId() {
        try {
            var id = sessionStorage.getItem(sessionKey);
            if (!id) {
                id = (Date.now().toString(36) + Math.random().toString(36).slice(2, 10));
                sessionStorage.setItem(sessionKey, id);
            }
            return id;
        } catch (e) {
            return '';
        }
    }

    function envelope(kind, message, extra) {
        var nav = window.navigator || {};
        var scr = window.screen || {};
        var conn = nav.connection || nav.mozConnection || nav.webkitConnection || {};
        var body = {
            kind: kind,
            message: String(message || '').slice(0, 2000),
            url: location.href,
            referrer: document.referrer || '',
            lang: nav.language || '',
            tz: (Intl && Intl.DateTimeFormat) ? Intl.DateTimeFormat().resolvedOptions().timeZone : '',
            viewport: (window.innerWidth || 0) + 'x' + (window.innerHeight || 0),
            screen: (scr.width || 0) + 'x' + (scr.height || 0),
            dpr: window.devicePixelRatio || 1,
            netType: conn.effectiveType || '',
            platform: nav.platform || '',
            memoryGB: nav.deviceMemory || null,
            cores: nav.hardwareConcurrency || null,
            session: clientId()
        };
        if (extra) {
            for (var k in extra) {
                if (Object.prototype.hasOwnProperty.call(extra, k) && body[k] === undefined) {
                    body[k] = extra[k];
                }
            }
        }
        return body;
    }

    function rateLimited() {
        var now = Date.now();
        sent = sent.filter(function (t) { return now - t < 60000; });
        if (sent.length >= maxPerMinute) {
            return true;
        }
        sent.push(now);
        return false;
    }

    function dedupeKey(payload) {
        return (payload.message || '') + '|' + (payload.source || '') + '|' + (payload.line || '');
    }
    var seen = {};

    function send(payload) {
        if (rateLimited()) { return; }
        var key = dedupeKey(payload);
        if (seen[key]) { return; }
        seen[key] = true;
        var body = JSON.stringify(payload);
        try {
            if (navigator.sendBeacon) {
                var blob = new Blob([body], { type: 'application/json' });
                if (navigator.sendBeacon(endpoint, blob)) { return; }
            }
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: body,
                credentials: 'same-origin',
                keepalive: true
            }).catch(function () {});
        } catch (e) { /* swallow */ }
    }

    window.addEventListener('error', function (event) {
        if (event.target && event.target !== window && (event.target.src || event.target.href)) {
            send(envelope('resource', 'Resource failed to load', {
                source: event.target.src || event.target.href,
                tag: (event.target.tagName || '').toLowerCase()
            }));
            return;
        }
        var err = event.error;
        send(envelope('error', event.message || (err && err.message) || 'Unknown error', {
            source: event.filename || '',
            line: event.lineno || 0,
            col: event.colno || 0,
            stack: (err && err.stack) ? String(err.stack).slice(0, 8000) : ''
        }));
    }, true);

    window.addEventListener('unhandledrejection', function (event) {
        var reason = event.reason;
        var msg = (reason && reason.message) ? reason.message : (typeof reason === 'string' ? reason : 'Unhandled promise rejection');
        send(envelope('unhandledrejection', msg, {
            stack: (reason && reason.stack) ? String(reason.stack).slice(0, 8000) : ''
        }));
    });

    window.EPT = window.EPT || {};
    window.EPT.logClientError = function (message, extra) {
        send(envelope('manual', message, extra || {}));
    };
})();
