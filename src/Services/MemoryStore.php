<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Services;

use AllenJB\Notifications\LoggingServiceInterface;
use AllenJB\Notifications\Notification;

/**
 * Stores events in memory. Used for tests.
 * @internal
 */
class MemoryStore implements LoggingServiceInterface
{
    /**
     * @var array<Notification>
     */
    public array $events = [];


    public function send(Notification $notification): bool
    {
        $this->events[] = $notification;
        return true;
    }


    /**
     * @return array<Notification>
     */
    public function retrieve(): array
    {
        $retVal = $this->events;
        $this->events = [];
        return $retVal;
    }
}
