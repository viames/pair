/** Run the same progressive PairMotion APIs used by a server-rendered operations dashboard. */
(() => {
	'use strict';
	const motion = window.PairMotion;
	if (!motion) return;
	const dashboard = document.getElementById('dashboard');
	const orders = document.getElementById('orders');
	const pickup = document.getElementById('pickup');
	const target = document.getElementById('target');
	const canvas = document.getElementById('deliveries');
	const table = document.getElementById('delivery-data');
	const note = document.getElementById('queue-note');
	const period = document.getElementById('period');
	const effect = document.getElementById('entrance-effect');
	const cascade = document.getElementById('cascade');
	const overlap = document.getElementById('contents-overlap');
	const overlapValue = document.getElementById('contents-overlap-value');
	const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
	const states = {
		oct: [{ values: [8, 11, 9, 13, 10], pickup: 12 }, { values: [12, 14, 11, 16, 13], pickup: 7 }],
		nov: [{ values: [9, 7, 12, 15, 11], pickup: 9 }, { values: [13, 10, 15, 16, 14], pickup: 5 }],
	};
	let snapshot = 0;
	let chart;

	/** Preserve the visible chart when the compatible series or period is replaced. */
	function draw(data, kind, after) {
		if (!window.Chart) return;
		const previous = chart && kind !== 'entrance' ? motion.chart.capture(chart) : null;
		chart?.destroy();
		canvas.parentElement.inert = false;
		const colors = getComputedStyle(canvas);
		const labelColor = getComputedStyle(dashboard).color;
		const reveal = kind !== 'refresh' && motion.chart.reveal({ after });
		const config = {
			type: 'line',
			data: { labels: days, datasets: [{ label: 'Delivered orders', data: data.values, borderColor: colors.color, tension: .25 }] },
			options: {
				responsive: true, maintainAspectRatio: false, animation: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { grid: { display: false }, ticks: { color: labelColor } },
					y: { min: 0, max: 20, ticks: { color: labelColor }, grid: { color: colors.borderTopColor } }
				}
			},
			plugins: reveal ? [reveal] : []
		};
		chart = new Chart(canvas, motion.chart.transition(config, previous, { morph: kind === 'refresh' }));
	}

	/** Update the real KPIs and table immediately before animating their visual presentation. */
	function render(kind, rollCounters = false) {
		if (kind !== 'entrance') motion.page.cancel(dashboard);
		const data = states[period.value][snapshot];
		const total = data.values.reduce((sum, value) => sum + value, 0);
		let after;
		if (kind === 'entrance') {
			motion.page.enter(dashboard, {
				effect: effect.value, trigger: 'viewport', stagger: 52, maxDelay: 208,
				contents: cascade.checked, contentsOverlap: overlap.valueAsNumber / 100,
			});
			after = cascade.checked ? motion.page.ready(dashboard, canvas) : undefined;
			if (rollCounters) {
				motion.counter.enter(orders, { after: cascade.checked ? motion.page.ready(dashboard, orders, { phase: 'reveal' }) : undefined });
				motion.counter.enter(pickup, { after: cascade.checked ? motion.page.ready(dashboard, pickup, { phase: 'reveal' }) : undefined });
			}
			revealContent(target, () => {
				motion.progress.set(target, null);
				motion.progress.set(target, total, 100, 0);
			});
		} else {
			motion.counter.set(orders, total);
			motion.counter.set(pickup, data.pickup);
			motion.progress.set(target, total, 100);
			motion.region.replace(note.parentElement, () => {
				note.textContent = data.pickup > 10 ? 'Next pickup Friday afternoon.' : 'Next pickup Friday morning.';
			});
		}
		for (const [index, row] of [...table.rows].entries()) row.cells[1].textContent = data.values[index];
		draw(data, kind, after);
	}

	/** Start the visual update after its card, or directly in the parallel preview. */
	function revealContent(node, play) {
		if (!cascade.checked) {
			play();
			return;
		}
		motion.page.ready(dashboard, node).then(completed => { if (completed) play(); });
	}

	/** Reflect the overlap percentage while dragging. */
	function updateOverlap() {
		overlapValue.value = overlap.value + '%';
		overlap.setAttribute('aria-valuetext', overlap.value + ' percent');
	}

	/** Replay the composition without changing period, progress or data. */
	function replay(rollCounters = false) {
		overlap.disabled = !cascade.checked;
		render('entrance', rollCounters);
	}

	mountPairMotionDemoTempo(dashboard, () => replay());
	effect.addEventListener('change', () => replay());
	cascade.addEventListener('change', () => replay());
	overlap.addEventListener('input', updateOverlap);
	overlap.addEventListener('change', () => replay());
	updateOverlap();
	motion.disclosure.mount(table.closest('details'));
	dashboard.querySelector('.toolbar').inert = false;
	/** Alternate between two server-equivalent snapshots for the selected period. */
	document.getElementById('refresh').addEventListener('click', () => { snapshot ^= 1; render('refresh'); });
	/** Load the new month's fulfilled orders and animate the populated chart. */
	period.addEventListener('change', () => { snapshot = 0; render('period'); });
	document.getElementById('replay').addEventListener('click', () => replay(true));
	replay(true);
})();
