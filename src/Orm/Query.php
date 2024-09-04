<?php

namespace Pair\Orm;

class Query {

	/**
	 * Returns a Collection instance containing the results of the query where each
	 * result is an instance of the PHP stdClass object. You may access each column's
	 * value by accessing the column as a property of the object.
	 */
	public function get(): Collection {

		return new Collection();

	}

	public function where($column, $operator, $value): Query {

		return $this;

	}

}
