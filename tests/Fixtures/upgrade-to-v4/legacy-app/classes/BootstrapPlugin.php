<?php

declare(strict_types=1);

use Pair\Core\Application;
use Pair\Core\PluginInterface;

class BootstrapPlugin implements PluginInterface {

	/**
	 * Register runtime services for the legacy fixture.
	 */
	public function register(Application $app): void {

		$app->registerPlugin($this);

	}

}
