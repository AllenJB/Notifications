<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Tests\LogParser;

use AllenJB\Notifications\LogParser\LoggedPHPEvent;
use AllenJB\Notifications\LogParser\PHP;
use PHPUnit\Framework\TestCase;

class PHPTest extends TestCase
{
    public function testParseWithoutLastEvent(): void
    {
        $filePath = __DIR__ . '/php_error_log.log';
        $parser = new PHP($filePath, null);
        $parser->setIgnoreSeverityList([]);

        $events = $parser->parse();

        $actual = var_export($events, true);
        $this->assertStringEqualsFile(__DIR__ . '/expectedWithoutLastEvent.output', $actual);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }


    public function testParseWithoutLastEventWithIgnoreList(): void
    {
        $filePath = __DIR__ . '/php_error_log.log';
        $parser = new PHP($filePath, null);

        $events = $parser->parse();

        $actual = var_export($events, true);
        $this->assertStringEqualsFile(__DIR__ . '/expectedWithoutLastEventWithIgnoreList.output', $actual);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }


    public function testParseWithLastEvent(): void
    {
        $filePath = __DIR__ . '/php_error_log.log';

        $lastEvent = new LoggedPHPEvent(
            \DateTimeImmutable::createFromFormat("Y-m-d H:i:s e", "2023-01-12 11:14:26 Europe/London"),
            "Fatal error",
            "Allowed memory size of 134217728 bytes exhausted (tried to allocate 262144 bytes)",
            "Unknown",
            "0"
        );
        $parser = new PHP($filePath, $lastEvent);
        $parser->setIgnoreSeverityList([]);

        $events = $parser->parse();

        $actual = var_export($events, true);
        $this->assertStringEqualsFile(__DIR__ . '/expectedWithLastEvent.output', $actual);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }

}
