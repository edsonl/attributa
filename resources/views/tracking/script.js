(function () {
    // evita execução dupla
    if (window.__LEADNODE_PAGEVIEW_SENT__) return;
    window.__LEADNODE_PAGEVIEW_SENT__ = true;

    // Tokens definidos no backend (pode vir null)
    var USER_CODE = '{USER_CODE}';
    var CAMPAIGN_CODE = '{CAMPAIGN_CODE}';
    var AUTH_TS = '{AUTH_TS}';
    var AUTH_NONCE = '{AUTH_NONCE}';
    var AUTH_SIG = '{AUTH_SIG}';
    var TRACKING_PARAM_KEYS = '{TRACKING_PARAM_KEYS}';

    // Validação final
    if (!USER_CODE || !CAMPAIGN_CODE || !AUTH_TS || !AUTH_NONCE || !AUTH_SIG) {
        console.warn('[LEADNODE] Dados de autenticação do tracking não informados');
        return;
    }

    function normalizeTrackingParamKeys(raw) {
        if (!Array.isArray(raw)) {
            return [];
        }

        var keys = [];
        raw.forEach(function (item) {
            var value = String(item || '').trim();
            if (!value) return;
            if (keys.indexOf(value) !== -1) return;
            keys.push(value);
        });

        return keys;
    }

    var trackingParamKeys = normalizeTrackingParamKeys(TRACKING_PARAM_KEYS);

    // ===============================
    // Cookies helpers
    // ===============================
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie =
            name + '=' + encodeURIComponent(value) +
            expires + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }

    function generateSessionCode() {
        try {
            if (window.crypto && window.crypto.getRandomValues) {
                var bytes = new Uint8Array(12);
                window.crypto.getRandomValues(bytes);
                var out = '';
                for (var i = 0; i < bytes.length; i++) {
                    out += bytes[i].toString(16).padStart(2, '0');
                }
                return out;
            }
        } catch (e) {}

        return String(Date.now()) + String(Math.floor(Math.random() * 1000000));
    }

    function resolveUserSession() {
        var existing = getCookie('at_user_session');
        if (existing) {
            return existing;
        }

        var created = generateSessionCode();
        // Cookie de sessao: expira ao encerrar o navegador.
        setCookie('at_user_session', created);
        return created;
    }

    function resolveNavigationType() {
        try {
            var navEntries = window.performance && window.performance.getEntriesByType
                ? window.performance.getEntriesByType('navigation')
                : [];
            if (navEntries && navEntries.length > 0) {
                var navType = String(navEntries[0].type || '').trim();
                if (navType) {
                    return navType;
                }
            }
        } catch (e) {}

        try {
            if (window.performance && window.performance.navigation) {
                var legacyType = window.performance.navigation.type;
                if (legacyType === 1) return 'reload';
                if (legacyType === 2) return 'back_forward';
                if (legacyType === 0) return 'navigate';
            }
        } catch (e) {}

        return 'unknown';
    }

    // ===============================
    // Acquisition params (UTM + Click IDs)
    // ===============================
    var trackedParams = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'gad_campaignid',
        'fbclid',
        'ttclid',
        'msclkid',
        'wbraid',
        'gbraid'
    ];
    var trackedData = {};
    var userSession = resolveUserSession();
    var navigationType = resolveNavigationType();

    function readQueryParam(name) {
        if (window.URLSearchParams) {
            return new URLSearchParams(window.location.search).get(name);
        }

        var query = window.location.search.substring(1).split('&');
        for (var i = 0; i < query.length; i++) {
            var pair = query[i].split('=');
            if (decodeURIComponent(pair[0] || '') === name) {
                return decodeURIComponent(pair[1] || '');
            }
        }
        return null;
    }

    trackedParams.forEach(function (key) {
        var value = readQueryParam(key);

        if (value) {
            setCookie('at_' + key, value, 30);
        } else {
            value = getCookie('at_' + key);
        }

        trackedData[key] = value || null;
    });

    // ===============================
    // Payload base
    // ===============================
    var payload = {
        user_code: USER_CODE,
        campaign_code: CAMPAIGN_CODE,
        visitor_code: getCookie('at_visitor_code'),
        user_session: userSession,
        navigation_type: navigationType,
        auth_ts: AUTH_TS,
        auth_nonce: AUTH_NONCE,
        auth_sig: AUTH_SIG,
        url: window.location.href,
        landing_url: window.location.href,
        referrer: document.referrer || null,
        user_agent: navigator.userAgent,
        screen_width: window.screen && window.screen.width ? window.screen.width : null,
        screen_height: window.screen && window.screen.height ? window.screen.height : null,
        viewport_width: window.innerWidth || null,
        viewport_height: window.innerHeight || null,
        device_pixel_ratio: window.devicePixelRatio || null,
        platform: navigator.platform || null,
        language: navigator.language || null,
        utm_source: trackedData.utm_source,
        utm_medium: trackedData.utm_medium,
        utm_campaign: trackedData.utm_campaign,
        utm_term: trackedData.utm_term,
        utm_content: trackedData.utm_content,
        timestamp: Date.now(),
        gclid: trackedData.gclid,
        gad_campaignid: trackedData.gad_campaignid,
        fbclid: trackedData.fbclid,
        ttclid: trackedData.ttclid,
        msclkid: trackedData.msclkid,
        wbraid: trackedData.wbraid,
        gbraid: trackedData.gbraid
    };

    // ===============================
    // Envio do tracking
    // ===============================
    var endpoint = '{ENDPOINT}';
    var eventEndpoint = '{EVENT_ENDPOINT}';

    function sendPayload(endpoint, payload) {
        var data = JSON.stringify(payload);

        // 1️⃣ Fetch (preferencial)
        if (window.fetch) {
            try {
                fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: data,
                    keepalive: true
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (json) {
                        var pageviewCode = json && json.pageview_code;
                        var visitorCode = json && json.visitor_code;
                        var eventSig = json && json.event_sig;
                        if (visitorCode) {
                            setCookie('at_visitor_code', visitorCode, 30);
                        }
                        if (pageviewCode) {
                            setCookie('at_pageview_code', pageviewCode, 90);
                            if (eventSig) {
                                setCookie('at_event_sig', eventSig, 90);
                            }
                            initSubInjection(trackingParamKeys);
                            initInteractionTracking(trackingParamKeys, navigationType);
                        }
                    })
                    .catch(function () {});
                return;
            } catch (e) {}
        }

        // 2️⃣ Fallback legado (XMLHttpRequest)
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var json = JSON.parse(xhr.responseText);
                            var pageviewCode = json && json.pageview_code;
                            var visitorCode = json && json.visitor_code;
                            var eventSig = json && json.event_sig;
                            if (visitorCode) {
                                setCookie('at_visitor_code', visitorCode, 30);
                            }
                            if (pageviewCode) {
                                setCookie('at_pageview_code', pageviewCode, 90);
                                if (eventSig) {
                                    setCookie('at_event_sig', eventSig, 90);
                                }
                                initSubInjection(trackingParamKeys);
                                initInteractionTracking(trackingParamKeys, navigationType);
                            }
                        } catch (e) {}
                    }
                }
            };
            xhr.send(data);
        } catch (e) {}
    }

    // ==============================================
    // Injeção de parâmetros de tracking (forms/links)
    // ==============================================
    function initSubInjection(paramKeys) {
        var pageviewCode = getCookie('at_pageview_code');
        if (!pageviewCode) return;

        var COMPOSED_CODE = USER_CODE + '-' + CAMPAIGN_CODE + '-' + pageviewCode;
        var keys = Array.isArray(paramKeys)
            ? paramKeys
                .map(function (key) { return String(key || '').trim(); })
                .filter(function (key) { return key.length > 0; })
            : [];

        if (keys.length === 0) return;

        function upsertHiddenInput(form, name, value) {
            var input = form.querySelector('input[name="' + name + '"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }
            input.value = value;
        }

        document.querySelectorAll('form').forEach(function (form) {
            keys.forEach(function (key) {
                upsertHiddenInput(form, key, COMPOSED_CODE);
            });
        });

        document.querySelectorAll('a[href]').forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (!href || href === '#' || href.startsWith('#')) return;
            if (href.toLowerCase().startsWith('javascript:')) return;

            try {
                var url = new URL(href, window.location.href);

                if (
                    url.origin === window.location.origin &&
                    url.pathname === window.location.pathname &&
                    url.search === '' &&
                    url.hash
                ) {
                    return;
                }

                keys.forEach(function (key) {
                    url.searchParams.set(key, COMPOSED_CODE);
                });

                a.href = url.toString();
            } catch (e) {}
        });
    }

    // ==============================================
    // Eventos de interação (link click / form submit)
    // ==============================================
    function initInteractionTracking(paramKeys, currentNavigationType) {
        if (window.__LEADNODE_EVENTS_INIT__) return;
        window.__LEADNODE_EVENTS_INIT__ = true;

        var engagedSent = false;
        var interactionCount = 0;
        var engagedTimer = null;
        var scrollTriggered = false;

        var ignoredNamesMap = {};
        (Array.isArray(paramKeys) ? paramKeys : []).forEach(function (name) {
            var normalized = String(name || '').trim().toLowerCase();
            if (normalized) {
                ignoredNamesMap[normalized] = true;
            }
        });

        function getElementPosition(el, selector) {
            var list = document.querySelectorAll(selector);
            for (var i = 0; i < list.length; i++) {
                if (list[i] === el) {
                    return i + 1;
                }
            }
            return 1;
        }

        function getSafeText(value, maxLen) {
            var text = String(value || '').replace(/\s+/g, ' ').trim();
            if (!text) return '';
            return text.length > maxLen ? text.slice(0, maxLen) : text;
        }

        function getElementClasses(el) {
            return getSafeText(el && el.className ? el.className : '', 500) || null;
        }

        function getElementName(el, kind) {
            if (!el || !el.getAttribute) return null;

            var attrName = getSafeText(el.getAttribute('name'), 191);
            if (attrName) return attrName;

            var dataName = getSafeText(el.getAttribute('data-name'), 191);
            if (dataName) return dataName;

            if (kind === 'form') {
                return 'Formulário ' + getElementPosition(el, 'form');
            }

            if (kind === 'link') {
                var linkIndex = getElementPosition(el, 'a[href]');
                var linkText = getSafeText(el.textContent, 120);
                return linkText ? ('Link ' + linkIndex + ' - ' + linkText) : ('Link ' + linkIndex);
            }

            return null;
        }

        function resolveTrackingContext() {
            var pageviewCode = getCookie('at_pageview_code');
            var eventSig = getCookie('at_event_sig');
            if (!pageviewCode || !eventSig) {
                return null;
            }

            return {
                user_code: USER_CODE,
                campaign_code: CAMPAIGN_CODE,
                pageview_code: pageviewCode,
                event_sig: eventSig
            };
        }

        function sendEvent(eventPayload) {
            if (!eventEndpoint) return;

            var context = resolveTrackingContext();
            if (!context) return;

            var payload = {
                user_code: context.user_code,
                campaign_code: context.campaign_code,
                pageview_code: context.pageview_code,
                event_sig: context.event_sig,
                event_ts: Date.now()
            };

            for (var key in eventPayload) {
                if (Object.prototype.hasOwnProperty.call(eventPayload, key)) {
                    payload[key] = eventPayload[key];
                }
            }

            var data = JSON.stringify(payload);

            if (navigator.sendBeacon) {
                try {
                    var blob = new Blob([data], { type: 'text/plain;charset=UTF-8' });
                    var sent = navigator.sendBeacon(eventEndpoint, blob);
                    if (sent) return;
                } catch (e) {}
            }

            if (window.fetch) {
                try {
                    fetch(eventEndpoint, {
                        method: 'POST',
                        mode: 'no-cors',
                        headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
                        body: data,
                        keepalive: true
                    }).catch(function () {});
                } catch (e) {}
            }
        }

        function markEngaged(reason) {
            if (engagedSent) return;
            engagedSent = true;

            if (engagedTimer) {
                clearTimeout(engagedTimer);
                engagedTimer = null;
            }

            // Guarda o motivo canônico do engajamento em campo curto para análise posterior.
            // Exemplos: scroll_30, time_10s, link_click, form_submit, interactions.
            var normalizedReason = getSafeText(reason, 64) || 'unknown';

            sendEvent({
                event_type: 'page_engaged',
                target_url: getSafeText(window.location.href, 2000),
                element_name: getSafeText('Page engaged', 191),
                element_id: getSafeText('engagement_reason:' + normalizedReason, 191),
                element_classes: null
            });
        }

        if (currentNavigationType === 'reload') {
            sendEvent({
                event_type: 'navigation_reload',
                target_url: getSafeText(window.location.href, 2000),
                element_name: getSafeText('Navigation reload', 191),
                element_id: getSafeText('navigation_type:reload', 191),
                element_classes: null
            });
            markEngaged('navigation_reload');
        }

        function getScrollPercent() {
            var doc = document.documentElement || document.body;
            if (!doc) return 0;

            var scrollTop = window.pageYOffset || doc.scrollTop || 0;
            var maxScroll = Math.max((doc.scrollHeight || 0) - (window.innerHeight || 0), 0);
            if (maxScroll <= 0) return 100;

            return Math.min(100, Math.max(0, (scrollTop / maxScroll) * 100));
        }

        function findLinkElement(target) {
            if (!target) return null;
            if (target.closest) {
                return target.closest('a[href]');
            }

            var node = target;
            while (node && node !== document) {
                if (node.tagName && node.tagName.toLowerCase() === 'a' && node.getAttribute('href')) {
                    return node;
                }
                node = node.parentNode;
            }

            return null;
        }

        function resolveLinkTargetUrl(a) {
            var href = a ? (a.getAttribute('href') || '') : '';
            if (!href || href === '#' || href.charAt(0) === '#') return null;
            if (href.toLowerCase().indexOf('javascript:') === 0) return null;

            try {
                return new URL(href, window.location.href).toString();
            } catch (e) {
                return href;
            }
        }

        function fieldHasValue(field) {
            var tag = (field.tagName || '').toLowerCase();
            var type = tag === 'input' ? String(field.type || '').toLowerCase() : '';

            if (type === 'checkbox' || type === 'radio') {
                return !!field.checked;
            }

            if (type === 'file') {
                return field.files && field.files.length > 0;
            }

            if (tag === 'select' && field.multiple) {
                if (!field.options) return false;
                for (var i = 0; i < field.options.length; i++) {
                    var option = field.options[i];
                    if (option.selected && String(option.value || '').trim() !== '') {
                        return true;
                    }
                }
                return false;
            }

            return String(field.value || '').trim() !== '';
        }

        function getFormMetrics(form) {
            var fields = Array.prototype.slice.call(form.querySelectorAll('input, textarea, select'));
            var candidates = [];

            fields.forEach(function (field) {
                if (!field || field.disabled) return;

                var tag = (field.tagName || '').toLowerCase();
                var type = tag === 'input' ? String(field.type || '').toLowerCase() : '';
                if (tag === 'input' && type === 'hidden') return;

                var rawName = String(field.getAttribute('name') || '').trim();
                var normalizedName = rawName.toLowerCase();
                if (normalizedName && ignoredNamesMap[normalizedName]) return;

                candidates.push({
                    field: field,
                    normalizedName: normalizedName
                });
            });

            var priority = candidates.filter(function (item) {
                return item.normalizedName === 'name' || item.normalizedName === 'phone';
            });

            var evaluated = priority.length > 0 ? priority : candidates;
            var checked = evaluated.length;
            var filled = 0;

            evaluated.forEach(function (item) {
                if (fieldHasValue(item.field)) {
                    filled += 1;
                }
            });

            return {
                checked: checked,
                filled: filled,
                hasUserData: filled > 0
            };
        }

        document.addEventListener('click', function (ev) {
            interactionCount += 1;
            if (interactionCount >= 2) {
                markEngaged('interactions');
            }

            var link = findLinkElement(ev.target);
            if (!link) return;

            var targetUrl = resolveLinkTargetUrl(link);
            if (!targetUrl) return;

            if (!engagedSent) {
                markEngaged('link_click');
            }

            sendEvent({
                event_type: 'link_click',
                target_url: getSafeText(targetUrl, 2000),
                element_id: getSafeText(link.id || '', 191) || null,
                element_name: getElementName(link, 'link'),
                element_classes: getElementClasses(link)
            });
        }, true);

        document.addEventListener('keydown', function () {
            interactionCount += 1;
            if (interactionCount >= 2) {
                markEngaged('interactions');
            }
        }, true);

        document.addEventListener('touchstart', function () {
            interactionCount += 1;
            if (interactionCount >= 2) {
                markEngaged('interactions');
            }
        }, true);

        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') {
                return;
            }

            if (!engagedSent) {
                markEngaged('form_submit');
            }

            var metrics = getFormMetrics(form);
            var action = '';
            try {
                action = form.getAttribute('action')
                    ? new URL(form.getAttribute('action'), window.location.href).toString()
                    : window.location.href;
            } catch (e) {
                action = form.getAttribute('action') || window.location.href;
            }

            sendEvent({
                event_type: 'form_submit',
                target_url: getSafeText(action, 2000),
                element_id: getSafeText(form.id || '', 191) || null,
                element_name: getElementName(form, 'form'),
                element_classes: getElementClasses(form),
                form_fields_checked: metrics.checked,
                form_fields_filled: metrics.filled,
                form_has_user_data: metrics.hasUserData
            });
        }, true);

        window.addEventListener('scroll', function () {
            if (scrollTriggered || engagedSent) return;

            var percent = getScrollPercent();
            if (percent >= 30) {
                scrollTriggered = true;
                markEngaged('scroll_30');
            }
        }, { passive: true });

        engagedTimer = setTimeout(function () {
            markEngaged('time_10s');
        }, 10000);
    }

    sendPayload(endpoint, payload);
})();
