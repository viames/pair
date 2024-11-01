<?php

namespace Pair\Traits;

use Pair\Support\Logger;

trait LogTrait {

    /**
     * Disable the collection of log events.
     */
    public function disableLog(): void {

        $logger = Logger::getInstance();
        $logger->disable();

    }

    /**
     * Add an event to the log bar.
     */
    public function log(string $description, string $type = 'notice', string $subtext = ''): void {

        Logger::event($description, $type, $subtext);

    }

    /**
     * Add an error to the log bar.
     */
    public function logError(string $description, string $subtext = ''): void {

        Logger::event($description, 'error', $subtext);

    }

    public function logWarning(string $description, string $subtext = ''): void {

        Logger::event($description, 'warning', $subtext);

    }

}