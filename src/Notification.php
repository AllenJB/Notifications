<?php

namespace AllenJB\Notifications;

class Notification
{

    protected $message;

    protected $level;

    protected static $validLevels = ['debug', 'info', 'warning', 'error', 'fatal'];

    protected $exception = null;

    protected $logger = null;

    protected $context = [];

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
        $this->exception = $exception;
        $this->logger = $loggerName;
        $this->context = $context;

        // Ensure the Notification class is loaded - this should help prevent logging from failing in cases
        // where available memory might be low
        new Notification("info", "Preloading", "preloading");
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


    public function addContext(string $key, $value): void
    {
        $this->context[$key] = $value;
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
