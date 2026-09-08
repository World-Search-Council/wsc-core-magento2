/**
 * WSC Tracker - Canonical Universal Analytics Client
 * Part of the World Search Council (WSC) Standard for Magento 2.
 *
 * @version 1.5.0
 * @license GPL-2.0-or-later
 */

(function(window, document) {
    'use strict';

    window.WSC = window.WSC || {};

    var cfg = Object.assign({}, window.WSC_CONFIG || {});
    var ENDPOINT = cfg.endpoint || '/wsc/api/track';
    var MODE = (cfg.mode === 'consent') ? 'consent' : 'cookieless';
    var CONSENT_KEY = cfg.consentKey || 'wsc_consent';
    var AUTO = Object.assign({
        downloads: false,
        timeOnPage: false,
    }, cfg.auto || {});

    var queue = [];
    var isSending = false;
    var pageviewSent = false;

    function now() {
        return (window.performance && performance.now) ? performance.now() : Date.now();
    }

    function readConsentStorage() {
        try {
            if (window.localStorage && localStorage.getItem(CONSENT_KEY) === '1') return true;
        } catch (e) {}
        try {
            return document.cookie.split(';').some(function(c) {
                return c.trim().indexOf(CONSENT_KEY + '=1') === 0;
            });
        } catch (e2) {}
        return false;
    }

    function writeConsentStorage(granted) {
        try {
            if (window.localStorage) {
                if (granted) localStorage.setItem(CONSENT_KEY, '1');
                else localStorage.removeItem(CONSENT_KEY);
            }
        } catch (e) {}
        try {
            if (granted) {
                document.cookie = CONSENT_KEY + '=1;path=/;max-age=31536000;SameSite=Lax';
            } else {
                document.cookie = CONSENT_KEY + '=;path=/;max-age=0;SameSite=Lax';
            }
        } catch (e2) {}
    }

    var trackingEnabled = (MODE !== 'consent') || readConsentStorage();

    function sendBatch(useBeacon) {
        if (queue.length === 0 || isSending) return;
        var dataToSend = queue.slice();
        var payload = JSON.stringify({ events: dataToSend });

        if (useBeacon && navigator.sendBeacon) {
            var blob = new Blob([payload], { type: 'application/json' });
            if (navigator.sendBeacon(ENDPOINT, blob)) {
                queue = queue.filter(function(item) { return dataToSend.indexOf(item) === -1; });
            }
        } else {
            isSending = true;
            fetch(ENDPOINT, {
                method: 'POST',
                body: payload,
                keepalive: true,
                headers: { 'Content-Type': 'application/json' }
            })
            .then(function(response) {
                if (response.ok) {
                    queue = queue.filter(function(item) { return dataToSend.indexOf(item) === -1; });
                }
            })
            .catch(function() {})
            .finally(function() { isSending = false; });
        }
    }

    function track(eventName, metaData, customPath) {
        if (!trackingEnabled) return;

        var payload = {
            t: Math.floor(Date.now() / 1000),
            e: String(eventName || 'custom'),
            p: customPath || window.location.pathname,
            r: document.referrer || '',
            m: (typeof metaData === 'object' && metaData !== null) ? metaData : { value: metaData }
        };

        queue.push(payload);
        if (queue.length >= 10) sendBatch(false);
    }

    function firePageviewOnce() {
        if (pageviewSent || !trackingEnabled) return;
        pageviewSent = true;
        track('pageview', {
            title: document.title || '',
            lang: document.documentElement.lang || navigator.language || '',
            screen: (window.screen.width || 0) + 'x' + (window.screen.height || 0)
        });
    }

    // Public API
    window.WSC.track = track;
    window.WSC.flush = function() { sendBatch(false); };
    window.WSC.grantConsent = function() {
        writeConsentStorage(true);
        trackingEnabled = true;
        firePageviewOnce();
        sendBatch(false);
    };
    window.WSC.revokeConsent = function() {
        writeConsentStorage(false);
        trackingEnabled = false;
        queue = [];
    };

    // Declarative Tracking
    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-wsc-track]');
        if (!el) return;

        var eventName = el.getAttribute('data-wsc-track') || 'click';
        var meta = {};
        try {
            var metaAttr = el.getAttribute('data-wsc-meta');
            if (metaAttr) meta = JSON.parse(metaAttr);
        } catch (err) {}

        if (el.tagName === 'A' && el.href) {
            meta.href = el.href;
            if (!meta.text) meta.text = el.textContent.trim().substring(0, 100);
        }

        track(eventName, meta);
    }, true);

    // Bootstrap
    firePageviewOnce();
    setInterval(function() { sendBatch(false); }, 5000);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') sendBatch(true);
    });

})(window, document);
