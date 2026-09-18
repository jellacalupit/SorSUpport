import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

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
