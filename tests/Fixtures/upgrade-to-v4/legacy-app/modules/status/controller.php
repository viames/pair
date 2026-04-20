<?php

declare(strict_types=1);

use Pair\Core\Controller;
use Pair\Html\Breadcrumb;
use Pair\Web\PageResponse;

class StatusController extends Controller {

	/**
	 * Keep the controller boot hook explicit in the fixture.
	 */
	protected function _init(): void {

		Breadcrumb::path($this->lang('STATUS'), 'status');

	}

	/**
	 * Return a typed page response through the new explicit contract.
	 */
	public function defaultAction(): PageResponse {

		$state = new class ('ready') {

			/**
			 * Build the anonymous status state.
			 */
			public function __construct(public string $status) {}

		};

		return new PageResponse(__DIR__ . '/layouts/default.php', $state, $this->lang('STATUS'));

	}

}
