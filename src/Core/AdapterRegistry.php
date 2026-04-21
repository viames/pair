<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Process-local registry for explicitly configured framework adapters.
 */
final class AdapterRegistry {

	/**
	 * Registered adapters keyed by normalized capability name.
	 *
	 * @var	array<string, object>
	 */
	private array $adapters = [];

	/**
	 * Remove every adapter from the registry.
	 */
	public function clear(): void {

		$this->adapters = [];

	}

	/**
	 * Return one adapter or null when it has not been registered.
	 *
	 * @param	string		$name			Capability name.
	 * @param	string|null	$expectedType	Optional class or interface the adapter must implement.
	 */
	public function get(string $name, ?string $expectedType = null): ?object {

		$key = $this->normalizeName($name);
		$adapter = $this->adapters[$key] ?? null;

		if (!$adapter) {
			return null;
		}

		$this->assertExpectedType($key, $adapter, $expectedType);

		return $adapter;

	}

	/**
	 * Return every registered adapter.
	 *
	 * @return	array<string, object>
	 */
	public function all(): array {

		return $this->adapters;

	}

	/**
	 * Return whether an adapter has been registered.
	 */
	public function has(string $name): bool {

		return array_key_exists($this->normalizeName($name), $this->adapters);

	}

	/**
	 * Remove one adapter from the registry.
	 */
	public function remove(string $name): void {

		unset($this->adapters[$this->normalizeName($name)]);

	}

	/**
	 * Return one registered adapter or fail explicitly when missing.
	 *
	 * @param	string		$name			Capability name.
	 * @param	string|null	$expectedType	Optional class or interface the adapter must implement.
	 */
	public function require(string $name, ?string $expectedType = null): object {

		$key = $this->normalizeName($name);
		$adapter = $this->get($key, $expectedType);

		if (!$adapter) {
			throw new \RuntimeException('Pair adapter "' . $key . '" has not been registered.');
		}

		return $adapter;

	}

	/**
	 * Register or replace one adapter for a capability.
	 */
	public function set(string $name, object $adapter): void {

		$this->adapters[$this->normalizeName($name)] = $adapter;

	}

	/**
	 * Fail when an adapter does not implement the expected contract.
	 */
	private function assertExpectedType(string $name, object $adapter, ?string $expectedType): void {

		if (!$expectedType or is_a($adapter, $expectedType)) {
			return;
		}

		throw new \InvalidArgumentException('Pair adapter "' . $name . '" must implement ' . $expectedType . '.');

	}

	/**
	 * Normalize capability names into explicit stable keys.
	 */
	private function normalizeName(string $name): string {

		$key = strtolower(trim($name));

		if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $key)) {
			throw new \InvalidArgumentException('Invalid Pair adapter name: ' . $name);
		}

		return $key;

	}

}
