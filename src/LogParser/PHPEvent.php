<?php
declare(strict_types=1);

namespace AllenJB\Notifications\LogParser;

use DateTimeImmutable;

class PHPEvent
{

    public DateTimeImmutable $dtEvent;

    public ?string $severityDesc = null;

    public ?int $severityCode = null;

    public string $message;

    public ?string $file = null;

    public ?string $lineNo = null;

    /**
     * @var array<string> Additional lines
     */
    public array $additional = [];


    public function __construct(
        DateTimeImmutable $dtEvent,
        ?string $severityDesc,
        string $message,
        ?string $file,
        ?string $lineNo
    ) {
        $this->dtEvent = $dtEvent;
        if ($severityDesc !== null) {
            $this->severityDesc = preg_replace('/^PHP /', '', $severityDesc);
        }
        $this->message = $message;
        $this->file = $file;
        $this->lineNo = $lineNo;
        $this->setSeverityCode();
    }


    public function setOccursOn(string $file, string $lineNo): void
    {
        $this->file = $file;
        $this->lineNo = $lineNo;
    }


    public function addLine(string $line): void
    {
        $this->additional[] = $line;
    }


    protected function setSeverityCode(): void
    {
        // Note: The log message severity is not precise
        // Ref: https://github.com/php/php-src/blob/a2bc7cf9ca74c051bfd287c1b3d54c76945f10cc/main/main.c#L1082
        // When logged by PHP's built-in error handler, 'PHP ' is always prepended to the type string
        // We strip the 'PHP ' prefix when handling the log line below
        $severitys = [
            'fatal error' => E_ERROR,
            'warning' => E_WARNING,
            'notice' => E_NOTICE,
            'user notice' => E_USER_NOTICE,
            'user warning' => E_USER_WARNING,
            'user error' => E_USER_ERROR,
            'recoverable fatal error' => E_RECOVERABLE_ERROR,
            'parse error' => E_PARSE,
            'deprecated' => E_DEPRECATED,
            'strict standards' => E_STRICT,
            'unknown error' => null,
        ];

        $severityKey = strtolower($this->severityDesc ?? '');
        $this->severityCode = ($severitys[$severityKey] ?? null);
    }

}
