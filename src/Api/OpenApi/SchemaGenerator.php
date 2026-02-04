<?php

namespace Pair\Api\OpenApi;

use Pair\Orm\ActiveRecord;
use Pair\Orm\Database;

/**
 * Generates OpenAPI 3.1 schema objects from ActiveRecord model classes.
 * Reads table structure, property types, and validation rules to produce
 * accurate JSON Schema definitions.
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
	 * Generate the full OpenAPI schema for a model class.
	 *
	 * @param	string	$modelClass	Fully qualified ActiveRecord class name.
	 * @return	array	OpenAPI schema object.
	 */
	public function generate(string $modelClass): array {

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

		$schema = $this->generate($modelClass);
		$tableKey = (array)$modelClass::TABLE_KEY;
		$db = Database::getInstance();

		// remove auto-increment keys and auto-populated fields
		$autoFields = ['createdAt', 'createdBy', 'updatedAt', 'updatedBy'];

		foreach ($autoFields as $field) {
			unset($schema['properties'][$field]);
		}

		// remove auto-increment primary key
		if ($db->isAutoIncrement($modelClass::TABLE_NAME)) {
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

		return $schema;

	}

	/**
	 * Generate a schema for the update request body (all fields optional).
	 *
	 * @param	string	$modelClass	Fully qualified ActiveRecord class name.
	 * @param	array	$rules		Validation rules from apiConfig.
	 * @return	array	OpenAPI schema object for the request body.
	 */
	public function generateUpdateSchema(string $modelClass, array $rules = []): array {

		$schema = $this->generateCreateSchema($modelClass, $rules);

		// all fields optional on update
		unset($schema['required']);

		return $schema;

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

}
