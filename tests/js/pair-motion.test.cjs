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
			children: [], style: { setProperty() {} }, x: 0, y: 0, offsetWidth: 100, offsetHeight: 40,
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
					target: this, frames, options, progress: 0, cancelled: false,
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
		getComputedStyle: node => ({ opacity: '1', display: node.display ?? 'block', getPropertyValue: () => {
			for (let item = node; item; item = item.parentElement) if (item.tempo) return item.tempo;
			return '';
		}, translate: node.translate ?? 'none', transform: node.transform ?? 'none', scale: node.scale ?? 'none', color: '#17293a', backgroundColor: 'transparent', direction: node.direction ?? 'ltr' }),
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

test('lift entrances combine a restrained scale, rise and fade without overriding authored scale', async () => {
	const { api, element, runs } = fixture();
	const { root, blocks } = composition(element, ['0', '1']);
	blocks[1].scale = '1.1';
	const entrance = api.page.enter(root, { effect: 'lift' });
	assert.equal(runs[0].options.duration, 310);
	assert.equal(runs[0].frames[0].opacity, .52);
	assert.equal(runs[0].frames[0].translate, '0 14px');
	assert.equal(runs[0].frames[0].scale, '.985');
	assert.equal(runs[0].frames[1].scale, '1');
	assert.equal(runs[1].frames[0].scale, undefined);
	const ready = api.page.ready(root, { closest: () => blocks[0] });
	api.page.cancel(root);
	assert.equal(await entrance, false);
	assert.equal(await ready, false);
});

/** Model the visible objects inside one card without changing the card layout. */
function contentBlocks(card, element, count) {
	const blocks = Array.from({ length: count }, () => element());
	for (const block of blocks) {
		block.closest = selector => selector === '[data-pair-motion-enter]' ? card : null;
		card.append(block);
	}
	card.querySelectorAll = () => blocks;
	return blocks;
}

test('composition overlaps each card with its contents and waits for both before nested effects', async () => {
	const { api, element, runs } = fixture();
	const { root, blocks } = composition(element, ['0', '1']);
	const contents = contentBlocks(blocks[0], element, 2);
	let inner = false;
	const compositionDone = api.page.enter(root, { contents: true });
	const contentDone = api.page.ready(root, contents[0]);
	contentDone.then(completed => { inner = completed; });
	assert.equal(runs.length, 4);
	assert.equal(runs[0].options.easing, 'cubic-bezier(.25, .28, .62, .9)');
	assert.deepEqual(runs.slice(1, 3).map(run => run.options.delay), [0, 38]);
	assert.equal(runs[1].options.easing, runs[0].options.easing);
	assert.equal(runs[1].options.duration, 220);
	assert.equal(runs[1].options.fill, 'backwards');
	assert.equal(runs[1].frames[0].opacity, 0);
	runs[0].finish();
	runs[3].finish();
	runs[1].finish();
	await Promise.resolve();
	assert.equal(inner, false);
	runs[2].finish();
	assert.equal(await compositionDone, true);
	assert.equal(await contentDone, true);
	assert.equal(inner, true);
});

test('composition follows the chosen overlap, card stagger and inherited tempo', () => {
	for (const [overlap, expected] of [[0, 680], [.3, 500], [.5, 380], [1, 80]]) {
		const { api, element, runs } = fixture();
		const { root, blocks } = composition(element, ['0', '1']);
		contentBlocks(blocks[1], element, 1);
		blocks[1].tempo = 2;

		api.page.enter(root, { contents: true, duration: 300, contentsOverlap: overlap });
		assert.equal(runs[1].options.delay, 80);
		assert.equal(runs[2].options.delay, expected);
		assert.equal(runs[2].options.duration, 440);
		api.page.cancel(root);
	}
});

test('keyboard focus and cancellation restore held card contents immediately', async () => {
	const { api, element, runs } = fixture();
	const { root, blocks } = composition(element, ['0', '1']);
	contentBlocks(blocks[0], element, 1);
	const [second] = contentBlocks(blocks[1], element, 1);
	const complete = api.page.enter(root, { contents: true });
	const ready = api.page.ready(root, second);
	blocks[0].dispatchEvent(new Event('focusin'));
	assert.equal(runs[1].cancelled, true);
	assert.equal(runs[3].cancelled, false);
	api.page.cancel(root);
	assert.equal(runs[3].cancelled, true);
	assert.equal(await ready, false);
	assert.equal(await complete, false);
});

/** Provide the actual card face while letting the library create the reverse. */
function flipCard(block, element) {
	const front = element();
	block.display = 'grid';
	block.setAttribute('data-pair-motion-flip', '');
	block.querySelector = selector => selector === ':scope > [data-pair-motion-flip-front]' ? front : null;
	block.append(front);
	return front;
}

test('card flip turns a decorative reverse into the real front and waits for the full composition', async () => {
	const { api, element, runs } = fixture();
	const { root, blocks } = composition(element, ['0', '1']);
	const front = flipCard(blocks[1], element);
	blocks[1].tempo = 2;

	const completed = api.page.enter(root, { effect: 'flip' });
	const back = blocks[1].children[1];
	assert.equal(back.getAttribute('aria-hidden'), 'true');
	assert.equal(back.className, 'pair-motion-flip-back');
	assert.equal(runs[0].frames[0].opacity, .7);
	assert.equal(runs[1].frames[0].transform, 'perspective(1000px) rotateY(180deg)');
	assert.equal(runs[1].frames[1].transform, 'perspective(1000px) rotateY(0deg)');
	assert.equal(runs[1].options.duration, 720);
	assert.equal(runs[1].options.delay, 80);
	assert.equal(runs[1].options.easing, 'ease-in-out');
	assert.deepEqual(Array.from(runs[2].frames, frame => frame.opacity), [0, 0, 1, 1]);
	assert.deepEqual(Array.from(runs[3].frames, frame => frame.opacity), [1, 1, 0, 0]);
	assert.equal(runs[3].options.duration, 720);
	runs[0].finish();
	await Promise.resolve();
	assert.equal(blocks[1].children[1], back);
	runs[1].finish();
	assert.equal(await completed, true);
	assert.deepEqual(blocks[1].children, [front]);
	assert.equal(runs[2].cancelled, true);
	assert.equal(runs[3].cancelled, true);
});

test('card replay, focus and reduced motion remove only their own temporary reverse', async () => {
	const { api, element, preference, runs } = fixture();
	const { root, blocks: [block] } = composition(element, ['0']);
	const front = flipCard(block, element);
	const old = api.page.enter(root, { effect: 'flip' });
	const replacement = api.page.enter(root, { effect: 'flip' });
	assert.equal(runs[1].cancelled, true);
	assert.equal(runs[2].cancelled, true);
	assert.equal(block.children.length, 2);
	assert.equal(await old, false);
	block.dispatchEvent(new Event('focusin'));
	assert.deepEqual(block.children, [front]);
	assert.equal(await replacement, true);
	assert.equal(runs[3].cancelled, true);

	api.page.enter(root, { effect: 'flip' });
	preference.matches = true;
	preference.dispatchEvent(new Event('change'));
	await Promise.resolve();
	assert.deepEqual(block.children, [front]);
	assert.equal(await api.page.enter(root, { effect: 'flip' }), true);
	assert.equal(runs.length, 9);
});

test('a card wrapper with its own transform falls back to the normal entrance', () => {
	const { api, element, runs } = fixture();
	const { root, blocks: [block] } = composition(element, ['0']);
	const front = flipCard(block, element);
	block.transform = 'matrix(1, 0, 0, 1, 5, 0)';

	api.page.enter(root, { effect: 'flip' });
	assert.equal(runs[0].frames[0].transform, undefined);
	assert.equal(runs[0].frames[0].opacity, .7);
	assert.deepEqual(block.children, [front]);
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

test('a viewport cascade waits for the chart card and the running cards, then leaves lower cards observable', async () => {
	const { api, element, runs, window } = fixture();
	const { root, blocks } = composition(element, ['0', '1', '2', '3']);
	const observed = new Set();
	let notify;
	window.IntersectionObserver = class {
		constructor(callback) { notify = callback; }
		observe(item) { observed.add(item); }
		unobserve(item) { observed.delete(item); }
		disconnect() { observed.clear(); }
	};
	let completed = false;
	let cardReady = false;
	let lowerReady = false;
	const entrance = api.page.enter(root, { trigger: 'viewport', waitFor: blocks[1] });
	const ready = api.page.ready(root, { closest: () => blocks[1] });
	const lower = api.page.ready(root, { closest: () => blocks[3] });
	ready.then(result => { cardReady = result; });
	lower.then(result => { lowerReady = result; });
	entrance.then(result => { completed = result; });
	notify([{ target: blocks[0], isIntersecting: true }]);
	runs[0].finish();
	await Promise.resolve();
	assert.equal(completed, false);

	notify([{ target: blocks[1], isIntersecting: true }, { target: blocks[2], isIntersecting: true }]);
	runs[1].finish();
	await Promise.resolve();
	assert.equal(completed, false);
	runs[2].finish();
	assert.equal(await entrance, true);
	assert.equal(await ready, true);
	assert.equal(cardReady, true);
	assert.equal(lowerReady, false);
	assert.equal(observed.has(blocks[3]), true);
	notify([{ target: blocks[3], isIntersecting: true }]);
	assert.equal(runs.length, 4);
	runs[3].finish();
	assert.equal(await lower, true);
	assert.equal(observed.size, 0);
});

test('page completion distinguishes cancellation from motion that is skipped', async () => {
	const { api, element, preference, window } = fixture();
	const { root, blocks } = composition(element, ['0']);
	window.IntersectionObserver = class {
		observe() {}
		disconnect() {}
	};
	const superseded = api.page.enter(root, { trigger: 'viewport', waitFor: blocks[0] });
	const skipped = api.page.enter(root, { trigger: 'viewport', waitFor: blocks[0] });
	assert.equal(await superseded, false);
	preference.matches = true;
	preference.dispatchEvent(new Event('change'));
	assert.equal(await skipped, true);
	assert.equal(await api.page.enter(root), true);
});

test('viewport entrance falls back to the existing HTML when the observer is unavailable', async () => {
	const { api, element, runs } = fixture();
	const { root } = composition(element, ['0']);
	assert.equal(await api.page.enter(root, { trigger: 'viewport' }), true);
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


test('chart cascades clip the series while waiting and ignore late completions after disposal', async () => {
	const { api, element, window } = fixture();
	const frames = [];
	window.requestAnimationFrame = draw => frames.push(draw);
	const rectangles = [];
	let draws = 0;
	const chart = {
		canvas: element(),
		chartArea: { left: 0, top: 0, right: 100, bottom: 60, width: 100, height: 60 },
		ctx: { save() {}, restore() {}, beginPath() {}, rect(...rect) { rectangles.push(rect); }, clip() {} },
		draw() { draws++; },
	};

	let finish;
	const after = new Promise(resolve => { finish = resolve; });
	const plugin = api.chart.reveal({ after });
	plugin.afterInit(chart);
	plugin.beforeDatasetsDraw(chart);
	plugin.afterDatasetsDraw(chart);
	assert.deepEqual(rectangles, [[0, 0, 0, 60]]);
	assert.equal(frames.length, 0);
	finish(true);
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(frames.length, 1);

	let late;
	const oldEntrance = new Promise(resolve => { late = resolve; });
	const removed = api.chart.reveal({ after: oldEntrance });
	removed.afterInit(chart);
	removed.beforeDestroy(chart);
	late(true);
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(frames.length, 1);
	assert.equal(draws, 0);
});

test('cancelled and reduced-motion cascades show the full chart without scheduling a reveal', async () => {
	const { api, element, preference, window } = fixture();
	let frames = 0;
	let draws = 0;
	window.requestAnimationFrame = () => { frames++; };
	const chart = { canvas: element(), draw() { draws++; } };

	const aborted = Promise.resolve(false);
	api.chart.reveal({ after: aborted }).afterInit(chart);
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(draws, 1);
	assert.equal(frames, 0);

	const rejected = Promise.reject(new Error('unavailable'));
	api.chart.reveal({ after: rejected }).afterInit(chart);
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(draws, 2);
	assert.equal(frames, 0);

	const never = new Promise(() => {});
	api.chart.reveal({ after: never }).afterInit(chart);
	preference.matches = true;
	preference.dispatchEvent(new Event('change'));
	assert.equal(draws, 3);
	assert.equal(frames, 0);
});


test('keyed removal creates an inert layer and keeps new and retained rows in the normal list', async () => {
	const { api, document, element, runs, window } = fixture();
	document.body = element();
	window.innerHeight = 600;
	window.innerWidth = 1000;
	const list = element();
	const old = element();
	const retained = element();
	const incoming = element();
	old.setAttribute('data-pair-motion-key', 'old');
	retained.setAttribute('data-pair-motion-key', 'kept');
	incoming.setAttribute('data-pair-motion-key', 'new');
	old.getBoundingClientRect = () => ({ top: 10, left: 10, right: 110, bottom: 50, width: 100, height: 40 });
	/** Make a decorative copy independent of the source row. */
	old.cloneNode = retained.cloneNode = () => Object.assign(element(), { attributes: [] });
	list.append(old, retained);

	api.layout.update(list, () => { old.remove(); list.prepend(incoming); }, { exit: true });
	assert.deepEqual(list.children, [incoming, retained]);
	const layer = document.body.firstElementChild;
	assert.equal(layer.inert, true);
	assert.equal(layer.getAttribute('aria-hidden'), 'true');
	assert.equal(runs[0].options.duration, 160);
	assert.equal(runs[1].frames[0].translate, '0 5px');
	runs[0].finish();
	await Promise.resolve();
	assert.equal(document.body.children.length, 0);
});

test('a related element bounds its travel and clears its motion on keyboard focus', async () => {
	const { api, element, runs } = fixture();
	const source = element();
	const destination = element();
	source.x = 150;
	api.element.enter(destination, api.element.capture(source), { distance: 30, duration: 210 });
	assert.equal(runs[0].frames[0].translate, '30px 0px');
	assert.equal(runs[0].options.duration, 210);
	destination.dispatchEvent(new Event('focusin'));
	assert.equal(runs[0].cancelled, true);
	await Promise.resolve();
});

test('confirmation keeps the result text and replaces an earlier icon timer', async () => {
	const { api, element, runs, window } = fixture();
	const button = element();
	const idle = element();
	const check = element();
	const status = element();
	const timers = new Map();
	let next = 0;
	window.setTimeout = callback => { timers.set(++next, callback); return next; };
	window.clearTimeout = id => timers.delete(id);
	button.querySelector = selector => selector === '[data-pair-motion-idle]' ? idle : check;

	api.effect.confirm(button, { status, message: 'Saved' });
	assert.equal(status.textContent, 'Saved');
	assert.equal(idle.hidden, true);
	assert.equal(check.hidden, false);
	api.effect.confirm(button, { status, message: 'Saved again' });
	assert.equal(runs[0].cancelled, true);
	assert.equal(timers.size, 1);
	timers.values().next().value();
	assert.equal(check.hidden, true);
	assert.equal(idle.hidden, false);
	assert.equal(status.textContent, 'Saved again');
	await Promise.resolve();
});

test('chart transition uses sampled Y only for matching labels and scales', () => {
	const { api, document, element, preference } = fixture();
	const canvas = Object.assign(element(), { width: 100, height: 100 });
	document.createElement = () => Object.assign(element(), { getContext: () => ({ drawImage() {} }) });
	const data = { labels: ['A', 'B'], datasets: [{ label: 'Mobile', data: [4, 6] }] };
	const y = { type: 'linear', min: 0, max: 10, getValueForPixel: pixel => 10 - pixel / 10, getPixelForValue: value => (10 - value) * 10 };
	const chart = {
		canvas, width: 100, height: 100, data, scales: { y, x: { type: 'category' } },
		getDatasetMeta: () => ({ data: [{ y: 60 }, { y: 40 }] }),
	};
	const previous = api.chart.capture(chart);
	const config = { type: 'line', data: { ...data, datasets: [{ label: 'Mobile', data: [7, 8] }] }, options: { animation: false } };
	const next = api.chart.transition(config, previous);
	const point = { chart, datasetIndex: 0, dataIndex: 0, mode: 'default' };
	assert.equal(next.options.animations.y.duration(point), 250);
	assert.equal(next.options.animations.y.from(point), 60);
	y.max = 15;
	assert.equal(next.options.animations.y.duration(point), 0);
	assert.equal(api.chart.transition(config, previous, { morph: false }).options.animation, false);
	preference.matches = true;
	assert.equal(api.chart.transition(config, previous), config);
});

test('initial reels preserve the rendered total and follow the inherited tempo', async () => {
	const { api, element, runs } = fixture();
	const root = element();
	root.tempo = '1.3';
	const value = { textContent: '51' };
	root.querySelector = () => value;
	api.counter.enter(root);
	assert.equal(value.textContent, '51');
	assert.deepEqual(runs.map(run => run.options.duration), [442, 442]);
	assert.equal(root.children[0].getAttribute('aria-hidden'), 'true');
	assert.equal(root.children[0].children[0].children[0].children.map(frame => frame.textContent).join(''), '012345');
	for (const run of runs) run.finish();
	await Promise.resolve();
	assert.equal(root.children.length, 0);
});

test('custom counter cadence rolls an added digit from zero', async () => {
	const { api, element, runs } = fixture();
	const root = element();
	const value = { textContent: '51' };
	root.querySelector = () => value;
	api.counter.set(root, '102', undefined, { duration: 380, stagger: 22, rollNewDigits: true });
	assert.equal(value.textContent, '102');
	assert.deepEqual(runs.map(run => run.options.delay), [0, 22, 44]);
	assert.deepEqual(runs.map(run => run.options.duration), [380, 380, 380]);
	assert.equal(root.children[0].children[0].children[0].children.map(frame => frame.textContent).join(''), '01');
	for (const run of runs) run.finish();
	await Promise.resolve();
	assert.equal(root.children.length, 0);
});

test('selection realigns instantly after a resize', () => {
	const { api, element, window } = fixture();
	let notify;
	window.ResizeObserver = class {
		/** Record the browser callback without introducing a layout clock. */
		constructor(callback) { notify = callback; }
		observe() {}
		unobserve() {}
		disconnect() {}
	};
	const root = element();
	const item = element();
	item.matches = () => true;
	item.offsetLeft = 4;
	item.offsetTop = 4;
	root.querySelectorAll = () => [item];
	root.append(item);
	api.selection.mount(root);
	const indicator = root.firstElementChild;
	item.offsetWidth = 72;
	/** Verify the instant style is in effect when the new geometry is committed. */
	indicator.getBoundingClientRect = () => {
		assert.equal(indicator.classList.contains('pair-motion-selection-instant'), true);
		return { width: 72 };
	};
	notify();
	assert.equal(indicator.style.width, '72px');
	assert.equal(indicator.classList.contains('pair-motion-selection-instant'), false);
});

test('only the last completed slide settles its background without changing the choice', async () => {
	const { api, element, runs } = fixture();
	const root = element();
	const items = [element(), element(), element()];
	let active = items[0];
	for (const [index, item] of items.entries()) {
		item.offsetLeft = 4;
		item.offsetTop = 4 + index * 44;
		item.matches = () => item === active;
	}
	root.append(...items);
	root.querySelectorAll = () => items;
	api.selection.mount(root);
	const indicator = root.firstElementChild;
	indicator.offsetWidth = 800;
	let finish;
	/** Expose the transition associated with each selection, independently of the test clock. */
	function nextSlide() {
		const finished = new Promise(resolve => { finish = resolve; });
		indicator.getAnimations = () => [{ transitionProperty: 'transform', finished }];
		return finish;
	}
	const finishFirst = nextSlide();
	active = items[1];
	api.selection.sync(root);
	const finishLast = nextSlide();
	active = items[2];
	api.selection.sync(root);
	assert.equal(runs.length, 0);
	finishFirst();
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(runs.length, 0);
	finishLast();
	await new Promise(resolve => setImmediate(resolve));
	assert.equal(runs.length, 1);
	assert.equal(runs[0].target, indicator);
	assert.equal(runs[0].options.duration, 220);
	const restingTransform = 'translate(4px, 92px)';
	assert.deepEqual(Array.from(runs[0].frames, frame => frame.transform), [
		restingTransform, restingTransform + ' scale(1.01, 1.07)', restingTransform,
	]);
	assert.equal(indicator.style.transform, restingTransform);
	assert.equal(indicator.style.height, '40px');
	assert.deepEqual(Array.from(runs[0].frames).slice(0, 2).map(frame => frame.easing), ['ease-in-out', 'ease-in-out']);
	assert.equal(runs[0].frames[1].color, undefined);
	api.selection.unmount(root);
	assert.equal(runs[0].cancelled, true);
	await Promise.resolve();
});
