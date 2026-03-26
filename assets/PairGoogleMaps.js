class PairGoogleMaps {

	/**
	 * Tracks already-initialized inputs to avoid duplicate bindings.
	 * @type {WeakSet<HTMLInputElement>}
	 */
	static #initializedInputs = new WeakSet();

	/**
	 * Google callback invoked when the Maps JavaScript API is ready.
	 * @returns {void}
	 */
	static onGoogleMapsReady() {
		this.initAll();
	}

	/**
	 * Initialize every Google-enhanced address field found in the provided root.
	 * @param {ParentNode|Document} root
	 * @returns {void}
	 */
	static initAll(root = document) {
		if (!this.#isAutocompleteAvailable()) {
			return;
		}

		const inputs = Array.from(root.querySelectorAll('input.pairGoogleAddress'));

		for (const input of inputs) {
			this.initInput(input);
		}
	}

	/**
	 * Initialize a single Google-enhanced address field.
	 * @param {HTMLInputElement} input
	 * @returns {google.maps.places.Autocomplete|null}
	 */
	static initInput(input) {
		if (!(input instanceof HTMLInputElement) || this.#initializedInputs.has(input) || !this.#isAutocompleteAvailable()) {
			return null;
		}

		const options = {
			fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id', 'types'],
		};

		const countries = this.#splitDatasetList(input.dataset.googleCountries);
		if (countries.length) {
			options.componentRestrictions = {
				country: countries.length === 1 ? countries[0] : countries,
			};
		}

		const types = this.#splitDatasetList(input.dataset.googleTypes);
		if (types.length) {
			options.types = types;
		}

		const bounds = this.#parseBounds(input.dataset.googleBounds);
		if (bounds) {
			options.bounds = new google.maps.LatLngBounds(
				{ lat: bounds.south, lng: bounds.west },
				{ lat: bounds.north, lng: bounds.east }
			);
		}

		if ('true' === (input.dataset.googleStrictBounds || '').toLowerCase()) {
			options.strictBounds = true;
		}

		const autocomplete = new google.maps.places.Autocomplete(input, options);

		// Any manual edit after a previous selection invalidates the hidden metadata fields.
		input.addEventListener('input', () => {
			if (input.dataset.pairGoogleSelectedValue !== input.value) {
				this.#clearBoundFields(input);
			}
		});

		autocomplete.addListener('place_changed', () => {
			this.#applySelection(input, autocomplete.getPlace());
		});

		this.#initializedInputs.add(input);

		return autocomplete;
	}

	/**
	 * Determine whether the Google Places autocomplete widget is available.
	 * @returns {boolean}
	 */
	static #isAutocompleteAvailable() {
		return !!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete);
	}

	/**
	 * Apply the selected place payload to the visible input and linked hidden fields.
	 * @param {HTMLInputElement} input
	 * @param {*} place
	 * @returns {void}
	 */
	static #applySelection(input, place) {
		if (!place) {
			this.#clearBoundFields(input);
			return;
		}

		const formattedAddress = place.formatted_address || input.value || place.name || '';
		const latitude = this.#extractCoordinate(place, 'lat');
		const longitude = this.#extractCoordinate(place, 'lng');
		const components = this.#normalizeComponents(place.address_components || []);

		input.value = formattedAddress;
		input.dataset.pairGoogleSelectedValue = formattedAddress;

		this.#writeBoundField(input, input.dataset.googlePlaceIdField, place.place_id || '');
		this.#writeBoundField(input, input.dataset.googleLatitudeField, latitude);
		this.#writeBoundField(input, input.dataset.googleLongitudeField, longitude);
		this.#writeBoundField(input, input.dataset.googleComponentsField, JSON.stringify(components));

		input.dispatchEvent(new CustomEvent('pair:google-address-selected', {
			bubbles: true,
			detail: {
				components,
				formattedAddress,
				latitude,
				longitude,
				place,
				placeId: place.place_id || null,
			},
		}));
	}

	/**
	 * Clear hidden metadata fields linked to an address input.
	 * @param {HTMLInputElement} input
	 * @returns {void}
	 */
	static #clearBoundFields(input) {
		delete input.dataset.pairGoogleSelectedValue;
		this.#writeBoundField(input, input.dataset.googlePlaceIdField, '');
		this.#writeBoundField(input, input.dataset.googleLatitudeField, '');
		this.#writeBoundField(input, input.dataset.googleLongitudeField, '');
		this.#writeBoundField(input, input.dataset.googleComponentsField, '');
	}

	/**
	 * Write a value into the field bound by name inside the same form context.
	 * @param {HTMLInputElement} input
	 * @param {string|undefined} fieldName
	 * @param {*} value
	 * @returns {void}
	 */
	static #writeBoundField(input, fieldName, value) {
		const field = this.#findField(input, fieldName);

		if (!field) {
			return;
		}

		field.value = (value ?? '').toString();
	}

	/**
	 * Find a field by exact HTML name within the same form, falling back to the document.
	 * @param {HTMLInputElement} input
	 * @param {string|undefined} fieldName
	 * @returns {HTMLInputElement|null}
	 */
	static #findField(input, fieldName) {
		const normalizedFieldName = (fieldName || '').trim();

		if (!normalizedFieldName) {
			return null;
		}

		const form = input.form;
		const candidates = form ? Array.from(form.elements) : Array.from(document.querySelectorAll('[name]'));

		for (const field of candidates) {
			if (field instanceof HTMLInputElement && field.name === normalizedFieldName) {
				return field;
			}
		}

		return null;
	}

	/**
	 * Extract a latitude or longitude value from a Google place geometry object.
	 * @param {*} place
	 * @param {'lat'|'lng'} axis
	 * @returns {string}
	 */
	static #extractCoordinate(place, axis) {
		if (!place || !place.geometry || !place.geometry.location) {
			return '';
		}

		const coordinate = place.geometry.location[axis];

		if (typeof coordinate === 'function') {
			return String(coordinate.call(place.geometry.location));
		}

		if (coordinate == null) {
			return '';
		}

		return String(coordinate);
	}

	/**
	 * Normalize Google address components into a JSON-friendly structure.
	 * @param {Array} components
	 * @returns {Array}
	 */
	static #normalizeComponents(components) {
		if (!Array.isArray(components)) {
			return [];
		}

		return components.map((component) => ({
			longName: component.long_name || '',
			shortName: component.short_name || '',
			types: Array.isArray(component.types) ? component.types : [],
		}));
	}

	/**
	 * Parse a comma-separated dataset list.
	 * @param {string|undefined} value
	 * @returns {string[]}
	 */
	static #splitDatasetList(value) {
		if (!value) {
			return [];
		}

		return value
			.split(',')
			.map((item) => item.trim())
			.filter(Boolean);
	}

	/**
	 * Parse rectangular bounds stored as JSON in a data attribute.
	 * @param {string|undefined} value
	 * @returns {{south:number,west:number,north:number,east:number}|null}
	 */
	static #parseBounds(value) {
		if (!value) {
			return null;
		}

		try {
			const parsed = JSON.parse(value);

			if (
				typeof parsed.south !== 'number'
				|| typeof parsed.west !== 'number'
				|| typeof parsed.north !== 'number'
				|| typeof parsed.east !== 'number'
			) {
				return null;
			}

			return parsed;
		} catch (error) {
			return null;
		}
	}
}

document.addEventListener('DOMContentLoaded', () => {
	PairGoogleMaps.initAll();
});

window.PairGoogleMaps = PairGoogleMaps;
