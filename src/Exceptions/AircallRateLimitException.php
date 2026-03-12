<?php

namespace Pair\Exceptions;

/**
 * Specific exception raised when Aircall returns a rate-limit response.
 * This exception must not extend `PairException`, otherwise the framework will automatically
 * log it already in the constructor even when the application catches it and degrades it
 * correctly to local cooldown.
 */
class AircallRateLimitException extends \RuntimeException {}
