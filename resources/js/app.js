import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

window.Alpine = Alpine;

Alpine.plugin(collapse);
Alpine.start();

// Floating cards marked data-keep-in-view (profile cards, etc.) open next to the name that was
// clicked, so near the right edge they would run off the screen. Slide them back inside the
// visible area of whatever scrolls them (the page, a drawer) whenever they are shown.
const keepInViewMargin = 8;
const adjustingFloatingCards = new WeakSet();

const visibleBoundsFor = (element) => {
	let left = keepInViewMargin;
	let right = document.documentElement.clientWidth - keepInViewMargin;

	for (let parent = element.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
		const style = getComputedStyle(parent);

		if (style.overflowX !== 'visible' || style.overflowY !== 'visible') {
			const rect = parent.getBoundingClientRect();
			left = Math.max(left, rect.left + keepInViewMargin);
			right = Math.min(right, rect.right - keepInViewMargin);
		}
	}

	return { left, right };
};

const keepInView = (card) => {
	if (adjustingFloatingCards.has(card)) {
		return;
	}

	adjustingFloatingCards.add(card);
	card.style.translate = '';

	const rect = card.getBoundingClientRect();

	if (rect.width > 0) {
		const { left, right } = visibleBoundsFor(card);
		let shift = 0;

		if (rect.right > right) {
			shift = right - rect.right;
		}

		if (rect.left + shift < left) {
			shift = left - rect.left;
		}

		if (shift !== 0) {
			card.style.translate = `${Math.round(shift)}px 0`;
		}
	}

	// Our own style changes are reported to the observer below before this timer fires.
	setTimeout(() => adjustingFloatingCards.delete(card), 0);
};

new MutationObserver((mutations) => {
	mutations.forEach((mutation) => {
		if (mutation.target instanceof HTMLElement && mutation.target.matches('[data-keep-in-view]')) {
			keepInView(mutation.target);
		}
	});
}).observe(document.documentElement, { attributes: true, attributeFilter: ['style'], subtree: true });

// Cards that open on hover through CSS never change their style attribute.
document.addEventListener('mouseover', (event) => {
	event.target.closest?.('.group')?.querySelectorAll('[data-keep-in-view]').forEach(keepInView);
});

window.addEventListener('resize', () => {
	document.querySelectorAll('[data-keep-in-view]').forEach(keepInView);
});

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
