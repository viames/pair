<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Server-side wrapper for Google Places API (New).
 * It exposes normalized autocomplete suggestions and
 * field-mask based place details retrieval.
 */
class GooglePlacesService {

	/**
	 * Default field mask for autocomplete responses.
	 */
	private const AUTOCOMPLETE_FIELD_MASK = 'suggestions.placePrediction.placeId,suggestions.placePrediction.text.text,suggestions.placePrediction.structuredFormat.mainText.text,suggestions.placePrediction.structuredFormat.secondaryText.text,suggestions.placePrediction.types,suggestions.placePrediction.distanceMeters,suggestions.queryPrediction.text.text';

	/**
	 * Default field mask for place detail responses.
	 */
	private const DEFAULT_PLACE_FIELDS = [
		'id',
		'displayName',
		'formattedAddress',
		'location',
		'addressComponents',
		'types',
		'viewport',
	];

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
	 * Retrieve autocomplete suggestions from Places API (New).
	 *
	 * Supported options:
	 * - include_query_predictions: bool
	 * - included_primary_types: string[]
	 * - included_region_codes: string[]
	 * - language: Google language code
	 * - location_bias: raw Google object
	 * - location_restriction: raw Google object
	 * - origin: ['latitude' => ..., 'longitude' => ...]
	 * - region: region code
	 * - session_token: billing session token
	 */
	public function autocomplete(string $input, array $options = []): array {

		$input = trim($input);

		if ('' === $input) {
			return [];
		}

		$payload = [
			'input' => $input,
		];

		if (!empty($options['include_query_predictions'])) {
			$payload['includeQueryPredictions'] = true;
		}

		if (isset($options['included_primary_types']) and is_array($options['included_primary_types']) and count($options['included_primary_types'])) {
			$payload['includedPrimaryTypes'] = array_values(array_filter(array_map('strval', $options['included_primary_types'])));
		}

		if (isset($options['included_region_codes']) and is_array($options['included_region_codes']) and count($options['included_region_codes'])) {
			$payload['includedRegionCodes'] = array_values(array_filter(array_map(function(mixed $value) {
				return strtolower(trim((string)$value));
			}, $options['included_region_codes'])));
		}

		if (isset($options['language']) and '' !== trim((string)$options['language'])) {
			$payload['languageCode'] = trim((string)$options['language']);
		}

		if (isset($options['region']) and '' !== trim((string)$options['region'])) {
			$payload['regionCode'] = strtolower(trim((string)$options['region']));
		}

		if (isset($options['location_bias']) and is_array($options['location_bias'])) {
			$payload['locationBias'] = $options['location_bias'];
		}

		if (isset($options['location_restriction']) and is_array($options['location_restriction'])) {
			$payload['locationRestriction'] = $options['location_restriction'];
		}

		if (isset($options['origin']) and is_array($options['origin']) and isset($options['origin']['latitude']) and isset($options['origin']['longitude'])) {
			$payload['origin'] = [
				'latitude'	=> (float)$options['origin']['latitude'],
				'longitude'	=> (float)$options['origin']['longitude'],
			];
		}

		if (isset($options['session_token']) and '' !== trim((string)$options['session_token'])) {
			$payload['sessionToken'] = trim((string)$options['session_token']);
		}

		$response = $this->client->placesPost('/v1/places:autocomplete', $payload, self::AUTOCOMPLETE_FIELD_MASK);

		return $this->normalizeSuggestions($response);

	}

	/**
	 * Fetch place details using the Places API (New).
	 *
	 * @param	string	$placeId	Google place ID.
	 * @param	array	$fields		Field mask list. Defaults to a small essentials set.
	 * @param	array	$options		Optional language, region and session_token filters.
	 */
	public function getPlace(string $placeId, array $fields = [], array $options = []): array {

		$placeId = trim($placeId);

		if ('' === $placeId) {
			throw new PairException('Google place ID cannot be empty.', ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		$fieldMask = $this->normalizeFieldMask($fields ?: self::DEFAULT_PLACE_FIELDS);
		$query = [];

		if (isset($options['language']) and '' !== trim((string)$options['language'])) {
			$query['languageCode'] = trim((string)$options['language']);
		}

		if (isset($options['region']) and '' !== trim((string)$options['region'])) {
			$query['regionCode'] = strtolower(trim((string)$options['region']));
		}

		if (isset($options['session_token']) and '' !== trim((string)$options['session_token'])) {
			$query['sessionToken'] = trim((string)$options['session_token']);
		}

		return $this->client->placesGet('/v1/places/' . rawurlencode($placeId), $query, $fieldMask);

	}

	/**
	 * Normalize autocomplete suggestions into framework-friendly arrays.
	 *
	 * @param	array	$response	Decoded autocomplete response.
	 */
	private function normalizeSuggestions(array $response): array {

		$suggestions = [];

		foreach (($response['suggestions'] ?? []) as $item) {
			if (isset($item['placePrediction']) and is_array($item['placePrediction'])) {
				$prediction = $item['placePrediction'];
				$suggestions[] = [
					'type'			=> 'place',
					'placeId'		=> $prediction['placeId'] ?? null,
					'text'			=> $prediction['text']['text'] ?? null,
					'mainText'		=> $prediction['structuredFormat']['mainText']['text'] ?? null,
					'secondaryText'	=> $prediction['structuredFormat']['secondaryText']['text'] ?? null,
					'types'			=> (isset($prediction['types']) and is_array($prediction['types'])) ? $prediction['types'] : [],
					'distanceMeters'=> $prediction['distanceMeters'] ?? null,
				];
				continue;
			}

			if (isset($item['queryPrediction']) and is_array($item['queryPrediction'])) {
				$queryPrediction = $item['queryPrediction'];
				$suggestions[] = [
					'type'	=> 'query',
					'text'	=> $queryPrediction['text']['text'] ?? null,
				];
			}
		}

		return $suggestions;

	}

	/**
	 * Normalize a field mask array into the format expected by Google.
	 *
	 * @param	string[]	$fields	Field names requested for place details.
	 */
	private function normalizeFieldMask(array $fields): string {

		$normalizedFields = [];

		foreach ($fields as $field) {
			$field = trim((string)$field);

			if ('' !== $field and !in_array($field, $normalizedFields)) {
				$normalizedFields[] = $field;
			}
		}

		if (!count($normalizedFields)) {
			throw new PairException('Google place details field mask cannot be empty.', ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		return implode(',', $normalizedFields);

	}

}
