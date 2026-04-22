<?php

declare(strict_types=1);

namespace Pair\Core;

/**
 * Receives non-span observability events from the Pair runtime.
 */
interface ObservabilityEventAdapter {

	/**
	 * Record one completed runtime event.
	 */
	public function recordEvent(ObservabilityEvent $event): void;

}
