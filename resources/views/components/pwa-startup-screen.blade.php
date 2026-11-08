<style>
    #pwa-startup-screen {
        position: fixed;
        inset: 0;
        z-index: 20000;
        display: none;
        place-items: center;
        background: #fff;
        opacity: 1;
        transition: opacity 240ms ease;
    }

    #pwa-startup-screen img {
        width: min(34vw, 144px);
        height: min(34vw, 144px);
        object-fit: contain;
        animation: pwa-logo-pulse 1.4s ease-in-out infinite;
    }

    #pwa-startup-screen.is-dismissing {
        opacity: 0;
        pointer-events: none;
    }

    @keyframes pwa-logo-pulse {
        50% { transform: scale(1.06); }
    }

    @media (display-mode: standalone) {
        #pwa-startup-screen {
            display: grid;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #pwa-startup-screen,
        #pwa-startup-screen img {
            transition: none;
            animation: none;
        }
    }
</style>
<div id="pwa-startup-screen" aria-hidden="true">
    <img src="{{ asset('branding/pwa-192.png') }}" alt="">
</div>
