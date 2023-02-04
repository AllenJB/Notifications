<?php
declare(strict_types=1);

namespace AllenJB\Notifications\Tests\LogParser;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected const PATH_TO_FIXTURES = __DIR__ . '/Fixtures/';


    /**
     * @param mixed $actual
     */
    protected function assertEqualsFixture(string $expectedFile, $actual): void
    {
        $actual = var_export($actual, true);
        // file_put_contents($expectedFile, $actual);
        $this->assertStringEqualsFile($expectedFile, $actual);
    }
}
