<?php
declare(strict_types=1);

namespace AllenJB\Notifications\LogParser;

use DateTimeImmutable;
use InvalidArgumentException;

class PHP
{
    /**
     * @var resource $fileHandle
     */
    protected $fileHandle;

    protected ?PHPEvent $previousLastEvent;

    protected ?PHPEvent $lastEvent = null;

    /**
     * @var array<string|int> List of severity descriptions and E_ constant values to ignore
     * The default list should already have been reported by normal error handling
     */
    protected array $ignoreSeverity = [
        'Deprecated',
        'Strict Standards',
        'Notice',
        'User Notice',
        'Warning',
        'User Warning',
        'User Error',
    ];


    public function __construct(string $logFilePath, ?PHPEvent $previousLastEvent)
    {
        $this->previousLastEvent = $previousLastEvent;

        $fh = fopen($logFilePath, "rb");
        if ($fh === false) {
            throw new InvalidArgumentException("Unable to open log file for reading: " . $logFilePath);
        }
        $this->fileHandle = $fh;
    }


    public function __destruct()
    {
        fclose($this->fileHandle);
    }


    /**
     * @param array<string|int> $ignoreList List of severity descriptions or error codes (E_ constant values)
     */
    public function setIgnoreSeverityList(array $ignoreList): void
    {
        $this->ignoreSeverity = $ignoreList;
    }


    public function getLastEvent(): ?PHPEvent
    {
        return $this->lastEvent;
    }


    /**
     * @return array<PHPEvent>
     */
    public function parse(): array
    {
        $collectEvents = false;
        if ($this->previousLastEvent === null) {
            $collectEvents = true;
        }

        /**
         * @var array<PHPEvent> $events
         */
        $events = [];
        $curEvent = null;
        // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
        while (false !== ($line = fgets($this->fileHandle))) {
            $line = trim($line);
            if ($line === "") {
                continue;
            }

            $isNewEvent = false;
            if (
                preg_match(
                    '/^\[(?P<date>[0-9A-Za-z\ \-\:\/]+)\] (?P<severity>[0-9A-Za-z\ ]+): (?P<msg>.*)/',
                    $line,
                    $matches
                )
            ) {
                $isNewEvent = true;
                $curEvent = $this->createEventFromMatches($matches);
            } elseif (
                preg_match(
                    '/^\[(?P<date>[0-9A-Za-z\ \-\:\/]+)\] (?P<msg>.*)/',
                    $line,
                    $matches
                )
            ) {
                $isNewEvent = true;
                $curEvent = $this->createEventFromMatches($matches);
            } elseif ($curEvent !== null) {
                // Sometimes the error message ends with a \n, moving the file and line info to a second line
                if (preg_match('/^\s*in (?P<file>[^\s]+) on line (?P<line>\d+)$/', $line, $matches)) {
                    $curEvent->setOccursOn($matches['file'], $matches['line']);
                } else {
                    $curEvent->addLine($line);
                }
            }

            if ($isNewEvent) {
                $this->lastEvent = $curEvent;
            }

            if (($curEvent === null) || (! $isNewEvent)) {
                continue;
            }

            if ((! $collectEvents) && ($this->previousLastEvent !== null)) {
                if ($curEvent->dtEvent < $this->previousLastEvent->dtEvent) {
                    continue;
                } elseif ($curEvent->dtEvent > $this->previousLastEvent->dtEvent) {
                    $collectEvents = true;
                } elseif ($curEvent == $this->previousLastEvent) {
                    $collectEvents = true;
                    continue;
                }
            }

            if (
                $collectEvents
                && ($curEvent != $this->previousLastEvent)
                && (! $this->shouldIgnoreEvent($curEvent))
            ) {
                $events[] = $curEvent;
            }
        }

        return $events;
    }


    /**
     * @param array{date: string, msg: string, severity?: string} $matches
     */
    protected function createEventFromMatches(array $matches): PHPEvent
    {
        $dtLastLine = new DateTimeImmutable($matches['date']);
        $severity = ($matches['severity'] ?? null);
        $msg = trim($matches['msg']);

        $lineNo = null;
        $file = null;
        $lineFileRegexs = [
            '/ in (?P<file>[^\s]+) on line (?P<line>\d+)$/',
            '/ in (?P<file>[^\s]+):(?P<line>\d+)$/',
        ];
        foreach ($lineFileRegexs as $regex) {
            $matchesLineFile = [];
            if (preg_match($regex, $msg, $matchesLineFile)) {
                $lineNo = $matchesLineFile['line'];
                $file = $matchesLineFile['file'];
                $msg = (string) preg_replace($regex, '', $msg);
                break;
            }
        }

        return new PHPEvent($dtLastLine, $severity, $msg, $file, $lineNo);
    }


    protected function shouldIgnoreEvent(PHPEvent $event): bool
    {
        if (($event->severityDesc === null) && ($event->severityCode === null)) {
            return false;
        }

        return in_array($event->severityDesc, $this->ignoreSeverity, true);
    }
}
