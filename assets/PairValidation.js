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

	/**
	 * Binds validation presets inside a root node.
	 * @param {ParentNode|Element} root Root node to inspect.
	 * @returns {void}
	 */
	function bindDocument(root = document) {
		matchingFields(root, '[data-pair-validation-preset]').forEach((field) => bindPresetField(field));
	}

	/**
	 * Boots validation helpers on the current document.
	 * @returns {void}
	 */
	function bootPairValidation() {
		bindDocument(document);
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
		completeEan13,
		decodeItalianFiscalCodeOmocodia,
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
