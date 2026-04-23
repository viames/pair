<?php

declare(strict_types=1);

use Pair\Helpers\Plugin;
use Pair\Helpers\PluginBase;

class ProviderPackage extends PluginBase {

	/**
	 * Return the provider installation folder.
	 */
	public function getBaseFolder(): string {

		return APPLICATION_PATH . '/providers';

	}

	/**
	 * Return the ZIP-backed provider plugin.
	 */
	public function getPlugin(): Plugin {

		return new Plugin('ProviderPackage', 'Provider', '1.0.0', '2026-04-23', '4.0.0', $this->getBaseFolder() . '/provider');

	}

	/**
	 * Return whether the provider plugin already exists.
	 */
	public static function pluginExists(string $name): bool {

		return false;

	}

	/**
	 * Store options loaded from the plugin manifest.
	 */
	public function storeByPlugin(\SimpleXMLElement $options): bool {

		return true;

	}

}
