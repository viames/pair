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
	const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
	const states = {
		oct: [{ values: [8, 11, 9, 13, 10], pickup: 12 }, { values: [12, 14, 11, 16, 13], pickup: 7 }],
		nov: [{ values: [9, 7, 12, 15, 11], pickup: 9 }, { values: [13, 10, 15, 16, 14], pickup: 5 }],
	};
	let snapshot = 0;
	let chart;

	/** Preserve the visible chart when the compatible series or period is replaced. */
	function draw(data, kind) {
		if (!window.Chart) return;
		const previous = chart && kind !== 'entrance' ? motion.chart.capture(chart) : null;
		chart?.destroy();
		canvas.parentElement.hidden = false;
		const colors = getComputedStyle(dashboard);
		const reveal = kind !== 'refresh' && motion.chart.reveal();
		const config = {
			type: 'line',
			data: { labels: days, datasets: [{ label: 'Delivered orders', data: data.values, borderColor: colors.getPropertyValue('--accent').trim(), tension: .25 }] },
			options: {
				responsive: true, maintainAspectRatio: false, animation: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { grid: { display: false }, ticks: { color: colors.color } },
					y: { min: 0, max: 20, ticks: { color: colors.color }, grid: { color: colors.getPropertyValue('--line').trim() } }
				}
			},
			plugins: reveal ? [reveal] : []
		};
		chart = new Chart(canvas, motion.chart.transition(config, previous, { morph: kind === 'refresh' }));
	}

	/** Update the real KPIs and table immediately before animating their visual presentation. */
	function render(kind) {
		const data = states[period.value][snapshot];
		const total = data.values.reduce((sum, value) => sum + value, 0);
		if (kind === 'entrance') {
			motion.page.enter(dashboard, { trigger: 'viewport' });
			motion.counter.enter(orders);
			motion.counter.enter(pickup);
			motion.progress.set(target, null);
			motion.progress.set(target, total, 100, 0);
		} else {
			motion.counter.set(orders, total);
			motion.counter.set(pickup, data.pickup);
			motion.progress.set(target, total, 100);
			motion.region.replace(note.parentElement, () => {
				note.textContent = data.pickup > 10 ? 'Next pickup Friday afternoon.' : 'Next pickup Friday morning.';
			});
		}
		for (const [index, row] of [...table.rows].entries()) row.cells[1].textContent = data.values[index];
		draw(data, kind);
	}

	motion.disclosure.mount(table.closest('details'));
	dashboard.querySelector('.toolbar').hidden = false;
	/** Alternate between two server-equivalent snapshots for the selected period. */
	document.getElementById('refresh').addEventListener('click', () => { snapshot ^= 1; render('refresh'); });
	/** Load the new month's fulfilled orders and animate the populated chart. */
	period.addEventListener('change', () => { snapshot = 0; render('period'); });
	/** Replay the initial composition without changing period, progress or data. */
	document.getElementById('replay').addEventListener('click', () => render('entrance'));
	render('entrance');
})();
