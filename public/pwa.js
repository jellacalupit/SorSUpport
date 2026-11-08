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
    const startupIsPending = document.documentElement.classList.contains('pwa-startup-pending');

    if (startupScreen && isStandalone && startupIsPending) {
        const startedAt = performance.now();
        let pageLoaded = document.readyState === 'complete';

        const finishStartup = () => {
            window.setTimeout(() => {
                startupScreen.classList.add('is-dismissing');
                document.documentElement.classList.remove('pwa-startup-pending');
                window.setTimeout(() => startupScreen.remove(), 520);
            }, Math.max(0, 500 - (performance.now() - startedAt)));
        };

        window.addEventListener('load', () => {
            pageLoaded = true;
            finishStartup();
        }, { once: true });

        if (pageLoaded) {
            finishStartup();
        }
    } else {
        startupScreen?.remove();
    }

    const userAgent = navigator.userAgent;
    const isIOS = /iphone|ipad|ipod/i.test(userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let installPrompt = null;
    let card = null;

    const positionCard = () => {
        if (!card) {
            return;
        }

        const tabBar = document.querySelector('[data-mobile-tab-bar]');
        const tabBarVisible = tabBar && getComputedStyle(tabBar).display !== 'none';
        const bottomOffset = tabBarVisible
            ? Math.max(16, window.innerHeight - tabBar.getBoundingClientRect().top + 12)
            : 16;

        card.style.bottom = `${bottomOffset}px`;
    };

    const dismissCard = () => {
        if (card) {
            card.remove();
            card = null;
        }
    };

    const showCard = () => {
        if (card || isStandalone || sessionStorage.getItem('sorsupport-install-dismissed') === 'true') {
            return;
        }

        card = document.createElement('aside');
        card.setAttribute('aria-label', 'Install SorSUpport');
        card.setAttribute('role', 'status');
        card.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:10000;display:flex;align-items:center;gap:12px;width:min(360px,calc(100vw - 32px));box-sizing:border-box;padding:14px;border:1px solid #e6dfdd;border-radius:16px;background:#fff;color:#252525;box-shadow:0 8px 32px #32101024;font:14px/1.45 system-ui,sans-serif';
        positionCard();

        const icon = document.createElement('img');
        icon.src = iconUrl || '';
        icon.alt = '';
        icon.width = 44;
        icon.height = 44;
        icon.style.cssText = 'flex:none;border-radius:10px;object-fit:cover';

        const content = document.createElement('div');
        content.style.cssText = 'min-width:0;flex:1';

        const title = document.createElement('strong');
        title.textContent = 'Install SorSUpport';
        title.style.display = 'block';

        const message = document.createElement('span');
        message.textContent = isIOS
            ? 'In Safari, tap Share, then Add to Home Screen.'
            : 'Add the app to your device for quick access.';
        message.style.display = 'block';

        const action = document.createElement('button');
        action.type = 'button';
        action.textContent = isIOS ? 'Got it' : 'Install';
        action.style.cssText = 'margin-top:8px;padding:7px 12px;border:0;border-radius:8px;background:#701f1f;color:#fff;font:inherit;font-weight:600;cursor:pointer';
        action.addEventListener('click', async () => {
            if (!installPrompt) {
                sessionStorage.setItem('sorsupport-install-dismissed', 'true');
                dismissCard();
                return;
            }

            installPrompt.prompt();
            await installPrompt.userChoice;
            installPrompt = null;
            dismissCard();
        });

        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = '×';
        close.setAttribute('aria-label', 'Dismiss install message');
        close.style.cssText = 'align-self:flex-start;padding:0 4px;border:0;background:transparent;color:#57534e;font-size:22px;line-height:1;cursor:pointer';
        close.addEventListener('click', () => {
            sessionStorage.setItem('sorsupport-install-dismissed', 'true');
            dismissCard();
        });

        content.append(title, message, action);
        card.append(icon, content, close);
        document.body.append(card);
        positionCard();
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        showCard();
    });

    window.addEventListener('appinstalled', dismissCard);
    window.addEventListener('resize', positionCard);

    if (isIOS && !isStandalone) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', showCard, { once: true });
        } else {
            showCard();
        }
    }
})();
