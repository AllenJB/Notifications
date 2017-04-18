<?php

namespace AllenJB\Notifications;

class LoggingServiceEvent
{

    /**
     * @var null|\DateTimeInterface
     */
    protected $timestamp = null;

    protected $message;

    protected $level;

    protected static $validLevels = ['debug', 'info', 'warning', 'error', 'fatal'];

    protected $logger = null;

    protected $user = null;

    protected $tags = [];

    protected $fingerprint = null;

    protected $exception = null;

    protected $context = [];

    protected $excludeStackTrace = false;


    public function __construct($messageOrException, ?string $level = null)
    {
        if (is_scalar($messageOrException)) {
            $this->setMessage($messageOrException);
        } else {
            $this->setException($messageOrException);
        }

        if ($level !== null) {
            $this->setLevel($level);
        }
    }


    public function setTimeStamp(\DateTimeInterface $dt)
    {
        $this->timestamp = $dt;
    }


    public function getTimeStamp() : \DateTimeInterface
    {
        return $this->timestamp;
    }


    public function getMessage() : ?string
    {
        return $this->message;
    }


    public function setMessage($message) : void
    {
        $this->message = $message;
    }


    public function getLevel() : ?string
    {
        return $this->level;
    }


    public function setLevel(string $level) : void
    {
        if (!in_array($level, static::$validLevels)) {
            throw new \InvalidArgumentException("Level must be one of: ". implode(', ', static::$validLevels));
        }
        $this->level = $level;
    }


    public function getLogger() : ?string
    {
        return $this->logger;
    }


    /**
     * Set a name for what produced this event
     */
    public function setLogger(string $logger) : void
    {
        $this->logger = $logger;
    }


    public function getTags() : array
    {
        return $this->tags;
    }


    public function addTags($tag) : void
    {
        if (!is_scalar($tag)) {
            throw new \InvalidArgumentException("Tag must be a scalar (non-array) value");
        }
        $this->tags[] = $tag;
    }


    public function setTags(array $tags) : void
    {
        $this->containsScalars($tags, "Tags");
        $this->tags = $tags;
    }


    public function getUser() : ?array
    {
        return $this->user;
    }


    public function setUser(array $user) : void
    {
        $this->containsScalars($user, "User data");
        $this->user = $user;
    }


    public function getFingerprint() : ?string
    {
        return $this->fingerprint;
    }


    /**
     * Set a fingerprint for this event. Events with the same fingerprint will be grouped.
     */
    public function setFingerprint(string $fingerprint) : void
    {
        $this->fingerprint = $fingerprint;
    }


    public function getException() : ?\Throwable
    {
        return $this->exception;
    }


    public function setException(\Throwable $e) : void
    {
        $this->exception = $e;
    }


    public function getContext() : array
    {
        return $this->context;
    }


    public function setContext(array $context) : void
    {
        $this->context = $context;
    }


    public function setExcludeStackTrace(bool $exclude = true) : void
    {
        $this->excludeStackTrace = $exclude;
    }


    public function getExcludeStackTrace() : bool
    {
        return $this->excludeStackTrace;
    }


    protected function containsScalars(array $arr, $thing) : void
    {
        foreach ($arr as $key => $value) {
            if (!is_scalar($value)) {
                throw new \InvalidArgumentException("{$thing} array may only contain scalar values and may not contain arrays ({$key})");
            }
        }
    }


    protected function containsScalarsOrArrays(array $arr, $thing, array $keyPath = []) : void
    {
        foreach ($arr as $key => $value) {
            $newKeyPath = $keyPath;
            $newKeyPath[] = $key;

            if (is_array($value)) {
                $this->containsScalarsOrArrays($value, $thing, $newKeyPath);
                continue;
            }

            if (! is_scalar($value)) {
                throw new \InvalidArgumentException("{$thing} array may only contain scalar values or arrays"
                    ." ([". implode('][', $keyPath) ."])");
            }
        }
    }

}
