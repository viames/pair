<?php

namespace Pair\Api\OpenApi;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * Generates OpenAPI 3.1 schema objects from ActiveRecord classes and typed
 * read-model/data-transfer classes.
 */
class SchemaGenerator {

	/**
	 * MySQL types mapped to OpenAPI/JSON Schema types.
	 */
	private const TYPE_MAP = [
		// integers
		'tinyint'    => ['type' => 'integer'],
		'smallint'   => ['type' => 'integer'],
		'mediumint'  => ['type' => 'integer'],
		'int'        => ['type' => 'integer'],
		'bigint'     => ['type' => 'integer'],
		// floats
		'float'      => ['type' => 'number', 'format' => 'float'],
		'double'     => ['type' => 'number', 'format' => 'double'],
		'decimal'    => ['type' => 'number'],
		// strings
		'char'       => ['type' => 'string'],
		'varchar'    => ['type' => 'string'],
		'tinytext'   => ['type' => 'string'],
		'text'       => ['type' => 'string'],
		'mediumtext' => ['type' => 'string'],
		'longtext'   => ['type' => 'string'],
		// dates
		'date'       => ['type' => 'string', 'format' => 'date'],
		'datetime'   => ['type' => 'string', 'format' => 'date-time'],
		'timestamp'  => ['type' => 'string', 'format' => 'date-time'],
		'time'       => ['type' => 'string', 'format' => 'time'],
		'year'       => ['type' => 'integer'],
		// binary
		'blob'       => ['type' => 'string', 'format' => 'binary'],
		'tinyblob'   => ['type' => 'string', 'format' => 'binary'],
		'mediumblob' => ['type' => 'string', 'format' => 'binary'],
		'longblob'   => ['type' => 'string', 'format' => 'binary'],
		// json
		'json'       => ['type' => 'object'],
		// boolean
		'bit'        => ['type' => 'boolean'],
	];

	/**
	 * Cached full schemas keyed by source class name.
	 *
	 * @var array<string, array>
	 */
	private array $schemaCache = [];

	/**
	 * Cached create request schemas keyed by source class and rule hash.
	 *
	 * @var array<string, array>
	 */
	private array $createSchemaCache = [];

	/**
	 * Cached update request schemas keyed by source class and rule hash.
	 *
	 * @var array<string, array>
	 */
	private array $updateSchemaCache = [];

	/**
	 * Clear generated schema caches, optionally for one source class.
	 *
	 * @param	string|null	$modelClass	Optional class to invalidate.
	 */
	public function clearCache(?string $modelClass = null): void {

		if (null === $modelClass) {
			$this->schemaCache = [];
			$this->createSchemaCache = [];
			$this->updateSchemaCache = [];
			return;
		}

		unset($this->schemaCache[$modelClass]);
		$this->clearRequestSchemaCacheFor($this->createSchemaCache, $modelClass);
		$this->clearRequestSchemaCacheFor($this->updateSchemaCache, $modelClass);

	}

	/**
	 * Generate the full OpenAPI schema for a persistence model or read-model class.
	 *
	 * @param	string	$modelClass	Fully qualified class name.
	 * @return	array	OpenAPI schema object.
	 */
	public function generate(string $modelClass): array {

		if (!class_exists($modelClass)) {
			throw new \InvalidArgumentException('Class ' . $modelClass . ' was not found');
		}

		if (array_key_exists($modelClass, $this->schemaCache)) {
			return $this->schemaCache[$modelClass];
		}

		// Allow explicit schema overrides for response models that need full control.
		if (is_callable([$modelClass, 'openApiSchema'])) {
			$this->schemaCache[$modelClass] = $modelClass::openApiSchema();
			return $this->schemaCache[$modelClass];
		}

		if (!is_subclass_of($modelClass, ActiveRecord::class)) {
			$this->schemaCache[$modelClass] = $this->generateTypedObjectSchema($modelClass);
			return $this->schemaCache[$modelClass];
		}

		$this->schemaCache[$modelClass] = $this->generateActiveRecordSchema($modelClass);

		return $this->schemaCache[$modelClass];

	}

	/**
	 * Generate the full OpenAPI schema for an ActiveRecord class.
	 *
	 * @param	string	$modelClass	Fully qualified ActiveRecord class name.
	 * @return	array	OpenAPI schema object.
	 */
	private function generateActiveRecordSchema(string $modelClass): array {

		$db = Database::getInstance();
		$columns = $db->describeTable($modelClass::TABLE_NAME);
		$binds = $modelClass::getBinds();
		$tableKey = (array)$modelClass::TABLE_KEY;

		$properties = [];
		$required = [];

		foreach ($columns as $col) {

			$property = $this->columnToPropertyName($col->Field, $binds);

			if (!$property) {
				continue;
			}

			$schema = $this->columnToSchema($col);

			// add to properties
			$properties[$property] = $schema;

			// determine required fields (non-nullable, no default, not auto-increment)
			$isKey = in_array($col->Field, $tableKey);
			$isAutoIncrement = str_contains($col->Extra ?? '', 'auto_increment');

			if ($col->Null === 'NO' and is_null($col->Default) and !$isAutoIncrement and !$isKey) {
				$required[] = $property;
			}

		}

		$schema = [
			'type'       => 'object',
			'properties' => $properties,
		];

		if (count($required)) {
			$schema['required'] = $required;
		}

		return $schema;

	}

	/**
	 * Generate a schema for the create request body, excluding auto-generated fields.
	 *
	 * @param	string	$modelClass	Fully qualified ActiveRecord class name.
	 * @param	array	$rules		Validation rules from apiConfig.
	 * @return	array	OpenAPI schema object for the request body.
	 */
	public function generateCreateSchema(string $modelClass, array $rules = []): array {

		$cacheKey = $this->requestSchemaCacheKey($modelClass, $rules);

		if (array_key_exists($cacheKey, $this->createSchemaCache)) {
			return $this->createSchemaCache[$cacheKey];
		}

		$isActiveRecord = is_subclass_of($modelClass, ActiveRecord::class);
		$schema = $isActiveRecord
			? $this->generateActiveRecordSchema($modelClass)
			: $this->generate($modelClass);
		$tableKey = $isActiveRecord ? (array)$modelClass::TABLE_KEY : [];
		$db = $isActiveRecord ? Database::getInstance() : null;

		// remove auto-increment keys and auto-populated fields
		$autoFields = ['createdAt', 'createdBy', 'updatedAt', 'updatedBy'];

		foreach ($autoFields as $field) {
			unset($schema['properties'][$field]);
		}

		// remove auto-increment primary key
		if ($db and $db->isAutoIncrement($modelClass::TABLE_NAME)) {
			$binds = $modelClass::getBinds();
			foreach ($tableKey as $keyField) {
				$property = array_search($keyField, $binds);
				if ($property !== false) {
					unset($schema['properties'][$property]);
				}
			}
		}

		// override required from validation rules
		if (count($rules)) {
			$required = [];
			foreach ($rules as $field => $ruleString) {
				if (str_contains($ruleString, 'required')) {
					$required[] = $field;
				}
			}
			$schema['required'] = $required;
		}

		// filter to only properties mentioned in rules, if any
		if (count($rules)) {
			$schema['properties'] = array_intersect_key(
				$schema['properties'],
				$rules
			);
		}

		$this->createSchemaCache[$cacheKey] = $schema;

		return $this->createSchemaCache[$cacheKey];

	}

	/**
	 * Generate a schema for the update request body (all fields optional).
	 *
	 * @param	string	$modelClass	Fully qualified ActiveRecord class name.
	 * @param	array	$rules		Validation rules from apiConfig.
	 * @return	array	OpenAPI schema object for the request body.
	 */
	public function generateUpdateSchema(string $modelClass, array $rules = []): array {

		$cacheKey = $this->requestSchemaCacheKey($modelClass, $rules);

		if (array_key_exists($cacheKey, $this->updateSchemaCache)) {
			return $this->updateSchemaCache[$cacheKey];
		}

		$schema = $this->generateCreateSchema($modelClass, $rules);

		// all fields optional on update
		unset($schema['required']);

		$this->updateSchemaCache[$cacheKey] = $schema;

		return $this->updateSchemaCache[$cacheKey];

	}

	/**
	 * Remove request schema cache entries for one source class.
	 *
	 * @param	array<string, array>	$cache		Request schema cache passed by reference.
	 * @param	string					$modelClass	Source class name to invalidate.
	 */
	private function clearRequestSchemaCacheFor(array &$cache, string $modelClass): void {

		$prefix = $modelClass . "\0";

		foreach (array_keys($cache) as $key) {
			if (str_starts_with($key, $prefix)) {
				unset($cache[$key]);
			}
		}

	}

	/**
	 * Build a stable cache key for request schemas with validation rules.
	 *
	 * @param	string	$modelClass	Source class name.
	 * @param	array	$rules		Validation rules.
	 */
	private function requestSchemaCacheKey(string $modelClass, array $rules): string {

		return $modelClass . "\0" . sha1(serialize($rules));

	}

	/**
	 * Convert a database column descriptor to an OpenAPI property schema.
	 *
	 * @param	\stdClass	$col	Column descriptor from describeTable.
	 * @return	array		OpenAPI property schema.
	 */
	private function columnToSchema(\stdClass $col): array {

		// parse the type string
		preg_match('#^([\w]+)(\(([^\)]+)\))?\s*(unsigned)?#i', $col->Type, $matches);

		$mysqlType = strtolower($matches[1] ?? 'varchar');
		$length = $matches[3] ?? null;
		$unsigned = !empty($matches[4]);
		$nullable = $col->Null === 'YES';

		// handle tinyint(1) as boolean
		if ($mysqlType === 'tinyint' and $length === '1') {
			$schema = ['type' => 'boolean'];
		}
		// handle enum
		else if ($mysqlType === 'enum') {
			$values = array_map(function ($v) {
				return trim($v, "'");
			}, explode(',', $length ?? ''));
			$schema = ['type' => 'string', 'enum' => $values];
		}
		// handle set
		else if ($mysqlType === 'set') {
			$values = array_map(function ($v) {
				return trim($v, "'");
			}, explode(',', $length ?? ''));
			$schema = [
				'type'  => 'array',
				'items' => ['type' => 'string', 'enum' => $values],
			];
		}
		// standard type mapping
		else if (isset(self::TYPE_MAP[$mysqlType])) {
			$schema = self::TYPE_MAP[$mysqlType];
		}
		// fallback
		else {
			$schema = ['type' => 'string'];
		}

		// add string max length
		if (($schema['type'] ?? '') === 'string' and $length and is_numeric($length)) {
			$schema['maxLength'] = (int)$length;
		}

		// handle nullable
		if ($nullable and isset($schema['type'])) {
			$schema['type'] = [$schema['type'], 'null'];
		}

		// add description from column default
		if (!is_null($col->Default) and $col->Default !== '') {
			$schema['default'] = $this->castDefault($col->Default, $mysqlType);
		}

		// mark read-only for auto-increment and virtual columns
		if (str_contains($col->Extra ?? '', 'auto_increment') or str_contains($col->Extra ?? '', 'VIRTUAL')) {
			$schema['readOnly'] = true;
		}

		return $schema;

	}

	/**
	 * Convert a column name to its camelCase property name.
	 *
	 * @param	string	$field	DB column name.
	 * @param	array	$binds	Property-to-column bindings from the model.
	 * @return	string|null		Property name or null if not found.
	 */
	private function columnToPropertyName(string $field, array $binds): ?string {

		$property = array_search($field, $binds);
		return $property !== false ? $property : null;

	}

	/**
	 * Cast a MySQL default value to the appropriate PHP type for JSON output.
	 *
	 * @param	string	$default	The default value from MySQL.
	 * @param	string	$mysqlType	The MySQL column type.
	 * @return	mixed
	 */
	private function castDefault(string $default, string $mysqlType): mixed {

		$intTypes = ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'year'];
		$floatTypes = ['float', 'double', 'decimal'];

		if (in_array($mysqlType, $intTypes)) {
			return (int)$default;
		}

		if (in_array($mysqlType, $floatTypes)) {
			return (float)$default;
		}

		// CURRENT_TIMESTAMP and similar expressions
		if (strtoupper($default) === 'CURRENT_TIMESTAMP') {
			return null;
		}

		return $default;

	}

	/**
	 * Generate a schema by reading the public typed properties of a plain PHP class.
	 *
	 * @param	string	$className	Fully qualified class name.
	 * @return	array	OpenAPI schema object.
	 */
	private function generateTypedObjectSchema(string $className): array {

		$reflection = new \ReflectionClass($className);
		$properties = [];
		$required = [];

		foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {

			if ($property->isStatic()) {
				continue;
			}

			$properties[$property->getName()] = $this->reflectionTypeToSchema($property->getType());

			if (!$property->getType()?->allowsNull()) {
				$required[] = $property->getName();
			}

		}

		$schema = [
			'type' => 'object',
			'properties' => $properties,
		];

		if (count($required)) {
			$schema['required'] = $required;
		}

		return $schema;

	}

	/**
	 * Convert a reflection type into an OpenAPI schema fragment.
	 */
	private function reflectionTypeToSchema(?\ReflectionType $type): array|\stdClass {

		if (is_null($type)) {
			return new \stdClass();
		}

		if ($type instanceof \ReflectionUnionType) {
			return $this->reflectionUnionTypeToSchema($type);
		}

		if (!$type instanceof \ReflectionNamedType) {
			return new \stdClass();
		}

		$schema = $this->namedReflectionTypeToSchema($type);

		if ($type->allowsNull()) {
			return $this->makeSchemaNullable($schema);
		}

		return $schema;

	}

	/**
	 * Convert a named reflection type into an OpenAPI schema fragment.
	 */
	private function namedReflectionTypeToSchema(\ReflectionNamedType $type): array|\stdClass {

		if ($type->isBuiltin()) {
			return match ($type->getName()) {
				'int' => ['type' => 'integer'],
				'float' => ['type' => 'number'],
				'string' => ['type' => 'string'],
				'bool' => ['type' => 'boolean'],
				'array' => ['type' => 'array', 'items' => new \stdClass()],
				'object' => ['type' => 'object'],
				'mixed' => new \stdClass(),
				default => ['type' => 'string'],
			};
		}

		$typeName = $type->getName();

		if (is_a($typeName, \DateTimeInterface::class, true)) {
			return ['type' => 'string', 'format' => 'date-time'];
		}

		if (enum_exists($typeName)) {
			return $this->enumToSchema($typeName);
		}

		// Inline nested typed objects to keep documentation aligned with the explicit read contract.
		return $this->generate($typeName);

	}

	/**
	 * Convert a union reflection type into an OpenAPI schema fragment.
	 */
	private function reflectionUnionTypeToSchema(\ReflectionUnionType $type): array {

		$schemas = [];
		$nullable = false;

		foreach ($type->getTypes() as $namedType) {

			if ($namedType instanceof \ReflectionNamedType and $namedType->getName() === 'null') {
				$nullable = true;
				continue;
			}

			$schemas[] = $this->namedReflectionTypeToSchema($namedType);

		}

		if (!count($schemas)) {
			return ['type' => 'null'];
		}

		if (count($schemas) === 1) {
			return $nullable ? $this->makeSchemaNullable($schemas[0]) : $schemas[0];
		}

		if ($nullable) {
			$schemas[] = ['type' => 'null'];
		}

		return ['anyOf' => $schemas];

	}

	/**
	 * Make a schema fragment nullable while preserving its main structure.
	 */
	private function makeSchemaNullable(array|\stdClass $schema): array {

		if ($schema instanceof \stdClass) {
			return ['anyOf' => [$schema, ['type' => 'null']]];
		}

		if (isset($schema['type']) and is_string($schema['type'])) {
			$schema['type'] = [$schema['type'], 'null'];
			return $schema;
		}

		if (isset($schema['anyOf']) and is_array($schema['anyOf'])) {
			$schema['anyOf'][] = ['type' => 'null'];
			return $schema;
		}

		return ['anyOf' => [$schema, ['type' => 'null']]];

	}

	/**
	 * Convert a PHP enum class into an OpenAPI schema fragment.
	 *
	 * @param	class-string	$enumClass	Fully qualified enum class name.
	 */
	private function enumToSchema(string $enumClass): array {

		$reflection = new \ReflectionEnum($enumClass);
		$cases = $enumClass::cases();

		if ($reflection->isBacked()) {

			$backingType = $reflection->getBackingType()?->getName() ?? 'string';
			$values = array_map(static function(\BackedEnum $case): string|int {
				return $case->value;
			}, $cases);

			return [
				'type' => $backingType === 'int' ? 'integer' : 'string',
				'enum' => $values,
			];

		}

		return [
			'type' => 'string',
			'enum' => array_map(static function(\UnitEnum $case): string {
				return $case->name;
			}, $cases),
		];

	}

}
