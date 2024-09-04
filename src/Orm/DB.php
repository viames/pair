<?php

namespace Pair\Orm;

class DB {

	public static function table($table): Query {

		return new Query($table);

	}

}
