/** Mount each interactive example using the same PairMotion APIs available to applications. */
(() => {
	'use strict';
	const motion = window.PairMotion;
	if (!motion) return;
	/** Get a control from the static demo markup. */
	const byId = id => document.getElementById(id);
	const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
	const tempo = byId('tempo');

	/** Explain immediately why a motion preview is static when the system requests it. */
	function updatePreference() {
		byId('motion-preference').hidden = !preference.matches;
		tempo.disabled = preference.matches;
	}

	/** Adjust the duration of subsequent examples without changing the system preference. */
	function setTempo() {
		document.documentElement.dataset.motionTempo = tempo.value;
	}
	tempo.addEventListener('change', setTempo);
	preference.addEventListener?.('change', updatePreference);
	updatePreference();

	const entrance = byId('entrance');
	const scroll = byId('scroll-entrance');

	/** Keep the preview frame in place and use either a full replay or a scroll trigger. */
	function playEntrance(onScroll) {
		entrance.classList.toggle('viewport', onScroll);
		if (onScroll) entrance.tabIndex = 0;
		else entrance.removeAttribute('tabindex');
		entrance.scrollTop = 0;
		scroll.setAttribute('aria-pressed', String(onScroll));
		const options = { duration: 280, stagger: 54, distance: 8, opacity: .64 };
		if (onScroll) Object.assign(options, { trigger: 'viewport', viewport: entrance });
		motion.page.enter(entrance, options);
	}
	byId('replay-entrance').addEventListener('click', () => playEntrance(false));
	scroll.addEventListener('click', () => playEntrance(true));
	motion.selection.mount(byId('selection'));

	const counter = byId('counter');
	const reel = { duration: 380, stagger: 22, rollNewDigits: true };
	/** Retarget the reel from its visible position and let any new digit roll as well. */
	byId('counter-controls').addEventListener('click', event => {
		const button = event.target.closest('button');
		if (button?.value) motion.counter.set(counter, button.value, undefined, reel);
	});
	byId('reel-replay').addEventListener('click', () => motion.counter.enter(counter, reel));

	const progress = byId('progress');
	const progressLabel = byId('progress-value');
	motion.progress.set(progress, progress.querySelector('progress').value);
	/** Move the existing fill while committing the new progress value immediately. */
	byId('progress-controls').addEventListener('click', event => {
		const button = event.target.closest('button');
		if (!button?.value) return;
		motion.progress.set(progress, Number(button.value));
		progressLabel.textContent = button.value + '%';
	});

	const rank = byId('ranking');
	const toggleRank = byId('reverse');
	const rows = [...rank.children];
	const [north, central, south, east] = rows;
	east.remove();
	east.hidden = false;
	/** Keep the two retained rows keyed across an entry and an exit. */
	toggleRank.addEventListener('click', () => {
		const changed = toggleRank.getAttribute('aria-pressed') !== 'true';
		motion.layout.update(rank, () => {
			rank.replaceChildren(...(changed ? [south, north, east] : [north, central, south]));
		}, { exit: true, duration: 220, distance: 6 });
		toggleRank.setAttribute('aria-pressed', String(changed));
	});

	motion.disclosure.mount(byId('details'));
	const report = byId('report');
	const reportReady = byId('report-ready').content;
	const overview = report.firstElementChild.cloneNode(true);
	const openReport = byId('show-report');
	const openOverview = byId('reset-report');
	/** Keep the report actions in place as the content changes height. */
	function showReport(expanded) {
		if ((openReport.getAttribute('aria-pressed') === 'true') === expanded) return;
		motion.region.replace(report, () => {
			report.replaceChildren((expanded ? reportReady : overview).cloneNode(true));
		});
		openReport.setAttribute('aria-pressed', String(expanded));
		openOverview.setAttribute('aria-pressed', String(!expanded));
	}
	openReport.addEventListener('click', () => showReport(true));
	openOverview.addEventListener('click', () => showReport(false));

	const wizard = byId('wizard');
	const heading = byId('wizard-heading');
	const description = byId('wizard-description');
	const previous = byId('previous-step');
	const next = byId('next-step');
	/** Preserve forward and backward direction and return focus to the current step. */
	function showStep(review) {
		motion.region.replace(wizard, () => {
			heading.textContent = review ? '2. Review' : '1. Delivery';
			description.textContent = review ? 'Review the quantities and delivery address before submitting.' : 'Delivery address confirmed.';
			previous.disabled = !review;
			next.disabled = review;
		}, { direction: review ? 'forward' : 'backward' });
		heading.focus({ preventScroll: true });
	}
	previous.addEventListener('click', () => showStep(false));
	next.addEventListener('click', () => showStep(true));
	for (const controls of document.querySelectorAll('[data-demo-controls]')) controls.hidden = false;
	byId('playback').hidden = false;
})();
