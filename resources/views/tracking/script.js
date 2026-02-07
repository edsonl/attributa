(function () {
    // evita execução dupla
    if (window.__ATTRIBUTA_PAGEVIEW_SENT__) return;
    window.__ATTRIBUTA_PAGEVIEW_SENT__ = true;

    // obtém o código da campanha da query string
    var params = new URLSearchParams(window.location.search);
    var scriptTag = document.currentScript;
    var campaignCode = null;

    if (scriptTag) {
        var src = scriptTag.getAttribute('src');
        if (src && src.indexOf('?') !== -1) {
            var query = src.split('?')[1];
            var q = new URLSearchParams(query);
            campaignCode = q.get('c');
        }
    }

    if (!campaignCode) return;

    var payload = {
        campaign_code: campaignCode,
        url: window.location.href,
        referrer: document.referrer || null,
        user_agent: navigator.userAgent,
        timestamp: Date.now()
    };

    // endpoint fixo
    var protocol = window.location.protocol === 'https:' ? 'https:' : 'http:';
    var endpoint =
        protocol +
        '//attributa.cloud/tracking/collect';

    function sendPayload(endpoint, payload) {
        var data = JSON.stringify(payload);

        // 1️⃣ Melhor opção: sendBeacon (tracking-friendly)
        if (navigator.sendBeacon) {
            var blob = new Blob([data], { type: 'application/json' });
            navigator.sendBeacon(endpoint, blob);
            return;
        }

        // 2️⃣ Fetch (browsers modernos)
        if (window.fetch) {
            try {
                fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: data,
                    keepalive: true
                });
                return;
            } catch (e) {}
        }

        // 3️⃣ Fallback final: XMLHttpRequest (legado)
        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(data);
        } catch (e) {}
    }

    // envio não bloqueante
    sendPayload(endpoint,payload);

})();
