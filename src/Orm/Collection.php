<?php

namespace Pair\Orm;

use Pair\Orm\ActiveRecord;

/**
 * Class inspired by Eloquent Collection, adapted to Pair’s ActiveRecord.
 */
class Collection implements \ArrayAccess, \Iterator, \Countable {

	/**
	 * The items contained in the collection.
	 */
	protected array $items;

	/**
	 * The current position of the iterator.
	 */
	private int $position = 0;

	/**
	 * Create a new collection.
	 */
	public function __construct(?array $array=NULL) {

		// ActiveRecord objects indexed by their ID doesn't work with Iterator valid() method

		/*
		// for performance reasons, only the first element of the array is checked for validity
		if (is_array($array) and isset($array[0]) and $array[0] instanceof ActiveRecord) {

			if ($array[0]::hasSimpleKey()) {

				// if the first element is an ActiveRecord object, the array is indexed by the object ID
				foreach ($array as $activeRecord) {
					$this->items[$activeRecord->getId()] = $activeRecord;
				}

			} else {

				foreach ($array as $activeRecord) {
					$this->items[] = $activeRecord;
				}

			}

		} else {

			$this->items = (array)$array;

		}
		*/

		$this->items = (array)$array;

	}

	/**
	 * Add an item to the collection.
	 */
	public function add(ActiveRecord $item): static {

		$this->items[] = $item;

		return $this;

	}

	/**
	 * Returns the underlying array represented by the collection.
	 * @return mixed[]
	 */
	public function all(): array {

		return $this->items;

	}

	/**
	 * Indicate that an attribute should be appended for every model in the collection.
	 * This method accepts an array of attributes or a single attribute.
	 */
	public function append(string|array $attributes): static {

		$items = [];

		foreach ($this->items as $item) {

			$items[] = $item->append($attributes);

		}

		return new static($items);

	}

	/**
	 * Returns the average value of a given key.
	 */
	public function avg(?string $key=NULL): float {

		$sum = $this->sum($key);

		return $sum / $this->count();

	}

	/**
	 * Is the opposite of the after method. It returns the item before
	 * the given item. null is returned if the given item is not found
	 * or is the first item.
	 */
	public function before(string $key, string $value): Collection {

		$items = [];

		foreach ($this->items as $item) {

			if ($item[$key] < $value) {

				$items[] = $item;

			}

		}

		return new Collection($items);

	}

	public function chunk(int $size): Collection {

		$chunks = [];

		for ($i = 0; $i < count($this->items); $i += $size) {

			$chunks[] = array_slice($this->items, $i, $size);

		}

		return new Collection($chunks);

	}

	public function chunkWhile(int $size): Collection {

		$chunks = [];

		for ($i = 0; $i < count($this->items); $i += $size) {

			$chunks[] = array_slice($this->items, $i, $size);

		}

		return new Collection($chunks);

	}

	public function collapse(): Collection {

		$items = [];

		foreach ($this->items as $item) {

			if ($item instanceof Collection) {

				$items = array_merge($items, $item->all());

			} else {

				$items[] = $item;

			}

		}

		return new Collection($items);

	}

	public function collect(): Collection {

		return new Collection($this->items);

	}

	/**
	 * Combines the values of the collection, as keys, with the values of another array or collection.
	 */
	public function combine(array $values): Collection {

		$items = [];

		foreach ($this->items as $key => $item) {

			$items[$item] = $values[$key];

		}

		return new Collection($items);

	}

	/**
	 * Appends the given array or collection's values onto the end of another collection.
	 */
	public function concat(array $values): Collection {

		return new Collection(array_merge($this->items, $values));

	}

	/**
	 * Determines whether the collection contains a given item. You may pass an ActiveRecord
	 * object to this method to determine if an element exists in the collection matching a
	 * given truth test.
	 */
	public function contains($key, $value = NULL): bool {

		if ($key instanceof \Pair\Orm\ActiveRecord) {

			foreach ($this->items as $item) {

				if ($item instanceof \Pair\Orm\ActiveRecord) {

					if ($item->id == $key->id) {
						return TRUE;
					}

				}

			}

		} else {

			foreach ($this->items as $item) {

				if ($item[$key] == $value) {
					return TRUE;
				}

			}

		}

		return FALSE;

	}

	/**
	 * Determines whether the collection contains a single item.
	 */
	public function containsOneItem(): bool {

		return 1 === $this->count();
	}

	/**
	 * Same signature as the contains method but values are compared using "strict" comparisons.
	 */
	public function containsStrict($key, $value = NULL): bool {

		if ($key instanceof \Pair\Orm\ActiveRecord) {

			return $this->contains($key, $value);

		} else {

			foreach ($this->items as $item) {

				if ($item[$key] === $value) {
					return TRUE;
				}

			}

		}

		return FALSE;

	}

	/**
	 * Count the number of items in the collection.
	 */
	public function count(): int {

		return count($this->items);

	}

	/**
	 * Counts the occurrences of values in the collection. By default, the
	 * method counts the occurrences of every element, allowing you to count
	 * certain "types" of elements in the collection.
	 */
	public function countBy(string $key): Collection {

		$counts = [];

		foreach ($this->items as $item) {

			if ($item instanceof \Pair\Orm\ActiveRecord) {

				$counts[$item->$key] = $counts[$item->$key] ?? 0;
				$counts[$item->$key]++;

			} else {

				$counts[$item[$key]] = $counts[$item[$key]] ?? 0;
				$counts[$item[$key]]++;

			}

		}

		return new Collection($counts);

	}

	/**
	 * A method required by the Iterator interface that returns the current element of the collection.
	 */
	public function current(): mixed {

		return $this->items[$this->position];

	}

	public function each(callable $callback): void {

		foreach ($this->items as $key => $item) {

			$callback($item, $key);

		}

	}

	/**
	 * Retrieves and returns duplicate values from the collection.
	 */
	public function duplicates(): Collection {

		$unique = array_unique($this->items);

		return new Collection(array_diff_assoc($this->items, $unique));

	}
 
	/**
	 * Returns the first element in the collection that passes a given truth test.
	 */
	public function first(): mixed {

		return $this->items[0];

	}

	/**
	 * Returns the first element in the collection with the given key / value pair.
	 */
	public function firstWhere(string $key, $value): mixed {

		foreach ($this->items as $item) {

			if ($item instanceof ActiveRecord) {

				if ($item->$key == $value) {
					return $item;
				}

			} else {

				if ($item[$key] == $value) {
					return $item;
				}

			}

		}

		return NULL;

	}

	/**
	 * Swaps the collection's keys with their corresponding values.
	 */
	public function flip(): Collection {

		return new Collection(array_flip($this->items));

	}

	/**
	 * Removes an item from the collection by its key.
	 */
	public function forget(int $position): void {

		unset($this->items[$position]);

	}

	/**
	 * Returns a new collection containing the items that would be present on a
	 * given page number. The method accepts the page number as its first argument
	 * and the number of items to show per page as its second argument.
	 */
	public function forPage(int $page, int $perPage): Collection {

		return new Collection(array_slice($this->items, ($page - 1) * $perPage, $perPage));

	}

	/**
	 * Returns the item at a given key. If the key does not exist, null is returned.
	 */
	public function get($key, $default = NULL): mixed {

		return $this->items[$key] ?? $default;

	}

	/**
	 * Get an iterator for the items.
	 */
	public function getIterator(): \Traversable {

		return new \ArrayIterator($this->items);

	}

	public function getOrPut($key, $value): mixed {

		if (!isset($this->items[$key])) {
			$this->items[$key] = $value;
		}

		return $this->items[$key];

	}

	public function groupBy(string $key): Collection {

		$groups = [];

		foreach ($this->items as $item) {

			$groups[$item[$key]][] = $item;

		}

		return new Collection($groups);

	}

	public function keyBy(string $key): Collection {

		$keys = [];

		foreach ($this->items as $item) {

			$keys[$item[$key]] = $item;

		}

		return new Collection($keys);

	}

	/**
	 * Determine if an item exists in the collection by key.
	 */
	public function has($key): bool {

		$keys = is_array($key) ? $key : func_get_args();

		foreach ($keys as $value) {
			if (!array_key_exists($value, $this->items)) {
				return FALSE;
			}
		}

		return TRUE;

	}

	/**
	 * Determine if any of the keys exist in the collection.
	 *
	 * @param  mixed  $key
	 * @return bool
	 */
	public function hasAny($key): bool {

		if ($this->isEmpty()) {
			return false;
		}

		$keys = is_array($key) ? $key : func_get_args();

		foreach ($keys as $value) {
			if ($this->has($value)) {
				return TRUE;
			}
		}

		return FALSE;

	}

	/**
	 * Concatenate values of a given key as a string.
	 */
	public function implode($value, string $glue = ''): string {

		$first = $this->first();

		if (is_array($first) && array_key_exists($value, $first)) {
			return implode($glue, $this->pluck($value)->all());
		}

		return implode($glue, $this->pluck($value)->all());

	}

	/**
	 * Determine if the collection is empty or not.
	 */
	public function isEmpty(): bool {

		return empty($this->items);

	}

	/**
	 * A method required by the Iterator interface that returns the current key
	 * (the position in the array).
	 */
    public function key(): int {

        return $this->position;

    }

    /**
     * Get the keys of the collection items.
     */
    public function keys(): static {

        return new static(array_keys($this->items));

    }

	public function last(): mixed {

		return end($this->items);

	}

    /**
     * Run a map over each of the items.
     */
    public function map(callable $callback): static {

		$keys = array_keys($this->items);

		try {
			$items = array_map($callback, $this->items, $keys);
		} catch (\Exception) {
			$items = array_map($callback, $this->items);
		}

		return new static(array_combine($keys, $items));

	}

	public function merge($items): Collection {

		return new Collection(array_merge($this->items, $items));

	}

	/**
	 * A method required by the Iterator interface that advances the pointer position to the next element.
	 */
    public function next(): void {

        ++$this->position;

    }

	/**
     * Determine if an item exists at an offset.
     */
    public function offsetExists($key): bool {

        return isset($this->items[$key]);

    }

    /**
     * Get an item at a given offset.
     */
    public function offsetGet($key): mixed {

		return $this->items[$key];

	}

    /**
     * Set the item at a given offset.
     */
    public function offsetSet($key, $value): void {

        if (is_null($key)) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }

    }

    /**
     * Unset the item at a given offset.
     */
    public function offsetUnset($key): void {

        unset($this->items[$key]);

    }

	public function only($keys): Collection {

		return new Collection(array_intersect_key($this->items, array_flip((array) $keys)));

	}

	/**
	 * Retrieves all of the values for a given key.
	 */
	public function pluck(string $value, ?string $key=NULL): Collection {

		$items = [];

		foreach ($this->items as $item) {

			if (is_null($key)) {

				$items[] = $item->$value;

			} else {

				$items[$item->$key] = $item->$value;

			}

		}

		return new Collection($items);

	}

	public function prepend($value, $key = NULL): Collection {

		if (is_null($key)) {
			array_unshift($this->items, $value);
		} else {
			$this->items = [$key => $value] + $this->items;
		}

		return $this;

	}

    /**
     * Get and remove an item from the collection.
     */
    public function pull($key, $default = NULL): mixed {

        return $this->items[$key] ?? $default;

    }

    /**
     * Push one or more items onto the end of the collection.
     */
    public function push(...$values): static {

        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;

	}

	/**
	 * Sets the given key and value in the collection.
	 */
	public function put(string $key, $item): static {

		$this->items[$key] = $item;

		return $this;

	}

    /**
     * Reverse items order.
     */
    public function reverse(): static {

        return new static(array_reverse($this->items, TRUE));

	}

	/**
	 * A method required by the Iterator interface that resets the pointer position to the beginning.
	 */
    public function rewind(): void {

        $this->position = 0;

    }

    /**
     * Get and remove the first N items from the collection.
     */
    public function shift(int $count = 1): static {

        if ($count === 1) {
            return array_shift($this->items);
        }

        if ($this->isEmpty()) {
            return new static;
        }

        $results = [];

        $collectionCount = $this->count();

        foreach (range(1, min($count, $collectionCount)) as $item) {
            array_push($results, array_shift($this->items));
        }

        return new static($results);

    }

	/**
     * Skip the first {$count} items.
     */
    public function skip(int $count): static  {

        return $this->slice($count);

    }

    /**
     * Slice the underlying collection array.
     */
    public function slice(int $offset, ?int $length = NULL): static {

        return new static(array_slice($this->items, $offset, $length, TRUE));

    }

	/**
	 * Sorts the collection. The sorted collection keeps the original array keys,
	 * so in the following example we will use the values method to reset the
	 * keys to consecutively numbered indexes.
	 */
	public function sort($callback = NULL) {

		$items = $this->items;

		$callback && is_callable($callback)
			? uasort($items, $callback)
			: asort($items, $callback ?? SORT_REGULAR);

		return new static($items);

	}

	/**
	 * Sorts the collection by the given key. The sorted collection keeps the
	 * original array keys, so in the following example we will use the values
	 * method to reset the keys to consecutively numbered indexes.
	 */
	public function sortBy(string $key, int $options = SORT_REGULAR, bool $descending = FALSE): Collection {

		$items = $this->items;

		uasort($items, function ($a, $b) use ($key, $options, $descending) {

			$a = is_object($a) ? $a->$key : $a[$key];
			$b = is_object($b) ? $b->$key : $b[$key];

			if ($a === $b) {
				return 0;
			}

			if ($descending) {
				return $a < $b ? 1 : -1;
			}

			return $a < $b ? -1 : 1;

		});

		return new static($items);

	}

	/**
	 * Sort the collection in the opposite order as the sort method.
	 */
	public function sortDesc(): Collection {

		return $this->sort()->reverse();

	}

	/**
     * Split a collection into a certain number of groups.
     */
    public function split(int $numberOfGroups): static {

        if ($this->isEmpty()) {
            return new static;
        }

        $groups = new static;

        $groupSize = floor($this->count() / $numberOfGroups);

        $remain = $this->count() % $numberOfGroups;

        $start = 0;

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $groupSize;

            if ($i < $remain) {
                $size++;
            }

            if ($size) {
                $groups->push(new static(array_slice($this->items, $start, $size)));
                $start += $size;
            }

        }

        return $groups;

    }

	/**
	 * Get the sum of the given values.
	 */
	public function sum(string $key): int {

		if (!property_exists($this->first(), $key)) {
			throw new \InvalidArgumentException('The key is not valid for sum.');
		}

		if (!is_numeric($this->first()->$key)) {
			throw new \InvalidArgumentException('Values are not valid for sum.');
		}

		return array_sum($this->pluck($key)->all());

	}

	/**
	 * Returns a new collection with the specified number of items.
	 */
	public function take(int $limit): static {

		if ($limit < 0) {
			return $this->slice($limit, abs($limit));
		}

		return $this->slice(0, $limit);

	}

	/**
	 * Converts the collection into a plain PHP array. If the collection's values are ActiveRecord
	 * objects, the objects will also be converted to arrays.
	 */
	public function toArray(): array {

		$items = [];

		foreach ($this->items as $key => $item) {

			if ($item instanceof \Pair\Orm\ActiveRecord) {

				if ($item::hasSimpleKey()) {
					$items[$item->getId()] = $item->toArray();
				} else {
					$items[] = $item->toArray();
				}

			} else {

				$items[$key] = $item;

			}

		}

		return $items;

	}

	/**
	 * Converts the collection into a JSON serialized string.
	 */
	public function toJson(int $options = 0): string {

		return json_encode($this->toArray(), $options);

	}

	public function unshift($value, $key = NULL): Collection {

		if (is_null($key)) {
			array_unshift($this->items, $value);
		} else {
			$this->items = [$key => $value] + $this->items;
		}

		return $this;

	}

	/**
	 * A method required by the Iterator interface that checks whether the current element is valid (exists).
	 */
    public function valid(): bool {

		return isset($this->items[$this->position]);

    }

	/**
	 * Reset the keys on the underlying array.
	 */
	public function values(): static {

		return new static(array_values($this->items));

	}

}