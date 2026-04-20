<?php

declare(strict_types=1);

use Pair\Core\View;

class StatefulViewDefault extends View {

	/**
	 * Render the legacy view with one explicit state object.
	 */
	public function render(): void {

		$state = new class ('ready') {

			/**
			 * Build the state object already exposed by the legacy view.
			 */
			public function __construct(public string $status) {}

		};

		$this->assignState($state);

	}

}
