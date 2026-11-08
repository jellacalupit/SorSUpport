<style>
    #pwa-startup-screen {
        position: fixed;
        inset: 0;
        z-index: 20000;
        display: none;
        place-items: center;
        background: #fbf8f7;
        opacity: 1;
        transition: opacity 240ms ease;
    }

    html.pwa-startup-pending #pwa-startup-screen {
        display: grid;
    }

    #pwa-startup-screen img {
        width: min(34vw, 144px);
        height: min(34vw, 144px);
        object-fit: contain;
        animation: pwa-logo-zoom-out 650ms cubic-bezier(0.2, 0.75, 0.25, 1) both;
    }

    #pwa-startup-screen .startup-brand {
        display: flex;
        width: min(72vw, 240px);
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }

    #pwa-startup-screen .startup-progress {
        width: 100%;
        opacity: 0;
        transform: translateY(8px);
        transition: opacity 220ms ease, transform 220ms ease;
    }

    #pwa-startup-screen.is-loading .startup-progress {
        opacity: 1;
        transform: translateY(0);
    }

    #pwa-startup-screen .startup-progress-track {
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background: #eadcda;
    }

    #pwa-startup-screen .startup-progress-fill {
        width: 4%;
        height: 100%;
        border-radius: inherit;
        background: #701f1f;
        transition: width 180ms ease-out;
    }

    #pwa-startup-screen .startup-percent {
        display: block;
        margin-top: 8px;
        color: #701f1f;
        font: 600 13px/1.2 system-ui, sans-serif;
        text-align: center;
    }

    #pwa-startup-screen.is-dismissing {
        opacity: 0;
        pointer-events: none;
    }

    @keyframes pwa-logo-zoom-out {
        from { transform: scale(1); }
        to { transform: scale(0.64); }
    }

    @media (prefers-reduced-motion: reduce) {
        #pwa-startup-screen,
        #pwa-startup-screen img,
        #pwa-startup-screen .startup-progress {
            transition: none;
            animation: none;
        }
    }
</style>
<div id="pwa-startup-screen" aria-hidden="true">
    <div class="startup-brand">
        <img src="{{ asset('branding/pwa-512.png') }}" alt="">
        <div class="startup-progress" role="progressbar" aria-label="Loading SorSUpport" aria-valuemin="0" aria-valuemax="100" aria-valuenow="4">
            <div class="startup-progress-track">
                <div class="startup-progress-fill"></div>
            </div>
            <span class="startup-percent">4%</span>
        </div>
    </div>
</div>