<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Tests;

use AllenJB\Notifications\Notification;
use AllenJB\Notifications\Services\DumpToFile;
use PHPUnit\Framework\TestCase;

class HardMemoryLimitTest extends TestCase
{
    public function testExceeded(): void
    {
        $dumpFileName = "hardLimitExceeded.dat";
        exec("php ". __DIR__ ."/scripts/exceedMemoryLimit.php {$dumpFileName}");

        // Retrieve dumped notifications
        /**
         * @var array<Notification> $events
         */
        $events = [];
        $fileHandle = fopen(__DIR__ ."/scripts/data/". $dumpFileName, "rb");
        $this->assertIsResource($fileHandle);

        $currentEventData = '';
        while (false !== ($line = fgets($fileHandle))) {
            if (trim($line) === DumpToFile::getEndOfEventMarker()) {
                $events[] = unserialize(trim($currentEventData));
                $currentEventData = '';
                continue;
            }
            $currentEventData .= $line;
        }

        $this->assertCount(2, $events);
        $this->assertNull($events[0]->getMessage());
        $this->assertStringContainsStringIgnoringCase('allowed memory size', ($events[0]->getException()->getMessage()));

        // Verify that the memory limit value has been re-set for soft memory limit reports
        $this->assertStringContainsStringIgnoringCase('soft memory limit reached', $events[1]->getMessage());
        $context = $events[1]->getContext();
        $this->assertEquals('2M', $context['Additional Data']['memory_limit_ini']);
    }
}
