<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Minimal runtime plugin contract for explicit application bootstrap registration.
 */
interface PluginInterface {

	/**
	 * Register plugin services, adapters, routes, or runtime configuration.
	 */
	public function register(Application $app): void;

}
