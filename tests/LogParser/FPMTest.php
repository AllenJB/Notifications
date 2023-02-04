<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Tests\LogParser;

use AllenJB\Notifications\LogParser\FPM;
use AllenJB\Notifications\LogParser\FPMEvent;

class FPMTest extends TestCase
{
    protected const PATH_TO_EXPECTED = self::PATH_TO_FIXTURES . 'FPM/';


    public function testParseWithoutLastEvent(): void
    {
        $filePath = self::PATH_TO_FIXTURES . 'fpm_errors.log';
        $parser = new FPM($filePath, null);
        $parser->setIgnoreSeverityList([]);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithoutLastEvent.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }

    public function testParseWithoutLastEventWithIgnoreList(): void
    {
        $filePath = self::PATH_TO_FIXTURES . 'fpm_errors.log';
        $parser = new FPM($filePath, null);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithoutLastEventWithIgnoreList.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }

    public function testParseWithLastEvent(): void
    {
        $previousLastEvent = new FPMEvent(
            new \DateTimeImmutable('2023-02-04 11:38:39.585518'),
            'NOTICE',
            'fpm is running, pid 907689',
            null,
            907689,
            'fpm_init()',
            83
        );

        $filePath = self::PATH_TO_FIXTURES . 'fpm_errors.log';
        $parser = new FPM($filePath, $previousLastEvent);
        $parser->setIgnoreSeverityList([]);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithLastEvent.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }


    public function testParseWithLastEventIgnored(): void
    {
        $previousLastEvent = new FPMEvent(
            new \DateTimeImmutable('2023-02-04 11:38:39.585518'),
            'NOTICE',
            'fpm is running, pid 907689',
            null,
            907689,
            'fpm_init()',
            83
        );

        $filePath = self::PATH_TO_FIXTURES . 'fpm_errors.log';
        $parser = new FPM($filePath, $previousLastEvent);

        $events = $parser->parse();

        $this->assertEqualsFixture(self::PATH_TO_EXPECTED . 'expectedWithLastEventIgnored.output', $events);

        $actualLastEvent = array_pop($events);
        $this->assertEquals($actualLastEvent, $parser->getLastEvent());
    }
}
