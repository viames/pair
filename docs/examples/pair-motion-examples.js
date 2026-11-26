/** Mount each interactive example using the same PairMotion APIs available to applications. */
(() => {
	'use strict';
	const motion = window.PairMotion;
	if (!motion) return;
	/** Get a control from the static demo markup. */
	const byId = id => document.getElementById(id);
	const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
	const tempo = byId('motion-speed');

	/** Disable the tempo control when the operating system requests reduced motion. */
	function updatePreference() {
		tempo.disabled = preference.matches;
	}

	preference.addEventListener?.('change', updatePreference);
	updatePreference();

	const page = byId('motion-cards');
	const manual = new Set();
	const entrance = byId('entrance');
	const scroll = byId('scroll-entrance');
	const boxes = new Map(Array.from(entrance.children, box => [box, box.getAttribute('data-pair-motion-enter')]));
	const previewOptions = { effect: 'lift', duration: 270, stagger: 45, distance: 8, opacity: .64 };
	let pendingScroll;

	/** Translate the same boxes between grid and column, then animate only unseen boxes on scroll. */
	function showScrollLayout(active) {
		pendingScroll = null;
		motion.layout.update(entrance, () => {
			motion.page.cancel(entrance);
			for (const [box, step] of boxes) box.setAttribute('data-pair-motion-enter', step);
			entrance.classList.toggle('viewport', active);
			if (active) entrance.tabIndex = 0;
			else entrance.removeAttribute('tabindex');
			entrance.scrollTop = 0;
			scroll.setAttribute('aria-pressed', String(active));
		}, { duration: 440, easing: 'ease-in-out' });
		if (!active) return;

		const moves = [...boxes.keys()].flatMap(box => box.getAnimations?.() ?? []);
		const settled = Promise.allSettled(moves.map(move => move.finished));
		pendingScroll = settled;
		settled.then(() => {
			if (pendingScroll !== settled) return;
			pendingScroll = null;
			const viewport = entrance.getBoundingClientRect();
			for (const box of boxes.keys()) {
				if (box.getBoundingClientRect().top < viewport.bottom) box.removeAttribute('data-pair-motion-enter');
			}
			motion.page.enter(entrance, { ...previewOptions, trigger: 'viewport', viewport: entrance });
		});
	}

	/** Replay the grid entrance or return from the scroll column. */
	function replayEntrance() {
		if (entrance.classList.contains('viewport')) showScrollLayout(false);
		else motion.page.enter(entrance, previewOptions);
	}

	byId('replay-entrance').addEventListener('click', replayEntrance);
	scroll.addEventListener('click', () => showScrollLayout(!entrance.classList.contains('viewport')));
	motion.selection.mount(byId('selection'));

	const counter = byId('counter');
	const reel = { duration: 380, stagger: 22, rollNewDigits: true };
	/** Retarget the reel from its visible position and let any new digit roll as well. */
	byId('counter-controls').addEventListener('click', event => {
		const button = event.target.closest('button');
		if (!button?.value) return;
		motion.counter.set(counter, button.value, undefined, reel);
	});
	/** Replay the reel from zero on explicit request. */
	byId('reel-replay').addEventListener('click', () => motion.counter.enter(counter, reel));

	const progress = byId('progress');
	const progressCounter = byId('progress-value');
	const progressTiming = { duration: 380, easing: 'ease-in-out' };
	progress.style.setProperty('--demo-progress-duration', progressTiming.duration + 'ms');
	progress.style.setProperty('--demo-progress-easing', progressTiming.easing);
	motion.progress.set(progress, progress.querySelector('progress').value);
	/** Commit the real value immediately and animate both its digits and its fill with the same timing. */
	byId('progress-controls').addEventListener('click', event => {
		const button = event.target.closest('button');
		if (!button?.value) return;
		manual.add(progress);
		motion.progress.set(progress, Number(button.value));
		motion.counter.set(progressCounter, button.value + '%', undefined, progressTiming);
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
	for (const controls of document.querySelectorAll('[data-demo-controls]')) controls.inert = false;
	byId('playback').inert = false;

	/** Play a card's preview only after the current group has reached its resting layout. */
	function afterCard(node, play) {
		motion.page.ready(page, node).then(completed => {
			if (completed && !manual.has(node) && !preference.matches && node.isConnected) play();
		});
	}

	/** Stage the entrance values before the cards appear and play the counters as their contents reveal. */
	function replayPage(rollCounters) {
		manual.clear();
		motion.page.enter(page, { effect: 'lift', trigger: 'viewport', contents: true, stagger: 48, maxDelay: 260 });
		if (rollCounters) {
			motion.counter.enter(counter, { ...reel, after: motion.page.ready(page, counter, { phase: 'reveal' }) });
			motion.counter.enter(progressCounter, { ...progressTiming, after: motion.page.ready(page, progressCounter, { phase: 'reveal' }) });
		}
		afterCard(progress, () => {
			const value = progress.querySelector('progress').value;
			motion.progress.set(progress, null);
			motion.progress.set(progress, value, undefined, 0);
		});
	}

	mountPairMotionDemoTempo(document.documentElement, () => replayPage(false));
	byId('replay-page').addEventListener('click', () => replayPage(true));
	replayPage(true);
})();
