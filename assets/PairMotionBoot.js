/** Guard opted-in page entrances before the browser renders their markup. */
(function guardPageEntrances() {
	'use strict';

	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
	const root = document.documentElement;
	root.setAttribute('data-pair-motion-boot', '');
	// Restore static markup if the deferred page entry point never starts.
	window.setTimeout(() => root.removeAttribute('data-pair-motion-boot'), 650);
})();
