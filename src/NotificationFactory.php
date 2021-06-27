<?php
declare(strict_types=1);

namespace AllenJB\Notifications;

class NotificationFactory implements NotificationFactoryInterface
{
    protected static $phpErrorNotifyLevel = [
        E_STRICT => 'warning',
        E_NOTICE => 'warning',
        E_WARNING => 'warning',
        E_USER_NOTICE => 'warning',
        E_USER_WARNING => 'warning',
    ];

    public static function fromThrowable(\Throwable $throwable, ?string $loggerName = null): Notification
    {
        $nLevel = "error";
        if ($throwable instanceof \ErrorException) {
            $severity = $throwable->getSeverity();
            $nLevel = (static::$phpErrorNotifyLevel[$severity] ?? 'error');
        }
        return new Notification($nLevel, ($loggerName ?? "None"), null, $throwable);
    }

}
