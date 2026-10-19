(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) {
        return;
    }

    var siteKey = script.getAttribute('data-site');
    if (!siteKey) {
        return;
    }

    var endpoint = script.getAttribute('data-api') || new URL(script.src).origin + '/api/event';
    var debug = script.hasAttribute('data-debug');
    var downloadExtensions = (script.getAttribute('data-download-ext') || 'pdf,zip,docx,xlsx,csv,mp3,mp4')
        .split(',')
        .map(function (ext) { return ext.trim().toLowerCase(); });

    function isIgnored() {
        var isLocal = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname);
        if (isLocal && !script.hasAttribute('data-allow-localhost')) {
            return true;
        }
        if (navigator.webdriver && !script.hasAttribute('data-allow-automation')) {
            return true;
        }
        try {
            if (window.localStorage.getItem('webstats_ignore') === '1') {
                return true;
            }
        } catch (e) {
        }
        return false;
    }

    var ignored = isIgnored();
    var currentUrl = location.href;
    var referrer = document.referrer;
    var visibleSince = null;
    var engagedMs = 0;
    var maxScroll = 0;

    function send(name, extra) {
        if (ignored) {
            return;
        }
        extra = extra || {};

        var payload = {
            s: siteKey,
            n: name,
            u: currentUrl,
            r: referrer,
            w: window.screen ? window.screen.width : 0,
            e: extra.e || 0,
            d: extra.d || 0,
            p: extra.p || {}
        };

        if (debug) {
            console.debug('[webstats]', payload);
        }

        var body = JSON.stringify(payload);
        var sent = false;

        if (navigator.sendBeacon) {
            try {
                sent = navigator.sendBeacon(endpoint, new Blob([body], { type: 'text/plain' }));
            } catch (e) {
                sent = false;
            }
        }

        if (!sent && window.fetch) {
            fetch(endpoint, {
                method: 'POST',
                body: body,
                keepalive: true,
                mode: 'no-cors',
                headers: { 'Content-Type': 'text/plain' }
            }).catch(function () {});
        }
    }

    function scrollDepth() {
        var doc = document.documentElement;
        var height = Math.max(doc.scrollHeight, document.body ? document.body.scrollHeight : 0);
        if (height <= 0) {
            return 0;
        }
        return Math.min(100, Math.round((window.scrollY + window.innerHeight) / height * 100));
    }

    function trackScroll() {
        var depth = scrollDepth();
        if (depth > maxScroll) {
            maxScroll = depth;
        }
    }

    function startTimer() {
        if (visibleSince === null && document.visibilityState === 'visible') {
            visibleSince = Date.now();
        }
    }

    function stopTimer() {
        if (visibleSince !== null) {
            engagedMs += Date.now() - visibleSince;
            visibleSince = null;
        }
    }

    function flushEngagement() {
        stopTimer();
        if (engagedMs > 0) {
            send('engagement', { e: engagedMs, d: maxScroll });
        }
        engagedMs = 0;
    }

    function pageview() {
        currentUrl = location.href;
        engagedMs = 0;
        maxScroll = 0;
        visibleSince = null;
        trackScroll();
        startTimer();
        send('pageview');
    }

    function pageKey(href) {
        var url = new URL(href);
        return url.origin + url.pathname + url.search;
    }

    function handleNavigation() {
        if (pageKey(location.href) === pageKey(currentUrl)) {
            return;
        }
        flushEngagement();
        referrer = currentUrl;
        pageview();
    }

    function parseProps(raw) {
        if (!raw) {
            return {};
        }
        try {
            var props = JSON.parse(raw);
            return props && typeof props === 'object' && !Array.isArray(props) ? props : {};
        } catch (e) {
            return {};
        }
    }

    function handleClick(event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) {
            return;
        }

        var tracked = target.closest('[data-webstats-event]');
        if (tracked) {
            send(tracked.getAttribute('data-webstats-event'), {
                p: parseProps(tracked.getAttribute('data-webstats-props'))
            });
        }

        var link = target.closest('a[href]');
        if (!link) {
            return;
        }

        var url;
        try {
            url = new URL(link.href, location.href);
        } catch (e) {
            return;
        }
        if (url.protocol !== 'http:' && url.protocol !== 'https:') {
            return;
        }

        var lastSegment = url.pathname.split('/').pop();
        var extension = lastSegment.indexOf('.') !== -1 ? lastSegment.split('.').pop().toLowerCase() : '';

        if (extension && downloadExtensions.indexOf(extension) !== -1) {
            send('download', { p: { url: url.href } });
        } else if (url.host !== location.host) {
            send('outbound', { p: { url: url.href } });
        }
    }

    function api(name, options) {
        if (typeof name !== 'string' || name === '') {
            return;
        }
        send(name, { p: options && options.props ? options.props : {} });
    }

    var queued = window.webstats && Array.isArray(window.webstats.q) ? window.webstats.q : [];
    window.webstats = api;

    ['pushState', 'replaceState'].forEach(function (method) {
        var original = history[method];
        history[method] = function () {
            var result = original.apply(this, arguments);
            handleNavigation();
            return result;
        };
    });

    window.addEventListener('popstate', handleNavigation);
    window.addEventListener('scroll', trackScroll, { passive: true });
    document.addEventListener('click', handleClick, true);
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            flushEngagement();
        } else {
            startTimer();
        }
    });
    window.addEventListener('pagehide', flushEngagement);

    pageview();

    queued.forEach(function (args) {
        api.apply(null, args);
    });
})();
