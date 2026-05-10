<?php

declare(strict_types=1);

namespace Pair\Tests\Fixtures\Html;

use Pair\Html\UiRenderers\NativeUiRenderer;

/**
 * Fixture renderer used to prove class-based UI renderer registration stays lazy.
 */
final class LazyUiRenderer extends NativeUiRenderer {

	/**
	 * Return the fixture renderer name.
	 */
	public function name(): string {

		return 'lazy-fixture';

	}

	/**
	 * Return a visible fixture class for alert rendering.
	 */
	public function alertClass(string $variant = 'primary'): string {

		return 'lazy-fixture-alert';

	}

}
