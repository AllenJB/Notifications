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


    /**
     * @param string $level Notification level (debug, info, warning, error or fatal)
     * @param string $loggerName Name of entity logging this notification
     * @param null|string $optionalMessage Notification message (optional if an exception is attached)
     * @param \Exception|null $exception Error or Exception to attach to this notification
     * @param mixed[] $context Additional context information (key/value pairs)
     */
    public function __construct($level, $loggerName, $optionalMessage, \Exception $exception = null, array $context = [])
    {
        $this->setLevel($level);
        $this->message = $optionalMessage;
        $this->exception = $exception;
        $this->logger = $loggerName;
        $this->context = $context;
    }


    public function addContext($key, $value)
    {
        $this->context[$key] = $value;
    }


    public function getLevel()
    {
        return $this->level;
    }


    public function setLevel($level)
    {
        if (!in_array($level, static::$validLevels, true)) {
            throw new \InvalidArgumentException("Level must be one of: ". implode(', ', static::$validLevels));
        }
        $this->level = $level;
    }


    public function getMessage()
    {
        return $this->message;
    }


    public function getLogger()
    {
        return $this->logger;
    }


    public function getException()
    {
        return $this->exception;
    }


    public function getContext()
    {
        return $this->context;
    }

}
