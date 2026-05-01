class PairOpenStreetMap {

	static #initializedMaps = new WeakSet();
	static #states = [];
	static #resizeFrame = 0;
	static #tileSize = 256;
	static #minZoom = 12;
	static #maxZoom = 17;
	static #singlePointZoom = 16;
	static #mapPadding = 52;
	static #maxMercatorLatitude = 85.05112878;
	static #minAllowedZoom = 0;
	static #maxAllowedZoom = 22;
	static #tileUrlTemplate = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

	/**
	 * Initialize every OpenStreetMap surface found in the provided root.
	 * @param {ParentNode|Document} root
	 * @returns {void}
	 */
	static initAll(root = document) {
		const maps = Array.from(root.querySelectorAll('[data-pair-osm-map]'));

		for (const map of maps) {
			this.initMap(map);
		}
	}

	/**
	 * Initialize a single OpenStreetMap surface.
	 * @param {Element} root
	 * @param {object} options
	 * @returns {object|null}
	 */
	static initMap(root, options = {}) {
		if (!(root instanceof HTMLElement) || this.#initializedMaps.has(root)) {
			return null;
		}

		const canvas = root.querySelector('[data-pair-osm-canvas]');
		const tiles = root.querySelector('[data-pair-osm-tiles]');
		const markers = root.querySelector('[data-pair-osm-markers]');
		const status = root.querySelector('[data-pair-osm-status]');

		if (!(canvas instanceof HTMLElement) || !(tiles instanceof HTMLElement) || !(markers instanceof HTMLElement) || !(status instanceof HTMLElement)) {
			return null;
		}

		const points = Array.isArray(options.points) ? this.#normalizePoints(options.points) : this.#readMapPoints(root);

		if (!points.length) {
			this.#showMapStatus(status, root.dataset.pairOsmEmptyMessage || 'No points to show.');
			return null;
		}

		const state = {
			canvas,
			markers,
			options: this.#normalizeOptions(root, options),
			points,
			root,
			status,
			tiles,
		};

		this.#initializedMaps.add(root);
		this.#states.push(state);
		this.#renderMap(state);

		return state;
	}

	/**
	 * Schedule one redraw for all initialized maps.
	 * @returns {void}
	 */
	static scheduleRenderAll() {
		if (this.#resizeFrame) {
			window.cancelAnimationFrame(this.#resizeFrame);
		}

		this.#resizeFrame = window.requestAnimationFrame(pairOpenStreetMapRenderAll);
	}

	/**
	 * Redraw every initialized map immediately.
	 * @returns {void}
	 */
	static renderAll() {
		this.#resizeFrame = 0;

		for (const state of this.#states) {
			this.#renderMap(state);
		}
	}

	/**
	 * Read points from the JSON script nested in the map root.
	 * @param {Element} root
	 * @returns {Array<object>}
	 */
	static #readMapPoints(root) {
		const payloadNode = root.querySelector('[data-pair-osm-points]');

		if (!(payloadNode instanceof HTMLScriptElement)) {
			return [];
		}

		try {
			const payload = JSON.parse(payloadNode.textContent || '{}');
			const rawPoints = Array.isArray(payload.points) ? payload.points : [];

			return this.#normalizePoints(rawPoints);
		} catch (error) {
			return [];
		}
	}

	/**
	 * Normalize all raw points and discard invalid coordinates.
	 * @param {Array<object>} rawPoints
	 * @returns {Array<object>}
	 */
	static #normalizePoints(rawPoints) {
		const points = [];

		for (const rawPoint of rawPoints) {
			const point = this.#normalizePoint(rawPoint);

			if (point) {
				points.push(point);
			}
		}

		return points;
	}

	/**
	 * Convert a generic object into a valid Web Mercator marker.
	 * @param {object} rawPoint
	 * @returns {object|null}
	 */
	static #normalizePoint(rawPoint) {
		if (!rawPoint || typeof rawPoint !== 'object') {
			return null;
		}

		const latitude = Number(rawPoint.latitude);
		const longitude = Number(rawPoint.longitude);

		if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
			return null;
		}

		return {
			category: String(rawPoint.category || ''),
			label: String(rawPoint.label || ''),
			latitude,
			location: String(rawPoint.location || ''),
			longitude,
			marker: Number.parseInt(rawPoint.marker, 10) || 0,
			status: String(rawPoint.status || ''),
			title: String(rawPoint.title || ''),
			url: this.#normalizeMarkerUrl(rawPoint.url || rawPoint.detailUrl || '#'),
		};
	}

	/**
	 * Merge dataset and explicit options into renderer options.
	 * @param {HTMLElement} root
	 * @param {object} options
	 * @returns {object}
	 */
	static #normalizeOptions(root, options) {
		const rawMinZoom = this.#readInteger(options.minZoom, root.dataset.pairOsmMinZoom, this.#minZoom);
		const rawMaxZoom = this.#readInteger(options.maxZoom, root.dataset.pairOsmMaxZoom, this.#maxZoom);
		const minZoom = this.#clampInteger(Math.min(rawMinZoom, rawMaxZoom), this.#minAllowedZoom, this.#maxAllowedZoom);
		const maxZoom = this.#clampInteger(Math.max(rawMinZoom, rawMaxZoom), this.#minAllowedZoom, this.#maxAllowedZoom);

		return {
			mapPadding: Math.max(0, this.#readInteger(options.padding, root.dataset.pairOsmPadding, this.#mapPadding)),
			maxZoom,
			minZoom,
			singlePointZoom: this.#clampInteger(this.#readInteger(options.singlePointZoom, root.dataset.pairOsmSinglePointZoom, this.#singlePointZoom), minZoom, maxZoom),
			tileUrlTemplate: String(options.tileUrlTemplate || root.dataset.pairOsmTileUrlTemplate || this.#tileUrlTemplate),
		};
	}

	/**
	 * Normalize marker links to safe browser-navigation URL forms.
	 * @param {*} rawUrl
	 * @returns {string}
	 */
	static #normalizeMarkerUrl(rawUrl) {
		const url = String(rawUrl || '').trim();

		if (!url) {
			return '#';
		}

		if (url.startsWith('#') || url.startsWith('/') || url.startsWith('./') || url.startsWith('../')) {
			return url;
		}

		try {
			const parsedUrl = new URL(url, window.location.href);
			const allowedProtocols = ['http:', 'https:', 'mailto:', 'tel:'];

			return allowedProtocols.includes(parsedUrl.protocol) ? url : '#';
		} catch (error) {
			return '#';
		}
	}

	/**
	 * Read an integer from explicit and dataset values with fallback.
	 * @param {*} explicitValue
	 * @param {*} datasetValue
	 * @param {number} fallback
	 * @returns {number}
	 */
	static #readInteger(explicitValue, datasetValue, fallback) {
		const explicitNumber = Number.parseInt(explicitValue, 10);

		if (Number.isFinite(explicitNumber)) {
			return explicitNumber;
		}

		const datasetNumber = Number.parseInt(datasetValue, 10);

		return Number.isFinite(datasetNumber) ? datasetNumber : fallback;
	}

	/**
	 * Keep zoom-related options within a bounded tile range.
	 * @param {number} value
	 * @param {number} minimum
	 * @param {number} maximum
	 * @returns {number}
	 */
	static #clampInteger(value, minimum, maximum) {
		return Math.min(Math.max(value, minimum), maximum);
	}

	/**
	 * Redraw tiles and markers using the current container size.
	 * @param {object} state
	 * @returns {void}
	 */
	static #renderMap(state) {
		const size = this.#readCanvasSize(state.canvas);
		const zoom = this.#chooseZoom(state.points, size, state.options);
		const bounds = this.#projectedBounds(state.points, zoom);
		const center = {
			x: (bounds.minX + bounds.maxX) / 2,
			y: (bounds.minY + bounds.maxY) / 2,
		};
		const topLeft = {
			x: center.x - (size.width / 2),
			y: center.y - (size.height / 2),
		};

		this.#renderTiles(state.tiles, size, topLeft, zoom, state.options);
		this.#renderMarkers(state.markers, state.points, topLeft, zoom);
		this.#showMapStatus(state.status, '');
		state.root.classList.add('is-ready');
	}

	/**
	 * Return a stable minimum canvas size while the page is laying out.
	 * @param {HTMLElement} canvas
	 * @returns {{width: number, height: number}}
	 */
	static #readCanvasSize(canvas) {
		return {
			height: Math.max(canvas.clientHeight, 280),
			width: Math.max(canvas.clientWidth, 320),
		};
	}

	/**
	 * Pick the highest zoom that keeps all markers inside the viewport.
	 * @param {Array<object>} points
	 * @param {{width: number, height: number}} size
	 * @param {object} options
	 * @returns {number}
	 */
	static #chooseZoom(points, size, options) {
		if (1 === points.length) {
			return options.singlePointZoom;
		}

		const availableWidth = Math.max(size.width - (options.mapPadding * 2), 96);
		const availableHeight = Math.max(size.height - (options.mapPadding * 2), 96);

		for (let zoom = options.maxZoom; zoom >= options.minZoom; zoom -= 1) {
			const bounds = this.#projectedBounds(points, zoom);

			if ((bounds.maxX - bounds.minX) <= availableWidth && (bounds.maxY - bounds.minY) <= availableHeight) {
				return zoom;
			}
		}

		return options.minZoom;
	}

	/**
	 * Calculate the marker bounds in zoom-level pixel space.
	 * @param {Array<object>} points
	 * @param {number} zoom
	 * @returns {{minX: number, maxX: number, minY: number, maxY: number}}
	 */
	static #projectedBounds(points, zoom) {
		let minX = Number.POSITIVE_INFINITY;
		let maxX = Number.NEGATIVE_INFINITY;
		let minY = Number.POSITIVE_INFINITY;
		let maxY = Number.NEGATIVE_INFINITY;

		for (const point of points) {
			const projected = this.#projectPoint(point.latitude, point.longitude, zoom);
			minX = Math.min(minX, projected.x);
			maxX = Math.max(maxX, projected.x);
			minY = Math.min(minY, projected.y);
			maxY = Math.max(maxY, projected.y);
		}

		return { maxX, maxY, minX, minY };
	}

	/**
	 * Project latitude and longitude into Web Mercator pixel space.
	 * @param {number} latitude
	 * @param {number} longitude
	 * @param {number} zoom
	 * @returns {{x: number, y: number}}
	 */
	static #projectPoint(latitude, longitude, zoom) {
		const clampedLatitude = Math.max(Math.min(latitude, this.#maxMercatorLatitude), -this.#maxMercatorLatitude);
		const sinLatitude = Math.sin((clampedLatitude * Math.PI) / 180);
		const scale = this.#tileSize * (2 ** zoom);

		return {
			x: ((longitude + 180) / 360) * scale,
			y: (0.5 - (Math.log((1 + sinLatitude) / (1 - sinLatitude)) / (4 * Math.PI))) * scale,
		};
	}

	/**
	 * Draw the OpenStreetMap tiles visible in the canvas.
	 * @param {HTMLElement} tiles
	 * @param {{width: number, height: number}} size
	 * @param {{x: number, y: number}} topLeft
	 * @param {number} zoom
	 * @param {object} options
	 * @returns {void}
	 */
	static #renderTiles(tiles, size, topLeft, zoom, options) {
		const tileCount = 2 ** zoom;
		const startX = Math.floor(topLeft.x / this.#tileSize);
		const endX = Math.floor((topLeft.x + size.width) / this.#tileSize);
		const startY = Math.floor(topLeft.y / this.#tileSize);
		const endY = Math.floor((topLeft.y + size.height) / this.#tileSize);

		this.#clearElement(tiles);

		for (let tileY = startY; tileY <= endY; tileY += 1) {
			if (tileY < 0 || tileY >= tileCount) {
				continue;
			}

			for (let tileX = startX; tileX <= endX; tileX += 1) {
				tiles.appendChild(this.#createTileImage(tileX, tileY, zoom, tileCount, topLeft, options));
			}
		}
	}

	/**
	 * Create one map tile positioned in map pixel space.
	 * @param {number} tileX
	 * @param {number} tileY
	 * @param {number} zoom
	 * @param {number} tileCount
	 * @param {{x: number, y: number}} topLeft
	 * @param {object} options
	 * @returns {HTMLImageElement}
	 */
	static #createTileImage(tileX, tileY, zoom, tileCount, topLeft, options) {
		const image = document.createElement('img');
		const normalizedX = this.#normalizeTileX(tileX, tileCount);

		image.alt = '';
		image.decoding = 'async';
		image.draggable = false;
		image.loading = 'eager';
		image.referrerPolicy = 'strict-origin-when-cross-origin';
		image.src = this.#buildTileUrl(zoom, normalizedX, tileY, options.tileUrlTemplate);
		image.style.transform = 'translate(' + Math.round((tileX * this.#tileSize) - topLeft.x) + 'px, ' + Math.round((tileY * this.#tileSize) - topLeft.y) + 'px)';

		return image;
	}

	/**
	 * Normalize horizontal tile indexes to support world wrapping.
	 * @param {number} tileX
	 * @param {number} tileCount
	 * @returns {number}
	 */
	static #normalizeTileX(tileX, tileCount) {
		return ((tileX % tileCount) + tileCount) % tileCount;
	}

	/**
	 * Build the HTTPS tile URL from the configured template.
	 * @param {number} zoom
	 * @param {number} tileX
	 * @param {number} tileY
	 * @param {string} template
	 * @returns {string}
	 */
	static #buildTileUrl(zoom, tileX, tileY, template) {
		return template
			.replace('{z}', String(zoom))
			.replace('{x}', String(tileX))
			.replace('{y}', String(tileY));
	}

	/**
	 * Position markers over the rendered tiles.
	 * @param {HTMLElement} markers
	 * @param {Array<object>} points
	 * @param {{x: number, y: number}} topLeft
	 * @param {number} zoom
	 * @returns {void}
	 */
	static #renderMarkers(markers, points, topLeft, zoom) {
		this.#clearElement(markers);

		for (const point of points) {
			const projected = this.#projectPoint(point.latitude, point.longitude, zoom);
			const marker = this.#createMarkerLink(point);

			marker.style.left = Math.round(projected.x - topLeft.x) + 'px';
			marker.style.top = Math.round(projected.y - topLeft.y) + 'px';
			markers.appendChild(marker);
		}
	}

	/**
	 * Create an accessible marker link.
	 * @param {object} point
	 * @returns {HTMLAnchorElement}
	 */
	static #createMarkerLink(point) {
		const marker = document.createElement('a');
		const label = this.#markerLabel(point);

		marker.className = 'pair-osm-map__marker';
		marker.href = point.url;
		marker.title = label;
		marker.setAttribute('aria-label', label);
		marker.setAttribute('data-label', label);
		marker.textContent = String(point.marker);

		return marker;
	}

	/**
	 * Compose a readable label for marker accessibility and native tooltip.
	 * @param {object} point
	 * @returns {string}
	 */
	static #markerLabel(point) {
		const parts = ['#' + point.marker, point.title, point.location];
		const labelParts = [];

		for (const part of parts) {
			if (part) {
				labelParts.push(part);
			}
		}

		return labelParts.join(' - ');
	}

	/**
	 * Update the status message without injecting HTML.
	 * @param {HTMLElement} status
	 * @param {string} message
	 * @returns {void}
	 */
	static #showMapStatus(status, message) {
		status.textContent = message;
		status.hidden = '' === message;
	}

	/**
	 * Remove all children before a redraw.
	 * @param {HTMLElement} element
	 * @returns {void}
	 */
	static #clearElement(element) {
		while (element.firstChild) {
			element.removeChild(element.firstChild);
		}
	}
}

/**
 * Initialize OpenStreetMap surfaces after the document is ready.
 * @returns {void}
 */
function pairOpenStreetMapReady() {
	PairOpenStreetMap.initAll();
}

/**
 * Schedule map redraws when the viewport changes.
 * @returns {void}
 */
function pairOpenStreetMapResize() {
	PairOpenStreetMap.scheduleRenderAll();
}

/**
 * Redraw maps from the requestAnimationFrame callback.
 * @returns {void}
 */
function pairOpenStreetMapRenderAll() {
	PairOpenStreetMap.renderAll();
}

document.addEventListener('DOMContentLoaded', pairOpenStreetMapReady);
window.addEventListener('resize', pairOpenStreetMapResize);
window.PairOpenStreetMap = PairOpenStreetMap;
