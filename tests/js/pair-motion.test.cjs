const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = readFileSync(join(__dirname, '../../assets/PairMotion.js'), 'utf8');

/** Create a small DOM double with deterministic geometry and animation progress. */
function fixture() {
	const runs = [];
	const preference = new EventTarget();
	preference.matches = false;

	/** Make an element with an isolated animation and DOM state. */
	function element() {
		const attributes = new Map();
		const events = new EventTarget();
		const names = new Set();
		return {
			children: [], style: {}, x: 0, y: 0, offsetWidth: 100, offsetHeight: 40,
			classList: {
				add: name => names.add(name),
				contains: name => names.has(name),
				remove: name => names.delete(name),
				toggle: (name, enabled) => enabled ? names.add(name) : names.delete(name),
			},
			setAttribute: (name, value) => attributes.set(name, value),
			getAttribute: name => attributes.get(name) ?? null,
			removeAttribute: name => attributes.delete(name),
			getBoundingClientRect() { return { left: this.x, top: this.y, width: this.offsetWidth, height: this.offsetHeight }; },
			get firstElementChild() { return this.children[0]; },
			matches: () => false,
			querySelector: () => null,
			querySelectorAll: () => [],
			contains(other) { return other === this; },
			getClientRects: () => [1],
			addEventListener: events.addEventListener.bind(events),
			removeEventListener: events.removeEventListener.bind(events),
			dispatchEvent: events.dispatchEvent.bind(events),
			/** Assign each child to the same parent without duplicating existing nodes. */
			append(...children) {
				for (const child of children) {
					if (typeof child === 'string') continue;
					child.remove();
					child.parentElement = this;
					this.children.push(child);
				}
			},
			prepend(child) {
				this.append(child);
				this.children.unshift(this.children.pop());
			},
			remove() {
				if (!this.parentElement) return;
				this.parentElement.children = this.parentElement.children.filter(child => child !== this);
				this.parentElement = null;
			},
			/** Support a controlled intermediate frame, finish, and cancellation. */
			animate(frames, options) {
				let resolve;
				let reject;
				const finished = new Promise((complete, fail) => {
					resolve = complete;
					reject = fail;
				});
				const run = {
					frames, options, progress: 0, cancelled: false,
					finished,
					effect: { getComputedTiming: () => ({ progress: run.progress }) },
					finish: resolve,
					cancel() {
						run.cancelled = true;
						reject(new Error('cancelled'));
					}
				};
				runs.push(run);
				return run;
			},
		};
	}

	const document = { createElement: element, hidden: false };
	const window = {
		matchMedia: () => preference, CSS: { supports: () => true },
		getComputedStyle: node => ({ opacity: '1', translate: node.translate ?? 'none', backgroundColor: 'transparent', direction: node.direction ?? 'ltr' }),
		addEventListener() {}, removeEventListener() {}
	};
	vm.runInNewContext(source, { document, window });
	return { api: window.PairMotion, pair: window.Pair, element, preference, runs, document, window };
}

test('counter exposes the final text while only changed digits roll', async () => {
	const { api, pair, element, runs } = fixture();
	const root = element();
	const value = { textContent: '10' };
	root.querySelector = () => value;

	api.counter.set(root, '50');
	assert.equal(pair.Motion, api);
	assert.equal(value.textContent, '50');
	assert.equal(root.children[0].getAttribute('aria-hidden'), 'true');
	assert.equal(runs.length, 1);
	assert.equal(runs[0].options.duration, 340);
	assert.equal(root.children[0].children[0].children[0].children.map(frame => frame.textContent).join(''), '12345');

	runs[0].finish();
	await Promise.resolve();
	assert.equal(root.children.length, 0);
});

test('retargeting a counter starts at the fractional frame and stale cleanup preserves the new roll', async () => {
	const { api, element, runs } = fixture();
	const root = element();
	const value = { textContent: '10' };
	root.querySelector = () => value;

	api.counter.set(root, '50');
	runs[0].progress = 0.375; // The reel sits halfway between 2 and 3.
	api.counter.set(root, '40');
	await Promise.resolve();

	assert.equal(runs[0].cancelled, true);
	assert.equal(runs[1].frames[0].transform, 'translateY(-16.666666666666668%)');
	assert.equal(value.textContent, '40');
	assert.equal(root.children.length, 1);
});

test('reduced motion cancels owned effects and commits later counter values immediately', async () => {
	const { api, element, preference, runs } = fixture();
	const root = element();
	const value = { textContent: '10' };
	root.querySelector = () => value;

	api.counter.set(root, '50');
	preference.matches = true;
	preference.dispatchEvent(new Event('change'));
	await Promise.resolve();
	api.counter.set(root, '102');

	assert.equal(runs[0].cancelled, true);
	assert.equal(runs.length, 1);
	assert.equal(root.children.length, 0);
	assert.equal(value.textContent, '102');
});

test('selection follows ARIA state without moving or modifying the items', () => {
	const { api, element } = fixture();
	const root = element();
	const first = element();
	const second = element();
	first.offsetLeft = 4;
	first.offsetTop = 4;
	second.offsetLeft = 4;
	second.offsetTop = 48;
	let selected = first;
	first.matches = () => selected === first;
	second.matches = () => selected === second;
	root.append(first, second);
	root.querySelectorAll = () => [first, second];

	api.selection.mount(root);
	const indicator = root.children[0];
	assert.equal(indicator.style.transform, 'translate(4px, 4px)');
	selected = second;
	api.selection.sync(root);
	assert.equal(indicator.style.transform, 'translate(4px, 48px)');
	assert.equal(root.children[2], second);
	assert.equal(second.offsetTop, 48);
	api.selection.unmount(root);
	assert.deepEqual(root.children, [first, second]);
});

test('progress applies exact native values, validates the maximum and supports indeterminate state', () => {
	const { api, element } = fixture();
	const root = element();
	const progress = {
		value: 35, max: 100,
		get position() { return this.value / this.max; },
		removeAttribute() { this.value = null; },
	};
	root.querySelector = selector => selector.endsWith('progress') ? progress : root.firstElementChild;

	api.progress.set(root, 80);
	assert.equal(progress.value, 80);
	assert.equal(root.firstElementChild.firstElementChild.style.transform, 'scaleX(0.8)');
	api.progress.set(root, 70, 0);
	assert.equal(progress.value, 80);
	api.progress.set(root, null);
	assert.equal(progress.value, null);
	assert.equal(root.children.length, 0);
});

test('keyed layout measures movements, skips duplicate keys and applies the callback once', () => {
	const { api, element, runs } = fixture();
	const root = element();
	const retained = element();
	const duplicate = element();
	const otherDuplicate = element();
	retained.setAttribute('data-pair-motion-key', 'retained');
	duplicate.setAttribute('data-pair-motion-key', 'duplicate');
	otherDuplicate.setAttribute('data-pair-motion-key', 'duplicate');
	root.append(retained, duplicate, otherDuplicate);
	const order = [];

	const result = api.layout.update(root, () => {
		order.push('apply');
		retained.y = 120;
		duplicate.y = 80;
		return 7;
	});

	assert.equal(result, 7);
	assert.deepEqual(order, ['apply']);
	assert.equal(runs.length, 1);
	assert.equal(runs[0].frames[0].translate, '0px -120px');
	assert.equal(runs[0].frames[1].translate, '0px 0px');
});

test('a fragment can continue the old reel while keeping its authoritative value', () => {
	const { api, element, runs } = fixture();
	const old = element();
	const oldText = { textContent: '10' };
	old.querySelector = () => oldText;
	api.counter.set(old, '50');
	runs[0].progress = 0.375;
	const from = api.counter.capture(old);

	const incoming = element();
	const newText = { textContent: '40' };
	incoming.querySelector = () => newText;
	api.counter.set(incoming, newText.textContent, from);

	assert.equal(newText.textContent, '40');
	assert.equal(runs[1].frames[0].transform, 'translateY(-16.666666666666668%)');
});

test('layout snapshots use positions relative to a replacement region', () => {
	const { api, element, runs } = fixture();
	const old = element();
	old.y = 100;
	const original = element();
	original.setAttribute('data-pair-motion-key', 'agent');
	original.y = 120;
	old.append(original);
	const snapshot = api.layout.capture(old);

	const next = element();
	next.y = 200;
	const replacement = element();
	replacement.setAttribute('data-pair-motion-key', 'agent');
	replacement.y = 238;
	next.append(replacement);
	api.layout.play(next, snapshot);

	assert.equal(runs[0].frames[0].translate, '0px -18px');
});

test('feedback effects are interruptible and leave reduced-motion elements alone', () => {
	const { api, element, runs, preference } = fixture();
	const row = element();
	api.effect.highlight(row);
	api.effect.fade(row);
	assert.equal(runs[0].cancelled, true);
	assert.equal(runs[0].options.duration, 480);
	assert.equal(runs[1].frames[1].opacity, '1');

	preference.matches = true;
	preference.dispatchEvent(new Event('change'));
	api.effect.highlight(row);
	assert.equal(runs[1].cancelled, true);
	assert.equal(runs.length, 2);
});

test('disclosure reverses from the visible height and releases the final geometry', async () => {
	const { api, element, runs, document } = fixture();
	const root = element();
	const body = element();
	const summary = element();
	const link = element();
	root.open = true;
	body.inert = false;
	body.contains = node => node === link;
	body.getBoundingClientRect = () => ({ height: runs.length === 1 && !runs[0].cancelled ? 38 : 112 });
	root.querySelector = selector => selector.endsWith('summary') ? summary : body;
	summary.focus = () => { document.activeElement = summary; };
	document.activeElement = link;

	api.disclosure.mount(root);
	api.disclosure.set(root, false);
	assert.equal(document.activeElement, summary);
	assert.equal(body.inert, true);
	assert.equal(root.open, true);
	assert.equal(runs[0].frames[0].height, '112px');

	api.disclosure.set(root, true);
	await Promise.resolve();
	assert.equal(runs[0].cancelled, true);
	assert.equal(body.inert, false);
	assert.equal(runs[1].frames[0].height, '38px');
	assert.equal(runs[1].frames[1].height, '112px');
	runs[1].finish();
	await Promise.resolve();
	assert.equal(root.open, true);
	assert.equal(body.classList.contains('pair-motion-clipping'), false);
});

test('unmounting and reduced motion settle native disclosure state', async () => {
	const { api, element, preference, runs } = fixture();
	const root = element();
	const body = element();
	const summary = element();
	body.contains = () => false;
	root.querySelector = selector => selector.endsWith('summary') ? summary : body;

	root.open = true;
	api.disclosure.mount(root);
	api.disclosure.set(root, false);
	api.disclosure.unmount(root);
	await Promise.resolve();
	assert.equal(root.open, false);
	assert.equal(runs[0].cancelled, true);

	api.disclosure.mount(root);
	preference.matches = true;
	api.disclosure.set(root, true);
	assert.equal(root.open, true);
	assert.equal(runs.length, 1);
});

test('region replacement keeps the visible height on retry and restores natural dimensions', async () => {
	const { api, element, runs } = fixture();
	const root = element();
	root.querySelector = () => element();
	const first = api.region.replace(root, () => {
		root.offsetHeight = 120;
		return 'rendered';
	});

	assert.equal(first, 'rendered');
	assert.equal(runs[0].frames[0].height, '40px');
	assert.equal(runs[0].frames[1].height, '120px');
	assert.equal(runs[1].frames[0].opacity, .35);
	root.offsetHeight = 72;
	api.region.replace(root, () => { root.offsetHeight = 60; });
	await Promise.resolve();
	assert.equal(runs[0].cancelled, true);
	assert.equal(runs[1].cancelled, true);
	assert.equal(runs[2].frames[0].height, '72px');
	assert.equal(root.classList.contains('pair-motion-clipping'), true);

	runs[2].finish();
	runs[3].finish();
	await Promise.resolve();
	assert.equal(root.classList.contains('pair-motion-clipping'), false);
});

test('direction follows reading order and reduced motion still renders exactly once', () => {
	const { api, element, preference, runs } = fixture();
	const root = element();
	root.querySelector = () => element();
	const steps = [];
	api.region.replace(root, () => steps.push('next'), { direction: 'forward' });
	api.region.replace(root, () => steps.push('previous'), { direction: 'backward' });
	root.direction = 'rtl';
	api.region.replace(root, () => steps.push('next in RTL'), { direction: 'forward' });
	preference.matches = true;
	api.region.replace(root, () => steps.push('instant'));

	assert.equal(runs[0].frames[0].translate, '12px');
	assert.equal(runs[1].frames[0].translate, '-12px');
	assert.equal(runs[2].frames[0].translate, '-12px');
	assert.equal(runs[2].cancelled, true);
	assert.deepEqual(steps, ['next', 'previous', 'next in RTL', 'instant']);
	assert.equal(root.classList.contains('pair-motion-clipping'), false);
});


/** Set up an entrance with independently marked blocks and optional shared steps. */
function composition(element, steps) {
	const root = element();
	root.matches = selector => selector === '[data-pair-motion-page]';
	const blocks = steps.map(step => {
		const block = element();
		block.closest = () => root;
		if (step !== null) block.setAttribute('data-pair-motion-enter', step);
		return block;
	});
	root.querySelectorAll = () => blocks;
	return { root, blocks };
}

test('page entrance groups blocks, clamps the delay and respects the reading direction', () => {
	const { api, element, runs } = fixture();
	const { root, blocks } = composition(element, [null, '1', '1', '5', '7']);
	const nested = element();
	blocks[4].closest = () => nested;
	blocks[2].translate = '4px';
	root.direction = 'rtl';

	api.page.enter(root, { duration: '300', stagger: 45, maxDelay: 100, origin: 'start', opacity: .8, distance: 12, easing: 'linear' });
	assert.deepEqual(runs.map(run => run.options.delay), [0, 45, 45, 100]);
	assert.equal(runs[0].options.easing, 'linear');
	assert.equal(runs[0].options.duration, 300);
	assert.equal(runs[0].options.fill, 'backwards');
	assert.equal(runs[0].frames[0].opacity, .8);
	assert.equal(runs[0].frames[0].translate, '12px 0');
	assert.equal(runs[2].frames[0].translate, undefined);
});

test('page entrance keeps focus immediate and cancels superseded and reduced-motion runs', async () => {
	const { api, document, element, preference, runs } = fixture();
	const { root, blocks } = composition(element, [null, null, null]);
	document.activeElement = blocks[0];
	blocks[2].getClientRects = () => [];
	api.page.enter(root);
	assert.equal(runs.length, 1);
	blocks[1].dispatchEvent(new Event('focusin'));
	assert.equal(runs[0].cancelled, true);
	await Promise.resolve();

	api.page.enter(root);
	api.page.enter(root);
	assert.equal(runs[1].cancelled, true);
	assert.equal(runs[2].cancelled, false);
	preference.matches = true;
	preference.dispatchEvent(new Event('change'));
	assert.equal(runs[2].cancelled, true);
	api.page.enter(root);
	assert.equal(runs.length, 3);

	preference.matches = false;
	api.page.enter(root);
	api.page.cancel(root);
	assert.equal(runs[3].cancelled, true);
	await Promise.resolve();
});


test('viewport entrance plays newly visible blocks once and releases the observer when cancelled', async () => {
	const { api, element, runs, window } = fixture();
	const { root, blocks } = composition(element, ['2', '3', '4']);
	const viewport = element();
	const observed = new Set();
	let notify;
	let disconnected = false;
	window.IntersectionObserver = class {
		/** Record the scroll container and simulate entries independently from animation. */
		constructor(callback, options) {
			assert.equal(options.root, viewport);
			notify = callback;
		}
		observe(item) { observed.add(item); }
		unobserve(item) { observed.delete(item); }
		disconnect() { disconnected = true; observed.clear(); }
	};

	api.page.enter(root, { trigger: 'viewport', viewport });
	assert.equal(runs.length, 0);
	assert.equal(observed.size, 3);
	notify([{ target: blocks[0], isIntersecting: true }, { target: blocks[1], isIntersecting: true }, { target: blocks[2], isIntersecting: false }]);
	assert.deepEqual(runs.map(run => run.options.delay), [0, 40]);
	notify([{ target: blocks[2], isIntersecting: true }, { target: blocks[2], isIntersecting: true }, { target: blocks[0], isIntersecting: true }]);
	assert.deepEqual(runs.map(run => run.options.delay), [0, 40, 0]);
	assert.equal(observed.size, 0);

	api.page.cancel(root);
	assert.equal(disconnected, true);
	assert.equal(runs.every(run => run.cancelled), true);
	notify([{ target: blocks[2], isIntersecting: true }]);
	assert.equal(runs.length, 3);
	await Promise.resolve();
});

test('viewport entrance falls back to the existing HTML when the observer is unavailable', () => {
	const { api, element, runs } = fixture();
	const { root } = composition(element, ['0']);
	api.page.enter(root, { trigger: 'viewport' });
	assert.equal(runs.length, 0);
});

test('chart reveal clips only the plotted area and settles with reduced motion or destruction', () => {
	const { api, element, preference, window } = fixture();
	const frames = new Map();
	const rectangles = [];
	let nextFrame = 0;
	let saves = 0;
	window.requestAnimationFrame = draw => {
		frames.set(++nextFrame, draw);
		return nextFrame;
	};
	window.cancelAnimationFrame = frame => frames.delete(frame);

	/** Advance the callbacks scheduled for the next frame. */
	function advance(timestamp) {
		const batch = [...frames.values()];
		frames.clear();
		for (const draw of batch) draw(timestamp);
	}

	const plugin = api.chart.reveal({ duration: 400, origin: 'bottom' });
	const chart = {
		canvas: element(),
		chartArea: { left: 10, top: 10, right: 90, bottom: 50, width: 80, height: 40 },
		ctx: {
			save() { saves++; },
			restore() { saves--; },
			rect(...rectangle) { rectangles.push(rectangle); },
			beginPath() {},
			clip() {},
		},
		/** Simulate drawing and a skipped dataset completion hook. */
		draw() {
			plugin.beforeDatasetsDraw(this);
			plugin.afterDraw(this);
		},
	};
	plugin.afterInit(chart);
	advance(0);
	advance(200);
	assert.deepEqual(rectangles, [[10, 50, 80, 0], [10, 15, 80, 35]]);
	assert.equal(saves, 0);

	preference.matches = true;
	advance(210);
	assert.equal(rectangles.length, 2);
	assert.equal(frames.size, 0);
	assert.equal(api.chart.reveal(), null);
	preference.matches = false;
	const next = api.chart.reveal();
	next.afterInit(chart);
	assert.equal(frames.size, 1);
	next.beforeDestroy(chart);
	assert.equal(frames.size, 0);
});
