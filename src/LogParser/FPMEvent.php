<?php
declare(strict_types=1);

namespace AllenJB\Notifications\LogParser;

use DateTimeImmutable;

class FPMEvent
{
    public DateTimeImmutable $dtEvent;

    public string $severityDesc;

    public string $message;

    /**
     * @var string|null FPM pool which issued the message (if applicable / available)
     */
    public ?string $pool = null;

    /**
     * @var int|null (Debug level only) PID of FPM process which issued the message
     */
    public ?int $pid = null;

    /**
     * @var string|null (Debug level only) FPM function which issued the message
     */
    public ?string $function = null;

    /**
     * @var int|null (Debug level only) FPM line no which issued the message
     */
    public ?int $lineNo = null;

    /**
     * @var array<string> Additional lines
     */
    public array $additional = [];


    public function __construct(
        DateTimeImmutable $dtEvent,
        string $severityDesc,
        string $message,
        ?string $pool = null,
        ?int $pid = null,
        ?string $function = null,
        ?int $lineNo = null
    ) {
        $this->dtEvent = $dtEvent;
        $this->severityDesc = $severityDesc;
        $this->message = $message;
        $this->pool = $pool;
        $this->pid = $pid;
        $this->function = $function;
        $this->lineNo = $lineNo;
    }


    public function addLine(string $line): void
    {
        $this->additional[] = $line;
    }
}
