/** PairMotion: optional motion for server-rendered, semantic controls. */
(function (global) {
	'use strict';

	if (global.PairMotion) return;

	const motion = global.matchMedia('(prefers-reduced-motion: reduce)');
	const animations = new Set();
	const counters = new WeakMap();
	const selections = new WeakMap();
	const moves = new WeakMap();
	const exits = new WeakMap();
	const confirmations = new WeakMap();
	const sharedElements = new WeakMap();
	const effects = new WeakMap();
	const disclosures = new WeakMap();
	const regions = new WeakMap();
	const pages = new WeakMap();
	const easing = 'cubic-bezier(.22, 1, .36, 1)';
	const digit = /^[0-9]$/;
	const choiceItems = ':scope > [data-pair-motion-item]';
	const selectedChoice = '[aria-selected="true"], [aria-pressed="true"], [aria-current]:not([aria-current="false"], [aria-current=""])';

	/** Read a motion tempo that can be scoped to a page or component through CSS. */
	function durationFactor(element) {
		const value = global.getComputedStyle(element).getPropertyValue?.('--pair-motion-duration-factor');
		return boundedOption(value, 1, .25, 3);
	}

	/** Start an owned animation and remove its bookkeeping on completion or cancellation. */
	function animate(element, frames, options, done) {
		const factor = durationFactor(element);
		const timing = factor === 1 ? options : { ...options, duration: options.duration * factor };
		if (factor !== 1 && options.delay !== undefined) timing.delay = options.delay * factor;
		const animation = element.animate(frames, timing);
		animations.add(animation);
		/** Settle both successful and cancelled animations without leaving a rejected promise. */
		function settle() {
			animations.delete(animation);
			done?.();
		}
		animation.finished.then(settle, settle);
		return animation;
	}

	/** Clear active effects when the operating system switches to reduced motion. */
	function reduce() {
		if (motion.matches) {
			for (const animation of animations) animation.cancel();
		}
	}

	/** Play effects only when they can be rendered and the user allows motion. */
	function canAnimate(element) {
		return !motion.matches && !document.hidden && typeof element.animate === 'function';
	}

	/** Normalize a fractional position on the ten-digit reel. */
	function cycle(value) {
		return ((value % 10) + 10) % 10;
	}

	/** Sample the eased position currently visible on an interrupted reel. */
	function position(reel) {
		const progress = reel.animation?.effect?.getComputedTiming().progress ?? 0;
		return cycle(reel.start + reel.distance * progress);
	}

	/** Build the digit run from a whole or fractional frame. */
	function reelFor(start, target, forward) {
		const distance = forward ? cycle(target - start) : cycle(target - start + 5) - 5;
		const first = Math.floor(Math.min(start, start + distance));
		const last = Math.ceil(Math.max(start, start + distance));
		const track = document.createElement('span');
		track.className = 'pair-motion-reel';
		for (let frameNumber = first; frameNumber <= last; frameNumber++) {
			const frame = document.createElement('span');
			frame.textContent = String(cycle(frameNumber));
			track.append(frame);
		}

		const viewport = document.createElement('span');
		viewport.className = 'pair-motion-reel-window';
		viewport.append(track);
		const unit = 100 / track.children.length;
		return { viewport, track, start, distance, from: (first - start) * unit, to: (first - start - distance) * unit };
	}

	/** Restore the actual text and clear temporary frames for this update only. */
	function finishCounter(root, state) {
		if (counters.get(root) !== state) return;
		counters.delete(root);
		root.classList.remove('pair-motion-counter-running');
		state.visual.remove();
		for (const reel of state.reels) reel.animation?.cancel();
	}

	/** Capture the value and visible reel positions before replacing a component. */
	function captureCounter(root) {
		const value = root.querySelector(':scope > [data-pair-motion-value]')?.textContent ?? '';
		const state = counters.get(root);
		const positions = state
			? state.reels.map(position)
			: Array.from(value).filter(character => digit.test(character)).reverse().map(Number);
		return { value, positions, width: root.getBoundingClientRect().width };
	}

	/** Replace the semantic value immediately and animate only the decorative ASCII digits. */
	function setCounter(root, value, from, options = {}) {
		const text = root.querySelector(':scope > [data-pair-motion-value]');
		if (!text) return;
		const next = String(value);
		if (!from && text.textContent === next) return;

		const { positions, width } = from ?? captureCounter(root);
		const duration = boundedOption(options.duration, 340, 0, 1000);
		const stagger = boundedOption(options.stagger, 0, 0, 60);
		const curve = options.easing ? motionCurve(options.easing) : easing;
		const previous = counters.get(root);
		if (previous) finishCounter(root, previous);

		if (text.textContent !== next) text.textContent = next;
		if (!duration || !width || !canAnimate(root) || !global.CSS?.supports('block-size', '1lh')) return;

		const visual = document.createElement('span');
		visual.className = 'pair-motion-counter-visual';
		visual.setAttribute('aria-hidden', 'true');
		visual.style.minInlineSize = width + 'px';
		const reels = [];
		const characters = [];

		// Align digits by place from the right, independent of grouping separators.
		for (const character of Array.from(next).reverse()) {
			if (!digit.test(character)) {
				characters.push(character);
				continue;
			}
			const target = Number(character);
			const start = positions[reels.length] ?? (options.rollNewDigits && positions.length ? 0 : target);
			const reel = reelFor(start, target, options.forward);
			reels.push(reel);
			characters.push(reel.viewport);
		}

		const changed = reels.filter(reel => reel.distance !== 0);
		if (!changed.length) return;
		visual.append(...characters.reverse());
		const state = { visual, reels };
		counters.set(root, state);
		root.append(visual);
		root.classList.add('pair-motion-counter-running');

		let pending = changed.length;
		/** Remove the shared visual layer when all changed digits settle. */
		function settled() {
			pending -= 1;
			if (pending === 0) finishCounter(root, state);
		}

		for (const [index, reel] of changed.entries()) {
			reel.animation = animate(reel.track, [
				{ transform: 'translateY(' + reel.from + '%)' },
				{ transform: 'translateY(' + reel.to + '%)' }
			], { duration, delay: Math.min(120, stagger * index), easing: curve, fill: 'both' }, settled);
		}
	}

	/** Roll the initially rendered digits forward from zero without replacing their semantic text. */
	function enterCounter(root, options = {}) {
		const final = captureCounter(root);
		if (counters.has(root) || !final.positions.length) return;
		setCounter(root, final.value, { ...final, positions: final.positions.map(() => 0) }, { ...options, forward: true });
	}

	/** Read the selected item without modifying the app's radio or ARIA state. */
	function syncSelection(root, immediate = false) {
		const state = selections.get(root);
		if (!state) return;
		const items = new Set(root.querySelectorAll(choiceItems));
		for (const item of state.items) {
			if (!items.has(item)) state.observer?.unobserve(item);
		}
		for (const item of items) {
			if (!state.items.has(item)) state.observer?.observe(item);
		}
		state.items = items;
		const active = Array.from(items).find(item =>
			item.matches(selectedChoice) || item.querySelector(':scope > input[type="radio"]:checked'));
		const visible = Boolean(active?.offsetWidth && active.offsetHeight);
		const indicator = state.indicator;
		if (immediate) indicator.classList.add('pair-motion-selection-instant');
		if (visible) {
			indicator.style.width = active.offsetWidth + 'px';
			indicator.style.height = active.offsetHeight + 'px';
			indicator.style.transform = 'translate(' + active.offsetLeft + 'px, ' + active.offsetTop + 'px)';
		}
		if (indicator.parentElement !== root) root.prepend(indicator);
		indicator.hidden = !visible;
		root.classList.toggle('pair-motion-selection-mounted', visible);
		if (immediate) {
			indicator.getBoundingClientRect();
			indicator.classList.remove('pair-motion-selection-instant');
		}
	}

	/** Add one decorative layer and observe size changes for this group only. */
	function mountSelection(root) {
		if (!selections.has(root)) {
			const indicator = document.createElement('span');
			indicator.className = 'pair-motion-selection-indicator';
			indicator.setAttribute('aria-hidden', 'true');
			indicator.hidden = true;
			/** Animate native selection changes, but align immediately when layout changes. */
			const update = () => syncSelection(root);
			const resize = () => syncSelection(root, true);
			const observer = global.ResizeObserver ? new global.ResizeObserver(resize) : null;
			selections.set(root, { indicator, observer, update, resize, items: new Set() });
			observer?.observe(root);
			root.addEventListener('change', update);
			if (!observer) global.addEventListener('resize', resize);
		}
		syncSelection(root);
	}

	/** Stop listening and restore the static selection indicator. */
	function unmountSelection(root) {
		const state = selections.get(root);
		if (!state) return;
		state.observer?.disconnect();
		root.removeEventListener('change', state.update);
		if (!state.observer) global.removeEventListener('resize', state.resize);
		state.indicator.remove();
		root.classList.remove('pair-motion-selection-mounted');
		selections.delete(root);
	}

	/** Read a progress fill during a transition or fall back to the native ratio. */
	function captureProgress(root) {
		const fill = root.querySelector(':scope > .pair-motion-progress-track > .pair-motion-progress-fill');
		const native = root.querySelector(':scope > progress')?.position ?? -1;
		if (!fill || !global.DOMMatrixReadOnly) return native;
		const transform = global.getComputedStyle(fill).transform;
		return transform === 'none' ? native : new global.DOMMatrixReadOnly(transform).a;
	}

	/** Update the native progress immediately and transition a purely visual fill. */
	function setProgress(root, value, max, from) {
		const progress = root.querySelector(':scope > progress');
		if (!progress || (value !== null && !Number.isFinite(value))) return;
		if (max !== undefined && (!Number.isFinite(max) || max <= 0)) return;

		const previous = Number.isFinite(from) && from >= 0 ? Math.min(1, from) : progress.position;
		if (max !== undefined) progress.max = max;
		const currentTrack = root.querySelector(':scope > .pair-motion-progress-track');
		if (value === null) {
			progress.removeAttribute('value');
			root.classList.remove('pair-motion-progress-mounted');
			currentTrack?.remove();
			return;
		}

		progress.value = value;
		const next = progress.position;
		let fill = currentTrack?.firstElementChild;
		if (!fill) {
			const track = document.createElement('span');
			track.className = 'pair-motion-progress-track';
			track.setAttribute('aria-hidden', 'true');
			fill = document.createElement('span');
			fill.className = 'pair-motion-progress-fill';
			fill.style.transform = 'scaleX(' + (previous < 0 ? next : previous) + ')';
			track.append(fill);
			root.append(track);
			root.classList.add('pair-motion-progress-mounted');
			// Capture the installed track's initial style before changing its target.
			fill.getBoundingClientRect();
		}
		fill.style.transform = 'scaleX(' + next + ')';
	}

	/** Find unambiguous keyed direct children in their current render order. */
	function keyed(root) {
		const items = new Map();
		for (const child of root.children) {
			const key = child.getAttribute('data-pair-motion-key');
			if (key) items.set(key, items.has(key) ? null : child);
		}
		return items;
	}

	/** Capture removed items in a non-interactive visual layer when requested by the caller. */
	function captureLeaving(element, rect) {
		if (!document.body || rect.bottom < 0 || rect.top > global.innerHeight || rect.right < 0 || rect.left > global.innerWidth) return null;
		const copy = element.cloneNode(true);
		for (const node of copy.querySelectorAll('script, style, link, iframe, object, embed, video, audio')) node.remove();
		for (const node of [copy, ...copy.querySelectorAll('*')]) {
			for (const { name } of [...node.attributes]) {
				if (/^(on|data-)/i.test(name) || /^(id|name|autofocus|form)$/i.test(name)) node.removeAttribute(name);
			}
		}
		const shell = document.createElement('div');
		shell.className = 'pair-motion-leaving';
		shell.inert = true;
		shell.setAttribute('aria-hidden', 'true');
		shell.style.setProperty('--pair-motion-duration-factor', durationFactor(element));
		Object.assign(shell.style, { left: rect.left + 'px', top: rect.top + 'px', width: rect.width + 'px', height: rect.height + 'px' });
		if (element.tagName === 'TR') {
			const table = document.createElement('table');
			const body = document.createElement('tbody');
			table.className = element.closest('table')?.className ?? '';
			table.style.cssText = 'width:100%; table-layout:fixed; margin:0';
			for (const [index, cell] of [...copy.cells].entries()) {
				cell.style.width = element.cells[index].getBoundingClientRect().width + 'px';
			}
			body.append(copy);
			table.append(body);
			shell.append(table);
		} else shell.append(copy);
		return shell;
	}

	/** Remove outgoing layers owned by this list before an overlapping update. */
	function clearLeaving(root) {
		const active = exits.get(root);
		if (active) for (const animation of active) animation.cancel();
	}

	/** Fade a removed item in place without keeping it in the layout or accessibility tree. */
	function playLeaving(root, shell, duration, distance, curve) {
		if (!document.body) return;
		document.body.append(shell);
		const active = exits.get(root) ?? new Set();
		exits.set(root, active);
		/** Stop the viewport-anchored exit when the user scrolls. */
		function stop() { animation.cancel(); }
		/** Remove both the visual layer and the temporary scroll listener. */
		function settled() {
			shell.remove();
			global.removeEventListener('scroll', stop, true);
			active.delete(animation);
			if (!active.size && exits.get(root) === active) exits.delete(root);
		}
		const animation = animate(shell, [{ opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(' + -distance + 'px)' }], {
			duration, easing: curve
		}, settled);
		active.add(animation);
		global.addEventListener('scroll', stop, { capture: true, once: true });
	}

	/** Capture unique positions before a region or its list is replaced. */
	function captureLayout(root, { exit = false } = {}) {
		if (!root || !canAnimate(root) || !global.CSS?.supports('translate', '0px')) return null;
		clearLeaving(root);
		const starts = new Map();
		const origin = root.getBoundingClientRect();
		const previous = keyed(root);
		let capturedLeaving = 0;
		for (const [key, element] of previous) {
			if (!element) continue;
			const rect = element.getBoundingClientRect();
			if (rect.width && rect.height) {
				const shell = exit && capturedLeaving < 30 ? captureLeaving(element, rect) : null;
				if (shell) capturedLeaving += 1;
				starts.set(key, { left: rect.left - origin.left, top: rect.top - origin.top, shell });
			}
		}
		for (const element of previous.values()) {
			if (element) moves.get(element)?.cancel();
		}
		return starts;
	}

	/** Play a captured layout against the direct children now in the document. */
	function playLayout(root, starts, options = {}) {
		if (!root || !starts || !canAnimate(root)) return;
		clearLeaving(root);
		const duration = boundedOption(options.duration, 180, 0, 800);
		if (!duration) return;
		const distance = boundedOption(options.distance, options.exit ? 5 : 0, 0, 32);
		const curve = motionCurve(options.easing);
		const items = keyed(root);
		if (options.exit) {
			for (const [key, state] of starts) {
				if (!items.has(key) && state.shell) playLeaving(root, state.shell, Math.min(duration, 160), distance, curve);
			}
		}

		const origin = root.getBoundingClientRect();
		const changes = [];
		for (const [key, element] of items) {
			if (!element) continue;
			const end = element.getBoundingClientRect();
			if (!end.width || !end.height || (options.exit && element.contains(document.activeElement))) continue;
			const start = starts.get(key);
			const initial = start ? { translate: (start.left + origin.left - end.left) + 'px ' + (start.top + origin.top - end.top) + 'px' } : { opacity: 0 };
			if (!start && distance) initial.translate = '0 ' + distance + 'px';
			if (initial.translate !== '0px 0px') changes.push({ element, start, initial });
		}

		for (const { element, start, initial } of changes) {
			/** Keep the newest animation when an earlier animation settles. */
			function settled() {
				if (moves.get(element) === animation) moves.delete(element);
			}
			const final = start ? { translate: '0px 0px' } : { opacity: 1 };
			if (!start && distance) final.translate = 'none';
			moves.get(element)?.cancel();
			const animation = animate(element, [initial, final], { duration, easing: curve }, settled);
			moves.set(element, animation);
		}
	}

	/** Apply a synchronous DOM change with keyed motion and return the callback result. */
	function updateLayout(root, apply, options) {
		const starts = captureLayout(root, options);
		const result = apply();
		playLayout(root, starts, options);
		return result;
	}

	/** Replace the optional emphasis already running on an element. */
	function emphasize(element, property, from, duration) {
		if (!element || !canAnimate(element)) return;
		effects.get(element)?.cancel();
		const final = global.getComputedStyle(element)[property];
		/** Remove only the effect that finished. */
		function settled() {
			if (effects.get(element) === animation) effects.delete(element);
		}
		const animation = animate(element, [{ [property]: from }, { [property]: final }], { duration, easing }, settled);
		effects.set(element, animation);
	}

	/** Reveal an updated region without moving or interpolating its contents. */
	function fade(element) {
		emphasize(element, 'opacity', .35, 180);
	}

	/** Draw brief attention to a changed row or status with a themeable background. */
	function highlight(element) {
		emphasize(element, 'backgroundColor', 'var(--pair-motion-highlight, color-mix(in srgb, currentColor 14%, transparent))', 480);
	}

	/** Remove temporary geometry and restore the body's original interaction state. */
	function stopDisclosure(state) {
		const run = state.run;
		if (!run) return;
		state.run = null;
		run.animation.cancel();
		state.body.inert = run.inert;
		state.body.classList.remove('pair-motion-clipping');
		state.root.classList.remove('pair-motion-disclosure-closing');
	}

	/** Change a native disclosure, reversing from its currently visible height. */
	function setDisclosure(root, open) {
		open = Boolean(open);
		const state = disclosures.get(root);
		if (!state) {
			root.open = open;
			return;
		}
		if ((state.run?.open ?? root.open) === open) return;

		const { body, summary } = state;
		const animated = canAnimate(body);
		const from = animated && root.open ? body.getBoundingClientRect().height : 0;
		stopDisclosure(state);
		if (!open && body.contains(document.activeElement)) summary.focus({ preventScroll: true });

		if (!animated) {
			root.open = open;
			return;
		}
		if (open) root.open = true;
		const to = open ? body.getBoundingClientRect().height : 0;
		if (Math.abs(to - from) < .5) {
			root.open = open;
			return;
		}

		const run = { open, inert: body.inert, animation: null };
		state.run = run;
		// The body must stay visible while it closes, but it no longer accepts focus.
		if (!open) body.inert = true;
		body.classList.add('pair-motion-clipping');
		root.classList.toggle('pair-motion-disclosure-closing', !open);
		/** Settle only this run, even if a newer activation has reversed it. */
		function settled() {
			if (state.run !== run) return;
			if (!open) root.open = false;
			stopDisclosure(state);
		}
		run.animation = animate(body, [{ height: from + 'px' }, { height: to + 'px' }], { duration: 180, easing, fill: 'both' }, settled);
	}

	/** Enhance the native summary activation without adding a separate keyboard handler. */
	function mountDisclosure(root) {
		if (disclosures.has(root)) return;
		const summary = root.querySelector(':scope > summary');
		const body = root.querySelector(':scope > [data-pair-motion-disclosure-body]');
		if (!summary || !body) return;

		const state = { root, summary, body, run: null };
		/** Leave interactive controls inside the summary with their own activation. */
		state.activate = event => {
			const control = event.target.closest('a, button, input, select, textarea, [contenteditable]');
			if (event.defaultPrevented || (control && summary.contains(control))) return;
			event.preventDefault();
			setDisclosure(root, !(state.run?.open ?? root.open));
		};
		/** A native exclusive details group may close this member independently. */
		state.toggled = () => {
			if (!root.open) stopDisclosure(state);
		};
		summary.addEventListener('click', state.activate);
		root.addEventListener('toggle', state.toggled);
		disclosures.set(root, state);
	}

	/** Finish any pending activation and restore native-only behavior. */
	function unmountDisclosure(root) {
		const state = disclosures.get(root);
		if (!state) return;
		const open = root.open && (state.run?.open ?? true);
		stopDisclosure(state);
		root.open = open;
		state.summary.removeEventListener('click', state.activate);
		root.removeEventListener('toggle', state.toggled);
		disclosures.delete(root);
	}

	/** Cancel only the effects owned by the current region update. */
	function stopRegion(root, run) {
		if (!run || regions.get(root) !== run) return;
		regions.delete(root);
		root.classList.remove('pair-motion-clipping');
		for (const animation of run.animations) animation.cancel();
	}

	/** Apply a synchronous render and let the incoming panel take the previous space. */
	function replaceRegion(root, apply, { direction } = {}) {
		const animated = canAnimate(root);
		const from = animated ? root.getBoundingClientRect().height : 0;
		stopRegion(root, regions.get(root));
		const result = apply();
		if (!animated || !from) return result;

		const content = root.querySelector(':scope > [data-pair-motion-content]');
		const to = root.getBoundingClientRect().height;
		if (!content || !canAnimate(content) || !to) return result;
		const opacity = global.getComputedStyle(content).opacity;
		const start = { opacity: Number(opacity) * .35 };
		const end = { opacity };
		// Use a short logical inline displacement, while leaving the container in place.
		if ((direction === 'forward' || direction === 'backward') && global.CSS?.supports('translate', '0px')) {
			const viewDirection = global.getComputedStyle(root).direction === 'rtl' ? -1 : 1;
			const stepDirection = direction === 'forward' ? 1 : -1;
			start.translate = (12 * viewDirection * stepDirection) + 'px';
			end.translate = '0px';
		}

		const run = { animations: [] };
		const resize = Math.abs(to - from) >= .5;
		let pending = resize ? 2 : 1;
		/** Restore the natural size after all effects belonging to this update settle. */
		function settled() {
			pending -= 1;
			if (pending === 0) stopRegion(root, run);
		}
		regions.set(root, run);
		root.classList.add('pair-motion-clipping');
		if (resize) {
			run.animations.push(animate(root, [{ height: from + 'px' }, { height: to + 'px' }], { duration: 180, easing, fill: 'both' }, settled));
		}
		run.animations.push(animate(content, [start, end], { duration: 180, easing }, settled));
		return result;
	}

	/** Keep numeric options within the range of a short page entrance. */
	function boundedOption(value, fallback, min, max) {
		const number = typeof value === 'string' && value.trim() ? Number(value) : value;
		return Number.isFinite(number) ? Math.min(max, Math.max(min, number)) : fallback;
	}

	/** Release the observer and ownership of a completed or cancelled entrance. */
	function releasePage(root, run) {
		run.observer?.disconnect();
		pages.delete(root);
	}

	/** Settle the entrance for this page before replacing it or replaying the sequence. */
	function cancelPage(root) {
		const run = pages.get(root);
		if (!run) return;
		releasePage(root, run);
		for (const animation of run.animations.values()) animation.cancel();
	}

	/** Reveal marked blocks in place, using DOM order or an explicit shared step. */
	function enterPage(root, options = {}) {
		cancelPage(root);
		if (!root?.matches('[data-pair-motion-page]') || !canAnimate(root)) return;
		const duration = boundedOption(options.duration, 220, 0, 800);
		const inViewport = options.trigger === 'viewport';
		if (!duration || (inViewport && typeof global.IntersectionObserver !== 'function')) return;

		const stagger = boundedOption(options.stagger, 40, 0, 200);
		const maxDelay = boundedOption(options.maxDelay, 160, 0, 400);
		const distance = boundedOption(options.distance, 8, 0, 32);
		const opacity = boundedOption(options.opacity, .7, 0, 1);
		const curve = motionCurve(options.easing);
		const direction = global.getComputedStyle(root).direction === 'rtl' ? -1 : 1;
		const offsets = {
			bottom: '0 ' + distance + 'px', top: '0 ' + -distance + 'px',
			start: -direction * distance + 'px 0', end: direction * distance + 'px 0',
		};
		const origin = options.origin ?? 'bottom';
		const offset = distance && Object.hasOwn(offsets, origin) && global.CSS?.supports('translate', '0px') ? offsets[origin] : null;
		const items = [...root.querySelectorAll('[data-pair-motion-enter]')].filter(item => item.closest('[data-pair-motion-page]') === root);
		const pending = new Map();
		for (const [index, item] of items.entries()) {
			const step = item.getAttribute('data-pair-motion-enter');
			const order = /^\d+$/.test(step) ? Number(step) : index;
			pending.set(item, Number.isSafeInteger(order) ? order : index);
		}
		if (!pending.size) return;

		const run = { animations: new Map(), observer: null };
		pages.set(root, run);

		/** Settle after the last block has either finished or been skipped. */
		function releaseWhenDone() {
			if (!pending.size && !run.animations.size && pages.get(root) === run) releasePage(root, run);
		}

		/** Play a block relative to the first step of its current batch. */
		function play(item, order) {
			pending.delete(item);
			run.observer?.unobserve(item);
			if (!canAnimate(item) || !item.getClientRects().length || item.contains(document.activeElement)) {
				releaseWhenDone();
				return;
			}
			const style = global.getComputedStyle(item);
			const from = { opacity: Number(style.opacity) * opacity };
			const to = { opacity: Number(style.opacity) };
			// Leave an existing individual translation under the control of the page's styles.
			if (offset && style.translate === 'none') {
				from.translate = offset;
				to.translate = 'none';
			}
			const delay = Math.min(maxDelay, order * stagger);

			/** Show focused content in its resting position immediately. */
			function showFocused() {
				animation.cancel();
			}
			/** Remove the listener and only the bookkeeping for this run. */
			function settled() {
				item.removeEventListener('focusin', showFocused);
				run.animations.delete(item);
				releaseWhenDone();
			}
			const animation = animate(item, [from, to], { duration, delay, easing: curve, fill: 'backwards' }, settled);
			run.animations.set(item, animation);
			item.addEventListener('focusin', showFocused, { once: true });
		}

		if (!inViewport) {
			for (const [item, order] of pending) play(item, order);
			return;
		}

		/** Reveal each block once when it first intersects the chosen viewport. */
		function intersect(entries) {
			if (pages.get(root) !== run) return;
			const latest = new Map(entries.map(({ target, isIntersecting }) => [target, isIntersecting]));
			const visible = [...latest].filter(([item, isIntersecting]) => isIntersecting && pending.has(item));
			const first = visible.reduce((order, [item]) => Math.min(order, pending.get(item)), Infinity);
			for (const [item] of visible) play(item, pending.get(item) - first);
		}
		run.observer = new global.IntersectionObserver(intersect, { root: options.viewport ?? null });
		for (const item of pending.keys()) run.observer.observe(item);
	}

	/** Resolve a shared motion curve without passing untrusted CSS to the animation engine. */
	function motionCurve(curve) {
		const curves = { 'ease-out': easing, 'ease-in-out': 'ease-in-out', linear: 'linear' };
		return Object.hasOwn(curves, curve) ? curves[curve] : easing;
	}

	/** Capture a visible element's geometry to match its counterpart after rendering. */
	function captureElement(element) {
		if (!element || !canAnimate(element)) return null;
		const { left, top, width, height } = element.getBoundingClientRect();
		return width && height ? { left, top, width, height } : null;
	}

	/** Let an element arrive from the former position of the same content. */
	function enterElement(element, from, options = {}) {
		if (!element) return;
		sharedElements.get(element)?.cancel();
		if (!from || !canAnimate(element) || element.contains(document.activeElement)) return;
		const to = captureElement(element);
		if (!to) return;
		const duration = boundedOption(options.duration, 200, 0, 800);
		if (!duration) return;
		const distance = boundedOption(options.distance, 96, 0, 320);
		const x = from.left - to.left;
		const y = from.top - to.top;
		const factor = Math.min(1, distance / (Math.hypot(x, y) || 1));
		const style = global.getComputedStyle(element);
		const initial = { opacity: Number(style.opacity) * .55 };
		const final = { opacity: Number(style.opacity) };
		if (factor && style.translate === 'none' && global.CSS?.supports('translate', '0px')) {
			initial.translate = x * factor + 'px ' + y * factor + 'px';
			final.translate = 'none';
		}
		/** Settle immediately if the moving element receives keyboard focus. */
		function focus() { animation.cancel(); }
		/** Release the animation and listener when the destination settles. */
		function settled() {
			element.removeEventListener('focusin', focus);
			if (sharedElements.get(element) === animation) sharedElements.delete(element);
		}
		const animation = animate(element, [initial, final], { duration, easing: motionCurve(options.easing) }, settled);
		sharedElements.set(element, animation);
		element.addEventListener('focusin', focus, { once: true });
	}

	/** Restore the idle visual and clear the timer of the current confirmation. */
	function clearConfirmation(root) {
		const state = confirmations.get(root);
		if (!state) return;
		confirmations.delete(root);
		global.clearTimeout(state.timer);
		state.animation?.cancel();
		state.idle.hidden = false;
		state.confirmed.hidden = true;
	}

	/** Confirm a completed action while its native control remains usable. */
	function confirmEffect(root, options = {}) {
		if (options.status && typeof options.message === 'string') options.status.textContent = options.message;
		if (!root) return;
		clearConfirmation(root);
		const idle = root.querySelector('[data-pair-motion-idle]');
		const confirmed = root.querySelector('[data-pair-motion-confirmed]');
		if (!idle || !confirmed) return;
		const duration = boundedOption(options.duration, 180, 0, 800);
		const hold = boundedOption(options.hold, 950, 0, 4000);
		const distance = boundedOption(options.distance, 4, 0, 12);
		idle.hidden = true;
		confirmed.hidden = false;
		const state = { idle, confirmed };
		if (duration && canAnimate(confirmed)) {
			state.animation = animate(confirmed, [{ opacity: .25, transform: 'translateY(' + distance + 'px) scale(.8)' }, { opacity: 1, transform: 'none' }], {
				duration, easing: motionCurve(options.easing)
			});
		}
		/** Return to the idle icon without altering the confirmation text. */
		function reset() { clearConfirmation(root); }
		state.timer = global.setTimeout(reset, (state.animation ? duration * durationFactor(confirmed) : 0) + hold);
		confirmations.set(root, state);
	}

	/** Create an optional Chart.js 4 plugin that reveals the plotted series in place. */
	function revealChart({ duration = 400, origin = 'start' } = {}) {
		const length = boundedOption(duration, 400, 0, 1000);
		if (!length || motion.matches || document.hidden || typeof global.requestAnimationFrame !== 'function') return null;

		const horizontal = origin !== 'top' && origin !== 'bottom';
		let fromEnd = origin === 'bottom';
		let progress = 1;
		let frame;
		let clipped = false;

		/** Restore the canvas before axes overlays or tooltips are drawn. */
		function restore({ ctx }) {
			if (!clipped) return;
			ctx.restore();
			clipped = false;
		}

		return {
			id: 'pair-motion-reveal',
			/** Begin the reveal after the chart has been attached to the canvas. */
			afterInit(chart) {
				if (motion.matches || document.hidden) return;
				if (horizontal) {
					const rtl = global.getComputedStyle(chart.canvas).direction === 'rtl';
					fromEnd = (origin === 'end') !== rtl;
				}
				let start;
				const time = length * durationFactor(chart.canvas);
				progress = 0;
				/** Redraw from the real coordinates until the clipping window is fully open. */
				function draw(now) {
					start ??= now;
					const elapsed = Math.min(1, (now - start) / time);
					progress = motion.matches || document.hidden ? 1 : 1 - (1 - elapsed) ** 3;
					chart.draw();
					frame = progress < 1 ? global.requestAnimationFrame(draw) : undefined;
				}
				frame = global.requestAnimationFrame(draw);
			},
			/** Clip only dataset drawing, preserving the chart geometry and values. */
			beforeDatasetsDraw({ ctx, chartArea }) {
				if (progress === 1 || !chartArea) return;
				const { left, top, right, bottom, width, height } = chartArea;
				const visibleWidth = horizontal ? width * progress : width;
				const visibleHeight = horizontal ? height : height * progress;
				ctx.save();
				ctx.beginPath();
				ctx.rect(horizontal && fromEnd ? right - visibleWidth : left, !horizontal && fromEnd ? bottom - visibleHeight : top, visibleWidth, visibleHeight);
				ctx.clip();
				clipped = true;
			},
			afterDatasetsDraw: restore,
			afterDraw: restore,
			/** Release any pending redraw before Chart.js removes the canvas context. */
			beforeDestroy(chart) {
				if (frame !== undefined) global.cancelAnimationFrame(frame);
				restore(chart);
			},
		};
	}

	/** Capture dataset coordinates as currently painted, including interrupted updates. */
	function captureChart(chart) {
		if (!chart?.canvas || !canAnimate(chart.canvas) || !chart.width || !chart.height) return null;
		const bitmap = document.createElement('canvas');
		bitmap.width = chart.canvas.width;
		bitmap.height = chart.canvas.height;
		const context = bitmap.getContext('2d');
		context?.drawImage(chart.canvas, 0, 0);

		const scale = chart.scales.y;
		const labels = chart.data.labels ?? [];
		const series = new Map();
		for (const [index, dataset] of chart.data.datasets.entries()) {
			const key = dataset.pairMotionKey ?? dataset.label;
			if (!key || series.has(key)) {
				series.set(key, null);
				continue;
			}
			const points = chart.getDatasetMeta(index).data;
			if (scale?.type === 'linear' && chart.isDatasetVisible?.(index) !== false && points.length === labels.length) {
				series.set(key, points.map(point => point.skip ? NaN : scale.getValueForPixel(point.y)));
			}
		}
		return {
			bitmap: context ? bitmap : null, series, labels: [...labels],
			min: scale?.min, max: scale?.max, reverse: !!scale?.options?.reverse,
			xType: chart.scales.x?.type, xReverse: !!chart.scales.x?.options?.reverse
		};
	}

	/** Configure one replacement chart to morph compatible data or blend the old plot out. */
	function transitionChart(config, previous, options = {}) {
		if (!previous || motion.matches || document.hidden) return config;
		const duration = boundedOption(options.duration, 250, 0, 800);
		const fadeDuration = boundedOption(options.fadeDuration, 180, 0, 800);
		const labels = config.data?.labels ?? [];
		const datasets = config.data?.datasets ?? [];
		const axesMatch = options.morph !== false && config.type === 'line' && labels.length > 0 &&
			labels.length === previous.labels.length && labels.every((label, index) => label === previous.labels[index]);
		const keys = datasets.map(dataset => dataset.pairMotionKey ?? dataset.label);
		const before = keys.map(key => previous.series.get(key));
		const compatible = axesMatch && keys.length > 0 && keys.length === previous.series.size && new Set(keys).size === keys.length &&
			before.every(points => points?.every(Number.isFinite)) &&
			datasets.every(dataset => !dataset.hidden && dataset.animation !== false && Array.isArray(dataset.data) &&
				dataset.data.length === labels.length && dataset.data.every(Number.isFinite));
		const curves = { 'ease-out': 'easeOutCubic', 'ease-in-out': 'easeInOutCubic', linear: 'linear' };
		const curve = Object.hasOwn(curves, options.easing) ? curves[options.easing] : curves['ease-out'];
		let first = true;
		let fade = [];
		let overlay;
		let preference;
		let factor = 1;

		/** Require the real resolved scale to match before interpreting old point positions. */
		function sameScale(chart) {
			const y = chart.scales.y;
			const x = chart.scales.x;
			return compatible && y?.type === 'linear' && y.min === previous.min && y.max === previous.max &&
				!!y.options?.reverse === previous.reverse && x?.type === previous.xType && !!x?.options?.reverse === previous.xReverse;
		}

		/** Drop the source image immediately when the chart is superseded. */
		function removeOverlay() {
			overlay?.remove();
			overlay = null;
		}

		const plugin = {
			id: 'pair-motion-transition',
			/** Resolve the chart tempo once before Chart.js evaluates individual points. */
			beforeUpdate(chart) { factor = durationFactor(chart.canvas); },
			/** Crossfade when coordinates are not comparable and settle active morphs on preference changes. */
			afterUpdate(chart) {
				if (!first) return;
				first = false;
				if (duration && sameScale(chart)) {
					// Chart.js resets new controllers before its first draw. Paint the captured frame
					// immediately so a second refresh can also sample this new chart before the first tick.
					for (const [index, points] of before.entries()) {
						for (const [point, element] of chart.getDatasetMeta(index).data.entries()) {
							element.y = chart.scales.y.getPixelForValue(points[point]);
						}
					}
					/** Set the actual point positions immediately when reduced motion is enabled. */
					preference = () => {
						if (!motion.matches) return;
						chart.stop();
						chart.update('none');
					};
					motion.addEventListener?.('change', preference);
				} else if (fadeDuration && previous.bitmap && chart.canvas.parentElement && canAnimate(chart.canvas)) {
					overlay = previous.bitmap;
					overlay.className = 'pair-motion-chart-previous';
					overlay.setAttribute('aria-hidden', 'true');
					chart.canvas.parentElement.append(overlay);
					fade = [
						animate(chart.canvas, [{ opacity: .25 }, { opacity: 1 }], { duration: fadeDuration, easing: motionCurve(options.easing) }),
						animate(overlay, [{ opacity: 1 }, { opacity: 0 }], { duration: fadeDuration, easing: motionCurve(options.easing) }, removeOverlay)
					];
				}
			},
			/** Remove the old chart image and callback before Chart.js releases its canvas. */
			beforeDestroy() {
				for (const animation of fade) animation.cancel();
				removeOverlay();
				if (preference) motion.removeEventListener?.('change', preference);
			}
		};

		const chartOptions = { ...config.options };
		if (compatible && duration) {
			chartOptions.animation = { duration: 0, easing: curve };
			chartOptions.animations = {
				colors: false, numbers: false,
				y: {
					type: 'number', easing: curve,
					/** Animate only the first real update and only on a compatible scale. */
					duration(context) { return first && context.mode !== 'reset' && sameScale(context.chart) ? duration * factor : 0; },
					/** Use the visually sampled coordinates without changing parsed data or tooltips. */
					from(context) { return context.chart.scales.y.getPixelForValue(before[context.datasetIndex]?.[context.dataIndex]); },
				}
			};
		}
		return { ...config, options: chartOptions, plugins: [...(config.plugins ?? []), plugin] };
	}

	motion.addEventListener?.('change', reduce);
	const api = {
		counter: { capture: captureCounter, set: setCounter, enter: enterCounter },
		selection: { mount: mountSelection, sync: syncSelection, unmount: unmountSelection },
		progress: { capture: captureProgress, set: setProgress },
		layout: { capture: captureLayout, play: playLayout, update: updateLayout },
		effect: { fade, highlight, confirm: confirmEffect, clearConfirmation },
		element: { capture: captureElement, enter: enterElement },
		disclosure: { mount: mountDisclosure, set: setDisclosure, unmount: unmountDisclosure },
		region: { replace: replaceRegion },
		page: { enter: enterPage, cancel: cancelPage },
		chart: { reveal: revealChart, capture: captureChart, transition: transitionChart }
	};
	global.Pair = global.Pair || {};
	global.Pair.Motion = global.PairMotion = api;
})(window);
