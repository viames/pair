<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Conventional adapter keys used by Pair core and optional extension packages.
 */
final class AdapterKeys {

	/**
	 * Cache or storage adapter key.
	 */
	public const CACHE = 'cache';

	/**
	 * Mail delivery adapter key.
	 */
	public const MAILER = 'mailer';

	/**
	 * Observability adapter key.
	 */
	public const OBSERVABILITY = 'observability';

	/**
	 * Payment gateway adapter key.
	 */
	public const PAYMENTS = 'payments';

	/**
	 * Notification delivery adapter key.
	 */
	public const NOTIFICATIONS = 'notifications';

	/**
	 * AI client adapter key.
	 */
	public const AI = 'ai';

	/**
	 * UI renderer adapter key.
	 */
	public const UI = 'ui';

	/**
	 * Static constants only.
	 */
	private function __construct() {}

}
