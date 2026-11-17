export function profileCardPosition(anchor, card, viewport, margin = 8) {
    const below = viewport.height - anchor.bottom - margin;
    const above = anchor.top - margin;
    const openAbove = below < card.height + margin && above > below;
    const top = openAbove ? anchor.top - card.height - margin : anchor.bottom + margin;

    return {
        left: Math.max(margin, Math.min(anchor.left, viewport.width - card.width - margin)),
        top: Math.max(margin, Math.min(top, viewport.height - card.height - margin)),
    };
}

export function initializeProfileCards() {
    const initialized = new WeakSet();
    let active = null;
    let closeTimer;
    let positionFrame;

    const close = () => {
        clearTimeout(closeTimer);
        if (!active) return;
        const { card, trigger } = active;
        active = null;
        if (card.matches(':popover-open')) card.hidePopover();
        trigger.setAttribute('aria-expanded', 'false');
    };

    const position = () => {
        if (!active) return;
        const { card, trigger } = active;
        const anchor = trigger.getBoundingClientRect();
        const viewport = { width: document.documentElement.clientWidth, height: window.innerHeight };
        if (!trigger.isConnected || !trigger.getClientRects().length || anchor.bottom < 0 || anchor.top > viewport.height || anchor.right < 0 || anchor.left > viewport.width) {
            close();
            return;
        }
        const point = profileCardPosition(anchor, card.getBoundingClientRect(), viewport);
        card.style.left = `${point.left}px`;
        card.style.top = `${point.top}px`;
    };

    const show = (card, trigger, pinned = false) => {
        clearTimeout(closeTimer);
        if (active?.card !== card) {
            close();
            active = { card, trigger, pinned };
            card.showPopover();
            trigger.setAttribute('aria-expanded', 'true');
        } else if (pinned) {
            active.pinned = true;
        }
        position();
    };

    const deferClose = () => {
        clearTimeout(closeTimer);
        if (!active?.pinned) closeTimer = setTimeout(close, 180);
    };

    const register = (card) => {
        if (initialized.has(card)) return;
        const trigger = card.previousElementSibling;
        if (!(trigger instanceof HTMLElement)) return;
        initialized.add(card);
        card.setAttribute('popover', 'manual');
        trigger.setAttribute('aria-expanded', 'false');
        if (!trigger.matches('button, a, input')) {
            trigger.setAttribute('role', 'button');
            trigger.tabIndex = 0;
        }
        trigger.addEventListener('pointerenter', (event) => {
            if (event.pointerType !== 'touch') show(card, trigger);
        });
        trigger.addEventListener('pointerleave', deferClose);
        trigger.addEventListener('focus', () => show(card, trigger));
        trigger.addEventListener('blur', deferClose);
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (active?.card === card && active.pinned) close();
            else show(card, trigger, true);
        });
        trigger.addEventListener('keydown', (event) => {
            if (!trigger.matches('button, a') && ['Enter', ' '].includes(event.key)) {
                event.preventDefault();
                trigger.click();
            }
        });
        card.addEventListener('pointerenter', () => clearTimeout(closeTimer));
        card.addEventListener('pointerleave', deferClose);
    };

    const scan = (root) => {
        if (!(root instanceof Element)) return;
        if (root.matches('[data-keep-in-view]')) register(root);
        root.querySelectorAll('[data-keep-in-view]').forEach(register);
    };
    scan(document.documentElement);
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach(scan));
        if (active && (!active.trigger.isConnected || !active.trigger.getClientRects().length || active.trigger.closest('[aria-hidden="true"]'))) close();
    }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'aria-hidden'] });

    document.addEventListener('pointerdown', (event) => {
        if (active && !active.card.contains(event.target) && !active.trigger.contains(event.target)) close();
    }, true);
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && active) {
            event.preventDefault();
            event.stopImmediatePropagation();
            close();
        }
    }, true);
    const schedulePosition = () => {
        if (!active || positionFrame) return;
        positionFrame = requestAnimationFrame(() => {
            positionFrame = null;
            position();
        });
    };
    window.addEventListener('resize', schedulePosition);
    document.addEventListener('scroll', schedulePosition, true);
}
