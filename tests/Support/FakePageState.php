<?php

declare(strict_types=1);

namespace Pair\Tests\Support;

/**
 * Minimal typed state used to verify PageResponse rendering.
 */
final readonly class FakePageState {

	/**
	 * Create the fake page state.
	 */
	public function __construct(public string $message) {}

}
