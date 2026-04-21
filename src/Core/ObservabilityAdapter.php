<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Receives completed observability spans from the Pair runtime.
 */
interface ObservabilityAdapter {

	/**
	 * Record one completed runtime span.
	 */
	public function record(ObservabilitySpan $span): void;

}
