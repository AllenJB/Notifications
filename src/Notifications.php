<?php

namespace AllenJB\Notifications;

class Notifications
{

    protected static Notifications $instance;

    /**
     * @var array<LoggingServiceInterface>
     */
    protected array $serviceStack;


    public function __construct(array $serviceStack)
    {
        $this->serviceStack = $serviceStack;
    }


    protected static function setStaticInstance(Notifications $instance): void
    {
        static::$instance = $instance;
    }


    public function send(Notification $notification, ?string $excludeClass = null): void
    {
        foreach ($this->serviceStack as $service) {
            if (($excludeClass !== null) && (get_class($service) === $excludeClass)) {
                continue;
            }

            try {
                if ($service->send($notification)) {
                    return;
                }
            } catch (\Exception $e) {
                $n = new Notification("error", "Notification", null, $e);
                $this->send($n, get_class($service));
            }
        }
    }


    /**
     * Send a notification by the prefered channel
     *
     * @param Notification $notification
     */
    public static function any(Notification $notification): void
    {
        static::$instance->send($notification);
    }


    public static function exceptionAsString(\Throwable $e): string
    {
        $email = "Message: {$e->getMessage()}"
            . "\nType: " . get_class($e)
            . "\nCode: " . $e->getCode()
            . "\nLine: " . $e->getLine()
            . "\nFile: " . $e->getFile()
            . "\nStack Trace:\n" . $e->getTraceAsString()
            . "\n\n";

        if (is_object($e->getPrevious())) {
            $email .= "--- Previous Exception ---\n"
                . static::exceptionAsString($e->getPrevious());
        }

        return $email;
    }

}
