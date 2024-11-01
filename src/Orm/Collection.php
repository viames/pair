<?php

namespace Pair\Orm;

use Closure;
use Pair\Exceptions\PairException;
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
	public function concat(array|Collection $values): Collection {

		return new Collection(array_merge($this->items, $values instanceof Collection ? $values->all() : $values));

	}

	/**
	 * Determines whether the collection contains a given item. You may pass an ActiveRecord
	 * object to this method to determine if an element exists in the collection matching a
	 * given truth test. Alternatively, you may pass a string to the contains method to
	 * determine whether the collection contains a given item value. You may also pass a
	 * key / value pair to the contains method, which will determine if the given pair exists
	 * in the collection. The contains method uses "loose" comparisons when checking item values,
	 * meaning a string with an integer value will be considered equal to an integer of the same
	 * value. Use the containsStrict method to filter using "strict" comparisons.
	 * For he inverse of contains, see the doesntContain method.
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

	/**
	 * Stop the script execution after dumping the collection.
	 */
	public function dd(): void {

		var_dump($this->items);

		die;

	}

	/**
	 * Determines whether the collection does not contain a given item. You may pass a closure
	 * to the doesntContain method to determine if an element does not exist in the collection
	 * matching a given truth test. Alternatively, you may pass a string to the doesntContain
	 * method to determine whether the collection does not contain a given item value.
	 * You may also pass a key / value pair to the doesntContain method, which will determine
	 * if the given pair does not exist in the collection.
	 * The doesntContain method uses "loose" comparisons when checking item values, meaning a
	 * string with an integer value will be considered equal to an integer of the same value.
	 */
	public function doesntContain($key, $value = NULL): bool {

		return !$this->contains($key, $value);

	}

	/**
	 * Flattens a multi-dimensional collection into a single level collection that uses "dot"
	 * notation to indicate depth.
	 */
	public function dot(): Collection {

		$items = [];

		foreach ($this->items as $key => $item) {

			if ($item instanceof Collection) {

				$items = array_merge($items, $item->dot()->all());

			} else {

				$items[$key] = $item;

			}

		}

		return new Collection($items);

	}

	/**
	 * Dumps the collection's items.
	 */
	public function dump(): void {

		var_dump($this->items);

	}

	/**
	 * Retrieves and returns duplicate values from the collection.
	 */
	public function duplicates(): Collection {

		$unique = array_unique($this->items);

		return new Collection(array_diff_assoc($this->items, $unique));

	}

	/**
	 * This method has the same signature as the duplicates method; however, all values are compared using "strict" comparisons.
	 */
	public function duplicatesStrict(): Collection {

		$unique = array_unique($this->items);

		return new Collection(array_diff($this->items, $unique));

	}

	/**
	 * Iterates over the items in the collection and passes each item to a closure.
	 */
	public function each(callable $callback): void {

		foreach ($this->items as $key => $item) {

			$callback($item, $key);

		}

	}

	/**
	 * May be used to verify that all elements of a collection are of a given type or list
	 * of types. Otherwise, an UnexpectedValueException will be thrown.
	 */
	public function eachInstanceOf(string $type): void {

		foreach ($this->items as $item) {

			if (!$item instanceof $type) {
				throw new \UnexpectedValueException('The item is not an instance of ' . $type);
			}

		}

	}

	/**
	 * Iterates over the collection's items, passing each nested item value into the given callback.
	 */
	public function eachSpread(callable $callback): void {

		foreach ($this->items as $item) {

			$callback(...$item);

		}

	}

	/**
	 * May be used to verify that all elements of a collection pass a given truth test.
	 */
	public function every(callable $callback): bool {

		foreach ($this->items as $key => $item) {

			if (!$callback($item, $key)) {
				return FALSE;
			}

		}

		return TRUE;

	}

	/**
	 * Returns all items in the collection except for those with the specified keys.
	 */
	public function except(array $keys): Collection {

		return new Collection(array_diff_key($this->items, array_flip($keys)));

	}

	/**
	 * Returns the first element in the collection that passes a given truth test.
	 */
	public function first(): mixed {

		return $this->items[0] ?? NULL;

	}

	/**
	 * The firstOrFail method is identical to the first method; however, if no result is found,
	 * an OutOfBoundsException will be thrown. You may also call the firstOrFail method with no
	 * arguments to get the first element in the collection. If the collection is empty, an
	 * OutOfBoundsException exception will be thrown.
	 */
	public function firstOrFail(): mixed {

		if ($this->isEmpty()) {
			throw new \OutOfBoundsException('The collection is empty.');
		}

		return $this->first();

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
	 * Iterates through the collection and passes each value to the given closure. The closure
	 * is free to modify the item and return it, thus forming a new collection of modified items.
	 * Then, the array is flattened by one level.
	 */
	public function flatMap(callable $callback): Collection {

		return $this->map($callback)->collapse();

	}

	/**
	 * Flattens a multi-dimensional collection into a single dimension. If necessary, you may pass
	 * the flatten method a "depth" argument. Calling flatten without providing the depth would have
	 * also flattened the nested arrays. Providing a depth allows you to specify the number of levels
	 * nested arrays will be flattened.
	 */
	public function flatten(int $depth = INF): Collection {

		$items = [];

		foreach ($this->items as $item) {

			if ($item instanceof Collection) {

				$items = array_merge($items, $item->flatten($depth - 1)->all());

			} else {

				$items[] = $item;

			}

		}

		return new Collection($items);

	}

	/**
	 * Swaps the collection's keys with their corresponding values.
	 */
	public function flip(): Collection {

		return new Collection(array_flip($this->items));

	}

	/**
	 * Filters the collection using the given callback, keeping only those items that pass a given
	 * truth test. If no callback is supplied, all entries of the collection that are equivalent to
	 * false will be removed. For the inverse of filter, see the reject method.
	 */
	public function filter(?callable $callback = NULL): Collection {

		if (is_null($callback)) {
			return new Collection(array_filter($this->items));
		}

		return new Collection(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));

	}

	/**
	 * Removes an item from the collection by its key.
	 */
	public function forget(int $position): static {

		unset($this->items[$position]);

		// reset keys
		$this->items = array_values($this->items);

		return $this;

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
	 * Returns the item at a given key. If the key does not exist, null is returned. You may
	 * optionally pass a default value as the second argument. You may even pass a callback as the
	 * method's default value. The result of the callback will be returned if the specified key does
	 * not exist.
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

	/**
	 *
	 * Groups the collection's items by a given key. Instead of passing a string key, you may pass a
	 * callback. The callback should return the value you wish to key the group by. Multiple grouping
	 * criteria may be passed as an array. Each array element will be applied to the corresponding level
	 * within a multi-dimensional array.
	 */
	public function groupBy(string|callable $key): Collection {

		$groups = [];

		foreach ($this->items as $item) {

			if (is_callable($key)) {

				$groupKey = $key($item);

			} else {

				$groupKey = $item[$key];

			}

			$groups[$groupKey][] = $item;

		}

		return new Collection($groups);

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
	 */
	public function hasAny(mixed $key): bool {

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
	 * Joins items in a collection. Its arguments depend on the type of items in the collection. If
	 * the collection contains arrays or objects, you should pass the key of the attributes you wish
	 * to join, and the "glue" string you wish to place between the values. If the collection
	 * contains simple strings or numeric values, you should pass the "glue" as the only argument to
	 * the method. You may pass a closure to the implode method if you would like to format the
	 * values being imploded.
	 */
	public function implode(string $glue, ?string $key = NULL): string {

		if (is_null($key)) {
			return implode($glue, $this->items);
		}

		return implode($glue, $this->pluck($key)->all());

	}

	/**
	 * Removes any values from the original collection that are not present in the given array or
	 * collection. The resulting collection will preserve the original collection's keys.
	 */
	public function intersect(array|Collection $items): Collection {

		return new Collection(array_intersect($this->items, $items instanceof Collection ? $items->all() : $items));

	}

	/**
	 * Compares the original collection against another collection or array, returning the key / value
	 * pairs that are present in all of the given collections.
	 */
	public function intersectAssoc(array|Collection $items): Collection {

		return new Collection(array_intersect_assoc($this->items, $items instanceof Collection ? $items->all() : $items));

	}

	/**
	 * Determine if the collection is empty or not.
	 */
	public function isEmpty(): bool {

		return empty($this->items);

	}

	/**
	 * Returns true if the collection is not empty; otherwise, false is returned.
	 */
	public function isNotEmpty(): bool {

		return !$this->isEmpty();

	}

	/**
	 * Joins the collection's values with a string. Using this method's second argument, you may also
	 * specify how the final element should be appended to the string.
	 */
	public function join(string $glue, string $finalGlue = ''): string {

		$items = $this->items;

		$finalItem = array_pop($items);

		return implode($glue, $items) . $finalGlue . $finalItem;

	}

	/**
	 * A method required by the Iterator interface that returns the current key
	 * (the position in the array).
	 */
    public function key(): int {

        return $this->position;

    }

	/**
	 * Keys the collection by the given key. If multiple items have the same key, only the last one
	 * will appear in the new collection. You may also pass a callback to the method. The callback
	 * should return the value to key the collection by.
	 */
	public function keyBy(string|callable $key): Collection {

		$items = [];

		foreach ($this->items as $item) {

			if (is_callable($key)) {

				$items[$key($item)] = $item;

			} else {

				$items[$item[$key]] = $item;

			}

		}

		return new Collection($items);

	}

    /**
     * Returns all of the collection's keys.
     */
    public function keys(): static {

        return new static(array_keys($this->items));

    }

	/**
	 * Returns the last element in the collection that passes a given truth test.
	 */
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
		} catch (PairException) {
			$items = array_map($callback, $this->items);
		}

		return new static(array_combine($keys, $items));

	}

	/**
	 * Merges the given array or collection with the original collection. If a string key in
	 * the given items matches a string key in the original collection, the given item's value
	 * will overwrite the value in the original collection. If the given item's keys are numeric,
	 * the values will be appended to the end of the collection.
	 */
	public function merge(array|Collection $items): Collection {

		$this->items = array_merge($this->items, $items instanceof Collection ? $items->all() : $items);

		return $this;

	}

	/**
	 * Returns the minimum value of a given key.
	 */
	public function min(string $key): mixed {

		return min($this->pluck($key)->all());

	}

	/**
	 * Returns the "mode value" of a given key. In statistics, the mode is the value that appears
	 * most often in a set of data values.
	 */
	public function mode(?string $key=NULL): mixed {

		$count = $this->countBy($key);

		$mode = $count->sort()->last();

		return $mode;

	}

	/**
	 * Creates the specified number of copies of all items in the collection.
	 */
	public function multiply(int $times): Collection {

		$items = [];

		foreach ($this->items as $item) {

			$items = array_merge($items, array_fill(0, $times, $item));

		}

		return new Collection($items);

	}

	/**
	 * A method required by the Iterator interface that advances the pointer position to the next element.
	 */
    public function next(): void {

        ++$this->position;

    }

	/**
	 * Creates a new collection consisting of every n-th element. You may optionally pass a starting
	 * offset as the second argument.
	 */
	public function nth(int $step, int $offset = 0): Collection {

		$items = [];

		$position = 0;

		foreach ($this->items as $item) {

			if ($position % $step === $offset) {
				$items[] = $item;
			}

			$position++;

		}

		return new Collection($items);

	}

	/**
     * Determine if an item exists at an offset.
     */
    public function offsetExists(mixed $key): bool {

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

	/**
	 * Returns the items in the collection with the specified keys. For the inverse of only, see the
	 * except method.
	 */
	public function only(array $keys): Collection {

		return new Collection(array_intersect_key($this->items, array_flip($keys)));

	}

	/**
	 * Will fill the array with the given value until the array reaches the specified size. This
	 * method behaves like the array_pad PHP function. To pad to the left, you should specify a
	 * negative size. No padding will take place if the absolute value of the given size is less
	 * than or equal to the length of the array.
	 */
	public function pad(int $size, $value): Collection {

		return new Collection(array_pad($this->items, $size, $value));

	}

	/**
	 * May be used to quickly determine the percentage of items in the collection that pass a given
	 * truth test. By default, the percentage will be rounded to two decimal places. However, you may
	 * customize this behavior by providing a second argument to the method.
	 */
	public function percentage(callable $callback, int $precision = 2): float {

		$total = $this->count();

		$passed = $this->filter($callback)->count();

		return round($passed / $total * 100, $precision);

	}

	/**
	 * Retrieves all of the values for a given key. You may also specify how you wish the resulting
	 * collection to be keyed. If duplicate keys exist, the last matching element will be inserted
	 * into the plucked collection.
	 */
	public function pluck(string $value, ?string $key = NULL): Collection {

		$items = [];

		foreach ($this->items as $item) {

			if (is_object($item) and isset($item->$value)) {

				if (is_null($key)) {
					$items[] = $item->$value;
				} else {
					$items[$item->$key] = $item->$value;
				}

			} else if (is_array($item) and isset($item[$value])) {

				if (is_null($key)) {
					$items[] = $item[$value];
				} else {
					$items[$item[$key]] = $item[$value];
				}

			}

		}

		return new Collection($items);

	}

	/**
	 * Removes and returns the last item from the collection. You may pass an integer to the pop
	 * method to remove and return multiple items from the end of a collection.
	 */
	public function pop(int $count = 1): mixed {

		if ($count === 1) {
			return array_pop($this->items);
		}

		if ($this->isEmpty()) {
			return new static;
		}

		$results = [];

		foreach (range(1, min($count, $this->count())) as $item) {
			array_push($results, array_pop($this->items));
		}

		return new Collection($results);

	}

	/**
	 * Adds an item to the beginning of the collection.
	 */
	public function prepend($value, $key = NULL): static {

		if (is_null($key)) {

			array_unshift($this->items, $value);

		} else {

			$this->items = [$key => $value] + $this->items;

		}

		return $this;

	}

    /**
     * Removes and returns an item from the collection by its key.
     */
    public function pull($key): mixed {

		$value = $this->get($key);

		$this->forget($key);

		return $value;

	}

    /**
     * Appends an item to the end of the collection.
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
	public function put($key, $value): static {

		$this->items[$key] = $value;

		return $this;

	}

	/**
	 * The random method returns a random item from the collection. You may pass an integer to
	 * random to specify how many items you would like to randomly retrieve. A collection of items
	 * is always returned when explicitly passing the number of items you wish to receive. If the
	 * collection instance has fewer items than requested, the random method will throw an
	 * InvalidArgumentException. The random method also accepts a closure, which will receive the
	 * current collection instance.
	 */
	public function random(int $number = 1): mixed {

		if ($number === 1) {
			return $this->items[array_rand($this->items)];
		}

		if ($number > $this->count()) {
			throw new \InvalidArgumentException('You requested ' . $number . ' items, but there are only ' . $this->count() . ' items in the collection.');
		}

		return new Collection(array_rand($this->items, $number));

	}

	/**
	 * Returns a collection containing integers between the specified range.
	 */
	public function range(int $start, int $end): Collection {

		return new Collection(range($start, $end));

	}

	/**
	 * Reduces the collection to a single value, passing the result of each iteration into the
	 * subsequent iteration. The value for $carry on the first iteration is null; however, you may
	 * specify its initial value by passing a second argument to reduce. The reduce method also
	 * passes array keys in associative collections to the given callback.
	 */
	public function reduce(callable $callback, $initial = NULL): mixed {

		return array_reduce($this->items, $callback, $initial);

	}

	/**
	 * Reduces the collection to an array of values, passing the results of each iteration into the
	 * subsequent iteration. This method is similar to the reduce method; however, it can accept
	 * multiple initial values.
	 */
	public function reduceSpread(callable $callback, array $initial): Collection {

		return new Collection(array_reduce($this->items, $callback, $initial));

	}

	/**
	 * Filters the collection using the given closure. The closure should return true if the item
	 * should be removed from the resulting collection. For the inverse of the reject method, see the
	 * filter method.
	 */
	public function reject(callable $callback): Collection {

		return $this->filter(function ($item, $key) use ($callback) {

			return !$callback($item, $key);

		});

	}

	/**
	 * Behaves similarly to merge; however, in addition to overwriting matching items that have
	 * string keys, the replace method will also overwrite items in the collection that have
	 * matching numeric keys.
	 */
	public function replace(array|Collection $items): Collection {

		return new Collection(array_replace($this->items, $items instanceof Collection ? $items->all() : $items));

	}

	/**
	 * Works like replace, but it will recur into arrays and apply the same replacement process to
	 * the inner values.
	 */
	public function replaceRecursive(array|Collection $items): Collection {

		return new Collection(array_replace_recursive($this->items, $items instanceof Collection ? $items->all() : $items));

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
	 * Searches the collection for the given value and returns its key if found. If the item is not found,
	 * false is returned. The search is done using a "loose" comparison, meaning a string with an integer
	 * value will be considered equal to an integer of the same value. To use "strict" comparison, pass
	 * true as the second argument to the method. Alternatively, you may pass in your own callback to
	 * search for the first item that passes your truth test.
	 */
	public function search($value, bool $strict = FALSE): mixed {

		foreach ($this->items as $key => $item) {

			if (($strict and $item === $value) or (!$strict and $item == $value)) {

				return $key;

			}

		}

		return FALSE;

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
	 * Sorts the collection by the given key. The sorted collection keeps the original array keys and
	 * accepts sort flags as its second argument. Alternatively, you may pass your own closure to
	 * determine how to sort the collection's values. If you would like to sort your collection by multiple
	 * attributes, you may pass an array of sort operations to the sortBy method. Each sort operation
	 * should be an array consisting of the attribute that you wish to sort by and the direction of the
	 * desired sort. When sorting a collection by multiple attributes, you may also provide closures that
	 * define each sort operation.
	 */
	public function sortBy(array|string|Closure $key, int $options = SORT_REGULAR): Collection {

		if (is_array($key)) {
			return new Collection($this->sortByMultiple($key));
		}

		if ($key instanceof Closure) {
			return new Collection($this->sortByCallback($key));
		}

		return new Collection($this->sortBySingle($key, $options));

	}

	/**
	 * Sort the collection by a callback.
	 */
	protected function sortByCallback(Closure $callback): array {

		$items = $this->items;

		uasort($items, $callback);

		return $items;

	}

	/**
	 * Sort the collection by multiple attributes.
	 */
	protected function sortByMultiple(array $keys): array {

		$items = $this->items;

		usort($items, function ($a, $b) use ($keys) {

			foreach ($keys as $key => $direction) {

				$comparison = $this->sortBySingle($key, $direction, $a, $b);

				if ($comparison !== 0) {
					return $comparison;
				}

			}

			return 0;

		});

		return $items;

	}

	/**
	 * Sort the collection by a single attribute.
	 */
	protected function sortBySingle(string $key, int $options, mixed $a = NULL, mixed $b = NULL): int|array {

		$items = $this->items;

		if (is_null($a) or is_null($b)) {

			uasort($items, function ($a, $b) use ($key, $options) {

				return $this->sortBySingle($key, $options, $a, $b);

			});

		} else {

			$a = is_object($a) ? $a->$key : $a[$key];
			$b = is_object($b) ? $b->$key : $b[$key];

			if ($a === $b) {
				return 0;
			}

			if ($options & SORT_NATURAL) {
				return $a > $b ? 1 : -1;
			}

			return $a <=> $b;

		}

		return $items;

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

	/**
	 * Iterates over the collection and calls the given callback with each item in the collection. The
	 * items in the collection will be replaced by the values returned by the callback.
	 */
	public function transform(callable $callback): Collection {

		$items = [];

		foreach ($this->items as $key => $item) {

			$items[$key] = $callback($item, $key);

		}

		return new Collection($items);

	}

	/**
	 * Adds the given array to the collection. If the given array contains keys that are already in
	 * the original collection, the original collection's values will be preferred.
	 */
	public function union(array|Collection $items): Collection {

		return new Collection($this->items + ($items instanceof Collection ? $items->all() : $items));

	}

	/**
	 * Returns all of the unique items in the collection. The returned collection keeps the original
	 * array keys, so in the following example we will use the values method to reset the keys to
	 * consecutively numbered indexes. When dealing with nested arrays or objects, you may specify
	 * the key used to determine uniqueness. Finally, you may also pass your own closure to the
	 * unique method to specify which value should determine an item's uniqueness. The unique method
	 * uses "loose" comparisons when checking item values, meaning a string with an integer value
	 * will be considered equal to an integer of the same value. Use the uniqueStrict method to
	 * filter using "strict" comparisons.
	 */
	public function unique(?string $key = NULL): Collection {

		if (is_null($key)) {
			return new Collection(array_unique($this->items));
		}

		$items = [];

		foreach ($this->items as $item) {

			$items[$item[$key]] = $item;

		}

		return new Collection($items);

	}

	/**
	 * This method has the same signature as the unique method; however, all values are compared using "strict" comparisons.
	 */
	public function uniqueStrict(): Collection {

		return new Collection(array_unique($this->items));

	}

	/**
	 * Will execute the given callback unless the first argument given to the method evaluates to
	 * true. A second callback may be passed to the unless method. The second callback will be
	 * executed when the first argument given to the unless method evaluates to true. For the
	 * inverse of unless, see the when method.
	 */
	public function unless(bool $value, callable $callback, callable $default): mixed {

		if (!$value) {
			return $callback($this, $value);
		}

		return $default($this, $value);

	}

	/**
	 * Alias for the whenNotEmpty method.
	 */
	public function unlessEmpty(callable $callback, callable $default): mixed {

		return $this->whenNotEmpty($callback, $default);

	}

	/**
	 * Alias for the whenEmpty method.
	 */
	public function unlessNotEmpty(callable $callback, callable $default): mixed {

		return $this->whenEmpty($callback, $default);

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
	 * Returns the collection's underlying items from the given value when applicable.
	 */
	public static function unwrap($value): mixed {

		return $value instanceof self ? $value->all() : $value;

	}

	/**
	 * A method required by the Iterator interface that checks whether the current element is valid
	 * (exists).
	 */
    public function valid(): bool {

		return isset($this->items[$this->position]);

    }

	/**
	 * Retrieves a given value from the first element of the collection.
	 */
	public function value(string $key): mixed {

		return $this->first()[$key];

	}

	/**
	 * Returns a new collection with the keys reset to consecutive integers.
	 */
	public function values(): Collection {

		return new Collection(array_values($this->items));

	}

	/**
	 * The when method will execute the given callback when the first argument given to the method
	 * evaluates to true. The collection instance and the first argument given to the when method
	 * will be provided to the closure. A second callback may be passed to the when method. The
	 * second callback will be executed when the first argument given to the when method evaluates
	 * to false. For the inverse of when, see the unless method.
	 */
	public function when(bool $value, callable $callback, callable $default): mixed {

		if ($value) {
			return $callback($this, $value);
		}

		return $default($this, $value);

	}

	/**
	 * Will execute the given callback when the collection is empty. A second closure may be passed
	 * to the whenEmpty method that will be executed when the collection is not empty. For the
	 * inverse of whenEmpty, see the whenNotEmpty method.
	 */
	public function whenEmpty(callable $callback, callable $default): mixed {

		if ($this->isEmpty()) {
			return $callback($this);
		}

		return $default($this);

	}

	/**
	 * Will execute the given callback when the collection is not empty. A second closure may be
	 * passed to the whenNotEmpty method that will be executed when the collection is empty. For the
	 * inverse of whenNotEmpty, see the whenEmpty method.
	 */
	public function whenNotEmpty(callable $callback, callable $default): mixed {

		if ($this->isNotEmpty()) {
			return $callback($this);
		}

		return $default($this);

	}

	/**
	 * The where method filters the collection by a given key / value pair. The where method uses
	 * "loose" comparisons when checking item values, meaning a string with an integer value will be
	 * considered equal to an integer of the same value. Use the whereStrict method to filter using
	 * "strict" comparisons. Optionally, you may pass a comparison operator as the second parameter.
	 * Supported operators are: '===', '!==', '!=', '==', '=', '<>', '>', '<', '>=', and '<='.
	 */
	public function where(string $key, $operator, $value = NULL): Collection {

		return $this->filter(function ($item) use ($key, $operator, $value) {

			$actual = $item instanceof \Pair\Orm\ActiveRecord ? $item->$key : $item[$key];

			switch ($operator) {

				case '=':
				case '==':
					return $actual == $value;

				case '!=':
				case '<>':
					return $actual != $value;

				case '<':
					return $actual < $value;

				case '<=':
					return $actual <= $value;

				case '>':
					return $actual > $value;

				case '>=':
					return $actual >= $value;

			}

		});

	}

	/**
	 * Filters the collection by determining if a specified item value is within a given range.
	 */
	public function whereBetween(string $key, array $values): Collection {

		return $this->filter(function ($item) use ($key, $values) {

			$value = $item instanceof \Pair\Orm\ActiveRecord ? $item->$key : $item[$key];

			return $value >= $values[0] and $value <= $values[1];

		});

	}

	/**
	 * Removes elements from the collection that do not have a specified item value that is contained
	 * within the given array. Uses "loose" comparisons when checking item values, meaning a string
	 * with an integer value will be considered equal to an integer of the same value. Use the
	 * whereInStrict method to filter using "strict" comparisons.
	 */
	public function whereIn(string $key, array $values, bool $strict = FALSE): Collection {

		return $this->filter(function ($item) use ($key, $values, $strict) {

			$actual = $item instanceof \Pair\Orm\ActiveRecord ? $item->$key : $item[$key];

			foreach ($values as $value) {

				if ($strict and $actual === $value) {
					return TRUE;
				}

				if (!$strict and $actual == $value) {
					return TRUE;
				}

			}

			return FALSE;

		});

	}

	/**
	 * Filters the collection by a given class type.
	 */
	public function whereInstanceOf(string $type): Collection {

		return $this->filter(function ($value) use ($type) {

			return $value instanceof $type;

		});

	}

	/**
	 * This method has the same signature as the whereIn method; however, all values are compared
	 * using "strict" comparisons.
	 */
	public function whereInStrict(string $key, array $values): Collection {

		return $this->whereIn($key, $values, TRUE);

	}

	/**
	 * Filters the collection by determining if a specified item value is outside of a given range.
	 */
	public function whereNotBetween(string $key, array $values): Collection {

		return $this->reject(function ($item) use ($key, $values) {

			$value = $item instanceof \Pair\Orm\ActiveRecord ? $item->$key : $item[$key];

			return $value >= $values[0] and $value <= $values[1];

		});

	}

	/**
	 * Removes elements from the collection that have a specified item value that is contained within
	 * the given array. uses "loose" comparisons when checking item values, meaning a string with an
	 * integer value will be considered equal to an integer of the same value. Use the whereNotInStrict
	 * method to filter using "strict" comparisons.
	 */
	public function whereNotIn(string $key, array $values, bool $strict = FALSE): Collection {

		return $this->reject(function ($item) use ($key, $values, $strict) {

			$actual = $item instanceof \Pair\Orm\ActiveRecord ? $item->$key : $item[$key];

			foreach ($values as $value) {

				if ($strict and $actual === $value) {
					return TRUE;
				}

				if (!$strict and $actual == $value) {
					return TRUE;
				}

			}

			return FALSE;

		});

	}

	/**
	 * This method has the same signature as the whereNotIn method; however, all values are compared
	 * using "strict" comparisons.
	 */
	public function whereNotInStrict(string $key, array $values): Collection {

		return $this->whereNotIn($key, $values, TRUE);

	}

	/**
	 * Returns items from the collection where the given key is not null.
	 */
	public function whereNotNull(string $key): Collection {

		return $this->where($key, '!=', NULL);

	}

	/**
	 * Returns items from the collection where the given key is null.
	 */
	public function whereNull(string $key): Collection {

		return $this->where($key, NULL);

	}

	/**
	 * This method has the same signature as the where method; however, all values are compared using
	 * "strict" comparisons.
	 */
	public function whereStrict(string $key, $operator, $value = NULL): Collection {

		return $this->filter(function ($item) use ($key, $operator, $value) {

			$actual = $item instanceof \Pair\Orm\ActiveRecord ? $item->$key : $item[$key];

			switch ($operator) {

				case '=':
				case '==':
					return $actual === $value;

				case '!=':
				case '<>':
					return $actual !== $value;

				case '<':
					return $actual < $value;

				case '<=':
					return $actual <= $value;

				case '>':
					return $actual > $value;

				case '>=':
					return $actual >= $value;

			}

		});

	}

	/**
	 * Wraps the given value in a collection when applicable.
	 */
	public static function wrap($value): Collection {

		return $value instanceof Collection ? $value : new Collection($value);

	}

	/**
	 * Merges together the values of the given array with the values of the original collection at
	 * their corresponding index.
	 */
	public function zip(array|Collection $items): Collection {

		$items = $items instanceof Collection ? $items->all() : $items;

		$collection = new Collection;

		foreach ($this->items as $key => $value) {

			$collection->put($key, [$value, $items[$key]]);

		}

		return $collection;

	}

}