<style>
    #pwa-startup-screen {
        position: fixed;
        inset: 0;
        z-index: 20000;
        display: none;
        place-items: center;
        background: var(--background, #ffffff);
        color: var(--foreground, #252525);
        opacity: 1;
        transition: opacity 300ms ease;
        font-family: 'Source Sans 3', ui-sans-serif, system-ui, sans-serif;
    }

    #pwa-startup-screen .startup-brand {
        display: grid;
        place-items: center;
        gap: 1rem;
        text-align: center;
        padding: 0 1rem;
    }

    #pwa-startup-screen img {
        width: min(34vw, 144px);
        height: min(34vw, 144px);
        object-fit: contain;
        filter: drop-shadow(0 8px 24px oklch(0.35 0.13 25 / 0.18));
        animation: pwa-logo-pulse 1.6s ease-in-out infinite;
    }

    #pwa-startup-screen .startup-title {
        font-family: 'Bitter', ui-serif, Georgia, serif;
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--foreground);
        margin: 0;
    }

    #pwa-startup-screen .startup-subtitle {
        font-size: 0.95rem;
        font-weight: 500;
        color: var(--muted-foreground, #706f6c);
        margin: 0;
        letter-spacing: -0.01em;
    }

    #pwa-startup-screen.is-dismissing {
        opacity: 0;
        pointer-events: none;
    }

    @keyframes pwa-logo-pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.08); opacity: 0.92; }
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

    @media (prefers-color-scheme: dark) {
        #pwa-startup-screen {
            background: var(--background, oklch(0.18 0.01 250));
        }
    }
</style>
<div id="pwa-startup-screen" aria-hidden="true">
    <div class="startup-brand">
        <img src="{{ asset('branding/pwa-512.png') }}" alt="">
        <h1 class="startup-title">SorSUpport</h1>
        <p class="startup-subtitle">Student Complaint and Ticketing System</p>
    </div>
</div>

<script>
(() => {
    const serviceWorkerUrl = document.querySelector('meta[name="pwa-service-worker"]')?.content;
    const iconUrl = document.querySelector('meta[name="pwa-icon"]')?.content;

    if ('serviceWorker' in navigator && serviceWorkerUrl) {
        navigator.serviceWorker.register(serviceWorkerUrl).catch((error) => {
            console.error('SorSUpport could not register its service worker.', error);
        });
    }

    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const startupScreen = document.getElementById('pwa-startup-screen');
    const startupWasShown = sessionStorage.getItem('sorsupport-startup-shown') === 'true';

    if (startupScreen && isStandalone && !startupWasShown) {
        const startupStartedAt = performance.now();
        sessionStorage.setItem('sorsupport-startup-shown', 'true');

        const dismissStartupScreen = () => {
            const remainingTime = Math.max(0, 800 - (performance.now() - startupStartedAt));

            window.setTimeout(() => {
                startupScreen.classList.add('is-dismissing');
                window.setTimeout(() => startupScreen.remove(), 260);
            }, remainingTime);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', dismissStartupScreen, { once: true });
        } else {
            dismissStartupScreen();
        }
    } else {
        startupScreen?.remove();
    }
})();
</script>