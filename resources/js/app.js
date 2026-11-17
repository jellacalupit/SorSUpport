import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

window.Alpine = Alpine;

Alpine.plugin(collapse);
Alpine.start();

import { initializeProfileCards } from './profile-cards';

initializeProfileCards();

// Escape closes whatever is open on top (a modal, a drawer or the mobile menu) the same way its
// close button, or a click on the dark backdrop, would.
const overlaySelector = '.fixed.inset-0';

const isShown = (element) => {
	const style = getComputedStyle(element);

	return style.display !== 'none' && style.visibility !== 'hidden' && element.getClientRects().length > 0;
};

const zIndexOf = (element) => Number.parseInt(getComputedStyle(element).zIndex, 10) || 0;

window.addEventListener('keydown', (event) => {
	if (event.key !== 'Escape' || event.defaultPrevented) {
		return;
	}

	// Outer overlays only, highest first; among equals, the one added last sits on top.
	const overlays = [...document.querySelectorAll(overlaySelector)]
		.filter((overlay) => isShown(overlay) && !overlay.parentElement?.closest(overlaySelector));

	if (overlays.length === 0) {
		return;
	}

	const top = overlays.reduce((current, overlay) => (zIndexOf(overlay) >= zIndexOf(current) ? overlay : current));
	const closeButton = [...top.querySelectorAll('button[aria-label^="Close" i], [data-admin-ticket-close], [data-my-ticket-close], [data-ticket-review-close]')]
		.find((button) => isShown(button) && !button.disabled);

	if (closeButton) {
		closeButton.click();
	} else {
		top.querySelector('[data-admin-ticket-backdrop], [data-my-ticket-backdrop]')?.click() ?? top.click();
	}
}, true);

// On phones a ticket list loads its next page as the reader scrolls to the end, like an inbox;
// desktops keep the page numbers. The marker below the list carries the next page's address.
const phoneWidth = window.matchMedia('(max-width: 767px)');
const loadingTickets = new WeakSet();

const roundListEnds = (list) => {
	const cards = [...list.querySelectorAll(':scope > .surface, :scope > li > .surface')];

	cards.forEach((card, index) => {
		const first = index === 0;
		const last = index === cards.length - 1;
		card.classList.remove('rounded-xl', 'rounded-t-xl', 'rounded-b-xl', 'rounded-t-none', 'rounded-b-none', 'rounded-none');
		card.classList.add(...(first && last ? ['rounded-xl'] : first ? ['rounded-t-xl', 'rounded-b-none'] : last ? ['rounded-t-none', 'rounded-b-xl'] : ['rounded-none']));
	});
};

const ticketMarkerWatcher = new IntersectionObserver((entries) => {
	entries.forEach((entry) => {
		if (entry.isIntersecting) {
			loadMoreTickets(entry.target);
		}
	});
}, { rootMargin: '300px 0px' });

async function loadMoreTickets(marker) {
	const list = marker.closest('[data-ticket-results]')?.querySelector('[data-ticket-list]');
	const url = marker.dataset.nextUrl;

	if (!phoneWidth.matches || loadingTickets.has(marker) || !list || !url) {
		return;
	}

	loadingTickets.add(marker);
	marker.textContent = 'Loading more tickets…';

	try {
		const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

		if (!response.ok) {
			throw new Error('Unable to load more tickets');
		}

		const page = new DOMParser().parseFromString(await response.text(), 'text/html');

		// The list was replaced (a new filter or page) while this page loaded.
		if (!document.body.contains(marker)) {
			return;
		}

		list.append(...(page.querySelector('[data-ticket-list]')?.children ?? []));
		roundListEnds(list);

		const nextUrl = page.querySelector('[data-ticket-more]')?.dataset.nextUrl;

		if (!nextUrl) {
			marker.remove();
			return;
		}

		marker.dataset.nextUrl = nextUrl;
		loadingTickets.delete(marker);
		// Watching again reports at once if the marker is still in view on a short list.
		ticketMarkerWatcher.unobserve(marker);
		ticketMarkerWatcher.observe(marker);
	} catch {
		loadingTickets.delete(marker);
		marker.textContent = 'Could not load more tickets. Tap to try again.';
		marker.addEventListener('click', () => loadMoreTickets(marker), { once: true });
	}
}

const watchTicketMarkers = () => {
	document.querySelectorAll('[data-ticket-more]:not([data-watched])').forEach((marker) => {
		marker.dataset.watched = '';
		ticketMarkerWatcher.observe(marker);
	});
};

// Lists arrive with the page, after a filter change and after in-app navigation.
let ticketMarkerCheck = null;
new MutationObserver(() => {
	cancelAnimationFrame(ticketMarkerCheck);
	ticketMarkerCheck = requestAnimationFrame(watchTicketMarkers);
}).observe(document.documentElement, { childList: true, subtree: true });
watchTicketMarkers();

// Turning a phone to landscape can cross into the desktop layout, and back.
phoneWidth.addEventListener('change', () => {
	document.querySelectorAll('[data-ticket-more]').forEach((marker) => {
		ticketMarkerWatcher.unobserve(marker);
		ticketMarkerWatcher.observe(marker);
	});
});

document.addEventListener('click', (event) => {
	const trigger = event.target.closest('#bulk-upload-trigger');

	if (!trigger) {
		return;
	}

	const input = document.getElementById('bulk-upload-input');
	const type = document.getElementById('bulk-upload-type');
	const selectedTab = document.querySelector('[data-account-tab][aria-selected="true"]')?.dataset.accountTab;

	if (input && type) {
		type.value = selectedTab === 'recipients' ? 'recipient' : 'student';
		input.click();
	}
});

document.addEventListener('change', (event) => {
	if (event.target.id !== 'bulk-upload-input' || !event.target.files?.length) {
		return;
	}

	document.getElementById('bulk-upload-form')?.submit();
});

document.addEventListener('click', async (event) => {
	const link = event.target.closest('[data-ticket-filter-link]');

	if (!link) {
		return;
	}

	event.preventDefault();

	const form = link.closest('[data-ticket-filter-form]');
	const results = document.querySelector('[data-ticket-results]');

	if (!form || !results) {
		window.location.href = link.href;
		return;
	}

	try {
		const response = await fetch(link.href, {
			headers: { 'X-Requested-With': 'XMLHttpRequest' },
		});

		if (!response.ok) {
			throw new Error('Unable to refresh tickets');
		}

		const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
		const nextForm = documentFragment.querySelector('[data-ticket-filter-form]');
		const nextResults = documentFragment.querySelector('[data-ticket-results]');

		if (!nextForm || !nextResults) {
			throw new Error('Ticket results not found');
		}

		form.replaceWith(nextForm);
		results.replaceWith(nextResults);
		window.Alpine?.initTree(nextForm);
		window.history.pushState({}, '', link.href);
	} catch {
		window.location.href = link.href;
	}
});
