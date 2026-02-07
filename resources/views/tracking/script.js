(function () {
    // evita execução dupla
    if (window.__ATTRIBUTA_PAGEVIEW_SENT__) return;
    window.__ATTRIBUTA_PAGEVIEW_SENT__ = true;

    // 🔹 Código da campanha definido no backend (pode vir null)
    let CAMPAIGN_CODE = '{CAMPAIGN_CODE}';

    // 🔹 Validação final
    if (!CAMPAIGN_CODE) {
        console.warn('[Attributa] Campaign code não informado');
        return;
    }

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

    // ===============================
    // Google Ads params
    // ===============================
    var gclid = null;
    var gadCampaignId = null;

    if (window.URLSearchParams) {
        var urlParams = new URLSearchParams(window.location.search);
        gclid = urlParams.get('gclid');
        gadCampaignId = urlParams.get('gad_campaignid');
    } else {
        // fallback manual (IE / legacy)
        var query = window.location.search.substring(1).split('&');
        for (var i = 0; i < query.length; i++) {
            var pair = query[i].split('=');
            if (pair[0] === 'gclid') gclid = decodeURIComponent(pair[1] || '');
            if (pair[0] === 'gad_campaignid') gadCampaignId = decodeURIComponent(pair[1] || '');
        }
    }

    if (gclid) setCookie('at_gclid', gclid, 30);
    if (gadCampaignId) setCookie('at_gad_campaignid', gadCampaignId, 30);

    if (!gclid) gclid = getCookie('at_gclid');
    if (!gadCampaignId) gadCampaignId = getCookie('at_gad_campaignid');


    // ===============================
    // Payload base
    // ===============================
    var payload = {
        campaign_code: CAMPAIGN_CODE,
        url: window.location.href,
        referrer: document.referrer || null,
        user_agent: navigator.userAgent,
        timestamp: Date.now(),
        gclid: gclid || null,
        gad_campaignid: gadCampaignId || null
    };

    // ===============================
    // Envio do tracking
    // ===============================
    //var protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
    //var endpoint = protocol + '//attributa.cloud/tracking/collect';
    var endpoint = '{ENDPOINT}';

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
                        if (json && json.pageview_id) {
                            setCookie('at_pageview_id', json.pageview_id, 90);
                            initSubInjection();
                        }
                    })
                    .catch(function () {});
                return;
            } catch (e) {}
        }

        // fallback legado
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
                            if (json && json.pageview_id) {
                                setCookie('at_pageview_id', json.pageview_id, 90);
                                initSubInjection();
                            }
                        } catch (e) {}
                    }
                }
            };
            xhr.send(data);
        } catch (e) {}
    }

    sendPayload(endpoint, payload);

    // ===============================
    // SUBs injection (Dr.Cash)
    // ===============================
    function initSubInjection() {
        var pageviewId = getCookie('at_pageview_id');
        if (!pageviewId) return;

        var COMPOSED_CODE = CAMPAIGN_CODE + '-' + pageviewId;
        var SUB_KEYS = ['sub1', 'sub2', 'sub3', 'sub4', 'sub5'];

        // ----- Forms -----
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
            SUB_KEYS.forEach(function (key) {
                upsertHiddenInput(form, key, COMPOSED_CODE);
            });
        });

        // ----- Links -----
        document.querySelectorAll('a[href]').forEach(function (a) {
            var href = a.getAttribute('href') || '';
            if (!href || href === '#' || href.startsWith('#')) return;
            if (href.toLowerCase().startsWith('javascript:')) return;

            try {
                var url = new URL(href, window.location.href);

                // ignora âncoras da própria página
                if (
                    url.origin === window.location.origin &&
                    url.pathname === window.location.pathname &&
                    url.search === '' &&
                    url.hash
                ) {
                    return;
                }

                SUB_KEYS.forEach(function (key) {
                    url.searchParams.set(key, COMPOSED_CODE);
                });

                a.href = url.toString();
            } catch (e) {}
        });
    }

})();
