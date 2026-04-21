<?php

namespace Pair\Api;

/**
 * Value object for auto-CRUD resource configuration.
 *
 * Arrays remain accepted at public boundaries, but framework internals should use
 * this object so defaults and config shape stay centralized.
 */
final class CrudResourceConfig {

	/**
	 * Default configuration used by CRUD resources when no explicit override is provided.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = [
		'readModel'   => null,
		'resource'    => null,
		'searchable'  => [],
		'sortable'    => [],
		'filterable'  => [],
		'includes'    => [],
		'includeReadModels' => [],
		'includeResources' => [],
		'includePreloader' => null,
		'perPage'     => 20,
		'maxPerPage'  => 100,
		'rules'       => ['create' => [], 'update' => []],
		'defaultSort' => null,
	];

	/**
	 * Normalized configuration array.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Create a normalized CRUD resource configuration.
	 *
	 * @param	array<string, mixed>	$config	Configuration values merged with defaults.
	 */
	private function __construct(array $config) {

		$this->config = $config;

	}

	/**
	 * Return a value object from either an existing value object or a legacy array.
	 *
	 * @param	array<string, mixed>|self|null	$config	Resource configuration.
	 */
	public static function from(array|self|null $config): self {

		if ($config instanceof self) {
			return $config;
		}

		return self::fromArray($config ?? []);

	}

	/**
	 * Return a normalized value object from a legacy config array.
	 *
	 * @param	array<string, mixed>	$config	Resource configuration.
	 */
	public static function fromArray(array $config): self {

		$merged = array_merge(self::DEFAULTS, $config);
		$merged['readModel'] = is_string($merged['readModel']) ? $merged['readModel'] : null;
		$merged['resource'] = is_string($merged['resource']) ? $merged['resource'] : null;
		$merged['searchable'] = self::normalizeStringList($merged['searchable']);
		$merged['sortable'] = self::normalizeStringList($merged['sortable']);
		$merged['filterable'] = self::normalizeStringList($merged['filterable']);
		$merged['includes'] = self::normalizeStringList($merged['includes']);
		$merged['includeReadModels'] = self::normalizeStringMap($merged['includeReadModels']);
		$merged['includeResources'] = self::normalizeStringMap($merged['includeResources']);
		$merged['includePreloader'] = is_string($merged['includePreloader']) ? $merged['includePreloader'] : null;
		$merged['perPage'] = max(1, (int)$merged['perPage']);
		$merged['maxPerPage'] = max(1, (int)$merged['maxPerPage']);
		$merged['rules'] = self::normalizeRules($merged['rules']);
		$merged['defaultSort'] = is_string($merged['defaultSort']) ? $merged['defaultSort'] : null;

		return new self($merged);

	}

	/**
	 * Return validation rules for create requests.
	 *
	 * @return array<string, string>
	 */
	public function createRules(): array {

		return $this->config['rules']['create'];

	}

	/**
	 * Return the default sort string when one is configured.
	 */
	public function defaultSort(): ?string {

		return $this->config['defaultSort'];

	}

	/**
	 * Return properties allowed in filter query parameters.
	 *
	 * @return string[]
	 */
	public function filterable(): array {

		return $this->config['filterable'];

	}

	/**
	 * Return include names allowed in the public API.
	 *
	 * @return string[]
	 */
	public function includes(): array {

		return $this->config['includes'];

	}

	/**
	 * Return read-model classes keyed by include name.
	 *
	 * @return array<string, string>
	 */
	public function includeReadModels(): array {

		return $this->config['includeReadModels'];

	}

	/**
	 * Return legacy resource classes keyed by include name.
	 *
	 * @return array<string, string>
	 */
	public function includeResources(): array {

		return $this->config['includeResources'];

	}

	/**
	 * Return the optional bulk include preloader class.
	 */
	public function includePreloader(): ?string {

		return $this->config['includePreloader'];

	}

	/**
	 * Return the maximum allowed page size.
	 */
	public function maxPerPage(): int {

		return $this->config['maxPerPage'];

	}

	/**
	 * Return the default page size.
	 */
	public function perPage(): int {

		return $this->config['perPage'];

	}

	/**
	 * Return the configured read-model class, if any.
	 */
	public function readModel(): ?string {

		return $this->config['readModel'];

	}

	/**
	 * Return the configured legacy resource class, if any.
	 */
	public function resource(): ?string {

		return $this->config['resource'];

	}

	/**
	 * Return the full validation rule map.
	 *
	 * @return array{create: array<string, string>, update: array<string, string>}
	 */
	public function rules(): array {

		return $this->config['rules'];

	}

	/**
	 * Return properties allowed in search query parameters.
	 *
	 * @return string[]
	 */
	public function searchable(): array {

		return $this->config['searchable'];

	}

	/**
	 * Return properties allowed in sort query parameters.
	 *
	 * @return string[]
	 */
	public function sortable(): array {

		return $this->config['sortable'];

	}

	/**
	 * Return a legacy-compatible array representation.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {

		return $this->config;

	}

	/**
	 * Return validation rules for update requests.
	 *
	 * @return array<string, string>
	 */
	public function updateRules(): array {

		return $this->config['rules']['update'];

	}

	/**
	 * Normalize a map of field validation rules while preserving only string rules.
	 *
	 * @param	mixed	$rules	Raw validation rule map.
	 * @return	array<string, string>
	 */
	private static function normalizeRuleSet(mixed $rules): array {

		if (!is_array($rules)) {
			return [];
		}

		$normalized = [];

		foreach ($rules as $field => $rule) {
			if (is_string($field) and is_string($rule)) {
				$normalized[$field] = $rule;
			}
		}

		return $normalized;

	}

	/**
	 * Normalize create and update validation rules.
	 *
	 * @param	mixed	$rules	Raw CRUD validation rules.
	 * @return	array{create: array<string, string>, update: array<string, string>}
	 */
	private static function normalizeRules(mixed $rules): array {

		if (!is_array($rules)) {
			return self::DEFAULTS['rules'];
		}

		return [
			'create' => self::normalizeRuleSet($rules['create'] ?? []),
			'update' => self::normalizeRuleSet($rules['update'] ?? []),
		];

	}

	/**
	 * Normalize string lists while preserving order.
	 *
	 * @param	mixed	$values	Raw list value.
	 * @return string[]
	 */
	private static function normalizeStringList(mixed $values): array {

		if (!is_array($values)) {
			return [];
		}

		return array_values(array_filter($values, 'is_string'));

	}

	/**
	 * Normalize string maps used by include read-model and resource mappings.
	 *
	 * @param	mixed	$values	Raw map value.
	 * @return array<string, string>
	 */
	private static function normalizeStringMap(mixed $values): array {

		if (!is_array($values)) {
			return [];
		}

		$normalized = [];

		foreach ($values as $key => $value) {
			if (is_string($key) and is_string($value)) {
				$normalized[$key] = $value;
			}
		}

		return $normalized;

	}

}
