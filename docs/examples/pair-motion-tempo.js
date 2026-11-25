/** Bind the examples' native range control to an inherited PairMotion duration multiplier. */
function mountPairMotionDemoTempo(root, replay) {
	const speed = document.getElementById('motion-speed');
	const output = document.getElementById('motion-speed-value');
	const declaration = document.getElementById('motion-tempo-css');

	/** Show and apply the CSS value corresponding to the chosen playback speed. */
	function updateTempo() {
		const rate = speed.valueAsNumber;
		const factor = Number((1 / rate).toFixed(3));
		root.style.setProperty('--pair-motion-duration-factor', factor);
		output.value = rate + '×';
		speed.setAttribute('aria-valuetext', rate + ' times normal speed');
		declaration.textContent = '--pair-motion-duration-factor: ' + factor + ';';
	}

	updateTempo();
	speed.addEventListener('input', updateTempo);
	speed.addEventListener('change', replay);
}
