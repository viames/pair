<?php

declare(strict_types=1);

use Pair\Helpers\Utilities;

class ApiJsonHelper {

	/**
	 * Return a legacy JSON payload through a multiline helper call.
	 */
	public function userAction(): void {

		Utilities::jsonResponse(
			$user
				->toArray(),
			202
		);

	}

}
