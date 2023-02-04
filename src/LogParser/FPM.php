<?php
declare(strict_types=1);

namespace AllenJB\Notifications\LogParser;

use DateTimeImmutable;
use InvalidArgumentException;

class FPM
{
    /**
     * @var resource $fileHandle
     */
    protected $fileHandle;

    protected ?FPMEvent $previousLastEvent;

    protected ?FPMEvent $lastEvent = null;

    /**
     * @var array<string|int> List of severity descriptions to ignore
     */
    protected array $ignoreSeverity = [
        'DEBUG',
        'NOTICE',
    ];


    public function __construct(string $logFilePath, ?FPMEvent $previousLastEvent)
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
     * @param array<string> $ignoreList List of severity descriptions
     */
    public function setIgnoreSeverityList(array $ignoreList): void
    {
        $this->ignoreSeverity = array_map('strtoupper', $ignoreList);
    }


    public function getLastEvent(): ?FPMEvent
    {
        return $this->lastEvent;
    }


    /**
     * @return array<FPMEvent>
     */
    public function parse(): array
    {
        $collectEvents = false;

        if ($this->previousLastEvent === null) {
            $collectEvents = true;
        }

        /**
         * @var array<FPMEvent> $events
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
                    '/^\[(?P<date>[0-9A-Za-z\ \-\:\/\.]+)\] (?P<severity>[0-9A-Za-z\ ]+): (?P<msg>.*)/',
                    $line,
                    $matches
                )
            ) {
                $isNewEvent = true;
                $curEvent = $this->createEventFromMatches($matches);
            } elseif ($curEvent !== null) {
                $curEvent->addLine($line);
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
     * @param array{date: string, msg: string, severity: string} $matches
     */
    protected function createEventFromMatches(array $matches): FPMEvent
    {
        $dtLastLine = new DateTimeImmutable($matches['date']);
        $severity = $matches['severity'];
        $msg = trim($matches['msg']);
        $pool = null;
        $pid = null;
        $func = null;
        $line = null;

        // If FPM log_level is set to debug, every line has more information
        if (
            preg_match(
                '/^pid (?P<pid>[0-9]+), (?P<func>[^,]+), line (?P<line>[0-9]+): (?P<msg>.+)$/',
                $msg,
                $debugMatches
            )
        ) {
            $pid = (int) $debugMatches['pid'];
            $func = $debugMatches['func'];
            $line = (int) $debugMatches['line'];
            $msg = trim($debugMatches['msg']);
        }

        if (preg_match('/^\[pool (?P<pool>[^\]]+)\] (?P<msg>.+)$/', $msg, $poolMatches)) {
            $pool = $poolMatches['pool'];
            $msg = trim($poolMatches['msg']);
        }

        return new FPMEvent($dtLastLine, $severity, $msg, $pool, $pid, $func, $line);
    }


    protected function shouldIgnoreEvent(FPMEvent $event): bool
    {
        return in_array(strtoupper($event->severityDesc), $this->ignoreSeverity, true);
    }
}
