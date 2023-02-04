<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Tests\LogParser;

use AllenJB\Notifications\LogParser\PHPEvent;
use AllenJB\Notifications\LogParser\PHP;

class PHPTest extends TestCase
{
    protected const PATH_TO_EXPECTED = self::PATH_TO_FIXTURES . 'PHP/';


    public function testParseWithoutLastEvent(): void
    {
        $filePath = self::PATH_TO_FIXTURES . 'php_errors.log';
        $parser = new PHP($filePath, null);
        $parser->setIgnoreSeverityList([]);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithoutLastEvent.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }


    public function testParseWithoutLastEventWithIgnoreList(): void
    {
        $filePath = self::PATH_TO_FIXTURES . 'php_errors.log';
        $parser = new PHP($filePath, null);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithoutLastEventWithIgnoreList.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }


    public function testParseWithLastEvent(): void
    {
        $lastEvent = new PHPEvent(
            \DateTimeImmutable::createFromFormat("Y-m-d H:i:s e", "2023-01-12 11:14:26 Europe/London"),
            "Fatal error",
            "Allowed memory size of 134217728 bytes exhausted (tried to allocate 262144 bytes)",
            "Unknown",
            "0"
        );
        $filePath = self::PATH_TO_FIXTURES . 'php_errors.log';
        $parser = new PHP($filePath, $lastEvent);
        $parser->setIgnoreSeverityList([]);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithLastEvent.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }
}
