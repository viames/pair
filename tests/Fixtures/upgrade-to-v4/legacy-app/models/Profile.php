<?php

declare(strict_types=1);

class Profile {

	public function refresh(object $user): void {

		$user->reload();

	}

}
