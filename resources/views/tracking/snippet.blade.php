<!-- Leadnode Tracking -->
<script>
    (function(w,d,s,u,c){
        if (w.__LEADNODE_LOADED__) return;
        w.__LEADNODE_LOADED__ = true;
        var js = d.createElement(s);
        js.async = true;
        js.src = u + '?v=2&c=' + c;
        var fjs = d.getElementsByTagName(s)[0];
        fjs.parentNode.insertBefore(js, fjs);
    })(window, document,'script','{{ rtrim(config('app.url'), '/') . '/api/tracking/script.js' }}','{{ $userCode }}-{{ $campaignCode }}');
</script>
<!-- End Leadnode Tracking -->
