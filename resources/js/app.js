import './bootstrap';

import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';

window.Alpine = Alpine;

Alpine.plugin(collapse);
Alpine.start();

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
