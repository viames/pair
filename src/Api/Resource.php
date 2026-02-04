<?php

namespace Pair\Api;

/**
 * Abstract class for transforming objects (usually ActiveRecord) into arrays for API responses.
 * Decouples the database model from the JSON response.
 */
abstract class Resource implements \JsonSerializable {

	/**
	 * The underlying data object.
	 */
	protected mixed $data;

	/**
	 * Create a new resource instance.
	 */
	public function __construct(mixed $data) {

		$this->data = $data;

	}

	/**
	 * Transform the resource into an array.
	 */
	abstract public function toArray(): array;

	/**
	 * Serialize the resource to a value that can be serialized natively by json_encode().
	 */
	public function jsonSerialize(): array {

		return $this->toArray();

	}

	/**
	 * Transform a collection of items into an array of resource arrays.
	 */
	public static function collection(iterable $items): array {

		$collection = [];

		foreach ($items as $item) {
			$collection[] = (new static($item))->toArray();
		}

		return $collection;

	}

}
