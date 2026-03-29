<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

use Pair\Api\CrudController;

/**
 * Minimal CrudController test double exposing protected registration without bootstrapping MVC.
 */
class FakeCrudController extends CrudController {

	/**
	 * Register a CRUD resource through the protected framework API.
	 *
	 * @param	string		$slug		Resource slug.
	 * @param	string		$modelClass	Model class name.
	 * @param	array|null	$config		Optional explicit configuration.
	 */
	public function registerCrudResource(string $slug, string $modelClass, ?array $config = null): void {

		$this->crud($slug, $modelClass, $config);

	}

}
