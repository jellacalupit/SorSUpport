<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="application-name" content="SorSUpport">
<meta name="theme-color" content="#701f1f">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="SorSUpport">
<link rel="apple-touch-icon" href="{{ asset('branding/pwa-192.png') }}">
<meta name="pwa-icon" content="{{ asset('branding/pwa-192.png') }}">
<meta name="pwa-service-worker" content="{{ asset('service-worker.js') }}">
<script>
    (() => {
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

        try {
            if (isStandalone && sessionStorage.getItem('sorsupport-startup-shown') !== 'true') {
                sessionStorage.setItem('sorsupport-startup-shown', 'true');
                document.documentElement.classList.add('pwa-startup-pending');
            }
        } catch (error) {
            console.error('SorSUpport could not prepare its startup screen.', error);
        }
    })();
</script>
<script src="{{ asset('pwa.js') }}" defer></script>
