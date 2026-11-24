<?php

namespace Pair\Push;

/** Identifies endpoints operated by the supported browser push services. */
final class PushEndpoint {

	private const HOSTS = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'];

	/** Validates the complete URL before applications accept a browser subscription. */
	public static function isSupported(mixed $endpoint): bool {

		if (!is_string($endpoint) || strlen($endpoint) > 2048
			|| preg_match('/[^\x21-\x7e]/', $endpoint) || str_contains($endpoint, '\\')
			|| !filter_var($endpoint, FILTER_VALIDATE_URL)) {
			return false;
		}

		$url = parse_url($endpoint);
		$host = strtolower($url['host'] ?? '');

		return ($url['scheme'] ?? '') === 'https'
			&& ($url['port'] ?? 443) === 443 && !empty($url['path'])
			&& !isset($url['user']) && !isset($url['pass']) && !isset($url['fragment'])
			&& (in_array($host, self::HOSTS, true) || self::isWindowsHost($host));

	}

	/** Matches a single DNS label on the WNS service domain used by Microsoft Edge. */
	public static function isWindowsHost(string $host): bool {

		return preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.notify\.windows\.com\z/i', $host) === 1;

	}

}
