<?php

namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Lightweight HTTP client for Google Maps Platform web services.
 * It centralizes API key handling, timeouts, JSON decoding and
 * HTTP-level error normalization for Pair integrations.
 */
class GoogleMapsClient {

	/**
	 * Default connection timeout in seconds.
	 */
	private const DEFAULT_CONNECT_TIMEOUT = 5;

	/**
	 * Default request timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 15;

	/**
	 * Base URL for Google Geocoding API requests.
	 */
	private string $geocodingBaseUrl;

	/**
	 * Google Maps Platform server-side API key.
	 */
	private string $apiKey;

	/**
	 * Base URL for Google Places API (New) requests.
	 */
	private string $placesBaseUrl;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * HTTP request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Constructor.
	 *
	 * @param	string|null	$apiKey				Server-side Google Maps API key.
	 * @param	int|null		$timeout			Request timeout in seconds.
	 * @param	int|null		$connectTimeout		Connect timeout in seconds.
	 * @param	string|null	$geocodingBaseUrl	Optional geocoding base URL override.
	 * @param	string|null	$placesBaseUrl		Optional places base URL override.
	 */
	public function __construct(?string $apiKey = null, ?int $timeout = null, ?int $connectTimeout = null, ?string $geocodingBaseUrl = null, ?string $placesBaseUrl = null) {

		$this->apiKey = trim((string)($apiKey ?? Env::get('GOOGLE_MAPS_API_KEY')));
		$this->timeout = max(1, (int)($timeout ?? Env::get('GOOGLE_MAPS_TIMEOUT') ?? self::DEFAULT_TIMEOUT));
		$this->connectTimeout = max(1, (int)($connectTimeout ?? Env::get('GOOGLE_MAPS_CONNECT_TIMEOUT') ?? self::DEFAULT_CONNECT_TIMEOUT));
		$this->geocodingBaseUrl = rtrim((string)($geocodingBaseUrl ?? Env::get('GOOGLE_MAPS_GEOCODING_BASE_URL') ?? 'https://maps.googleapis.com'), '/');
		$this->placesBaseUrl = rtrim((string)($placesBaseUrl ?? Env::get('GOOGLE_MAPS_PLACES_BASE_URL') ?? 'https://places.googleapis.com'), '/');

		if ('' === $this->apiKey) {
			throw new PairException('Missing Google Maps API key. Set GOOGLE_MAPS_API_KEY.', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Perform a GET request against the Google Geocoding API.
	 *
	 * @param	string	$path	Path relative to the geocoding base URL.
	 * @param	array	$query	Query-string parameters.
	 */
	public function geocodingGet(string $path, array $query = []): array {

		$query['key'] = $this->apiKey;

		return $this->request('GET', $this->geocodingBaseUrl . $path, $query);

	}

	/**
	 * Perform a GET request against the Google Places API (New).
	 *
	 * @param	string		$path		Path relative to the places base URL.
	 * @param	array		$query		Query-string parameters.
	 * @param	string|null	$fieldMask	Optional response field mask.
	 */
	public function placesGet(string $path, array $query = [], ?string $fieldMask = null): array {

		$headers = [
			'X-Goog-Api-Key: ' . $this->apiKey,
		];

		if ($fieldMask) {
			$headers[] = 'X-Goog-FieldMask: ' . $fieldMask;
		}

		return $this->request('GET', $this->placesBaseUrl . $path, $query, null, $headers);

	}

	/**
	 * Perform a POST request against the Google Places API (New).
	 *
	 * @param	string		$path		Path relative to the places base URL.
	 * @param	array		$payload	JSON payload.
	 * @param	string|null	$fieldMask	Optional response field mask.
	 */
	public function placesPost(string $path, array $payload = [], ?string $fieldMask = null): array {

		$headers = [
			'X-Goog-Api-Key: ' . $this->apiKey,
		];

		if ($fieldMask) {
			$headers[] = 'X-Goog-FieldMask: ' . $fieldMask;
		}

		return $this->request('POST', $this->placesBaseUrl . $path, [], $payload, $headers);

	}

	/**
	 * Execute an HTTP JSON request.
	 *
	 * @param	string		$method		HTTP method.
	 * @param	string		$url		Absolute URL.
	 * @param	array		$query		Query-string parameters.
	 * @param	array|null	$payload	Optional JSON payload.
	 * @param	string[]	$headers	Additional request headers.
	 */
	private function request(string $method, string $url, array $query = [], ?array $payload = null, array $headers = []): array {

		$method = strtoupper(trim($method));
		$finalUrl = $this->buildUrl($url, $query);
		$curl = curl_init($finalUrl);

		if (false === $curl) {
			throw new PairException('Unable to initialize Google Maps HTTP client.', ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		$httpHeaders = array_merge([
			'Accept: application/json',
		], $headers);

		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHeaders);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);

		if (!is_null($payload)) {
			$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);

			if (false === $encodedPayload) {
				curl_close($curl);
				throw new PairException('Unable to encode Google Maps request payload.', ErrorCodes::GOOGLE_MAPS_ERROR);
			}

			$httpHeaders[] = 'Content-Type: application/json';
			curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHeaders);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $encodedPayload);
		}

		$responseBody = curl_exec($curl);
		$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		if (false === $responseBody) {
			$error = curl_error($curl);
			curl_close($curl);
			throw new PairException('Google Maps request failed: ' . $error, ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		curl_close($curl);

		if ('' === trim($responseBody)) {
			return [];
		}

		$decodedResponse = json_decode($responseBody, true);

		if (!is_array($decodedResponse)) {
			throw new PairException('Google Maps returned an invalid JSON response.', ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		if ($statusCode >= 400) {
			$message = $this->resolveErrorMessage($decodedResponse);
			throw new PairException($message, ErrorCodes::GOOGLE_MAPS_ERROR);
		}

		return $decodedResponse;

	}

	/**
	 * Build the final request URL with query-string parameters.
	 *
	 * @param	string	$url	Absolute URL.
	 * @param	array	$query	Query-string parameters.
	 */
	private function buildUrl(string $url, array $query = []): string {

		if (!count($query)) {
			return $url;
		}

		return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);

	}

	/**
	 * Extract the most useful error message from a Google response payload.
	 *
	 * @param	array	$response	Decoded JSON response.
	 */
	private function resolveErrorMessage(array $response): string {

		if (isset($response['error']['message']) and is_string($response['error']['message']) and '' !== trim($response['error']['message'])) {
			return trim($response['error']['message']);
		}

		if (isset($response['error_message']) and is_string($response['error_message']) and '' !== trim($response['error_message'])) {
			return trim($response['error_message']);
		}

		if (isset($response['status']) and is_string($response['status']) and '' !== trim($response['status'])) {
			return 'Google Maps request failed with status ' . trim($response['status']) . '.';
		}

		return 'Google Maps request failed.';

	}

}
