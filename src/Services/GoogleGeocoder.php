<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Server-side wrapper for Google Geocoding API operations.
 * It provides small, framework-friendly helpers around geocoding,
 * reverse geocoding and place-id based lookups.
 */
class GoogleGeocoder {

	/**
	 * Shared Google Maps HTTP client.
	 */
	private GoogleMapsClient $client;

	/**
	 * Constructor.
	 *
	 * @param	GoogleMapsClient|null	$client	Optional injected client.
	 */
	public function __construct(?GoogleMapsClient $client = null) {

		$this->client = $client ?? new GoogleMapsClient();

	}

	/**
	 * Geocode a free-form address and return all matching results.
	 *
	 * Supported options:
	 * - bounds: ['south' => ..., 'west' => ..., 'north' => ..., 'east' => ...]
	 * - components: string or ['country' => 'IT', 'postal_code' => '20100']
	 * - language: Google language code
	 * - region: region code
	 */
	public function geocode(string $address, array $options = []): array {

		$address = trim($address);

		if ('' === $address) {
			throw new PairException('Google geocoding address cannot be empty.', ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		$response = $this->client->geocodingGet('/maps/api/geocode/json', $this->buildBaseQuery($options) + [
			'address' => $address,
		]);

		return $this->extractResults($response);

	}

	/**
	 * Geocode an address and return the first matching result, if any.
	 *
	 * @param	string	$address	Free-form address.
	 * @param	array	$options	Optional filters.
	 */
	public function geocodeOne(string $address, array $options = []): ?array {

		$results = $this->geocode($address, $options);

		return $results[0] ?? null;

	}

	/**
	 * Look up a place by its Google place ID using the geocoding endpoint.
	 *
	 * @param	string	$placeId	Google place ID.
	 * @param	array	$options	Optional filters.
	 */
	public function geocodePlaceId(string $placeId, array $options = []): ?array {

		$placeId = trim($placeId);

		if ('' === $placeId) {
			throw new PairException('Google place ID cannot be empty.', ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		$response = $this->client->geocodingGet('/maps/api/geocode/json', $this->buildBaseQuery($options) + [
			'place_id' => $placeId,
		]);

		$results = $this->extractResults($response);

		return $results[0] ?? null;

	}

	/**
	 * Reverse geocode a latitude/longitude pair and return all matching results.
	 *
	 * Supported options:
	 * - language: Google language code
	 * - region: region code
	 * - result_type: string or array
	 * - location_type: string or array
	 */
	public function reverseGeocode(float $latitude, float $longitude, array $options = []): array {

		$response = $this->client->geocodingGet('/maps/api/geocode/json', $this->buildBaseQuery($options) + [
			'latlng' => $latitude . ',' . $longitude,
		]);

		return $this->extractResults($response);

	}

	/**
	 * Reverse geocode a latitude/longitude pair and return the first result, if any.
	 *
	 * @param	float	$latitude	Latitude in decimal degrees.
	 * @param	float	$longitude	Longitude in decimal degrees.
	 * @param	array	$options		Optional filters.
	 */
	public function reverseGeocodeOne(float $latitude, float $longitude, array $options = []): ?array {

		$results = $this->reverseGeocode($latitude, $longitude, $options);

		return $results[0] ?? null;

	}

	/**
	 * Build the common geocoding query parameters from optional filters.
	 *
	 * @param	array	$options	Supported geocoding options.
	 */
	private function buildBaseQuery(array $options): array {

		$query = [];

		if (isset($options['language']) and '' !== trim((string)$options['language'])) {
			$query['language'] = trim((string)$options['language']);
		}

		if (isset($options['region']) and '' !== trim((string)$options['region'])) {
			$query['region'] = trim((string)$options['region']);
		}

		if (isset($options['components']) and $options['components']) {
			$query['components'] = $this->normalizeComponents($options['components']);
		}

		if (isset($options['bounds']) and is_array($options['bounds']) and count($options['bounds'])) {
			$query['bounds'] = $this->normalizeBounds($options['bounds']);
		}

		if (isset($options['result_type']) and $options['result_type']) {
			$query['result_type'] = $this->normalizePipeSeparatedValue($options['result_type']);
		}

		if (isset($options['location_type']) and $options['location_type']) {
			$query['location_type'] = $this->normalizePipeSeparatedValue($options['location_type']);
		}

		return $query;

	}

	/**
	 * Normalize a Google components filter.
	 *
	 * @param	string|array	$components	Components filter.
	 */
	private function normalizeComponents(string|array $components): string {

		if (is_string($components)) {
			return trim($components);
		}

		$pairs = [];

		foreach ($components as $name => $value) {
			if ('' === trim((string)$value)) {
				continue;
			}

			$pairs[] = trim((string)$name) . ':' . trim((string)$value);
		}

		return implode('|', $pairs);

	}

	/**
	 * Normalize a Google rectangular bounds filter.
	 *
	 * @param	array	$bounds	Bounds array with south, west, north and east keys.
	 */
	private function normalizeBounds(array $bounds): string {

		$requiredKeys = ['south', 'west', 'north', 'east'];

		foreach ($requiredKeys as $key) {
			if (!array_key_exists($key, $bounds)) {
				throw new PairException('Google bounds must include south, west, north and east.', ErrorCodes::GOOGLE_MAPS_ERROR);
			}
		}

		return $bounds['south'] . ',' . $bounds['west'] . '|' . $bounds['north'] . ',' . $bounds['east'];

	}

	/**
	 * Normalize values accepted by Google as pipe-separated lists.
	 *
	 * @param	string|array	$value	Input string or list.
	 */
	private function normalizePipeSeparatedValue(string|array $value): string {

		if (is_string($value)) {
			return trim($value);
		}

		$values = [];

		foreach ($value as $item) {
			$item = trim((string)$item);

			if ('' !== $item) {
				$values[] = $item;
			}
		}

		return implode('|', $values);

	}

	/**
	 * Extract geocoding results and normalize Google status errors.
	 *
	 * @param	array	$response	Decoded Google Geocoding response.
	 */
	private function extractResults(array $response): array {

		$status = strtoupper((string)($response['status'] ?? ''));

		if ('ZERO_RESULTS' === $status) {
			return [];
		}

		if ('OK' !== $status) {
			$message = trim((string)($response['error_message'] ?? 'Google geocoding failed with status ' . ($status ?: 'UNKNOWN') . '.'));
			throw new PairException($message, ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		return (isset($response['results']) and is_array($response['results'])) ? $response['results'] : [];

	}

}
