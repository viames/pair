<?php

declare(strict_types=1);

use Pair\Api\ApiResponse;

class ApiController {

	public function userAction(): void {

		ApiResponse::respond($user->toArray(), 201);

	}

}
