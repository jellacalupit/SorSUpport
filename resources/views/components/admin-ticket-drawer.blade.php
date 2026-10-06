{{--
    Desktop only: tickets in the admin lists open in a panel at the side, over the list.
    Below desktop width the same links simply open the ticket page (with its Back link).
    The panel shows the ticket page's own content, so both views always match.
--}}
<div id="admin-ticket-drawer" class="admin-ticket-drawer pointer-events-none invisible fixed inset-0 z-[70]" aria-hidden="true">
    <div data-drawer-backdrop class="absolute inset-0 bg-black/35 opacity-0 transition-opacity"></div>
    <aside data-drawer-panel class="absolute top-0 right-0 flex h-full w-full max-w-[72vw] translate-x-full flex-col overflow-y-auto bg-background shadow-2xl transition-transform duration-200 xl:max-w-[60vw]" role="dialog" aria-modal="true" aria-label="Ticket details">
        <div class="sticky top-0 z-20 flex items-center justify-between border-b border-border bg-card px-4 py-3">
            <h2 class="font-display text-lg font-bold text-primary">Ticket Details</h2>
            <button type="button" data-drawer-close class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-xl leading-none text-muted-foreground hover:bg-muted hover:text-primary" aria-label="Close ticket details">&times;</button>
        </div>
        <div data-drawer-body class="p-4"></div>
    </aside>
</div>

<script>
    (() => {
        // The sidebar swaps pages in without reloading, so listen once and look the panel up each time.
        if (window.adminTicketDrawerReady) return;
        window.adminTicketDrawerReady = true;

        const parts = () => {
            const drawer = document.getElementById('admin-ticket-drawer');

            return drawer ? {
                drawer,
                panel: drawer.querySelector('[data-drawer-panel]'),
                body: drawer.querySelector('[data-drawer-body]'),
                backdrop: drawer.querySelector('[data-drawer-backdrop]'),
            } : null;
        };
        const isTicketLink = (link) => /\/admin\/complaints\/\d+$/.test(new URL(link.href, window.location.href).pathname);
        const message = (text) => `<p class="py-16 text-center text-sm text-muted-foreground">${text}</p>`;
        let request = 0;

        const close = () => {
            const ui = parts();
            if (!ui) return;

            request++;
            ui.drawer.classList.add('pointer-events-none', 'invisible');
            ui.drawer.setAttribute('aria-hidden', 'true');
            ui.backdrop.classList.replace('opacity-100', 'opacity-0');
            ui.panel.classList.add('translate-x-full');
            ui.body.innerHTML = '';
        };

        const open = async (ui, url) => {
            const current = ++request;
            ui.body.innerHTML = message('Loading ticket…');
            ui.panel.scrollTop = 0;
            ui.drawer.classList.remove('pointer-events-none', 'invisible');
            ui.drawer.setAttribute('aria-hidden', 'false');
            ui.backdrop.classList.replace('opacity-0', 'opacity-100');
            ui.panel.classList.remove('translate-x-full');

            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Ticket could not be loaded');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const content = page.querySelector('[data-admin-main] .admin-content');
                if (!content) throw new Error('Ticket content not found');
                if (current !== request) return;

                ui.body.innerHTML = content.innerHTML;

                // Scripts that arrive as HTML do not run on their own (the conversation needs its script).
                ui.body.querySelectorAll('script').forEach((script) => {
                    const runnable = document.createElement('script');
                    runnable.textContent = script.textContent;
                    script.replaceWith(runnable);
                });

                window.requestAnimationFrame(() => {
                    const messages = ui.body.querySelector('[data-ticket-message-list]');
                    if (messages) messages.scrollTop = messages.scrollHeight;
                });
            } catch {
                // Fall back to the ticket page itself.
                window.location.href = url;
            }
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('#admin-ticket-drawer [data-drawer-close], #admin-ticket-drawer [data-drawer-backdrop]')) {
                close();
                return;
            }

            const link = event.target.closest('[data-ticket-results] a[href]');
            const ui = parts();
            if (!link || !ui || !isTicketLink(link)) return;
            if (window.innerWidth < 1024 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

            event.preventDefault();

            // The ticket is marked as read when it loads; show that in the list straight away.
            const row = link.closest('tr, li');
            row?.classList.remove('bg-primary-soft/70');
            row?.querySelectorAll('td.text-black').forEach((cell) => cell.classList.replace('text-black', 'text-muted-foreground'));
            row?.querySelector('[data-unread-badge]')?.remove();
            row?.querySelector('a.surface')?.classList.replace('bg-primary-soft', 'bg-card');

            open(ui, link.href);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && parts() && !parts().drawer.classList.contains('invisible')) close();
        });
    })();
</script>
