<?php

declare(strict_types=1);

namespace Pair\Html;

/**
 * Provides shared normalization and validation rules for common form fields.
 */
final class FormValidationPreset {

	public const BIC = 'bic';
	public const EAN13 = 'ean13';
	public const E164_PHONE = 'e164_phone';
	public const EMAIL = 'email';
	public const ITALIAN_FISCAL_CODE = 'it.fiscal_code';
	public const HEX_COLOR = 'hex_color';
	public const IBAN = 'iban';
	public const IP_ADDRESS = 'ip_address';
	public const IPV4_ADDRESS = 'ipv4_address';
	public const IPV6_ADDRESS = 'ipv6_address';
	public const ITALIAN_VAT_NUMBER = 'it.vat_number';
	public const MAC_ADDRESS = 'mac_address';
	public const ITALIAN_PERSONAL_FISCAL_CODE = 'it.personal_fiscal_code';
	public const ITALIAN_SDI_RECIPIENT_CODE = 'it.sdi_recipient_code';
	public const SLUG = 'slug';
	public const URL = 'url';
	public const UUID = 'uuid';

	private const ALIASES = [
		'bic' => self::BIC,
		'bic_code' => self::BIC,
		'codice_destinatario' => self::ITALIAN_SDI_RECIPIENT_CODE,
		'codice_fiscale' => self::ITALIAN_FISCAL_CODE,
		'cf' => self::ITALIAN_FISCAL_CODE,
		'ean' => self::EAN13,
		'ean-13' => self::EAN13,
		'ean13' => self::EAN13,
		'e164' => self::E164_PHONE,
		'e164_phone' => self::E164_PHONE,
		'email' => self::EMAIL,
		'email_address' => self::EMAIL,
		'hex_color' => self::HEX_COLOR,
		'iban' => self::IBAN,
		'iban_code' => self::IBAN,
		'international_phone' => self::E164_PHONE,
		'ip' => self::IP_ADDRESS,
		'ip_address' => self::IP_ADDRESS,
		'ipv4' => self::IPV4_ADDRESS,
		'ipv4_address' => self::IPV4_ADDRESS,
		'ipv6' => self::IPV6_ADDRESS,
		'ipv6_address' => self::IPV6_ADDRESS,
		'italian_fiscal_code' => self::ITALIAN_FISCAL_CODE,
		'italian_personal_fiscal_code' => self::ITALIAN_PERSONAL_FISCAL_CODE,
		'italian_sdi_recipient_code' => self::ITALIAN_SDI_RECIPIENT_CODE,
		'italian_vat_number' => self::ITALIAN_VAT_NUMBER,
		'it.fiscal_code' => self::ITALIAN_FISCAL_CODE,
		'it.personal_fiscal_code' => self::ITALIAN_PERSONAL_FISCAL_CODE,
		'it.sdi_recipient_code' => self::ITALIAN_SDI_RECIPIENT_CODE,
		'it.vat_number' => self::ITALIAN_VAT_NUMBER,
		'mac' => self::MAC_ADDRESS,
		'mac_address' => self::MAC_ADDRESS,
		'partita_iva' => self::ITALIAN_VAT_NUMBER,
		'phone_e164' => self::E164_PHONE,
		'piva' => self::ITALIAN_VAT_NUMBER,
		'sdi' => self::ITALIAN_SDI_RECIPIENT_CODE,
		'slug' => self::SLUG,
		'swift' => self::BIC,
		'swift_bic' => self::BIC,
		'url' => self::URL,
		'uuid' => self::UUID,
	];
	private const BIC_PATTERN = '/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/';
	private const EAN13_PATTERN = '/^[0-9]{13}$/';
	private const E164_PHONE_PATTERN = '/^\+[1-9][0-9]{1,14}$/';
	private const FISCAL_CODE_BIRTH_MONTHS = [
		'A' => 1,
		'B' => 2,
		'C' => 3,
		'D' => 4,
		'E' => 5,
		'H' => 6,
		'L' => 7,
		'M' => 8,
		'P' => 9,
		'R' => 10,
		'S' => 11,
		'T' => 12,
	];
	private const FISCAL_CODE_NUMERIC_PATTERN = '/^[0-9]{11}$/';
	private const FISCAL_CODE_ODD_VALUES = [
		'0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
		'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
		'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
		'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
	];
	private const FISCAL_CODE_OMOCODIA_MAP = [
		'L' => '0',
		'M' => '1',
		'N' => '2',
		'P' => '3',
		'Q' => '4',
		'R' => '5',
		'S' => '6',
		'T' => '7',
		'U' => '8',
		'V' => '9',
	];
	private const FISCAL_CODE_OMOCODIA_POSITIONS = [6, 7, 9, 10, 12, 13, 14];
	private const FISCAL_CODE_PERSONAL_PATTERN = '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[A-EHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';
	private const HEX_COLOR_PATTERN = '/^#(?:[0-9A-F]{3}|[0-9A-F]{6})$/';
	private const IBAN_PATTERN = '/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/';
	private const MAC_ADDRESS_PATTERN = '/^[0-9A-F]{2}(:[0-9A-F]{2}){5}$/';
	private const SDI_RECIPIENT_CODE_PATTERN = '/^[A-Z0-9]{7}$/';
	private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
	private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

	/**
	 * Prevents instantiation of this stateless helper.
	 */
	private function __construct() {

	}

	/**
	 * Returns the canonical preset name from a canonical name or alias.
	 */
	public static function canonicalName(string $preset): string {

		$normalizedPreset = strtolower(trim($preset));

		if (!isset(self::ALIASES[$normalizedPreset])) {
			throw new \InvalidArgumentException('Unknown form validation preset "' . $preset . '".');
		}

		return self::ALIASES[$normalizedPreset];

	}

	/**
	 * Returns the display and HTML defaults for a preset.
	 *
	 * @return array<string, string|int>
	 */
	public static function definition(string $preset): array {

		return match (self::canonicalName($preset)) {
			self::BIC => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 11,
				'message' => 'Enter a valid BIC/SWIFT code.',
				'messageKey' => 'FORM_VALIDATION_INVALID_BIC',
				'minLength' => 8,
				'pattern' => '[A-Za-z]{4}[A-Za-z]{2}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?',
				'placeholder' => 'UNCRITMMXXX',
			],
			self::EAN13 => [
				'inputmode' => 'numeric',
				'maxLength' => 13,
				'message' => 'Enter a valid EAN-13 code.',
				'messageKey' => 'FORM_VALIDATION_INVALID_EAN13',
				'minLength' => 12,
				'pattern' => '[0-9]{12,13}',
				'placeholder' => '9781234567897',
			],
			self::E164_PHONE => [
				'autocomplete' => 'tel',
				'inputmode' => 'tel',
				'maxLength' => 16,
				'message' => 'Enter a valid international phone number in E.164 format.',
				'messageKey' => 'FORM_VALIDATION_INVALID_E164_PHONE',
				'minLength' => 3,
				'pattern' => '\\+[1-9][0-9]{1,14}',
				'placeholder' => '+393331234567',
			],
			self::EMAIL => [
				'autocomplete' => 'email',
				'inputmode' => 'email',
				'maxLength' => 254,
				'message' => 'Enter a valid email address.',
				'messageKey' => 'FORM_VALIDATION_INVALID_EMAIL',
				'minLength' => 3,
				'pattern' => '[^@\\s]+@[^@\\s]+\\.[^@\\s]+',
				'placeholder' => 'user@example.com',
			],
			self::ITALIAN_FISCAL_CODE => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 16,
				'message' => 'Enter a valid Italian fiscal code.',
				'messageKey' => 'FORM_VALIDATION_INVALID_ITALIAN_FISCAL_CODE',
				'minLength' => 11,
				'pattern' => '[A-Za-z0-9]{11}|[A-Za-z0-9]{16}',
				'placeholder' => 'RSSMRA80A01H501U',
			],
			self::HEX_COLOR => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 7,
				'message' => 'Enter a valid hexadecimal color.',
				'messageKey' => 'FORM_VALIDATION_INVALID_HEX_COLOR',
				'minLength' => 4,
				'pattern' => '#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})',
				'placeholder' => '#336699',
			],
			self::IBAN => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 34,
				'message' => 'Enter a valid IBAN.',
				'messageKey' => 'FORM_VALIDATION_INVALID_IBAN',
				'minLength' => 15,
				'pattern' => '[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{11,30}',
				'placeholder' => 'IT60X0542811101000000123456',
			],
			self::IP_ADDRESS => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 45,
				'message' => 'Enter a valid IP address.',
				'messageKey' => 'FORM_VALIDATION_INVALID_IP_ADDRESS',
				'minLength' => 3,
				'placeholder' => '192.0.2.1',
			],
			self::IPV4_ADDRESS => [
				'autocomplete' => 'off',
				'inputmode' => 'decimal',
				'maxLength' => 15,
				'message' => 'Enter a valid IPv4 address.',
				'messageKey' => 'FORM_VALIDATION_INVALID_IPV4_ADDRESS',
				'minLength' => 7,
				'pattern' => '([0-9]{1,3}\\.){3}[0-9]{1,3}',
				'placeholder' => '192.0.2.1',
			],
			self::IPV6_ADDRESS => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 45,
				'message' => 'Enter a valid IPv6 address.',
				'messageKey' => 'FORM_VALIDATION_INVALID_IPV6_ADDRESS',
				'minLength' => 2,
				'placeholder' => '2001:db8::1',
			],
			self::ITALIAN_VAT_NUMBER => [
				'autocomplete' => 'off',
				'inputmode' => 'numeric',
				'maxLength' => 11,
				'message' => 'Enter a valid Italian VAT number.',
				'messageKey' => 'FORM_VALIDATION_INVALID_ITALIAN_VAT_NUMBER',
				'minLength' => 11,
				'pattern' => '[0-9]{11}',
				'placeholder' => '12345678903',
			],
			self::MAC_ADDRESS => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 17,
				'message' => 'Enter a valid MAC address.',
				'messageKey' => 'FORM_VALIDATION_INVALID_MAC_ADDRESS',
				'minLength' => 12,
				'pattern' => '([0-9A-Fa-f]{2}[:-]){5}[0-9A-Fa-f]{2}|[0-9A-Fa-f]{12}',
				'placeholder' => '00:11:22:AA:BB:CC',
			],
			self::ITALIAN_PERSONAL_FISCAL_CODE => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 16,
				'message' => 'Enter a valid Italian personal fiscal code.',
				'messageKey' => 'FORM_VALIDATION_INVALID_ITALIAN_PERSONAL_FISCAL_CODE',
				'minLength' => 16,
				'pattern' => '[A-Za-z]{6}[0-9LMNPQRSTUVlmnpqrstuv]{2}[A-EHLMPRSTa-ehlmprst][0-9LMNPQRSTUVlmnpqrstuv]{2}[A-Za-z][0-9LMNPQRSTUVlmnpqrstuv]{3}[A-Za-z]',
				'placeholder' => 'RSSMRA80A01H501U',
			],
			self::ITALIAN_SDI_RECIPIENT_CODE => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 7,
				'message' => 'Enter a valid Italian SdI recipient code.',
				'messageKey' => 'FORM_VALIDATION_INVALID_ITALIAN_SDI_RECIPIENT_CODE',
				'minLength' => 7,
				'pattern' => '[A-Za-z0-9]{7}',
				'placeholder' => 'ABC1234',
			],
			self::SLUG => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 120,
				'message' => 'Enter a valid slug.',
				'messageKey' => 'FORM_VALIDATION_INVALID_SLUG',
				'minLength' => 1,
				'pattern' => '[a-z0-9]+(-[a-z0-9]+)*',
				'placeholder' => 'example-slug',
			],
			self::URL => [
				'autocomplete' => 'url',
				'inputmode' => 'url',
				'maxLength' => 2048,
				'message' => 'Enter a valid URL.',
				'messageKey' => 'FORM_VALIDATION_INVALID_URL',
				'minLength' => 4,
				'pattern' => '.+://.+',
				'placeholder' => 'https://example.com',
			],
			self::UUID => [
				'autocomplete' => 'off',
				'inputmode' => 'text',
				'maxLength' => 36,
				'message' => 'Enter a valid UUID.',
				'messageKey' => 'FORM_VALIDATION_INVALID_UUID',
				'minLength' => 36,
				'pattern' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
				'placeholder' => '550e8400-e29b-41d4-a716-446655440000',
			],
		};

	}

	/**
	 * Returns the expected EAN-13 control digit for the first twelve digits.
	 */
	private static function expectedEan13Control(string $ean13): ?int {

		if (strlen($ean13) < 12) {
			return null;
		}

		$sum = 0;
		for ($index = 0; $index < 12; $index++) {
			$digit = (int)$ean13[$index];

			// EAN-13 multiplies even human positions by 3; zero-based indexes make them odd.
			$sum += (1 === $index % 2) ? $digit * 3 : $digit;
		}

		return (10 - ($sum % 10)) % 10;

	}

	/**
	 * Returns the expected control character for an Italian personal fiscal code.
	 */
	private static function expectedFiscalCodeControl(string $fiscalCode): string {

		if (strlen($fiscalCode) < 15) {
			return '';
		}

		$sum = 0;
		for ($index = 0; $index < 15; $index++) {
			$character = $fiscalCode[$index];

			// Odd fiscal-code positions use the official weighted lookup table.
			if (0 === $index % 2) {
				if (!isset(self::FISCAL_CODE_ODD_VALUES[$character])) {
					return '';
				}

				$sum += self::FISCAL_CODE_ODD_VALUES[$character];
				continue;
			}

			$sum += ctype_digit($character) ? (int)$character : ord($character) - ord('A');
		}

		return chr(ord('A') + ($sum % 26));

	}

	/**
	 * Returns true when a value matches the selected preset.
	 */
	public static function isValid(string $preset, mixed $value, bool $required = false): bool {

		$canonicalPreset = self::canonicalName($preset);
		$definition = self::definition($canonicalPreset);
		$normalizedValue = self::normalize($canonicalPreset, $value);

		if ('' === $normalizedValue) {
			return !$required;
		}

		// enforce shared length constraints for direct preset validation calls.
		$length = strlen($normalizedValue);
		if (isset($definition['minLength']) and $length < (int)$definition['minLength']) {
			return false;
		}

		if (isset($definition['maxLength']) and $length > (int)$definition['maxLength']) {
			return false;
		}

		return match ($canonicalPreset) {
			self::BIC => self::isValidBic($normalizedValue),
			self::EAN13 => self::isValidEan13($normalizedValue),
			self::E164_PHONE => self::isValidE164Phone($normalizedValue),
			self::EMAIL => self::isValidEmail($normalizedValue),
			self::ITALIAN_FISCAL_CODE => self::isValidFiscalCode($normalizedValue),
			self::HEX_COLOR => self::isValidHexColor($normalizedValue),
			self::IBAN => self::isValidIban($normalizedValue),
			self::IP_ADDRESS => self::isValidIpAddress($normalizedValue),
			self::IPV4_ADDRESS => self::isValidIpv4Address($normalizedValue),
			self::IPV6_ADDRESS => self::isValidIpv6Address($normalizedValue),
			self::ITALIAN_VAT_NUMBER => self::isValidItalianVatNumber($normalizedValue),
			self::MAC_ADDRESS => self::isValidMacAddress($normalizedValue),
			self::ITALIAN_PERSONAL_FISCAL_CODE => self::isValidPersonalFiscalCode($normalizedValue),
			self::ITALIAN_SDI_RECIPIENT_CODE => self::isValidSdiRecipientCode($normalizedValue),
			self::SLUG => self::isValidSlug($normalizedValue),
			self::URL => self::isValidUrl($normalizedValue),
			self::UUID => self::isValidUuid($normalizedValue),
		};

	}

	/**
	 * Returns true when a BIC/SWIFT code has valid ISO 9362 syntax.
	 */
	public static function isValidBic(mixed $value): bool {

		return (bool)preg_match(self::BIC_PATTERN, self::normalizeBic($value));

	}

	/**
	 * Returns true when a phone number follows E.164 syntax.
	 */
	public static function isValidE164Phone(mixed $value): bool {

		return (bool)preg_match(self::E164_PHONE_PATTERN, self::normalizeE164Phone($value));

	}

	/**
	 * Returns true when an EAN-13 code has valid length and checksum.
	 */
	public static function isValidEan13(mixed $value): bool {

		$ean13 = self::normalizeEan13($value);
		if (!preg_match(self::EAN13_PATTERN, $ean13)) {
			return false;
		}

		return self::expectedEan13Control($ean13) === (int)$ean13[12];

	}

	/**
	 * Returns true when an email address passes PHP email validation.
	 */
	public static function isValidEmail(mixed $value): bool {

		return false !== filter_var(self::normalizeEmail($value), FILTER_VALIDATE_EMAIL);

	}

	/**
	 * Returns true for numeric or personal Italian fiscal codes.
	 */
	public static function isValidFiscalCode(mixed $value): bool {

		$fiscalCode = self::normalizeFiscalCode($value);

		if (preg_match(self::FISCAL_CODE_NUMERIC_PATTERN, $fiscalCode)) {
			return self::isValidNumericFiscalCode($fiscalCode);
		}

		return self::isValidPersonalFiscalCode($fiscalCode);

	}

	/**
	 * Returns true when a hexadecimal color uses the #RGB or #RRGGBB format.
	 */
	public static function isValidHexColor(mixed $value): bool {

		return (bool)preg_match(self::HEX_COLOR_PATTERN, self::normalizeHexColor($value));

	}

	/**
	 * Returns true when an IBAN has valid syntax and MOD-97 checksum.
	 */
	public static function isValidIban(mixed $value): bool {

		$iban = self::normalizeIban($value);
		$length = strlen($iban);

		if ($length < 15 or $length > 34 or !preg_match(self::IBAN_PATTERN, $iban)) {
			return false;
		}

		return 1 === self::mod97(substr($iban, 4) . substr($iban, 0, 4));

	}

	/**
	 * Returns true when a value is a valid IPv4 or IPv6 address.
	 */
	public static function isValidIpAddress(mixed $value): bool {

		return false !== filter_var(self::normalizeIpAddress($value), FILTER_VALIDATE_IP);

	}

	/**
	 * Returns true when a value is a valid IPv4 address.
	 */
	public static function isValidIpv4Address(mixed $value): bool {

		return false !== filter_var(self::normalizeIpAddress($value), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

	}

	/**
	 * Returns true when a value is a valid IPv6 address.
	 */
	public static function isValidIpv6Address(mixed $value): bool {

		return false !== filter_var(self::normalizeIpAddress($value), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

	}

	/**
	 * Returns true when an Italian VAT number has a valid checksum.
	 */
	public static function isValidItalianVatNumber(mixed $value): bool {

		return self::isValidNumericFiscalCode(self::normalizeVatNumber($value));

	}

	/**
	 * Returns true when an eleven-digit Italian numeric tax code has a valid checksum.
	 */
	public static function isValidNumericFiscalCode(mixed $value): bool {

		$fiscalCode = self::normalizeVatNumber($value);
		if (!preg_match(self::FISCAL_CODE_NUMERIC_PATTERN, $fiscalCode)) {
			return false;
		}

		$sum = 0;
		for ($index = 0; $index < 10; $index++) {
			$digit = (int)$fiscalCode[$index];

			// In eleven-digit Italian codes, zero-based odd indexes are doubled and reduced.
			if (1 === $index % 2) {
				$digit *= 2;
				if ($digit > 9) {
					$digit -= 9;
				}
			}

			$sum += $digit;
		}

		$expectedControl = (10 - ($sum % 10)) % 10;

		return $expectedControl === (int)$fiscalCode[10];

	}

	/**
	 * Returns true when a MAC address has valid hexadecimal pairs.
	 */
	public static function isValidMacAddress(mixed $value): bool {

		return (bool)preg_match(self::MAC_ADDRESS_PATTERN, self::normalizeMacAddress($value));

	}

	/**
	 * Returns true when an Italian personal fiscal code has valid syntax, date, and checksum.
	 */
	public static function isValidPersonalFiscalCode(mixed $value): bool {

		$fiscalCode = self::normalizeFiscalCode($value);
		if (!preg_match(self::FISCAL_CODE_PERSONAL_PATTERN, $fiscalCode)) {
			return false;
		}

		$birthData = self::personalFiscalCodeBirthData($fiscalCode);
		if (!$birthData or !$birthData['dateOfBirth']) {
			return false;
		}

		return self::expectedFiscalCodeControl($fiscalCode) === $fiscalCode[15];

	}

	/**
	 * Returns true when a SdI recipient code has seven alphanumeric characters.
	 */
	public static function isValidSdiRecipientCode(mixed $value): bool {

		return (bool)preg_match(self::SDI_RECIPIENT_CODE_PATTERN, self::normalizeSdiRecipientCode($value));

	}

	/**
	 * Returns true when a slug uses lowercase letters, digits, and internal hyphens.
	 */
	public static function isValidSlug(mixed $value): bool {

		return (bool)preg_match(self::SLUG_PATTERN, self::normalizeSlug($value));

	}

	/**
	 * Returns true when a URL has a hierarchical scheme and passes PHP URL validation.
	 */
	public static function isValidUrl(mixed $value): bool {

		$url = self::normalizeUrl($value);

		return str_contains($url, '://') and false !== filter_var($url, FILTER_VALIDATE_URL);

	}

	/**
	 * Returns true when a UUID has RFC-compatible canonical syntax.
	 */
	public static function isValidUuid(mixed $value): bool {

		return (bool)preg_match(self::UUID_PATTERN, self::normalizeUuid($value));

	}

	/**
	 * Calculates MOD-97 without converting long IBAN strings to large integers.
	 */
	private static function mod97(string $value): int {

		$remainder = 0;
		$length = strlen($value);

		for ($index = 0; $index < $length; $index++) {
			$character = $value[$index];
			$digits = ctype_alpha($character) ? (string)(ord($character) - 55) : $character;
			$digitsLength = strlen($digits);

			// Process one digit at a time to keep arithmetic inside native integer bounds.
			for ($digitIndex = 0; $digitIndex < $digitsLength; $digitIndex++) {
				$remainder = (int)(($remainder * 10 + (int)$digits[$digitIndex]) % 97);
			}
		}

		return $remainder;

	}

	/**
	 * Normalizes a value according to the selected preset.
	 */
	public static function normalize(string $preset, mixed $value): string {

		return match (self::canonicalName($preset)) {
			self::BIC => self::normalizeBic($value),
			self::EAN13 => self::normalizeEan13($value),
			self::E164_PHONE => self::normalizeE164Phone($value),
			self::EMAIL => self::normalizeEmail($value),
			self::ITALIAN_FISCAL_CODE, self::ITALIAN_PERSONAL_FISCAL_CODE => self::normalizeFiscalCode($value),
			self::HEX_COLOR => self::normalizeHexColor($value),
			self::IBAN => self::normalizeIban($value),
			self::IP_ADDRESS, self::IPV4_ADDRESS, self::IPV6_ADDRESS => self::normalizeIpAddress($value),
			self::ITALIAN_VAT_NUMBER => self::normalizeVatNumber($value),
			self::MAC_ADDRESS => self::normalizeMacAddress($value),
			self::ITALIAN_SDI_RECIPIENT_CODE => self::normalizeSdiRecipientCode($value),
			self::SLUG => self::normalizeSlug($value),
			self::URL => self::normalizeUrl($value),
			self::UUID => self::normalizeUuid($value),
		};

	}

	/**
	 * Normalizes a BIC/SWIFT code to uppercase alphanumeric characters.
	 */
	public static function normalizeBic(mixed $value): string {

		return substr(self::normalizeUpperAlphanumeric($value), 0, 11);

	}

	/**
	 * Normalizes a phone number by keeping a leading plus sign and digits.
	 */
	public static function normalizeE164Phone(mixed $value): string {

		$value = trim((string)$value);
		$prefix = str_starts_with($value, '+') ? '+' : '';
		$digits = self::normalizeDigits($value);

		return substr($prefix . $digits, 0, 16);

	}

	/**
	 * Returns only digits from a free text value.
	 */
	private static function normalizeDigits(mixed $value): string {

		return preg_replace('/\D+/', '', trim((string)$value)) ?: '';

	}

	/**
	 * Normalizes EAN-13 and completes UPC-A twelve-digit values with a leading zero.
	 */
	public static function normalizeEan13(mixed $value): string {

		$ean13 = substr(self::normalizeDigits($value), 0, 13);

		return 12 === strlen($ean13) ? '0' . $ean13 : $ean13;

	}

	/**
	 * Normalizes an email address by trimming surrounding whitespace.
	 */
	public static function normalizeEmail(mixed $value): string {

		return trim((string)$value);

	}

	/**
	 * Normalizes an Italian fiscal code to uppercase alphanumeric characters.
	 */
	public static function normalizeFiscalCode(mixed $value): string {

		return substr(self::normalizeUpperAlphanumeric($value), 0, 16);

	}

	/**
	 * Normalizes a hexadecimal color to uppercase #RGB or #RRGGBB form.
	 */
	public static function normalizeHexColor(mixed $value): string {

		$value = strtoupper(trim((string)$value));
		$value = preg_replace('/[^0-9A-F]/', '', $value) ?: '';
		$value = substr($value, 0, 6);

		if (strlen($value) < 3) {
			return $value;
		}

		return '#' . $value;

	}

	/**
	 * Normalizes an IBAN to uppercase alphanumeric characters.
	 */
	public static function normalizeIban(mixed $value): string {

		return substr(self::normalizeUpperAlphanumeric($value), 0, 34);

	}

	/**
	 * Normalizes an IP address by trimming surrounding whitespace.
	 */
	public static function normalizeIpAddress(mixed $value): string {

		return trim((string)$value);

	}

	/**
	 * Normalizes a MAC address to colon-separated uppercase pairs.
	 */
	public static function normalizeMacAddress(mixed $value): string {

		$value = substr(self::normalizeUpperAlphanumeric($value), 0, 12);

		if (12 !== strlen($value)) {
			return $value;
		}

		return implode(':', str_split($value, 2));

	}

	/**
	 * Normalizes a SdI recipient code to uppercase alphanumeric characters.
	 */
	public static function normalizeSdiRecipientCode(mixed $value): string {

		return substr(self::normalizeUpperAlphanumeric($value), 0, 7);

	}

	/**
	 * Normalizes a slug to lowercase ASCII words separated by single hyphens.
	 */
	public static function normalizeSlug(mixed $value): string {

		$value = strtolower(trim((string)$value));
		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
		$value = trim($value, '-');

		return substr(preg_replace('/-+/', '-', $value) ?: '', 0, 120);

	}

	/**
	 * Returns only uppercase ASCII alphanumeric characters from a free text value.
	 */
	private static function normalizeUpperAlphanumeric(mixed $value): string {

		return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$value))) ?: '';

	}

	/**
	 * Normalizes a URL by trimming surrounding whitespace.
	 */
	public static function normalizeUrl(mixed $value): string {

		return trim((string)$value);

	}

	/**
	 * Normalizes a UUID to lowercase canonical form.
	 */
	public static function normalizeUuid(mixed $value): string {

		return strtolower(trim((string)$value));

	}

	/**
	 * Normalizes an Italian VAT number by removing the optional IT prefix and separators.
	 */
	public static function normalizeVatNumber(mixed $value): string {

		$value = strtoupper(trim((string)$value));
		$value = preg_replace('/^IT/i', '', $value) ?: $value;

		return substr(self::normalizeDigits($value), 0, 11);

	}

	/**
	 * Extracts birth data from an Italian personal fiscal code.
	 *
	 * @return array{dateOfBirth:?string,sex:?string,birthPlaceCode:string}|null
	 */
	private static function personalFiscalCodeBirthData(string $fiscalCode): ?array {

		$fiscalCode = self::normalizeFiscalCode($fiscalCode);
		if (16 !== strlen($fiscalCode)) {
			return null;
		}

		$decodedFiscalCode = str_split($fiscalCode);
		foreach (self::FISCAL_CODE_OMOCODIA_POSITIONS as $position) {
			if (isset($decodedFiscalCode[$position])) {
				$decodedFiscalCode[$position] = self::FISCAL_CODE_OMOCODIA_MAP[$decodedFiscalCode[$position]] ?? $decodedFiscalCode[$position];
			}
		}

		$decodedFiscalCode = implode('', $decodedFiscalCode);
		$yearDigits = substr($decodedFiscalCode, 6, 2);
		$dayDigits = substr($decodedFiscalCode, 9, 2);
		$month = self::FISCAL_CODE_BIRTH_MONTHS[substr($decodedFiscalCode, 8, 1)] ?? 0;
		$sex = null;
		$birthDate = null;

		if (ctype_digit($yearDigits) and ctype_digit($dayDigits)) {
			$dayCode = (int)$dayDigits;
			$day = $dayCode > 40 ? $dayCode - 40 : $dayCode;
			$year = 2000 + (int)$yearDigits;
			$sex = $dayCode > 40 ? 'f' : 'm';

			if ($month > 0 and checkdate($month, $day, $year)) {
				$candidate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

				if ($candidate > new \DateTimeImmutable('today')) {
					$year -= 100;
					$candidate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
				}

				$birthDate = $candidate->format('Y-m-d');
			}
		}

		return [
			'dateOfBirth' => $birthDate,
			'sex' => $sex,
			'birthPlaceCode' => substr($decodedFiscalCode, 11, 4),
		];

	}

}
