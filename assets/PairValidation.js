/**
 * Shared Pair form validation presets for client-side normalization and validity hints.
 */
(function () {
	'use strict';

	const PRESET_BIC = 'bic';
	const PRESET_E164_PHONE = 'e164_phone';
	const PRESET_EAN13 = 'ean13';
	const PRESET_EMAIL = 'email';
	const PRESET_ITALIAN_FISCAL_CODE = 'it.fiscal_code';
	const PRESET_HEX_COLOR = 'hex_color';
	const PRESET_IBAN = 'iban';
	const PRESET_IP_ADDRESS = 'ip_address';
	const PRESET_IPV4_ADDRESS = 'ipv4_address';
	const PRESET_IPV6_ADDRESS = 'ipv6_address';
	const PRESET_MAC_ADDRESS = 'mac_address';
	const PRESET_ITALIAN_PERSONAL_FISCAL_CODE = 'it.personal_fiscal_code';
	const PRESET_ITALIAN_SDI_RECIPIENT_CODE = 'it.sdi_recipient_code';
	const PRESET_SLUG = 'slug';
	const PRESET_URL = 'url';
	const PRESET_UUID = 'uuid';
	const PRESET_ITALIAN_VAT_NUMBER = 'it.vat_number';
	const FISCAL_CODE_BIRTH_MONTHS = {
		A: 1,
		B: 2,
		C: 3,
		D: 4,
		E: 5,
		H: 6,
		L: 7,
		M: 8,
		P: 9,
		R: 10,
		S: 11,
		T: 12
	};
	const FISCAL_CODE_ODD_VALUES = {
		0: 1, 1: 0, 2: 5, 3: 7, 4: 9, 5: 13, 6: 15, 7: 17, 8: 19, 9: 21,
		A: 1, B: 0, C: 5, D: 7, E: 9, F: 13, G: 15, H: 17, I: 19, J: 21,
		K: 2, L: 4, M: 18, N: 20, O: 11, P: 3, Q: 6, R: 8, S: 12, T: 14,
		U: 16, V: 10, W: 22, X: 25, Y: 24, Z: 23
	};
	const FISCAL_CODE_OMOCODIA_MAP = {
		L: '0',
		M: '1',
		N: '2',
		P: '3',
		Q: '4',
		R: '5',
		S: '6',
		T: '7',
		U: '8',
		V: '9'
	};
	const FISCAL_CODE_OMOCODIA_POSITIONS = [6, 7, 9, 10, 12, 13, 14];
	const BIC_PATTERN = /^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/;
	const E164_PHONE_PATTERN = /^\+[1-9][0-9]{1,14}$/;
	const HEX_COLOR_PATTERN = /^#(?:[0-9A-F]{3}|[0-9A-F]{6})$/;
	const MAC_ADDRESS_PATTERN = /^[0-9A-F]{2}(:[0-9A-F]{2}){5}$/;
	const NUMERIC_FISCAL_CODE_PATTERN = /^[0-9]{11}$/;
	const PERSONAL_FISCAL_CODE_PATTERN = /^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/;
	const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
	const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
	const PRESET_ALIASES = {
		bic: PRESET_BIC,
		bic_code: PRESET_BIC,
		codice_destinatario: PRESET_ITALIAN_SDI_RECIPIENT_CODE,
		codice_fiscale: PRESET_ITALIAN_FISCAL_CODE,
		cf: PRESET_ITALIAN_FISCAL_CODE,
		e164: PRESET_E164_PHONE,
		e164_phone: PRESET_E164_PHONE,
		ean: PRESET_EAN13,
		'ean-13': PRESET_EAN13,
		ean13: PRESET_EAN13,
		email: PRESET_EMAIL,
		email_address: PRESET_EMAIL,
		hex_color: PRESET_HEX_COLOR,
		iban: PRESET_IBAN,
		iban_code: PRESET_IBAN,
		international_phone: PRESET_E164_PHONE,
		ip: PRESET_IP_ADDRESS,
		ip_address: PRESET_IP_ADDRESS,
		ipv4: PRESET_IPV4_ADDRESS,
		ipv4_address: PRESET_IPV4_ADDRESS,
		ipv6: PRESET_IPV6_ADDRESS,
		ipv6_address: PRESET_IPV6_ADDRESS,
		italian_fiscal_code: PRESET_ITALIAN_FISCAL_CODE,
		italian_personal_fiscal_code: PRESET_ITALIAN_PERSONAL_FISCAL_CODE,
		italian_sdi_recipient_code: PRESET_ITALIAN_SDI_RECIPIENT_CODE,
		italian_vat_number: PRESET_ITALIAN_VAT_NUMBER,
		'it.fiscal_code': PRESET_ITALIAN_FISCAL_CODE,
		'it.personal_fiscal_code': PRESET_ITALIAN_PERSONAL_FISCAL_CODE,
		'it.sdi_recipient_code': PRESET_ITALIAN_SDI_RECIPIENT_CODE,
		'it.vat_number': PRESET_ITALIAN_VAT_NUMBER,
		mac: PRESET_MAC_ADDRESS,
		mac_address: PRESET_MAC_ADDRESS,
		partita_iva: PRESET_ITALIAN_VAT_NUMBER,
		phone_e164: PRESET_E164_PHONE,
		piva: PRESET_ITALIAN_VAT_NUMBER,
		sdi: PRESET_ITALIAN_SDI_RECIPIENT_CODE,
		slug: PRESET_SLUG,
		swift: PRESET_BIC,
		swift_bic: PRESET_BIC,
		url: PRESET_URL,
		uuid: PRESET_UUID
	};
	const PRESET_DEFINITIONS = {
		[PRESET_BIC]: {
			message: 'Enter a valid BIC/SWIFT code.',
			normalizer: normalizeBic,
			validator: isValidBic
		},
		[PRESET_E164_PHONE]: {
			message: 'Enter a valid international phone number in E.164 format.',
			normalizer: normalizeE164Phone,
			validator: isValidE164Phone
		},
		[PRESET_EAN13]: {
			changeNormalizer: completeEan13,
			message: 'Enter a valid EAN-13 code.',
			normalizer: normalizeEan13,
			validationNormalizer: completeEan13,
			validator: isValidEan13
		},
		[PRESET_EMAIL]: {
			message: 'Enter a valid email address.',
			normalizer: normalizeEmail,
			validator: isValidEmail
		},
		[PRESET_ITALIAN_FISCAL_CODE]: {
			message: 'Enter a valid Italian fiscal code.',
			normalizer: normalizeItalianFiscalCode,
			validator: isValidItalianFiscalCode
		},
		[PRESET_HEX_COLOR]: {
			message: 'Enter a valid hexadecimal color.',
			normalizer: normalizeHexColor,
			validator: isValidHexColor
		},
		[PRESET_IBAN]: {
			message: 'Enter a valid IBAN.',
			normalizer: normalizeIban,
			validator: isValidIban
		},
		[PRESET_IP_ADDRESS]: {
			message: 'Enter a valid IP address.',
			normalizer: normalizeIpAddress,
			validator: isValidIpAddress
		},
		[PRESET_IPV4_ADDRESS]: {
			message: 'Enter a valid IPv4 address.',
			normalizer: normalizeIpAddress,
			validator: isValidIpv4Address
		},
		[PRESET_IPV6_ADDRESS]: {
			message: 'Enter a valid IPv6 address.',
			normalizer: normalizeIpAddress,
			validator: isValidIpv6Address
		},
		[PRESET_MAC_ADDRESS]: {
			message: 'Enter a valid MAC address.',
			normalizer: normalizeMacAddress,
			validator: isValidMacAddress
		},
		[PRESET_ITALIAN_PERSONAL_FISCAL_CODE]: {
			message: 'Enter a valid Italian personal fiscal code.',
			normalizer: normalizeItalianFiscalCode,
			validator: isValidItalianPersonalFiscalCode
		},
		[PRESET_ITALIAN_SDI_RECIPIENT_CODE]: {
			message: 'Enter a valid Italian SdI recipient code.',
			normalizer: normalizeItalianSdiRecipientCode,
			validator: isValidItalianSdiRecipientCode
		},
		[PRESET_ITALIAN_VAT_NUMBER]: {
			message: 'Enter a valid Italian VAT number.',
			normalizer: normalizeItalianVatNumber,
			validator: isValidItalianVatNumber
		},
		[PRESET_SLUG]: {
			message: 'Enter a valid slug.',
			normalizer: normalizeSlug,
			validator: isValidSlug
		},
		[PRESET_URL]: {
			message: 'Enter a valid URL.',
			normalizer: normalizeUrl,
			validator: isValidUrl
		},
		[PRESET_UUID]: {
			message: 'Enter a valid UUID.',
			normalizer: normalizeUuid,
			validator: isValidUuid
		}
	};
	const FORM_SELECTOR = 'form[data-pair-validate]';
	const FORM_FIELD_SELECTOR = [
		'input:not([type="button"]):not([type="hidden"]):not([type="reset"]):not([type="submit"])',
		'select',
		'textarea'
	].join(',');
	const FORM_STATES = new WeakMap();
	const FORM_ADAPTERS = [];
	const VALIDITY_RULES = [
		['customError', 'custom'],
		['valueMissing', 'required'],
		['typeMismatch', 'type'],
		['patternMismatch', 'pattern'],
		['tooShort', 'minLength'],
		['tooLong', 'maxLength'],
		['rangeUnderflow', 'min'],
		['rangeOverflow', 'max'],
		['stepMismatch', 'step'],
		['badInput', 'badInput']
	];
	const FORM_MESSAGES = {
		en: {
			badInput: 'Enter a valid value for “{label}”.',
			custom: 'Correct “{label}”.',
			email: 'Enter a valid email address.',
			max: 'Enter a value no greater than {max} for “{label}”.',
			maxLength: 'Use no more than {maxLength} characters for “{label}”.',
			min: 'Enter a value of at least {min} for “{label}”.',
			minLength: 'Use at least {minLength} characters for “{label}”.',
			pattern: 'Use the required format for “{label}”.',
			required: 'Complete “{label}”.',
			step: 'Enter an allowed value for “{label}”.',
			summaryMany: 'Correct {count} fields',
			summaryOne: 'Correct the indicated field',
			type: 'Enter a valid value for “{label}”.',
			url: 'Enter a complete and valid URL.'
		},
		it: {
			badInput: 'Inserisci un valore valido per “{label}”.',
			custom: 'Correggi “{label}”.',
			email: 'Inserisci un indirizzo e-mail valido.',
			max: 'Inserisci per “{label}” un valore non superiore a {max}.',
			maxLength: 'Usa al massimo {maxLength} caratteri per “{label}”.',
			min: 'Inserisci per “{label}” un valore almeno pari a {min}.',
			minLength: 'Usa almeno {minLength} caratteri per “{label}”.',
			pattern: 'Usa il formato richiesto per “{label}”.',
			required: 'Completa “{label}”.',
			step: 'Inserisci un valore consentito per “{label}”.',
			summaryMany: 'Correggi {count} campi',
			summaryOne: 'Correggi il campo indicato',
			type: 'Inserisci un valore valido per “{label}”.',
			url: 'Inserisci un URL completo e valido.'
		}
	};
	let formFieldSequence = 0;
	let formValidationOptions = {
		focusTarget: 'first-invalid',
		locale: null,
		messages: {},
		summary: false
	};

	/**
	 * Binds accessible constraint validation to an opt-in form.
	 * @param {HTMLFormElement|null} form Form to bind.
	 * @returns {void}
	 */
	function bindForm(form) {
		if (!form || FORM_STATES.has(form)) {
			return;
		}

		const state = {
			previousNoValidate: form.noValidate,
			onChange: (event) => handleFieldChange(form, event),
			onFocusOut: (event) => handleFieldFocusOut(form, event),
			onInput: (event) => handleFieldInput(form, event),
			onInvalid: (event) => event.preventDefault(),
			onReset: () => window.setTimeout(() => resetForm(form), 0),
			onSubmit: (event) => handleFormSubmit(form, event),
			widgetObserver: null
		};

		FORM_STATES.set(form, state);
		form.dataset.pairValidationReady = '1';

		// Native validation remains the fallback until this point.
		form.noValidate = true;
		form.addEventListener('change', state.onChange);
		form.addEventListener('focusout', state.onFocusOut);
		form.addEventListener('input', state.onInput);
		form.addEventListener('invalid', state.onInvalid, true);
		form.addEventListener('reset', state.onReset);
		form.addEventListener('submit', state.onSubmit, true);
		state.widgetObserver = observeCustomWidgets(form);

		formFields(form).forEach((field) => ensureFieldId(field));
	}

	/**
	 * Clears the visible and programmatic error owned by Pair for one field.
	 * @param {HTMLElement|null} field Field to clear.
	 * @returns {void}
	 */
	function clearField(field) {
		field = validationFieldFor(field);

		if (!isFormField(field)) {
			return;
		}

		const validityFields = validityFieldsFor(field);
		const ui = fieldUi(field);
		const errorId = errorIdFor(field);
		const error = document.getElementById(errorId);
		const wasInvalid = validityFields.some((candidate) => {
			return candidate.dataset.pairValidationInvalid === '1';
		});

		validityFields.forEach((candidate) => {
			if (candidate.dataset.pairValidationExternalError === '1') {
				candidate.setCustomValidity('');
				delete candidate.dataset.pairValidationExternalError;
			}

			candidate.classList.remove('is-invalid');
			candidate.removeAttribute('aria-invalid');
			removeAttributeToken(candidate, 'aria-describedby', errorId);
			delete candidate.dataset.pairValidationInvalid;
		});

		syncPresetValidity(field, false);
		ui.visual.classList.remove('is-invalid', 'pair-validation-invalid');

		if (ui.focus !== field) {
			ui.focus.removeAttribute('aria-invalid');
			removeAttributeToken(ui.focus, 'aria-describedby', errorId);
		}

		if (error) {
			error.remove();
		}

		if (wasInvalid) {
			field.dispatchEvent(new CustomEvent('pair:validation:field-valid', {
				bubbles: true,
				detail: {field: field}
			}));
		}
	}

	/**
	 * Updates global defaults used by opt-in Pair validation forms.
	 * @param {Object} options Validation options.
	 * @returns {Object} Current options.
	 */
	function configure(options = {}) {
		const messages = options.messages && 'object' === typeof options.messages
			? {...formValidationOptions.messages, ...options.messages}
			: formValidationOptions.messages;

		formValidationOptions = {
			...formValidationOptions,
			...options,
			messages: messages
		};

		return {...formValidationOptions, messages: {...formValidationOptions.messages}};
	}

	/**
	 * Destroys Pair validation for one form and restores its native validation state.
	 * @param {HTMLFormElement|null} form Form to destroy.
	 * @returns {void}
	 */
	function destroyForm(form) {
		const state = form ? FORM_STATES.get(form) : null;

		if (!form || !state) {
			return;
		}

		form.removeEventListener('change', state.onChange);
		form.removeEventListener('focusout', state.onFocusOut);
		form.removeEventListener('input', state.onInput);
		form.removeEventListener('invalid', state.onInvalid, true);
		form.removeEventListener('reset', state.onReset);
		form.removeEventListener('submit', state.onSubmit, true);

		if (state.widgetObserver) {
			state.widgetObserver.disconnect();
		}

		formFields(form).forEach((field) => {
			clearField(field);
			delete field.dataset.pairValidationTouched;
		});
		clearSummary(form);
		removeLiveRegion(form);
		form.noValidate = state.previousNoValidate;
		delete form.dataset.pairValidationReady;
		delete form.dataset.pairValidationSubmitted;
		FORM_STATES.delete(form);
	}

	/**
	 * Resolves the current visual, insertion, and focus targets for a field.
	 * @param {HTMLElement} field Native form field.
	 * @returns {{anchor: HTMLElement, focus: HTMLElement, visual: HTMLElement}} UI targets.
	 */
	function fieldUi(field) {
		for (const entry of FORM_ADAPTERS) {
			if (entry.adapter.matches(field)) {
				const resolved = entry.adapter.resolve(field);

				if (resolved && resolved.anchor && resolved.focus && resolved.visual) {
					return resolved;
				}
			}
		}

		const explicitAnchor = field.closest('[data-pair-validation-anchor]');
		const inputGroup = field.closest('.input-group');
		const checkGroup = field.closest('[data-pair-validation-group], .form-check, .form-switch');

		return {
			anchor: explicitAnchor || inputGroup || checkGroup || field,
			focus: field,
			visual: field
		};
	}

	/**
	 * Returns all enabled, visible constraint fields owned by a form.
	 * @param {HTMLFormElement} form Form to inspect.
	 * @returns {HTMLElement[]} Form fields.
	 */
	function formFields(form) {
		const fields = Array.from(form.elements || []);
		const uniqueGroups = new Set();

		return fields.filter((field) => {
			if (!isFormField(field) || field.disabled || field.dataset.pairValidationIgnore === 'true') {
				return false;
			}

			if ('radio' !== field.type) {
				return true;
			}

			const groupKey = field.name || ensureFieldId(field);
			if (uniqueGroups.has(groupKey)) {
				return false;
			}

			uniqueGroups.add(groupKey);
			return true;
		});
	}

	/**
	 * Handles a change event after a field was visited or a submit was attempted.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {Event} event Change event.
	 * @returns {void}
	 */
	function handleFieldChange(form, event) {
		const field = validationFieldFor(event.target);

		if (!isFormField(field)) {
			return;
		}

		field.dataset.pairValidationTouched = '1';
		clearExternalError(field);
		validateField(field, {show: true});
		refreshSummaryAfterFieldChange(form);
	}

	/**
	 * Handles focus leaving a field without validating untouched controls.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {FocusEvent} event Focus event.
	 * @returns {void}
	 */
	function handleFieldFocusOut(form, event) {
		const field = validationFieldFor(event.target);

		if (!isFormField(field)) {
			return;
		}

		const wasInvalid = field.dataset.pairValidationInvalid === '1';
		field.dataset.pairValidationTouched = '1';
		const valid = validateField(field, {show: true});

		if (!valid && !wasInvalid) {
			announceFieldError(form, field);
		}

		refreshSummaryAfterFieldChange(form);
	}

	/**
	 * Revalidates typing only after the field already has visible validation state.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {InputEvent} event Input event.
	 * @returns {void}
	 */
	function handleFieldInput(form, event) {
		const field = validationFieldFor(event.target);

		if (!isFormField(field)) {
			return;
		}

		clearExternalError(field);

		if (
			field.dataset.pairValidationInvalid === '1'
			|| field.dataset.pairValidationTouched === '1'
			|| form.dataset.pairValidationSubmitted === '1'
		) {
			validateField(field, {show: true});
			refreshSummaryAfterFieldChange(form);
		}
	}

	/**
	 * Resolves one stable representative for controls that share radio validity.
	 * @param {EventTarget|null} field Candidate field.
	 * @returns {HTMLElement|null} Field used for validation rendering.
	 */
	function validationFieldFor(field) {
		const fields = validityFieldsFor(field);

		return fields[0] || field;
	}

	/**
	 * Returns all native controls that share one validity and error message.
	 * @param {EventTarget|null} field Candidate field.
	 * @returns {HTMLElement[]} Related form controls.
	 */
	function validityFieldsFor(field) {
		if (!isFormField(field)) {
			return [];
		}

		if ('radio' !== field.type || !field.form || !field.name) {
			return [field];
		}

		const namedGroup = field.form.elements.namedItem(field.name);
		const candidates = namedGroup && typeof namedGroup.length === 'number'
			? Array.from(namedGroup)
			: [namedGroup];

		return candidates.filter((candidate) => {
			return isFormField(candidate)
				&& 'radio' === candidate.type
				&& candidate.form === field.form;
		});
	}

	/**
	 * Prevents invalid submissions while respecting formnovalidate submitters.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {SubmitEvent} event Submit event.
	 * @returns {void}
	 */
	function handleFormSubmit(form, event) {
		if (event.submitter && event.submitter.formNoValidate) {
			return;
		}

		if (validateForm(form, {focus: true})) {
			return;
		}

		event.preventDefault();
		event.stopImmediatePropagation();
	}

	/**
	 * Returns true when a node can participate in HTML constraint validation.
	 * @param {EventTarget|null} field Candidate field.
	 * @returns {boolean} True for supported controls.
	 */
	function isFormField(field) {
		return field instanceof HTMLElement
			&& typeof field.matches === 'function'
			&& field.matches(FORM_FIELD_SELECTOR)
			&& typeof field.setCustomValidity === 'function';
	}

	/**
	 * Observes value presentation changes from widgets that emit library-only events.
	 * @param {HTMLFormElement} form Form containing custom widgets.
	 * @returns {MutationObserver|null} Active observer when supported.
	 */
	function observeCustomWidgets(form) {
		if (typeof MutationObserver !== 'function') {
			return null;
		}

		const observer = new MutationObserver((mutations) => {
			const changedFields = new Set();

			mutations.forEach((mutation) => {
				const field = customWidgetFieldForMutation(form, mutation.target);

				if (field) {
					changedFields.add(field);
				}
			});

			let refreshed = false;
			changedFields.forEach((field) => {
				clearExternalError(field);

				if (
					field.dataset.pairValidationInvalid === '1'
					|| field.dataset.pairValidationTouched === '1'
					|| form.dataset.pairValidationSubmitted === '1'
				) {
					validateField(field, {show: true});
					refreshed = true;
				}
			});

			if (refreshed) {
				refreshSummaryAfterFieldChange(form);
			}
		});

		observer.observe(form, {
			characterData: true,
			childList: true,
			subtree: true
		});

		return observer;
	}

	/**
	 * Maps a Select2 or NiceSelect2 mutation back to its native form field.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {Node} target Mutation target.
	 * @returns {HTMLElement|null} Native field or null.
	 */
	function customWidgetFieldForMutation(form, target) {
		const element = target instanceof Element ? target : target.parentElement;
		const widget = element ? element.closest('.select2-container, .nice-select') : null;
		const field = widget ? widget.previousElementSibling : null;

		if (!widget || !form.contains(widget) || !isFormField(field) || field.form !== form) {
			return null;
		}

		return validationFieldFor(field);
	}

	/**
	 * Registers or replaces a visual adapter for a custom form widget.
	 * @param {string} name Adapter name.
	 * @param {{matches: Function, resolve: Function}} adapter Adapter implementation.
	 * @returns {void}
	 */
	function registerAdapter(name, adapter) {
		if (!name || !adapter || typeof adapter.matches !== 'function' || typeof adapter.resolve !== 'function') {
			throw new TypeError('A Pair validation adapter requires a name, matches(), and resolve().');
		}

		const existingIndex = FORM_ADAPTERS.findIndex((entry) => entry.name === name);
		if (existingIndex >= 0) {
			FORM_ADAPTERS.splice(existingIndex, 1);
		}

		FORM_ADAPTERS.unshift({name: name, adapter: adapter});
	}

	/**
	 * Removes all Pair validation state after a native form reset.
	 * @param {HTMLFormElement|null} form Form to reset.
	 * @returns {void}
	 */
	function resetForm(form) {
		if (!form) {
			return;
		}

		formFields(form).forEach((field) => {
			clearField(field);
			delete field.dataset.pairValidationTouched;
		});
		clearSummary(form);
		delete form.dataset.pairValidationSubmitted;
	}

	/**
	 * Applies an external or server-side error through the same accessible UI.
	 * @param {HTMLElement|null} field Field to mark.
	 * @param {string} message Human-readable correction.
	 * @returns {void}
	 */
	function setFieldError(field, message) {
		field = validationFieldFor(field);

		if (!isFormField(field)) {
			return;
		}

		field.setCustomValidity(String(message || ''));
		field.dataset.pairValidationExternalError = '1';
		renderFieldError(field, String(message || field.validationMessage));
	}

	/**
	 * Applies field errors returned by the server.
	 * @param {HTMLFormElement|null} form Owning form.
	 * @param {Object<string, string|string[]>} errors Errors keyed by field name.
	 * @param {Object} options Focus options.
	 * @returns {boolean} False when at least one error was applied.
	 */
	function setServerErrors(form, errors, options = {}) {
		if (!form || !errors || 'object' !== typeof errors) {
			return true;
		}

		bindForm(form);
		const invalidFields = [];

		Object.entries(errors).forEach(([name, value]) => {
			const field = namedField(form, name);
			const message = Array.isArray(value) ? value.filter(Boolean).join(' ') : String(value || '');

			if (field && message) {
				setFieldError(field, message);
				invalidFields.push(field);
			}
		});

		if (!invalidFields.length) {
			return true;
		}

		form.dataset.pairValidationSubmitted = '1';
		updateSummary(form, invalidFields);
		announceErrors(form, invalidFields.length);

		if (options.focus !== false) {
			focusValidationTarget(form, invalidFields[0]);
		}

		form.dispatchEvent(new CustomEvent('pair:validation:form-invalid', {
			bubbles: true,
			detail: {fields: invalidFields, source: 'server'}
		}));

		return false;
	}

	/**
	 * Validates and renders one field using HTML ValidityState.
	 * @param {HTMLElement|null} field Field to validate.
	 * @param {Object} options Rendering options.
	 * @returns {boolean} True when valid.
	 */
	function validateField(field, options = {}) {
		field = validationFieldFor(field);

		if (!isFormField(field) || field.disabled || field.dataset.pairValidationIgnore === 'true') {
			return true;
		}

		if (field.dataset.pairValidationPreset) {
			syncPresetValidity(field, false);
		}

		if (field.validity.valid) {
			if (options.show !== false) {
				clearField(field);
			}
			return true;
		}

		if (options.show !== false) {
			renderFieldError(field, validationMessageFor(field));
		}

		return false;
	}

	/**
	 * Validates every constraint field and optionally focuses the recovery target.
	 * @param {HTMLFormElement|null} form Form to validate.
	 * @param {Object} options Validation options.
	 * @returns {boolean} True when valid.
	 */
	function validateForm(form, options = {}) {
		if (!form) {
			return true;
		}

		bindForm(form);
		form.dataset.pairValidationSubmitted = '1';
		const invalidFields = formFields(form).filter((field) => !validateField(field, {show: true}));

		updateSummary(form, invalidFields);

		if (!invalidFields.length) {
			announceErrors(form, 0);
			form.dispatchEvent(new CustomEvent('pair:validation:form-valid', {
				bubbles: true,
				detail: {fields: []}
			}));
			return true;
		}

		announceErrors(form, invalidFields.length);

		if (options.focus !== false) {
			focusValidationTarget(form, invalidFields[0]);
		}

		form.dispatchEvent(new CustomEvent('pair:validation:form-invalid', {
			bubbles: true,
			detail: {fields: invalidFields, source: 'client'}
		}));

		return false;
	}

	/**
	 * Resolves an error message from field overrides, ValidityState, and locale defaults.
	 * @param {HTMLElement} field Invalid field.
	 * @returns {string} Error message.
	 */
	function validationMessageFor(field) {
		const rule = validityRule(field);
		const context = validationMessageContext(field);
		const specificMessage = field.dataset['pairValidationMessage' + upperFirst(rule)];

		if (specificMessage) {
			return interpolateMessage(specificMessage, context);
		}

		if ('custom' === rule && field.dataset.pairValidationMessage) {
			return interpolateMessage(field.dataset.pairValidationMessage, context);
		}

		const form = field.form;
		const formMessage = form ? form.dataset['pairValidationMessage' + upperFirst(rule)] : '';
		if (formMessage) {
			return interpolateMessage(formMessage, context);
		}

		const typeRule = 'type' === rule && ['email', 'url'].includes(String(field.type || '').toLowerCase())
			? String(field.type).toLowerCase()
			: rule;
		const configuredMessage = formValidationOptions.messages[typeRule];
		if (configuredMessage) {
			return interpolateMessage(resolveMessageValue(configuredMessage, context), context);
		}

		const localeMessages = FORM_MESSAGES[validationLocale(form)] || FORM_MESSAGES.en;
		const fallback = localeMessages[typeRule] || localeMessages[rule];

		if (fallback) {
			return interpolateMessage(fallback, context);
		}

		return field.validationMessage || localeMessages.custom.replace('{label}', context.label);
	}

	/**
	 * Adds built-in adapters without requiring their JavaScript libraries.
	 * @returns {void}
	 */
	function registerDefaultAdapters() {
		registerAdapter('nice-select2', {
			matches: (field) => field.matches('select') && Boolean(
				field.nextElementSibling && field.nextElementSibling.matches('.nice-select')
			),
			resolve: (field) => {
				const widget = field.nextElementSibling;

				return {anchor: widget, focus: widget, visual: widget};
			}
		});
		registerAdapter('select2', {
			matches: (field) => field.matches('select') && Boolean(
				field.nextElementSibling && field.nextElementSibling.matches('.select2-container')
			),
			resolve: (field) => {
				const container = field.nextElementSibling;
				const selection = container.querySelector('.select2-selection') || container;
				const focus = container.querySelector('[role="combobox"]') || selection;

				return {anchor: container, focus: focus, visual: selection};
			}
		});
	}

	/**
	 * Adds one token to a space-separated ARIA relationship.
	 * @param {HTMLElement} element Element to update.
	 * @param {string} attribute Attribute name.
	 * @param {string} token ID token.
	 * @returns {void}
	 */
	function addAttributeToken(element, attribute, token) {
		const tokens = new Set(String(element.getAttribute(attribute) || '').split(/\s+/).filter(Boolean));
		tokens.add(token);
		element.setAttribute(attribute, Array.from(tokens).join(' '));
	}

	/**
	 * Announces an aggregate validation result without repeating every inline error.
	 * @param {HTMLFormElement} form Validated form.
	 * @param {number} count Invalid field count.
	 * @returns {void}
	 */
	function announceErrors(form, count) {
		const region = liveRegion(form);

		if (count < 1) {
			region.textContent = '';
			return;
		}

		region.textContent = summaryMessage(form, count);
	}

	/**
	 * Announces one newly discovered field error after focus leaves the control.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {HTMLElement} field Invalid field.
	 * @returns {void}
	 */
	function announceFieldError(form, field) {
		const region = liveRegion(form);
		const message = fieldLabel(field) + ': ' + validationMessageFor(field);
		const wasSubmitted = form.dataset.pairValidationSubmitted === '1';

		region.textContent = '';
		window.setTimeout(() => {
			if (
				form.contains(region)
				&& (wasSubmitted || form.dataset.pairValidationSubmitted !== '1')
			) {
				region.textContent = message;
			}
		}, 0);
	}

	/**
	 * Resolves the configured singular or plural summary message.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {number} count Invalid field count.
	 * @returns {string} Localized summary message.
	 */
	function summaryMessage(form, count) {
		const rule = 1 === count ? 'summaryOne' : 'summaryMany';
		const context = {count: count};
		const formMessage = form.dataset['pairValidationMessage' + upperFirst(rule)];
		const configuredMessage = formValidationOptions.messages[rule];
		const messages = FORM_MESSAGES[validationLocale(form)] || FORM_MESSAGES.en;
		const template = formMessage
			|| (configuredMessage ? resolveMessageValue(configuredMessage, context) : messages[rule]);

		return interpolateMessage(template, context);
	}

	/**
	 * Clears an external custom error before running normal field validation again.
	 * @param {HTMLElement} field Field to update.
	 * @returns {void}
	 */
	function clearExternalError(field) {
		if (field.dataset.pairValidationExternalError !== '1') {
			return;
		}

		field.setCustomValidity('');
		delete field.dataset.pairValidationExternalError;

		if (field.dataset.pairValidationPreset) {
			syncPresetValidity(field, false);
		}
	}

	/**
	 * Clears an optional validation summary.
	 * @param {HTMLFormElement} form Form containing the summary.
	 * @returns {void}
	 */
	function clearSummary(form) {
		const summary = form.querySelector('[data-pair-validation-summary-region]');

		if (!summary) {
			return;
		}

		if (summary.dataset.pairValidationGenerated === '1') {
			summary.remove();
			return;
		}

		summary.replaceChildren();
		summary.hidden = true;
	}

	/**
	 * Ensures a field has a stable DOM ID for labels, errors, and summary links.
	 * @param {HTMLElement} field Field to identify.
	 * @returns {string} Field ID.
	 */
	function ensureFieldId(field) {
		if (field.id) {
			return field.id;
		}

		const base = String(field.name || 'field')
			.replace(/\[\]$/, '')
			.replace(/[^a-zA-Z0-9_-]+/g, '-')
			.replace(/^-+|-+$/g, '') || 'field';
		let candidate = base;

		while (document.getElementById(candidate) && document.getElementById(candidate) !== field) {
			formFieldSequence += 1;
			candidate = base + '-' + formFieldSequence;
		}

		field.id = candidate;

		const formOwnsBaseId = field.form && Array.from(field.form.querySelectorAll('[id]')).some((element) => {
			return element.id === base && element !== field;
		});

		if (candidate !== base && field.form && !formOwnsBaseId) {
			Array.from(field.form.querySelectorAll('label[for]')).forEach((label) => {
				if (label.htmlFor === base) {
					label.htmlFor = candidate;
				}
			});
		}

		return candidate;
	}

	/**
	 * Returns the stable inline error ID for a field.
	 * @param {HTMLElement} field Field to identify.
	 * @returns {string} Error element ID.
	 */
	function errorIdFor(field) {
		return ensureFieldId(field) + '-pair-error';
	}

	/**
	 * Moves focus to the configured recovery target and keeps it unobscured.
	 * @param {HTMLFormElement} form Invalid form.
	 * @param {HTMLElement} firstInvalid First invalid field.
	 * @returns {void}
	 */
	function focusValidationTarget(form, firstInvalid) {
		const requestedTarget = form.dataset.pairValidationFocus || formValidationOptions.focusTarget;
		const summary = form.querySelector('[data-pair-validation-summary-region]:not([hidden])');

		if ('none' === requestedTarget) {
			return;
		}

		if ('summary' === requestedTarget && summary) {
			summary.focus({preventScroll: true});
			scrollIntoViewIfNeeded(summary);
			return;
		}

		const ui = fieldUi(firstInvalid);
		ui.focus.focus({preventScroll: true});
		scrollIntoViewIfNeeded(ui.anchor);
	}

	/**
	 * Replaces message placeholders with escaped text values.
	 * @param {string} message Message template.
	 * @param {Object} context Placeholder values.
	 * @returns {string} Resolved message.
	 */
	function interpolateMessage(message, context) {
		return String(message || '').replace(/\{([a-zA-Z0-9]+)\}/g, (match, key) => {
			return Object.prototype.hasOwnProperty.call(context, key) ? String(context[key]) : match;
		});
	}

	/**
	 * Returns or creates the form-level polite live region.
	 * @param {HTMLFormElement} form Owning form.
	 * @returns {HTMLElement} Live region.
	 */
	function liveRegion(form) {
		let region = form.querySelector('[data-pair-validation-live]');

		if (region) {
			return region;
		}

		region = document.createElement('div');
		region.className = 'pair-visually-hidden';
		region.dataset.pairValidationLive = '1';
		region.setAttribute('aria-atomic', 'true');
		region.setAttribute('aria-live', 'polite');
		form.prepend(region);

		return region;
	}

	/**
	 * Finds one concrete form field by submitted name.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {string} name Submitted field name.
	 * @returns {HTMLElement|null} Matching field.
	 */
	function namedField(form, name) {
		const entry = form.elements.namedItem(name);

		if (isFormField(entry)) {
			return entry;
		}

		if (entry && typeof entry.length === 'number') {
			return Array.from(entry).find((field) => isFormField(field)) || null;
		}

		return null;
	}

	/**
	 * Rebuilds a visible summary while the user corrects a submitted form.
	 * @param {HTMLFormElement} form Owning form.
	 * @returns {void}
	 */
	function refreshSummaryAfterFieldChange(form) {
		if (form.dataset.pairValidationSubmitted !== '1') {
			return;
		}

		const invalidFields = formFields(form).filter((field) => !field.validity.valid);
		updateSummary(form, invalidFields);
	}

	/**
	 * Removes one token from a space-separated ARIA relationship.
	 * @param {HTMLElement} element Element to update.
	 * @param {string} attribute Attribute name.
	 * @param {string} token ID token.
	 * @returns {void}
	 */
	function removeAttributeToken(element, attribute, token) {
		const tokens = String(element.getAttribute(attribute) || '').split(/\s+/).filter((value) => {
			return value && value !== token;
		});

		if (tokens.length) {
			element.setAttribute(attribute, tokens.join(' '));
		} else {
			element.removeAttribute(attribute);
		}
	}

	/**
	 * Removes the generated live region from a form.
	 * @param {HTMLFormElement} form Owning form.
	 * @returns {void}
	 */
	function removeLiveRegion(form) {
		const region = form.querySelector('[data-pair-validation-live]');

		if (region) {
			region.remove();
		}
	}

	/**
	 * Renders one inline error after the visible control or widget.
	 * @param {HTMLElement} field Invalid native field.
	 * @param {string} message Recovery message.
	 * @returns {void}
	 */
	function renderFieldError(field, message) {
		field = validationFieldFor(field);

		if (!isFormField(field)) {
			return;
		}

		const validityFields = validityFieldsFor(field);
		const ui = fieldUi(field);
		const errorId = errorIdFor(field);
		let error = document.getElementById(errorId);
		const wasInvalid = validityFields.some((candidate) => {
			return candidate.dataset.pairValidationInvalid === '1';
		});

		if (!error) {
			error = document.createElement('div');
			error.id = errorId;
			error.className = 'pair-validation-error';
			error.dataset.pairValidationErrorFor = ensureFieldId(field);
			ui.anchor.insertAdjacentElement('afterend', error);
		}

		error.textContent = message;
		ui.visual.classList.add('is-invalid', 'pair-validation-invalid');
		validityFields.forEach((candidate) => {
			candidate.classList.add('is-invalid');
			candidate.setAttribute('aria-invalid', 'true');
			addAttributeToken(candidate, 'aria-describedby', errorId);
			candidate.dataset.pairValidationInvalid = '1';
		});

		if (ui.focus !== field) {
			ui.focus.setAttribute('aria-invalid', 'true');
			addAttributeToken(ui.focus, 'aria-describedby', errorId);
		}

		if (!wasInvalid) {
			field.dispatchEvent(new CustomEvent('pair:validation:field-invalid', {
				bubbles: true,
				detail: {field: field, message: message}
			}));
		}
	}

	/**
	 * Resolves string or callback message configuration.
	 * @param {string|Function} message Configured message.
	 * @param {Object} context Field context.
	 * @returns {string} Resolved value.
	 */
	function resolveMessageValue(message, context) {
		return typeof message === 'function' ? String(message(context)) : String(message);
	}

	/**
	 * Scrolls only when the target is outside the current viewport.
	 * @param {HTMLElement} target Element to reveal.
	 * @returns {void}
	 */
	function scrollIntoViewIfNeeded(target) {
		const rect = target.getBoundingClientRect();

		if (rect.top < 0 || rect.bottom > window.innerHeight) {
			target.scrollIntoView({behavior: 'auto', block: 'center'});
		}
	}

	/**
	 * Returns whether a visible error summary is enabled for a form.
	 * @param {HTMLFormElement} form Form to inspect.
	 * @returns {boolean} True when enabled.
	 */
	function summaryEnabled(form) {
		if (form.hasAttribute('data-pair-validation-summary')) {
			return !['0', 'false', 'off'].includes(
				String(form.getAttribute('data-pair-validation-summary') || '').toLowerCase()
			);
		}

		return Boolean(formValidationOptions.summary);
	}

	/**
	 * Returns a string with its first character uppercased.
	 * @param {string} value Input value.
	 * @returns {string} Capitalized value.
	 */
	function upperFirst(value) {
		const stringValue = String(value || '');

		return stringValue.charAt(0).toUpperCase() + stringValue.slice(1);
	}

	/**
	 * Builds or clears an optional linked error summary.
	 * @param {HTMLFormElement} form Owning form.
	 * @param {HTMLElement[]} invalidFields Invalid fields.
	 * @returns {void}
	 */
	function updateSummary(form, invalidFields) {
		if (!summaryEnabled(form) || !invalidFields.length) {
			clearSummary(form);
			return;
		}

		let summary = form.querySelector('[data-pair-validation-summary-region]');
		if (!summary) {
			summary = document.createElement('section');
			summary.className = 'pair-validation-summary';
			summary.dataset.pairValidationGenerated = '1';
			summary.dataset.pairValidationSummaryRegion = '1';
			form.prepend(summary);
		}

		const heading = document.createElement('h2');
		const list = document.createElement('ul');

		heading.className = 'pair-validation-summary__title';
		heading.textContent = summaryMessage(form, invalidFields.length);
		invalidFields.forEach((field) => {
			const item = document.createElement('li');
			const link = document.createElement('a');
			const message = validationMessageFor(field);

			link.href = '#' + ensureFieldId(field);
			link.textContent = fieldLabel(field) + ': ' + message;
			link.addEventListener('click', (event) => {
				event.preventDefault();
				focusValidationTarget(form, field);
			});
			item.append(link);
			list.append(item);
		});

		summary.replaceChildren(heading, list);
		summary.hidden = false;
		summary.tabIndex = -1;
	}

	/**
	 * Returns a concise human label for error copy and summaries.
	 * @param {HTMLElement} field Field to describe.
	 * @returns {string} Field label.
	 */
	function fieldLabel(field) {
		const fieldId = ensureFieldId(field);
		const form = field.form;
		const label = form
			? Array.from(form.querySelectorAll('label[for]')).find((candidate) => candidate.htmlFor === fieldId)
			: null;

		return String(
			(label && label.textContent)
			|| field.getAttribute('aria-label')
			|| field.name
			|| fieldId
		).trim();
	}

	/**
	 * Returns the locale used by one form.
	 * @param {HTMLFormElement|null} form Form to inspect.
	 * @returns {string} Supported locale key.
	 */
	function validationLocale(form) {
		const requested = String(
			(form && form.dataset.pairValidationLocale)
			|| formValidationOptions.locale
			|| document.documentElement.lang
			|| 'en'
		).toLowerCase().split('-')[0];

		return Object.prototype.hasOwnProperty.call(FORM_MESSAGES, requested) ? requested : 'en';
	}

	/**
	 * Returns interpolation values for a field error.
	 * @param {HTMLElement} field Field to inspect.
	 * @returns {Object} Message context.
	 */
	function validationMessageContext(field) {
		return {
			label: fieldLabel(field),
			max: validationConstraintValue(field, 'max'),
			maxLength: field.getAttribute('maxlength') || '',
			min: validationConstraintValue(field, 'min'),
			minLength: field.getAttribute('minlength') || '',
			name: field.name || '',
			type: field.type || ''
		};
	}

	/**
	 * Formats date constraints for user-facing validation messages while preserving raw values for other controls.
	 * @param {HTMLElement} field Field whose constraint is being rendered.
	 * @param {'min'|'max'} attribute Constraint attribute name.
	 * @returns {string} Localized constraint value.
	 */
	function validationConstraintValue(field, attribute) {
		const value = field.getAttribute(attribute) || '';

		if ('date' !== String(field.type || '').toLowerCase()) {
			return value;
		}

		const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

		if (!match) {
			return value;
		}

		const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

		// Do not silently normalize invalid constraints, such as February 31.
		if (
			date.getUTCFullYear() !== Number(match[1])
			|| date.getUTCMonth() !== Number(match[2]) - 1
			|| date.getUTCDate() !== Number(match[3])
		) {
			return value;
		}

		return new Intl.DateTimeFormat(validationLocale(field.form), {
			day: '2-digit',
			month: '2-digit',
			timeZone: 'UTC',
			year: 'numeric'
		}).format(date);
	}

	/**
	 * Maps the first failing ValidityState property to a public message rule.
	 * @param {HTMLElement} field Invalid field.
	 * @returns {string} Message rule.
	 */
	function validityRule(field) {
		const match = VALIDITY_RULES.find(([property]) => Boolean(field.validity[property]));

		return match ? match[1] : 'custom';
	}

	/**
	 * Binds validation presets inside a root node.
	 * @param {ParentNode|Element} root Root node to inspect.
	 * @returns {void}
	 */
	function bindDocument(root = document) {
		matchingFields(root, '[data-pair-validation-preset]').forEach((field) => bindPresetField(field));
		matchingFields(root, FORM_SELECTOR).forEach((form) => bindForm(form));
	}

	/**
	 * Binds validation inside a progressively replaced application region.
	 * @param {CustomEvent} event Region replacement event.
	 * @returns {void}
	 */
	function bindReplacementRegion(event) {
		const detailRoot = event.detail && event.detail.root;
		const root = detailRoot && typeof detailRoot.querySelectorAll === 'function'
			? detailRoot
			: event.target;

		bindDocument(root);
	}

	/**
	 * Boots validation helpers on the current document.
	 * @returns {void}
	 */
	function bootPairValidation() {
		registerDefaultAdapters();
		bindDocument(document);
		document.addEventListener('pair:region:replaced', bindReplacementRegion);
	}

	/**
	 * Binds BIC/SWIFT normalization and validation to a field.
	 * @param {HTMLInputElement|null} field BIC/SWIFT field.
	 * @returns {void}
	 */
	function bindBicField(field) {
		bindPresetField(field, PRESET_BIC);
	}

	/**
	 * Binds E.164 phone normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Phone field.
	 * @returns {void}
	 */
	function bindE164PhoneField(field) {
		bindPresetField(field, PRESET_E164_PHONE);
	}

	/**
	 * Binds EAN-13 normalization and validation to a field.
	 * @param {HTMLInputElement|null} field EAN-13 field.
	 * @returns {void}
	 */
	function bindEan13Field(field) {
		bindPresetField(field, PRESET_EAN13);
	}

	/**
	 * Binds email normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Email field.
	 * @returns {void}
	 */
	function bindEmailAddressField(field) {
		bindPresetField(field, PRESET_EMAIL);
	}

	/**
	 * Binds hexadecimal color normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Hexadecimal color field.
	 * @returns {void}
	 */
	function bindHexColorField(field) {
		bindPresetField(field, PRESET_HEX_COLOR);
	}

	/**
	 * Binds Italian fiscal-code normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Fiscal-code field.
	 * @returns {void}
	 */
	function bindItalianFiscalCodeField(field) {
		bindPresetField(field, PRESET_ITALIAN_FISCAL_CODE);
	}

	/**
	 * Binds Italian personal fiscal-code normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Fiscal-code field.
	 * @returns {void}
	 */
	function bindItalianPersonalFiscalCodeField(field) {
		bindPresetField(field, PRESET_ITALIAN_PERSONAL_FISCAL_CODE);
	}

	/**
	 * Binds IBAN normalization and validation to a field.
	 * @param {HTMLInputElement|null} field IBAN field.
	 * @returns {void}
	 */
	function bindIbanField(field) {
		bindPresetField(field, PRESET_IBAN);
	}

	/**
	 * Binds IP address normalization and validation to a field.
	 * @param {HTMLInputElement|null} field IP address field.
	 * @returns {void}
	 */
	function bindIpAddressField(field) {
		bindPresetField(field, PRESET_IP_ADDRESS);
	}

	/**
	 * Binds IPv4 address normalization and validation to a field.
	 * @param {HTMLInputElement|null} field IPv4 address field.
	 * @returns {void}
	 */
	function bindIpv4AddressField(field) {
		bindPresetField(field, PRESET_IPV4_ADDRESS);
	}

	/**
	 * Binds IPv6 address normalization and validation to a field.
	 * @param {HTMLInputElement|null} field IPv6 address field.
	 * @returns {void}
	 */
	function bindIpv6AddressField(field) {
		bindPresetField(field, PRESET_IPV6_ADDRESS);
	}

	/**
	 * Binds MAC address normalization and validation to a field.
	 * @param {HTMLInputElement|null} field MAC address field.
	 * @returns {void}
	 */
	function bindMacAddressField(field) {
		bindPresetField(field, PRESET_MAC_ADDRESS);
	}

	/**
	 * Binds a validation preset to a field.
	 * @param {HTMLInputElement|null} field Field to bind.
	 * @param {string|null} forcedPreset Preset to force, or null to read from data attributes.
	 * @returns {void}
	 */
	function bindPresetField(field, forcedPreset = null) {
		if (!field) {
			return;
		}

		const preset = canonicalPreset(forcedPreset || field.dataset.pairValidationPreset || '');
		const definition = presetDefinition(preset);

		if (!definition) {
			return;
		}

		field.dataset.pairValidationPreset = preset;
		bindPresetForm(field.form);

		if (field.dataset.pairValidationReady === preset) {
			syncPresetValidity(field, false);
			return;
		}

		field.dataset.pairValidationReady = preset;
		normalizeInputField(field, definition.normalizer);
		syncPresetValidity(field, false);

		field.addEventListener('input', () => {
			field.dataset.pairValidationTouched = '1';
			normalizeInputField(field, definition.normalizer);
			syncPresetValidity(field, true);
		});

		field.addEventListener('change', () => {
			field.dataset.pairValidationTouched = '1';
			normalizeInputField(field, definition.changeNormalizer || definition.normalizer);
			syncPresetValidity(field, true);
		});

		field.addEventListener('blur', () => {
			field.dataset.pairValidationTouched = '1';
			syncPresetValidity(field, true);
		});
	}

	/**
	 * Binds Italian SdI recipient-code normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Recipient-code field.
	 * @returns {void}
	 */
	function bindItalianSdiRecipientCodeField(field) {
		bindPresetField(field, PRESET_ITALIAN_SDI_RECIPIENT_CODE);
	}

	/**
	 * Binds Italian VAT-number normalization and validation to a field.
	 * @param {HTMLInputElement|null} field VAT-number field.
	 * @returns {void}
	 */
	function bindItalianVatNumberField(field) {
		bindPresetField(field, PRESET_ITALIAN_VAT_NUMBER);
	}

	/**
	 * Binds slug normalization and validation to a field.
	 * @param {HTMLInputElement|null} field Slug field.
	 * @returns {void}
	 */
	function bindSlugField(field) {
		bindPresetField(field, PRESET_SLUG);
	}

	/**
	 * Binds URL normalization and validation to a field.
	 * @param {HTMLInputElement|null} field URL field.
	 * @returns {void}
	 */
	function bindUrlField(field) {
		bindPresetField(field, PRESET_URL);
	}

	/**
	 * Binds UUID normalization and validation to a field.
	 * @param {HTMLInputElement|null} field UUID field.
	 * @returns {void}
	 */
	function bindUuidField(field) {
		bindPresetField(field, PRESET_UUID);
	}

	/**
	 * Binds one submit listener per form to refresh validation state.
	 * @param {HTMLFormElement|null} form Form that owns a preset field.
	 * @returns {void}
	 */
	function bindPresetForm(form) {
		if (!form || form.dataset.pairValidationFormReady === '1') {
			return;
		}

		form.dataset.pairValidationFormReady = '1';
		form.addEventListener('submit', () => {
			validatePresetFields(form, true);
		});
	}

	/**
	 * Normalizes a preset name or alias to its canonical form.
	 * @param {string} preset Preset name or alias.
	 * @returns {string|null} Canonical preset name or null.
	 */
	function canonicalPreset(preset) {
		const normalized = String(preset || '').trim().toLowerCase();

		return PRESET_ALIASES[normalized] || null;
	}

	/**
	 * Returns true when a value matches the selected preset.
	 * @param {string} preset Preset name or alias.
	 * @param {string} value Value to validate.
	 * @param {boolean} required True when an empty value must be rejected.
	 * @returns {boolean} True when valid.
	 */
	function isValid(preset, value, required = false) {
		const definition = presetDefinition(canonicalPreset(preset));

		if (!definition) {
			return false;
		}

		const normalizer = definition.validationNormalizer || definition.normalizer;
		const normalized = normalizer(value);

		if (normalized === '') {
			return !required;
		}

		if (definition.minLength && normalized.length < definition.minLength) {
			return false;
		}

		if (definition.maxLength && normalized.length > definition.maxLength) {
			return false;
		}

		return definition.validator(normalized);
	}

	/**
	 * Completes UPC-A twelve-digit values as EAN-13 by adding the leading zero.
	 * @param {string} value Free value.
	 * @returns {string} Normalized EAN-13 value.
	 */
	function completeEan13(value) {
		const ean13 = normalizeEan13(value);

		return ean13.length === 12 ? '0' + ean13 : ean13;
	}

	/**
	 * Decodes omocodia characters in numeric fiscal-code positions.
	 * @param {string} fiscalCode Normalized or free fiscal code.
	 * @returns {string} Fiscal code with numeric positions restored.
	 */
	function decodeItalianFiscalCodeOmocodia(fiscalCode) {
		const characters = normalizeItalianFiscalCode(fiscalCode).split('');

		FISCAL_CODE_OMOCODIA_POSITIONS.forEach((position) => {
			if (characters[position]) {
				characters[position] = FISCAL_CODE_OMOCODIA_MAP[characters[position]] || characters[position];
			}
		});

		return characters.join('');
	}

	/**
	 * Returns the expected EAN-13 control digit for the first twelve digits.
	 * @param {string} ean13 Normalized EAN-13 value.
	 * @returns {number|null} Expected control digit.
	 */
	function ean13ControlDigit(ean13) {
		const normalized = completeEan13(ean13);

		if (normalized.length < 12) {
			return null;
		}

		let sum = 0;
		for (let index = 0; index < 12; index += 1) {
			const digit = Number.parseInt(normalized[index], 10);

			// EAN-13 multiplies even human positions by 3; zero-based indexes make them odd.
			sum += index % 2 === 1 ? digit * 3 : digit;
		}

		return (10 - (sum % 10)) % 10;
	}

	/**
	 * Returns true when a BIC/SWIFT code has valid ISO 9362 syntax.
	 * @param {string} bic BIC/SWIFT code.
	 * @returns {boolean} True when valid.
	 */
	function isValidBic(bic) {
		return BIC_PATTERN.test(normalizeBic(bic));
	}

	/**
	 * Returns true when a phone number follows E.164 syntax.
	 * @param {string} phone Phone number.
	 * @returns {boolean} True when valid.
	 */
	function isValidE164Phone(phone) {
		return E164_PHONE_PATTERN.test(normalizeE164Phone(phone));
	}

	/**
	 * Returns the expected control character for a personal fiscal code.
	 * @param {string} fiscalCode Full or partial fiscal code.
	 * @returns {string} Expected control character.
	 */
	function italianFiscalCodeControlCharacter(fiscalCode) {
		const normalized = normalizeItalianFiscalCode(fiscalCode);

		if (normalized.length < 15) {
			return '';
		}

		let sum = 0;

		for (let index = 0; index < 15; index += 1) {
			const character = normalized[index];

			// Odd fiscal-code positions use the official weighted lookup table.
			if (index % 2 === 0) {
				if (!Object.prototype.hasOwnProperty.call(FISCAL_CODE_ODD_VALUES, character)) {
					return '';
				}

				sum += FISCAL_CODE_ODD_VALUES[character];
				continue;
			}

			sum += /\d/.test(character) ? Number.parseInt(character, 10) : character.charCodeAt(0) - 65;
		}

		return String.fromCharCode(65 + (sum % 26));
	}

	/**
	 * Returns true when an EAN-13 value has valid length and checksum.
	 * @param {string} ean13 EAN-13 value.
	 * @returns {boolean} True when valid.
	 */
	function isValidEan13(ean13) {
		const normalized = completeEan13(ean13);

		if (!/^[0-9]{13}$/.test(normalized)) {
			return false;
		}

		return ean13ControlDigit(normalized) === Number.parseInt(normalized[12], 10);
	}

	/**
	 * Returns true when an email address has common RFC-style syntax.
	 * @param {string} email Email address.
	 * @returns {boolean} True when valid.
	 */
	function isValidEmail(email) {
		const normalized = normalizeEmail(email);

		if (!normalized || normalized.length > 254 || /[^\x21-\x7E]/.test(normalized)) {
			return false;
		}

		const parts = normalized.split('@');
		if (parts.length !== 2) {
			return false;
		}

		const [localPart, domain] = parts;
		if (
			localPart.length < 1
			|| localPart.length > 64
			|| localPart.startsWith('.')
			|| localPart.endsWith('.')
			|| localPart.includes('..')
			|| !/^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+$/.test(localPart)
		) {
			return false;
		}

		const labels = domain.split('.');
		if (domain.length > 253 || labels.length < 2) {
			return false;
		}

		// Match PHP FILTER_VALIDATE_EMAIL by accepting ASCII DNS labels only.
		return labels.every((label) => /^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/.test(label))
			&& /^[A-Za-z]{1,63}$/.test(labels[labels.length - 1]);
	}

	/**
	 * Returns true when a hexadecimal color uses #RGB or #RRGGBB.
	 * @param {string} color Hexadecimal color.
	 * @returns {boolean} True when valid.
	 */
	function isValidHexColor(color) {
		return HEX_COLOR_PATTERN.test(normalizeHexColor(color));
	}

	/**
	 * Returns true when a fiscal code is either a valid numeric or personal Italian code.
	 * @param {string} fiscalCode Fiscal code.
	 * @returns {boolean} True when valid.
	 */
	function isValidItalianFiscalCode(fiscalCode) {
		const normalized = normalizeItalianFiscalCode(fiscalCode);

		if (NUMERIC_FISCAL_CODE_PATTERN.test(normalized)) {
			return isValidItalianNumericFiscalCode(normalized);
		}

		return isValidItalianPersonalFiscalCode(normalized);
	}

	/**
	 * Returns true when an IBAN has valid syntax and MOD-97 checksum.
	 * @param {string} iban IBAN value.
	 * @returns {boolean} True when valid.
	 */
	function isValidIban(iban) {
		const normalized = normalizeIban(iban);

		if (normalized.length < 15 || normalized.length > 34 || !/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/.test(normalized)) {
			return false;
		}

		return mod97(normalized.slice(4) + normalized.slice(0, 4)) === 1;
	}

	/**
	 * Returns true when a value is a valid IPv4 or IPv6 address.
	 * @param {string} ipAddress IP address.
	 * @returns {boolean} True when valid.
	 */
	function isValidIpAddress(ipAddress) {
		return isValidIpv4Address(ipAddress) || isValidIpv6Address(ipAddress);
	}

	/**
	 * Returns true when a value is a valid IPv4 address.
	 * @param {string} ipAddress IPv4 address.
	 * @returns {boolean} True when valid.
	 */
	function isValidIpv4Address(ipAddress) {
		const normalized = normalizeIpAddress(ipAddress);
		const parts = normalized.split('.');

		return parts.length === 4 && parts.every((part) => {
			if (!/^[0-9]{1,3}$/.test(part)) {
				return false;
			}

			const value = Number.parseInt(part, 10);

			return value >= 0 && value <= 255 && String(value) === part;
		});
	}

	/**
	 * Returns true when a value is a valid IPv6 address, including IPv4-mapped forms.
	 * @param {string} ipAddress IPv6 address.
	 * @returns {boolean} True when valid.
	 */
	function isValidIpv6Address(ipAddress) {
		const normalized = normalizeIpAddress(ipAddress);

		if (!normalized) {
			return false;
		}

		let address = normalized;

		if (address.includes('.')) {
			const lastColonIndex = address.lastIndexOf(':');
			const ipv4Suffix = address.slice(lastColonIndex + 1);

			if (-1 === lastColonIndex || !isValidIpv4Address(ipv4Suffix)) {
				return false;
			}

			// An embedded IPv4 suffix represents the final two IPv6 segments.
			address = `${address.slice(0, lastColonIndex)}:0:0`;
		}

		if (address.includes(':::')) {
			return false;
		}

		const doubleColonParts = address.split('::');
		if (doubleColonParts.length > 2) {
			return false;
		}

		const left = doubleColonParts[0] ? doubleColonParts[0].split(':') : [];
		const right = doubleColonParts.length === 2 && doubleColonParts[1] ? doubleColonParts[1].split(':') : [];
		const segments = left.concat(right);
		const hasCompression = doubleColonParts.length === 2;

		if (!segments.every((segment) => /^[0-9A-Fa-f]{1,4}$/.test(segment))) {
			return false;
		}

		return hasCompression ? segments.length < 8 : segments.length === 8;
	}

	/**
	 * Returns true when an eleven-digit Italian numeric tax code has a valid checksum.
	 * @param {string} fiscalCode Numeric code.
	 * @returns {boolean} True when valid.
	 */
	function isValidItalianNumericFiscalCode(fiscalCode) {
		const normalized = normalizeItalianVatNumber(fiscalCode);

		if (!NUMERIC_FISCAL_CODE_PATTERN.test(normalized)) {
			return false;
		}

		let sum = 0;
		for (let index = 0; index < 10; index += 1) {
			let digit = Number.parseInt(normalized[index], 10);

			// In eleven-digit Italian codes, zero-based odd indexes are doubled and reduced.
			if (index % 2 === 1) {
				digit *= 2;
				if (digit > 9) {
					digit -= 9;
				}
			}

			sum += digit;
		}

		return ((10 - (sum % 10)) % 10) === Number.parseInt(normalized[10], 10);
	}

	/**
	 * Returns true when a personal fiscal code has valid syntax, date, and checksum.
	 * @param {string} fiscalCode Fiscal code.
	 * @returns {boolean} True when valid.
	 */
	function isValidItalianPersonalFiscalCode(fiscalCode) {
		const normalized = normalizeItalianFiscalCode(fiscalCode);

		if (!PERSONAL_FISCAL_CODE_PATTERN.test(normalized)) {
			return false;
		}

		if (!italianPersonalFiscalCodeBirthData(normalized).dateOfBirth) {
			return false;
		}

		return italianFiscalCodeControlCharacter(normalized) === normalized[15];
	}

	/**
	 * Returns true when a SdI recipient code has seven alphanumeric characters.
	 * @param {string} recipientCode Recipient code.
	 * @returns {boolean} True when valid.
	 */
	function isValidItalianSdiRecipientCode(recipientCode) {
		return /^[A-Z0-9]{7}$/.test(normalizeItalianSdiRecipientCode(recipientCode));
	}

	/**
	 * Returns true when an Italian VAT number has a valid checksum.
	 * @param {string} vatNumber VAT number.
	 * @returns {boolean} True when valid.
	 */
	function isValidItalianVatNumber(vatNumber) {
		return isValidItalianNumericFiscalCode(normalizeItalianVatNumber(vatNumber));
	}

	/**
	 * Returns true when a MAC address has six hexadecimal pairs.
	 * @param {string} macAddress MAC address.
	 * @returns {boolean} True when valid.
	 */
	function isValidMacAddress(macAddress) {
		return MAC_ADDRESS_PATTERN.test(normalizeMacAddress(macAddress));
	}

	/**
	 * Returns true when a slug uses lowercase words separated by hyphens.
	 * @param {string} slug Slug.
	 * @returns {boolean} True when valid.
	 */
	function isValidSlug(slug) {
		return SLUG_PATTERN.test(normalizeSlug(slug));
	}

	/**
	 * Returns true when a URL has a scheme and is accepted by the browser URL parser.
	 * @param {string} url URL.
	 * @returns {boolean} True when valid.
	 */
	function isValidUrl(url) {
		const normalized = normalizeUrl(url);

		if (!normalized.includes('://') || /[^\x21-\x7E]/.test(normalized)) {
			return false;
		}

		try {
			const parsedUrl = new URL(normalized);

			return Boolean(parsedUrl.protocol && parsedUrl.host);
		} catch (error) {
			return false;
		}
	}

	/**
	 * Returns true when a UUID has RFC-compatible canonical syntax.
	 * @param {string} uuid UUID.
	 * @returns {boolean} True when valid.
	 */
	function isValidUuid(uuid) {
		return UUID_PATTERN.test(normalizeUuid(uuid));
	}

	/**
	 * Returns all fields matching a selector, including the root itself when applicable.
	 * @param {ParentNode|Element} root Root node.
	 * @param {string} selector CSS selector.
	 * @returns {Element[]} Matching elements.
	 */
	function matchingFields(root, selector) {
		const scope = root || document;
		const fields = [];

		if (typeof scope.matches === 'function' && scope.matches(selector)) {
			fields.push(scope);
		}

		if (typeof scope.querySelectorAll === 'function') {
			fields.push(...scope.querySelectorAll(selector));
		}

		return fields;
	}

	/**
	 * Calculates MOD-97 over an already rearranged IBAN sequence.
	 * @param {string} value Alphanumeric value.
	 * @returns {number} MOD-97 remainder.
	 */
	function mod97(value) {
		let remainder = 0;

		String(value || '').split('').forEach((character) => {
			const digits = /[A-Z]/.test(character)
				? String(character.charCodeAt(0) - 55)
				: character;

			digits.split('').forEach((digit) => {
				remainder = (remainder * 10 + Number.parseInt(digit, 10)) % 97;
			});
		});

		return remainder;
	}

	/**
	 * Normalizes a BIC/SWIFT code to uppercase alphanumeric characters.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial BIC/SWIFT code.
	 */
	function normalizeBic(value) {
		return normalizeUpperAlphanumeric(value).slice(0, 11);
	}

	/**
	 * Normalizes a value according to the selected preset.
	 * @param {string} preset Preset name or alias.
	 * @param {string} value Value to normalize.
	 * @returns {string} Normalized value, or the original value when the preset is unknown.
	 */
	function normalize(preset, value) {
		const definition = presetDefinition(canonicalPreset(preset));

		return definition
			? (definition.changeNormalizer || definition.validationNormalizer || definition.normalizer)(value)
			: String(value || '');
	}

	/**
	 * Normalizes a phone number by keeping a leading plus sign and digits.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial E.164 phone number.
	 */
	function normalizeE164Phone(value) {
		const rawValue = String(value || '').trim();
		const prefix = rawValue.startsWith('+') ? '+' : '';

		return (prefix + normalizeDigits(rawValue)).slice(0, 16);
	}

	/**
	 * Normalizes EAN-13 to digits and at most thirteen characters.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial EAN-13 value.
	 */
	function normalizeEan13(value) {
		return normalizeDigits(value).slice(0, 13);
	}

	/**
	 * Normalizes an email address by trimming surrounding whitespace.
	 * @param {string} value Free value.
	 * @returns {string} Trimmed email address.
	 */
	function normalizeEmail(value) {
		return String(value || '').trim();
	}

	/**
	 * Normalizes a hexadecimal color to uppercase #RGB or #RRGGBB form.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial hexadecimal color.
	 */
	function normalizeHexColor(value) {
		const color = String(value || '').toUpperCase().replace(/[^0-9A-F]/g, '').slice(0, 6);

		return color.length >= 3 ? '#' + color : color;
	}

	/**
	 * Normalizes an Italian fiscal code to uppercase alphanumeric characters.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial fiscal code.
	 */
	function normalizeItalianFiscalCode(value) {
		return normalizeUpperAlphanumeric(value).slice(0, 16);
	}

	/**
	 * Normalizes an IBAN to uppercase alphanumeric characters.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial IBAN.
	 */
	function normalizeIban(value) {
		return normalizeUpperAlphanumeric(value).slice(0, 34);
	}

	/**
	 * Normalizes an IP address by trimming surrounding whitespace.
	 * @param {string} value Free value.
	 * @returns {string} Trimmed IP address.
	 */
	function normalizeIpAddress(value) {
		return String(value || '').trim();
	}

	/**
	 * Normalizes a MAC address to colon-separated uppercase pairs.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial MAC address.
	 */
	function normalizeMacAddress(value) {
		const macAddress = normalizeUpperAlphanumeric(value).slice(0, 12);

		if (macAddress.length !== 12) {
			return macAddress;
		}

		return macAddress.match(/.{1,2}/g).join(':');
	}

	/**
	 * Updates a field value while preserving the caret where possible.
	 * @param {HTMLInputElement} field Field to update.
	 * @param {Function} normalizer Normalizer to apply.
	 * @returns {void}
	 */
	function normalizeInputField(field, normalizer) {
		const originalValue = String(field.value || '');
		const originalStart = typeof field.selectionStart === 'number' ? field.selectionStart : originalValue.length;
		const normalizedValue = normalizer(originalValue);
		const normalizedStart = normalizer(originalValue.slice(0, originalStart)).length;

		if (field.value === normalizedValue) {
			return;
		}

		field.value = normalizedValue;

		if (document.activeElement === field && typeof field.setSelectionRange === 'function') {
			const caret = Math.min(normalizedStart, normalizedValue.length);
			field.setSelectionRange(caret, caret);
		}
	}

	/**
	 * Normalizes a SdI recipient code to uppercase alphanumeric characters.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial recipient code.
	 */
	function normalizeItalianSdiRecipientCode(value) {
		return normalizeUpperAlphanumeric(value).slice(0, 7);
	}

	/**
	 * Normalizes a slug to lowercase ASCII words separated by single hyphens.
	 * @param {string} value Free value.
	 * @returns {string} Normalized slug.
	 */
	function normalizeSlug(value) {
		return String(value || '')
			.trim()
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '')
			.replace(/-+/g, '-')
			.slice(0, 120);
	}

	/**
	 * Normalizes a URL by trimming surrounding whitespace.
	 * @param {string} value Free value.
	 * @returns {string} Trimmed URL.
	 */
	function normalizeUrl(value) {
		return String(value || '').trim();
	}

	/**
	 * Normalizes a UUID to lowercase canonical form.
	 * @param {string} value Free value.
	 * @returns {string} Lowercase UUID.
	 */
	function normalizeUuid(value) {
		return String(value || '').trim().toLowerCase();
	}

	/**
	 * Returns only digits from a free value.
	 * @param {string} value Free value.
	 * @returns {string} Digits.
	 */
	function normalizeDigits(value) {
		return String(value || '').replace(/\D+/g, '');
	}

	/**
	 * Returns only uppercase alphanumeric characters from a free value.
	 * @param {string} value Free value.
	 * @returns {string} Uppercase alphanumeric value.
	 */
	function normalizeUpperAlphanumeric(value) {
		return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
	}

	/**
	 * Normalizes an Italian VAT number by removing the optional IT prefix and separators.
	 * @param {string} value Free value.
	 * @returns {string} Normalized or partial VAT number.
	 */
	function normalizeItalianVatNumber(value) {
		return String(value || '')
			.toUpperCase()
			.replace(/^IT/, '')
			.replace(/\D+/g, '')
			.slice(0, 11);
	}

	/**
	 * Extracts birth data encoded in an Italian personal fiscal code.
	 * @param {string} fiscalCode Fiscal code.
	 * @returns {{dateOfBirth: string|null, sex: string|null, birthPlaceCode: string}|null} Birth data.
	 */
	function italianPersonalFiscalCodeBirthData(fiscalCode) {
		const normalized = normalizeItalianFiscalCode(fiscalCode);

		if (normalized.length !== 16) {
			return null;
		}

		const decodedFiscalCode = decodeItalianFiscalCodeOmocodia(normalized);
		const yearDigits = decodedFiscalCode.slice(6, 8);
		const dayDigits = decodedFiscalCode.slice(9, 11);
		const month = FISCAL_CODE_BIRTH_MONTHS[decodedFiscalCode[8]] || 0;
		let dateOfBirth = null;
		let sex = null;

		if (/^[0-9]{2}$/.test(yearDigits) && /^[0-9]{2}$/.test(dayDigits)) {
			const dayCode = Number.parseInt(dayDigits, 10);
			const day = dayCode > 40 ? dayCode - 40 : dayCode;
			let year = 2000 + Number.parseInt(yearDigits, 10);
			sex = dayCode > 40 ? 'f' : 'm';

			if (isRealDate(year, month, day)) {
				let birthDate = new Date(year, month - 1, day);
				if (birthDate > todayDateWithoutTime()) {
					year -= 100;
					birthDate = new Date(year, month - 1, day);
				}

				dateOfBirth = [
					String(birthDate.getFullYear()).padStart(4, '0'),
					String(birthDate.getMonth() + 1).padStart(2, '0'),
					String(birthDate.getDate()).padStart(2, '0')
				].join('-');
			}
		}

		return {
			dateOfBirth: dateOfBirth,
			sex: sex,
			birthPlaceCode: decodedFiscalCode.slice(11, 15)
		};
	}

	/**
	 * Returns a preset definition by canonical preset name.
	 * @param {string|null} preset Preset name.
	 * @returns {Object|null} Preset definition.
	 */
	function presetDefinition(preset) {
		return PRESET_DEFINITIONS[preset] || null;
	}

	/**
	 * Validates one field and updates custom validity plus visual state.
	 * @param {HTMLInputElement} field Field to validate.
	 * @param {boolean} showState True to update Bootstrap invalid state immediately.
	 * @returns {boolean} True when the field is valid.
	 */
	function syncPresetValidity(field, showState) {
		const preset = canonicalPreset(field.dataset.pairValidationPreset || '');
		const definition = presetDefinition(preset);

		if (!definition) {
			return true;
		}

		const validationNormalizer = definition.validationNormalizer || definition.normalizer;
		const value = validationNormalizer(field.value);
		const valid = value === '' || definition.validator(value);
		const message = field.dataset.pairValidationMessage || definition.message;

		field.setCustomValidity(valid ? '' : message);
		syncValidationClass(field, showState);

		return field.validity.valid;
	}

	/**
	 * Updates Bootstrap invalid state without forcing a positive validation style.
	 * @param {HTMLInputElement} field Field to update.
	 * @param {boolean} showState True to show invalid state.
	 * @returns {void}
	 */
	function syncValidationClass(field, showState) {
		const form = field.form;
		const shouldShow = showState
			|| field.dataset.pairValidationTouched === '1'
			|| (form && form.classList.contains('was-validated'));

		field.classList.toggle('is-invalid', Boolean(shouldShow && !field.validity.valid));
	}

	/**
	 * Returns the current date at local midnight.
	 * @returns {Date} Today without time.
	 */
	function todayDateWithoutTime() {
		const now = new Date();

		return new Date(now.getFullYear(), now.getMonth(), now.getDate());
	}

	/**
	 * Validates every preset field inside a root node.
	 * @param {ParentNode|Element} root Root node.
	 * @param {boolean} showState True to update visible validation state.
	 * @returns {boolean} True when all preset fields are valid.
	 */
	function validatePresetFields(root = document, showState = false) {
		let valid = true;

		matchingFields(root, '[data-pair-validation-preset]').forEach((field) => {
			if (!syncPresetValidity(field, showState)) {
				valid = false;
			}
		});

		return valid;
	}

	/**
	 * Checks whether a date exists without relying on Date rollover.
	 * @param {number} year Full year.
	 * @param {number} month Month from 1 to 12.
	 * @param {number} day Day of month.
	 * @returns {boolean} True when the date exists.
	 */
	function isRealDate(year, month, day) {
		const date = new Date(year, month - 1, day);

		return month > 0
			&& day > 0
			&& date.getFullYear() === year
			&& date.getMonth() === month - 1
			&& date.getDate() === day;
	}

	window.PairValidation = Object.freeze({
		bindBicField,
		bindDocument,
		bindE164PhoneField,
		bindEan13Field,
		bindEmailAddressField,
		bindForm,
		bindHexColorField,
		bindIbanField,
		bindIpAddressField,
		bindIpv4AddressField,
		bindIpv6AddressField,
		bindItalianFiscalCodeField,
		bindItalianPersonalFiscalCodeField,
		bindItalianSdiRecipientCodeField,
		bindItalianVatNumberField,
		bindMacAddressField,
		bindPresetField,
		bindSlugField,
		bindUrlField,
		bindUuidField,
		canonicalPreset,
		clearField,
		completeEan13,
		configure,
		decodeItalianFiscalCodeOmocodia,
		destroyForm,
		ean13ControlDigit,
		italianFiscalCodeControlCharacter,
		italianPersonalFiscalCodeBirthData,
		isValid,
		isValidBic,
		isValidE164Phone,
		isValidEan13,
		isValidEmail,
		isValidHexColor,
		isValidIban,
		isValidIpAddress,
		isValidIpv4Address,
		isValidIpv6Address,
		isValidItalianFiscalCode,
		isValidItalianNumericFiscalCode,
		isValidItalianPersonalFiscalCode,
		isValidItalianSdiRecipientCode,
		isValidItalianVatNumber,
		isValidMacAddress,
		isValidSlug,
		isValidUrl,
		isValidUuid,
		normalize,
		normalizeBic,
		normalizeE164Phone,
		normalizeEan13,
		normalizeEmail,
		normalizeHexColor,
		normalizeIban,
		normalizeIpAddress,
		normalizeItalianFiscalCode,
		normalizeItalianSdiRecipientCode,
		normalizeItalianVatNumber,
		normalizeMacAddress,
		normalizeSlug,
		normalizeUrl,
		normalizeUuid,
		registerAdapter,
		resetForm,
		setFieldError,
		setServerErrors,
		validateField,
		validateForm,
		validatePresetFields
	});

	if (!window.EpFiscalIdentity) {
		window.EpFiscalIdentity = Object.freeze({
			bindDocument,
			bindEan13Field,
			bindFiscalCodeField: bindItalianFiscalCodeField,
			bindIbanField,
			bindRecipientCodeField: bindItalianSdiRecipientCodeField,
			bindVatNumberField: bindItalianVatNumberField,
			completeEan13,
			decodeOmocodiaFiscalCode: decodeItalianFiscalCodeOmocodia,
			fiscalCodeControlCharacter: italianFiscalCodeControlCharacter,
			isValidEan13,
			isValidFiscalCode: isValidItalianFiscalCode,
			isValidIban,
			isValidNumericFiscalCode: isValidItalianNumericFiscalCode,
			isValidPersonalFiscalCode: isValidItalianPersonalFiscalCode,
			isValidRecipientCode: isValidItalianSdiRecipientCode,
			isValidVatNumber: isValidItalianVatNumber,
			normalizeEan13,
			normalizeFiscalCode: normalizeItalianFiscalCode,
			normalizeIban,
			normalizeRecipientCode: normalizeItalianSdiRecipientCode,
			normalizeVatNumber: normalizeItalianVatNumber,
			personalFiscalCodeBirthData: italianPersonalFiscalCodeBirthData
		});
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', bootPairValidation);
	} else {
		bootPairValidation();
	}
})();
