<?php

declare(strict_types=1);

use Pair\Api\ApiExposable;

class Faq {

	use ApiExposable;

	public static function apiConfig(): array {

		return [
			'searchable' => ['question'],
		];

	}

}
