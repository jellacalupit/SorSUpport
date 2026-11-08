<style>
    #pwa-startup-screen {
        position: fixed;
        inset: 0;
        z-index: 20000;
        display: none;
        place-items: center;
        background: #ffffff;
        opacity: 1;
        transition: opacity 500ms ease;
    }

    html.pwa-startup-pending #pwa-startup-screen {
        display: grid;
    }

    #pwa-startup-screen .startup-brand {
        display: grid;
        place-items: center;
    }

    #pwa-startup-screen img {
        width: min(42vw, 180px);
        height: min(42vw, 180px);
        object-fit: contain;
        transition: transform 500ms ease, opacity 500ms ease;
    }

    #pwa-startup-screen.is-dismissing {
        opacity: 0;
        pointer-events: none;
    }

    #pwa-startup-screen.is-dismissing img {
        transform: scale(1.2);
        opacity: 0;
    }

    @media (prefers-reduced-motion: reduce) {
        #pwa-startup-screen,
        #pwa-startup-screen img {
            transition: none;
        }
    }
</style>
<div id="pwa-startup-screen" aria-hidden="true">
    <div class="startup-brand">
        <img src="{{ asset('branding/pwa-512.png') }}" alt="">
    </div>
</div>