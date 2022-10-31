<?php

namespace AllenJB\Notifications;

class Notification
{

    protected ?string $message;

    protected string $level;

    protected static array $validLevels = ['debug', 'info', 'warning', 'error', 'fatal'];

    protected ?\Throwable $exception = null;

    protected ?string $logger = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $context = [];

    protected bool $includeSessionData = true;

    protected bool $excludeStrackTrace = false;

    protected \DateTimeImmutable $timestamp;


    /**
     * @param string $level Notification level (debug, info, warning, error or fatal)
     * @param string $loggerName Name of entity logging this notification
     * @param null|string $optionalMessage Notification message (optional if an exception is attached)
     * @param \Throwable|null $exception Error or Exception to attach to this notification
     * @param mixed[] $context Additional context information (key/value pairs)
     */
    public function __construct(
        string $level,
        string $loggerName,
        ?string $optionalMessage,
        \Throwable $exception = null,
        array $context = []
    ) {
        $this->timestamp = new \DateTimeImmutable();
        $this->setLevel($level);
        $this->message = $optionalMessage;
        $this->logger = $loggerName;
        $this->context["Additional Data"] = $context;

        $this->exception = $exception;
        if ($this->exception !== null) {
            $exceptionData = get_object_vars($exception);
            if (count($exceptionData)) {
                $this->context["Exception Properties"] = $exceptionData;
            }
        }
    }


    public function setIncludeSessionData(bool $enabled): void
    {
        $this->includeSessionData = $enabled;
    }


    public function setExcludeStackTrace(bool $enabled = true): void
    {
        $this->excludeStrackTrace = $enabled;
    }


    public function shouldIncludeSessionData(): bool
    {
        return $this->includeSessionData;
    }


    public function shouldExcludeStackTrace(): bool
    {
        return $this->excludeStrackTrace;
    }


    public function addContext(string $key, $value, $section = 'Additional Data'): void
    {
        $this->context[$section][$key] = $value;
    }


    public function getLevel()
    {
        return $this->level;
    }


    public function setLevel(string $level): void
    {
        if ($level === "warn") {
            $level = "warning";
        }
        if (! in_array($level, static::$validLevels, true)) {
            throw new \InvalidArgumentException("Level must be one of: " . implode(', ', static::$validLevels));
        }
        $this->level = $level;
    }


    public function getMessage(): ?string
    {
        return $this->message;
    }


    public function getLogger(): ?string
    {
        return $this->logger;
    }


    public function getException(): ?\Throwable
    {
        return $this->exception;
    }


    public function getContext(): array
    {
        return $this->context;
    }


    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }


    public function setTimestamp(\DateTimeImmutable $ts): void
    {
        $this->timestamp = $ts;
    }

}
