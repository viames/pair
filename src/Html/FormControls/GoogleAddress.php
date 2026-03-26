<?php

namespace Pair\Html\FormControls;

/**
 * Google-enhanced address input for Pair forms.
 * It keeps the plain text address field compatible with regular forms
 * while wiring optional hidden fields for place metadata.
 */
class GoogleAddress extends Address {

	/**
	 * Optional hidden field name storing JSON-encoded address components.
	 */
	private ?string $componentsFieldName = null;

	/**
	 * Optional initial value for the JSON-encoded address components field.
	 */
	private ?string $componentsFieldValue = null;

	/**
	 * Country restrictions for Google autocomplete.
	 * @var string[]
	 */
	private array $countries = [];

	/**
	 * Optional autocomplete latitude field name.
	 */
	private ?string $latitudeFieldName = null;

	/**
	 * Optional initial value for the latitude field.
	 */
	private ?string $latitudeFieldValue = null;

	/**
	 * Optional autocomplete longitude field name.
	 */
	private ?string $longitudeFieldName = null;

	/**
	 * Optional initial value for the longitude field.
	 */
	private ?string $longitudeFieldValue = null;

	/**
	 * Optional autocomplete place id field name.
	 */
	private ?string $placeIdFieldName = null;

	/**
	 * Optional initial value for the place id field.
	 */
	private ?string $placeIdFieldValue = null;

	/**
	 * Whether Google should strictly restrict results to the configured bounds.
	 */
	private bool $strictBounds = false;

	/**
	 * Optional Google place types restriction.
	 * @var string[]
	 */
	private array $types = [];

	/**
	 * Optional rectangular bounds.
	 *
	 * @var array<string,float>|null
	 */
	private ?array $bounds = null;

	/**
	 * Configure a hidden field that stores the selected address components as JSON.
	 *
	 * @param	string				$name	Field name.
	 * @param	string|array|null	$value	Optional initial JSON string or component array.
	 */
	public function componentsField(string $name, string|array|null $value = null): static {

		$this->componentsFieldName = trim($name);
		$this->componentsFieldValue = is_array($value)
			? json_encode($value, JSON_UNESCAPED_SLASHES)
			: (($value === null) ? null : (string)$value);

		return $this;

	}

	/**
	 * Restrict autocomplete suggestions to one or more countries.
	 *
	 * @param	string|array	$countries	ISO 3166-1 alpha-2 country codes.
	 */
	public function countries(string|array $countries): static {

		$countryList = is_array($countries) ? $countries : [$countries];
		$this->countries = [];

		foreach ($countryList as $country) {
			$country = strtolower(trim((string)$country));

			if ('' !== $country and !in_array($country, $this->countries)) {
				$this->countries[] = $country;
			}
		}

		return $this;

	}

	/**
	 * Configure hidden latitude and longitude fields.
	 *
	 * @param	string				$latitudeName	Field name used for latitude.
	 * @param	string				$longitudeName	Field name used for longitude.
	 * @param	float|string|null	$latitudeValue	Optional initial latitude value.
	 * @param	float|string|null	$longitudeValue	Optional initial longitude value.
	 */
	public function coordinatesFields(string $latitudeName, string $longitudeName, float|string|null $latitudeValue = null, float|string|null $longitudeValue = null): static {

		$this->latitudeFieldName = trim($latitudeName);
		$this->longitudeFieldName = trim($longitudeName);
		$this->latitudeFieldValue = is_null($latitudeValue) ? null : (string)$latitudeValue;
		$this->longitudeFieldValue = is_null($longitudeValue) ? null : (string)$longitudeValue;

		return $this;

	}

	/**
	 * Configure a rectangular bounds bias for autocomplete predictions.
	 *
	 * @param	float	$south	South latitude.
	 * @param	float	$west	West longitude.
	 * @param	float	$north	North latitude.
	 * @param	float	$east	East longitude.
	 */
	public function bounds(float $south, float $west, float $north, float $east): static {

		$this->bounds = [
			'south'	=> $south,
			'west'	=> $west,
			'north'	=> $north,
			'east'	=> $east,
		];

		return $this;

	}

	/**
	 * Configure a hidden field that stores the selected Google place ID.
	 *
	 * @param	string		$name	Field name.
	 * @param	string|null	$value	Optional initial place ID.
	 */
	public function placeIdField(string $name, ?string $value = null): static {

		$this->placeIdFieldName = trim($name);
		$this->placeIdFieldValue = $value;

		return $this;

	}

	/**
	 * Render the Google-enhanced address field together with its optional hidden metadata inputs.
	 */
	public function render(): string {

		$this->class('pairGoogleAddress');
		$this->data('google-components-field', (string)$this->componentsFieldName);
		$this->data('google-latitude-field', (string)$this->latitudeFieldName);
		$this->data('google-longitude-field', (string)$this->longitudeFieldName);
		$this->data('google-place-id-field', (string)$this->placeIdFieldName);
		$this->data('google-strict-bounds', $this->strictBounds ? 'true' : 'false');

		if (count($this->countries)) {
			$this->data('google-countries', implode(',', $this->countries));
		}

		if (!is_null($this->bounds)) {
			$this->data('google-bounds', json_encode($this->bounds, JSON_UNESCAPED_SLASHES));
		}

		if (count($this->types)) {
			$this->data('google-types', implode(',', $this->types));
		}

		return parent::render() . $this->renderMetadataFields();

	}

	/**
	 * Enable or disable strict bounds for Google autocomplete.
	 *
	 * @param	bool	$strictBounds	Whether only in-bounds results should be returned.
	 */
	public function strictBounds(bool $strictBounds = true): static {

		$this->strictBounds = $strictBounds;
		return $this;

	}

	/**
	 * Restrict autocomplete suggestions to specific Google place types.
	 *
	 * @param	string|array	$types	Google place types.
	 */
	public function types(string|array $types): static {

		$typeList = is_array($types) ? $types : [$types];
		$this->types = [];

		foreach ($typeList as $type) {
			$type = trim((string)$type);

			if ('' !== $type and !in_array($type, $this->types)) {
				$this->types[] = $type;
			}
		}

		return $this;

	}

	/**
	 * Render hidden metadata inputs required by the enhanced Google field.
	 */
	private function renderMetadataFields(): string {

		$ret = '';

		// Hidden metadata fields are optional and rendered only when explicitly configured.
		$ret .= $this->renderHiddenField($this->placeIdFieldName, $this->placeIdFieldValue);
		$ret .= $this->renderHiddenField($this->latitudeFieldName, $this->latitudeFieldValue);
		$ret .= $this->renderHiddenField($this->longitudeFieldName, $this->longitudeFieldValue);
		$ret .= $this->renderHiddenField($this->componentsFieldName, $this->componentsFieldValue);

		return $ret;

	}

	/**
	 * Render a hidden input only when a field name is configured.
	 *
	 * @param	string|null	$name	Field name.
	 * @param	string|null	$value	Optional field value.
	 */
	private function renderHiddenField(?string $name, ?string $value = null): string {

		if (!$name) {
			return '';
		}

		return '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars((string)$value) . '" />';

	}

}
