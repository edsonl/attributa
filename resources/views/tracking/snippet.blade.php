<!-- Attributa Tracking -->
<script>
    (function(w,d,s,u,c){
        if (w.__ATTRIBUTA_LOADED__) return;
        w.__ATTRIBUTA_LOADED__ = true;
        var js = d.createElement(s);
        js.async = true;
        js.src = u + '?c=' + encodeURIComponent(c);
        var fjs = d.getElementsByTagName(s)[0];
        fjs.parentNode.insertBefore(js, fjs);
    })(window, document, 'script','{{ rtrim(config('app.url'), '/') . '/api/tracking/script.js?v=2.0.0' }}', '{{ $code }}');
</script>
<!-- End Attributa Tracking -->
