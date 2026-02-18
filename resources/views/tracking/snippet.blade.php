<!-- Attributa Tracking -->
<script>
    (function(w,d,s,u,c){
        if (w.__ATTRIBUTA_LOADED__) return;
        w.__ATTRIBUTA_LOADED__ = true;
        var js = d.createElement(s);
        js.async = true;
        js.src = u + '?v=2&c=' + c;
        var fjs = d.getElementsByTagName(s)[0];
        fjs.parentNode.insertBefore(js, fjs);
    })(window, document,'script','{{ rtrim(config('app.url'), '/') . '/api/tracking/script.js' }}','{{ $userCode }}-{{ $campaignCode }}');
</script>
<!-- End Attributa Tracking -->
