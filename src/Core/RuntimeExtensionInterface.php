<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Minimal runtime extension contract for explicit application bootstrap registration.
 */
interface RuntimeExtensionInterface {

	/**
	 * Register runtime services, adapters, routes, renderers, or configuration.
	 */
	public function register(Application $app): void;

}
