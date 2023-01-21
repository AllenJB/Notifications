<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Services;

use AllenJB\Notifications\LoggingServiceInterface;
use AllenJB\Notifications\Notification;

/**
 * Dumps events to a file. Used for tests.
 * @internal
 */
class DumpToFile implements LoggingServiceInterface
{
    /**
     * @var resource
     */
    protected $fileHandle;


    public function __construct(string $file)
    {
        $fileHandle = fopen($file, 'wb');
        if ($fileHandle === false) {
            throw new \UnexpectedValueException("Failed to open output file for writing");
        }
        $this->fileHandle = $fileHandle;
    }


    public function send(Notification $notification): bool
    {
        fputs($this->fileHandle, serialize($notification) . PHP_EOL);
        fputs($this->fileHandle, $this::getEndOfEventMarker() . PHP_EOL);
        return true;
    }


    public static function getEndOfEventMarker(): string
    {
        return "--- END OF EVENT ---";
    }

}
