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

        // Ensure the Notification class is loaded - this should help prevent logging from failing in cases
        // where available memory might be low
        new Notification("info", "Preloading", "preloading");
    }


    public static function setStaticInstance(Notifications $instance): void
    {
        static::$instance = $instance;
    }


    /**
     * @return bool Was notification (reportedly) sent?
     */
    public function send(Notification $notification, ?string $excludeClass = null): bool
    {
        foreach ($this->serviceStack as $service) {
            if (($excludeClass !== null) && (get_class($service) === $excludeClass)) {
                continue;
            }

            try {
                if ($service->send($notification)) {
                    return true;
                }
            } catch (\Exception $e) {
                $n = new Notification("error", "Notification", null, $e);
                return $this->send($n, get_class($service));
            }
        }

        return false;
    }


    /**
     * Send a notification by the prefered channel
     *
     * @param Notification $notification
     * @return bool Was notification (reportedly) sent?
     */
    public static function any(Notification $notification): bool
    {
        if (! isset(static::$instance)) {
            return false;
        }
        return static::$instance->send($notification);
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
